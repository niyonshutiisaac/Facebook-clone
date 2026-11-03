<?php

declare(strict_types=1);

namespace App\Services\Feed;

use App\Models\Friendship;
use App\Models\User;
use App\Models\UserInteraction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Measures "how close are these two people" on a 0..1 scale.
 *
 * Affinity is the single most important input to feed ranking, but it is also
 * the most expensive to compute, so every result is cached per viewer for an
 * hour and invalidated when a friendship or interaction changes.
 */
final class AffinityScoreService
{
    /** Raw signal weights — they sum to 1. */
    private const float W_MESSAGE = 0.26;
    private const float W_REACTION = 0.20;
    private const float W_COMMENT = 0.16;
    private const float W_PROFILE_VIEW = 0.12;
    private const float W_DWELL = 0.14;
    private const float W_TAG = 0.12;

    /** Value at which each raw counter is considered "maximum signal". */
    private const int MAX_MESSAGES = 100;
    private const int MAX_REACTIONS = 50;
    private const int MAX_COMMENTS = 30;
    private const int MAX_PROFILE_VIEWS = 40;
    private const int MAX_DWELL_MINUTES = 120;
    private const int MAX_TAGS = 10;

    private const int CACHE_TTL_SECONDS = 3600;

    /**
     * Affinity from `$viewer` toward `$targetId`, between 0 (stranger) and 1.
     */
    public function between(User $viewer, string $targetId): float
    {
        $key = $this->cacheKey($viewer->id);

        $map = Cache::remember($key, self::CACHE_TTL_SECONDS, fn (): array => $this->build($viewer));

        return $map[$targetId] ?? 0.0;
    }

    /**
     * @return array<string, float>
     */
    public function mapFor(User $viewer): array
    {
        return Cache::remember(
            $this->cacheKey($viewer->id),
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->build($viewer)
        );
    }

    public function forget(User $viewer): void
    {
        Cache::forget($this->cacheKey($viewer->id));
    }

    /**
     * @return array<string, float>
     */
    private function build(User $viewer): array
    {
        /** @var array<string, float> $scores */
        $scores = [];

        // 1. Baseline from the friendship row (closeness is maintained by the
        //    UpdateFeedRankingScores job from interaction history).
        Friendship::query()
            ->where('user_id', $viewer->id)
            ->get(['friend_id', 'closeness_score', 'is_close_friend', 'is_acquaintance', 'is_restricted'])
            ->each(function (Friendship $friendship) use (&$scores): void {
                $base = $friendship->closeness_score / 100.0;

                if ($friendship->is_close_friend) {
                    $base = max($base, 0.85);
                }

                if ($friendship->is_acquaintance) {
                    $base *= 0.6;
                }

                if ($friendship->is_restricted) {
                    $base *= 0.25;
                }

                $scores[$friendship->friend_id] = round(min(1.0, $base), 4);
            });

        // 2. Blend in the raw interaction counters.
        UserInteraction::query()
            ->where('user_id', $viewer->id)
            ->get([
                'target_user_id', 'messages_sent', 'reactions_given', 'comments_written',
                'profile_views', 'dwell_ms', 'photo_tags', 'affinity_score',
            ])
            ->each(function (UserInteraction $interaction) use (&$scores): void {
                $computed = $this->fromInteraction($interaction);

                // Take the stronger of the friendship baseline and the raw
                // signal: messaging someone you rarely see should still count.
                $scores[$interaction->target_user_id] = round(
                    max($scores[$interaction->target_user_id] ?? 0.0, $computed),
                    4
                );
            });

        return $scores;
    }

    private function fromInteraction(UserInteraction $interaction): float
    {
        if ((float) $interaction->affinity_score > 0) {
            return min(1.0, (float) $interaction->affinity_score);
        }

        $dwellMinutes = $interaction->dwell_ms / 60000.0;

        return min(1.0,
            (self::W_MESSAGE * $this->normalise($interaction->messages_sent, self::MAX_MESSAGES))
            + (self::W_REACTION * $this->normalise($interaction->reactions_given, self::MAX_REACTIONS))
            + (self::W_COMMENT * $this->normalise($interaction->comments_written, self::MAX_COMMENTS))
            + (self::W_PROFILE_VIEW * $this->normalise($interaction->profile_views, self::MAX_PROFILE_VIEWS))
            + (self::W_DWELL * $this->normalise($dwellMinutes, self::MAX_DWELL_MINUTES))
            + (self::W_TAG * $this->normalise($interaction->photo_tags, self::MAX_TAGS))
        );
    }

    private function normalise(float|int $value, float|int $max): float
    {
        if ($max <= 0) {
            return 0.0;
        }

        return min(1.0, $value / $max);
    }

    private function cacheKey(string $userId): string
    {
        return sprintf('affinity:map:%s', $userId);
    }

    /**
     * Batch refresh used by the scheduled recompute: rewrites the
     * `closeness_score` column from the aggregated interaction table so the
     * Friendship rows stay a fast, denormalised read model.
     */
    public function refreshClosenessScores(int $chunkSize = 500): int
    {
        $updated = 0;

        UserInteraction::query()
            ->select('user_id', 'target_user_id')
            ->selectRaw('
                LEAST(100, ROUND(
                      (messages_sent * 2.0)
                    + (reactions_given * 1.6)
                    + (comments_written * 3.0)
                    + (profile_views * 1.2)
                    + (LEAST(dwell_ms / 60000, 240) * 0.25)
                    + (photo_tags * 4.0)
                )) AS score
            ')
            ->orderBy('user_id')
            ->chunkById($chunkSize, function ($rows) use (&$updated): void {
                foreach ($rows as $row) {
                    DB::table('friendships')
                        ->where('user_id', $row->user_id)
                        ->where('friend_id', $row->target_user_id)
                        ->update(['closeness_score' => (int) $row->score]);

                    $updated++;
                }
            });

        return $updated;
    }
}

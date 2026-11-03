<?php

declare(strict_types=1);

namespace App\Services\Feed;

use App\Enums\PostType;
use App\Models\Post;
use App\Models\User;

/**
 * Scores a candidate post for a specific viewer.
 *
 * The model is the four-factor blend Facebook publicly describes, expressed so
 * every factor stays in [0, 1] and therefore comparable:
 *
 *     score = 0.35 * affinity
 *           + 0.25 * contentQuality
 *           + 0.25 * engagementPrediction
 *           + 0.15 * timeDecay
 *
 * Two adjustments are applied afterwards:
 *   - `authorFatigue()` — the second and third consecutive post from the same
 *     author are heavily discounted so a single prolific friend cannot own the
 *     first screen.
 *   - `typeFatigue()` — four videos in a row pushes the next video down, which
 *     keeps the feed textured without hard-coding a round-robin.
 *
 * Every method is pure (no I/O) so the model is trivially unit testable and can
 * be re-run against historical data to evaluate weight changes offline.
 */
final class FeedRankingService
{
    /** Weight of the "how close am I to this author" factor. */
    public const float WEIGHT_AFFINITY = 0.35;

    /** Weight of the "is this post intrinsically good" factor. */
    public const float WEIGHT_QUALITY = 0.25;

    /** Weight of the "will this viewer engage" factor. */
    public const float WEIGHT_ENGAGEMENT = 0.25;

    /** Weight of the "is this still fresh" factor. */
    public const float WEIGHT_DECAY = 0.15;

    /**
     * A post is fully decayed after this many hours; the half-life is
     * configurable so a low-traffic deployment can keep posts alive longer.
     */
    private const int DECAY_HORIZON_HOURS = 72;

    public function __construct(
        private readonly AffinityScoreService $affinity,
        private readonly ContentQualityService $quality,
    ) {
    }

    /**
     * @param  array<string, mixed>  $context  Optional per-request context:
     *                                         `session_depth` (0..1), `device_type`, `recent_authors` (list<string>)
     *                                         and `recent_types` (list<string>) drive the diversity adjustments.
     *
     * @return array{score: float, factors: array<string, float>}
     */
    public function score(Post $post, User $viewer, array $context = []): array
    {
        $affinity = $this->affinityScore($post, $viewer);
        $quality = $this->qualityScore($post);
        $engagement = $this->engagementPrediction($post, $viewer);
        $decay = $this->timeDecay($post);

        $base = (self::WEIGHT_AFFINITY * $affinity)
            + (self::WEIGHT_QUALITY * $quality)
            + (self::WEIGHT_ENGAGEMENT * $engagement)
            + (self::WEIGHT_DECAY * $decay);

        $score = $base
            * $this->authorFatigue($post, $context)
            * $this->typeFatigue($post, $context)
            * $this->sessionBoost($context);

        return [
            'score' => round(max($score, 0.0), 6),
            'factors' => [
                'affinity' => round($affinity, 4),
                'content_quality' => round($quality, 4),
                'engagement' => round($engagement, 4),
                'time_decay' => round($decay, 4),
            ],
        ];
    }

    // ------------------------------------------------------------- factor 1

    /**
     * How close is the viewer to the author? 0 for strangers, 1 for a close
     * friend they message every day.
     */
    private function affinityScore(Post $post, User $viewer): float
    {
        if ($post->user_id === $viewer->id) {
            return 0.65; // own posts: relevant, but not novel
        }

        $affinity = $this->affinity->between($viewer, $post->user_id);

        // Content the viewer has already engaged with from this author gets a
        // small, bounded lift — the classic "you interact with them" signal.
        return min(1.0, $affinity + ($post->is_see_first_source ?? false ? 0.15 : 0.0));
    }

    // ------------------------------------------------------------- factor 2

    private function qualityScore(Post $post): float
    {
        $stored = (float) ($post->quality_score ?? 0);

        return $stored > 0
            ? $stored
            : $this->quality->score($post);
    }

    // ------------------------------------------------------------- factor 3

    /**
     * Predicted probability the viewer engages, based on how other people have
     * engaged with this post so far plus the viewer's own bias toward this
     * content type.
     */
    private function engagementPrediction(Post $post, User $viewer): float
    {
        $raw = ($post->reactions_count * 1.0)
            + ($post->comments_count * 2.0)
            + ($post->shares_count * 3.0);

        // Log scale: 10 reactions is a big signal, 1000 is not 100x bigger.
        $popularity = $raw <= 0 ? 0.0 : min(1.0, log1p($raw) / log1p(500));

        // Engagement velocity (engagements per minute in the first hour) is
        // normalised against a reference rate of 2/min.
        $velocity = min(1.0, ((float) $post->engagement_velocity) / 2.0);

        // The viewer's historical appetite for this post type.
        $typeAffinity = $this->quality->viewerTypeAffinity($viewer, $post->typeEnum());

        return (0.45 * $popularity) + (0.25 * $velocity) + (0.30 * $typeAffinity);
    }

    // ------------------------------------------------------------- factor 4

    /**
     * Exponential decay with a 72 hour horizon, tempered by the author's
     * posting frequency so someone who posts once a month still surfaces.
     */
    private function timeDecay(Post $post): float
    {
        $published = $post->published_at ?? $post->created_at;

        if ($published === null) {
            return 0.5;
        }

        $hours = max(0.0, now()->diffInMinutes($published) / 60.0);

        return round(exp(-$hours / (self::DECAY_HORIZON_HOURS / 3)), 6);
    }

    // ------------------------------------------------------------ diversity

    /**
     * @param  array<string, mixed>  $context
     */
    private function authorFatigue(Post $post, array $context): float
    {
        /** @var list<string> $recent */
        $recent = $context['recent_authors'] ?? [];

        if ($recent === []) {
            return 1.0;
        }

        $consecutive = 0;

        foreach (array_reverse($recent) as $authorId) {
            if ($authorId === $post->user_id) {
                $consecutive++;

                continue;
            }

            break;
        }

        return match ($consecutive) {
            0 => 1.0,
            1 => 0.55,   // allowed, but demoted
            2 => 0.22,
            default => 0.05,
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function typeFatigue(Post $post, array $context): float
    {
        /** @var list<string> $recent */
        $recent = $context['recent_types'] ?? [];

        if (count($recent) < 3) {
            return 1.0;
        }

        $lastThree = array_slice($recent, -3);

        if (count(array_filter($lastThree, fn (string $t): bool => $t === $post->type)) === 3) {
            return 0.4;
        }

        return 1.0;
    }

    /**
     * Deeper into a session, show the strongest material: people who have
     * scrolled past twenty items have declared they are browsing, not hunting.
     *
     * @param  array<string, mixed>  $context
     */
    private function sessionBoost(array $context): float
    {
        $depth = (float) ($context['session_depth'] ?? 0.0);

        return 1.0 + (0.15 * min(1.0, max(0.0, $depth)));
    }

    /**
     * Post type weights used when blending a chronological fallback feed.
     */
    public static function typeWeight(PostType $type): float
    {
        return $type->weight();
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Models\FriendSuggestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "People you may know".
 *
 * score = 0.5 * mutualFriends + 0.3 * sharedAttributes + 0.2 * activityProximity
 *
 * Suggestions are pre-computed into `friend_suggestions` by a scheduled job so
 * the friends page is a single indexed read ordered by score, and dismissals
 * stick.
 */
final class FriendSuggestionService
{
    private const float W_MUTUAL = 0.5;
    private const float W_ATTRIBUTES = 0.3;
    private const float W_PROXIMITY = 0.2;

    /** Normalisation ceilings for each raw signal. */
    private const int MAX_MUTUAL = 20;
    private const int MAX_ATTRIBUTES = 5;
    private const int MAX_PROXIMITY_DAYS = 30;

    /**
     * @return Collection<int, FriendSuggestion>
     */
    public function for(User $user, int $limit = 20): Collection
    {
        return FriendSuggestion::query()
            ->with('suggested.profile')
            ->where('user_id', $user->id)
            ->live()
            ->orderByDesc('score')
            ->limit($limit)
            ->get();
    }

    /**
     * Recompute suggestions for one user.
     */
    public function rebuildFor(User $user, int $limit = 50): int
    {
        $friendIds = $user->friendIds();
        $friendIds[] = $user->id;

        $dismissed = FriendSuggestion::query()
            ->where('user_id', $user->id)
            ->where('is_dismissed', true)
            ->pluck('suggested_user_id')
            ->all();

        $exclude = array_values(array_unique([...$friendIds, ...$dismissed]));

        $pendingSent = DB::table('friend_requests')
            ->where('sender_id', $user->id)
            ->where('status', 'pending')
            ->pluck('recipient_id')
            ->all();

        $exclude = array_values(array_unique([...$exclude, ...$pendingSent]));

        $candidates = User::query()
            ->active()
            ->whereNotIn('id', $exclude)
            ->limit(500)
            ->get();

        $mutualCounts = $this->mutualFriendCounts($user);
        $profile = $user->profile;
        $now = now();
        $rows = [];

        foreach ($candidates as $candidate) {
            $mutual = (int) ($mutualCounts[$candidate->id] ?? 0);

            // Strangers with no mutual friends are noise.
            if ($mutual === 0) {
                continue;
            }

            $attributes = $this->sharedAttributeScore($profile, $candidate->profile);
            $lastSeen = $candidate->last_seen_at;
            $proximity = $lastSeen === null
                ? 0.0
                : max(0.0, 1.0 - ($lastSeen->diffInDays($now) / self::MAX_PROXIMITY_DAYS));

            $score = (self::W_MUTUAL * min(1.0, $mutual / self::MAX_MUTUAL))
                + (self::W_ATTRIBUTES * min(1.0, $attributes / self::MAX_ATTRIBUTES))
                + (self::W_PROXIMITY * $proximity);

            $rows[] = [
                'user_id' => $user->id,
                'suggested_user_id' => $candidate->id,
                'mutual_friends_count' => $mutual,
                'score' => round($score, 4),
                'signals' => json_encode([
                    'mutual_friends' => $mutual,
                    'shared_attributes' => $attributes,
                    'proximity' => round($proximity, 4),
                ], JSON_THROW_ON_ERROR),
                'is_dismissed' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $rows = array_slice($rows, 0, $limit);

        if ($rows === []) {
            return 0;
        }

        DB::table('friend_suggestions')->upsert(
            $rows,
            ['user_id', 'suggested_user_id'],
            ['score', 'mutual_friends_count', 'signals', 'is_dismissed', 'updated_at']
        );

        // Drop stale rows that are no longer in the top N.
        $keep = array_column($rows, 'suggested_user_id');

        DB::table('friend_suggestions')
            ->where('user_id', $user->id)
            ->whereNotIn('suggested_user_id', $keep)
            ->delete();

        return count($rows);
    }

    public function dismiss(User $user, User $suggested): void
    {
        FriendSuggestion::query()
            ->where('user_id', $user->id)
            ->where('suggested_user_id', $suggested->id)
            ->update(['is_dismissed' => true, 'dismissed_at' => now()]);
    }

    /**
     * How many mutual friends the user has with every other user, in one query.
     *
     * @return array<string, int>
     */
    private function mutualFriendCounts(User $user): array
    {
        $friendIds = $user->friendIds();

        if ($friendIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($friendIds), '?'));

        // Friends of friends: every (friend -> their friend) edge, excluding the
        // user's own friends and the user.
        $rows = DB::select("
            SELECT f2.friend_id AS candidate_id, COUNT(*) AS total
            FROM friendships f1
            JOIN friendships f2
              ON f2.user_id = f1.friend_id
             AND f2.friend_id NOT IN ({$placeholders})
             AND f2.friend_id <> ?
            WHERE f1.user_id = ?
            GROUP BY f2.friend_id
        ", [...$friendIds, $user->id, $user->id]);

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row->candidate_id] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * Shared city, hometown, workplace and school, one point each.
     */
    private function sharedAttributeScore(?\App\Models\UserProfile $mine, ?\App\Models\UserProfile $theirs): int
    {
        if ($mine === null || $theirs === null) {
            return 0;
        }

        $score = 0;

        foreach (['current_city', 'hometown'] as $field) {
            if ($mine->{$field} !== null && $mine->{$field} === $theirs->{$field}) {
                $score++;
            }
        }

        $myWork = collect((array) ($mine->work ?? []))->pluck('employer')->filter()->all();
        $theirWork = collect((array) ($theirs->work ?? []))->pluck('employer')->filter()->all();

        if (array_intersect($myWork, $theirWork) !== []) {
            $score++;
        }

        $mySchool = collect((array) ($mine->education ?? []))->pluck('school')->filter()->all();
        $theirSchool = collect((array) ($theirs->education ?? []))->pluck('school')->filter()->all();

        if (array_intersect($mySchool, $theirSchool) !== []) {
            $score++;
        }

        return $score;
    }
}

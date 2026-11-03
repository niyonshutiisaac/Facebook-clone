<?php

declare(strict_types=1);

namespace App\Services\Feed;

use App\Models\Post;
use App\Models\User;
use App\Support\Cursor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Two-tier feed cache.
 *
 * Redis holds the hot copy: an ordered list of post ids plus the computed score
 * for each, keyed per user. `newsfeed_cache` holds the durable copy so a Redis
 * restart (or a horizontal scale-out to a cold node) degrades to one slow
 * request instead of a full recompute storm.
 *
 * Invalidation is explicit rather than TTL-based: posting, hiding or changing a
 * feed preference calls `forget()` for the affected user, and the scheduled
 * RefreshNewsFeedCache command warms feeds for users active in the last hour.
 */
final class FeedCacheService
{
    private const string KEY_PREFIX = 'feed';
    private const string STALE_PREFIX = 'feed:build';

    public function __construct(
        private readonly FeedRankingService $ranking,
        private readonly AffinityScoreService $affinity,
    ) {
    }

    /**
     * Cached candidate ids in ranking order, or null on a cold cache.
     *
     * @return list<array{id: string, score: float}>|null
     */
    public function get(User $user): ?array
    {
        /** @var list<array{id: string, score: float}>|null $cached */
        $cached = Cache::store('feed')->get($this->key($user));

        if (is_array($cached)) {
            return $cached;
        }

        return $this->fromDurableCache($user);
    }

    /**
     * @param  list<array{id: string, score: float}>  $items
     */
    public function put(User $user, array $items): void
    {
        $ttl = (int) config('feed.cache_ttl', 15) * 60;

        Cache::store('feed')->put($this->key($user), $items, $ttl);

        $this->writeDurableCache($user, $items);
    }

    public function forget(User $user): void
    {
        Cache::store('feed')->forget($this->key($user));

        DB::table('newsfeed_cache')->where('user_id', $user->id)->delete();
    }

    /**
     * Forget the feeds of everyone who follows the author — called when a new
     * post is published so friends see it on their next refresh.
     */
    public function forgetForAudienceOf(User $author): void
    {
        $friendIds = DB::table('friendships')
            ->where('friend_id', $author->id)
            ->where('is_following', true)
            ->pluck('user_id');

        foreach ($friendIds as $friendId) {
            Cache::store('feed')->forget($this->keyFor($friendId));
        }

        DB::table('newsfeed_cache')->whereIn('user_id', $friendIds)->delete();
    }

    /**
     * Prevents a thundering herd: only one worker rebuilds a given feed, the
     * rest read the (slightly stale) previous value instead of piling on.
     */
    public function lockForRebuild(User $user, int $seconds = 30): bool
    {
        return Cache::store('feed')->add($this->staleKey($user), true, $seconds);
    }

    public function unlock(User $user): void
    {
        Cache::store('feed')->forget($this->staleKey($user));
    }

    /**
     * Encodes a cursor from the last item of the delivered page.
     *
     * @param  array<string, mixed>  $item
     */
    public function encodeCursor(array $item, int $offset): string
    {
        return Cursor::encode([
            's' => (float) ($item['score'] ?? 0),
            'i' => (string) ($item['id'] ?? ''),
            'o' => $offset,
            't' => now()->timestamp,
        ]);
    }

    /**
     * @return array{score: float|null, id: string|null, offset: int}|null
     */
    public function decodeCursor(?string $cursor): ?array
    {
        $payload = Cursor::decode($cursor);

        if ($payload === null || ! isset($payload['i'])) {
            return null;
        }

        return [
            'score' => isset($payload['s']) ? (float) $payload['s'] : null,
            'id' => (string) $payload['i'],
            'offset' => (int) ($payload['o'] ?? 0),
        ];
    }

    /**
     * Rebuild and persist the ranked candidate list for a user.
     *
     * @param  Collection<int, Post>  $candidates
     * @return list<array{id: string, score: float, factors: array<string, float>}>
     */
    public function rebuild(User $user, Collection $candidates): array
    {
        $recentAuthors = [];
        $recentTypes = [];
        $ranked = [];
        $total = max(1, $candidates->count());

        foreach ($candidates->values() as $index => $post) {
            $result = $this->ranking->score($post, $user, [
                'session_depth' => $index / $total,
                'recent_authors' => $recentAuthors,
                'recent_types' => $recentTypes,
            ]);

            $ranked[] = [
                'id' => (string) $post->id,
                'score' => $result['score'],
                'factors' => $result['factors'],
            ];

            $recentAuthors[] = (string) $post->user_id;
            $recentTypes[] = (string) $post->type;

            // Diversity only needs a short lookback window.
            if (count($recentAuthors) > 5) {
                array_shift($recentAuthors);
                array_shift($recentTypes);
            }
        }

        usort($ranked, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $this->put($user, $ranked);

        $this->persistScores($candidates->keyBy('id'), $ranked);

        return $ranked;
    }

    /**
     * @param  list<array{id: string, score: float, factors: array<string, float>}>  $ranked
     */
    private function persistScores(\Illuminate\Support\Collection $posts, array $ranked): void
    {
        foreach ($ranked as $item) {
            $post = $posts->get($item['id']);

            if ($post === null) {
                continue;
            }

            // Ranking scores are per-post global inputs plus the stored quality
            // score; the per-viewer component lives in the cache only.
            $post->withoutEvents(function () use ($post, $item): void {
                DB::table('posts')->where('id', $post->id)->update([
                    'ranking_score' => $item['score'],
                ]);
            });
        }
    }

    /**
     * @return list<array{id: string, score: float}>|null
     */
    private function fromDurableCache(User $user): ?array
    {
        $rows = DB::table('newsfeed_cache')
            ->where('user_id', $user->id)
            ->orderByDesc('score')
            ->limit((int) config('feed.candidate_limit', 500))
            ->get(['post_id', 'score']);

        if ($rows->isEmpty()) {
            return null;
        }

        /** @var list<array{id: string, score: float}> $items */
        $items = $rows->map(fn (object $row): array => [
            'id' => (string) $row->post_id,
            'score' => (float) $row->score,
        ])->all();

        // Re-warm Redis from the durable copy so the next request is fast.
        Cache::store('feed')->put($this->key($user), $items, (int) config('feed.cache_ttl', 15) * 60);

        return $items;
    }

    /**
     * @param  list<array{id: string, score: float, factors?: array<string, float>}>  $items
     */
    private function writeDurableCache(User $user, array $items): void
    {
        $now = now();

        $rows = array_map(static fn (array $item): array => [
            'user_id' => $user->id,
            'post_id' => $item['id'],
            'score' => $item['score'],
            'signals' => isset($item['factors']) ? json_encode($item['factors'], JSON_THROW_ON_ERROR) : null,
            'computed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $items);

        try {
            DB::table('newsfeed_cache')->upsert(
                $rows,
                ['user_id', 'post_id'],
                ['score', 'signals', 'computed_at', 'updated_at']
            );
        } catch (\Throwable $e) {
            // A cache write failure must never break feed delivery.
            Log::warning('Durable feed cache write failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function key(User $user): string
    {
        return $this->keyFor($user->id);
    }

    private function keyFor(string $userId): string
    {
        return sprintf('%s:%s', self::KEY_PREFIX, $userId);
    }

    private function staleKey(User $user): string
    {
        return sprintf('%s:%s', self::STALE_PREFIX, $user->id);
    }
}

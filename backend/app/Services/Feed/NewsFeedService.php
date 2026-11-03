<?php

declare(strict_types=1);

namespace App\Services\Feed;

use App\Enums\Audience;
use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Candidate generation + delivery for the ranked news feed.
 *
 * Pipeline:
 *   1. candidates()  — posts from followed friends, joined groups, followed
 *                      pages and the viewer themselves, inside the lookback
 *                      window, minus hidden / blocked / expired content.
 *   2. FeedCacheService::rebuild / get — rank and cache the ordering.
 *   3. paginate()    — slice one page, hydrate posts in ranking order, hand
 *                      back a cursor the client replays verbatim.
 */
final class NewsFeedService
{
    public function __construct(
        private readonly FeedCacheService $cache,
        private readonly FeedRankingService $ranking,
        private readonly InteractionTrackingService $tracking,
    ) {
    }

    /**
     * One page of the ranked feed.
     *
     * @param  array{limit?: int, session_depth?: float}  $options
     * @return array{items: list<Post>, cursor: string|null, has_more: bool, total: int}
     */
    public function forUser(User $user, ?string $cursor = null, array $options = []): array
    {
        $limit = max(1, min(50, (int) ($options['limit'] ?? config('feed.page_size', 10))));

        $ranked = $this->cache->get($user);

        if ($ranked === null) {
            $ranked = $this->cache->rebuild($user, $this->candidates($user));
        }

        $decoded = $this->cache->decodeCursor($cursor);
        $offset = $decoded['offset'] ?? 0;

        if ($decoded !== null && $decoded['id'] !== null) {
            // Re-anchor the offset on the last delivered post so inserts at the
            // top of the feed cannot shift the window and duplicate posts.
            $position = $this->positionOf($ranked, $decoded['id']);
            $offset = $position !== null ? $position + 1 : $offset;
        }

        $slice = array_slice($ranked, $offset, $limit + 1);
        $hasMore = count($slice) > $limit;
        $page = array_slice($slice, 0, $limit);

        /** @var list<Post> $posts */
        $posts = $this->hydrate($page);

        $this->trackImpressions($user, $posts);

        $nextCursor = $hasMore && $page !== []
            ? $this->cache->encodeCursor($page[count($page) - 1], $offset + $limit)
            : null;

        return [
            'items' => $posts,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
            'total' => count($ranked),
        ];
    }

    /**
     * Chronological fallback ("Most recent" tab). No ranking, no cache — a
     * simple keyset pagination on (created_at, id).
     *
     * @return array{items: LengthAwarePaginator<Post>, next_cursor: string|null}
     */
    public function recentForUser(User $user, ?string $cursor = null, int $limit = 20): array
    {
        $sourceIds = $this->sourceIds($user);
        $decoded = $this->cache->decodeCursor($cursor);

        $query = Post::query()
            ->published()
            ->with($this->eagerLoads())
            ->whereIn('user_id', $sourceIds)
            ->where(function (Builder $q) use ($user, $sourceIds): void {
                $q->where('audience', Audience::Public->value)
                    ->orWhere(fn (Builder $b) => $b->where('audience', Audience::Friends->value)
                        ->whereIn('user_id', $user->friendIds()))
                    ->orWhere('user_id', $user->id);
            })
            ->whereNotIn('user_id', $user->blockedIds())
            ->whereNotIn('id', $this->hiddenPostIds($user))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($decoded !== null && $decoded['id'] !== null) {
            $anchor = Post::find($decoded['id']);

            if ($anchor !== null) {
                $query->where(function (Builder $q) use ($anchor): void {
                    $q->where('created_at', '<', $anchor->created_at)
                        ->orWhere(fn (Builder $b) => $b->where('created_at', '=', $anchor->created_at)
                            ->where('id', '<', $anchor->id));
                });
            }
        }

        $posts = $query->limit($limit + 1)->get();
        $hasMore = $posts->count() > $limit;
        $items = $posts->slice(0, $limit);

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $items->values()->all(),
            $hasMore ? $limit + 1 : $items->count(),
            $limit,
            1,
            ['path' => request()->url()]
        );

        $last = $items->last();

        return [
            'items' => $paginator,
            'next_cursor' => $hasMore && $last !== null
                ? $this->cache->encodeCursor(['id' => $last->id, 'score' => 0], 0)
                : null,
        ];
    }

    /**
     * Raw candidate pool for ranking.
     *
     * Deliberately capped (default 500 posts / 7 days) — ranking all of a
     * heavy user's graph on every request is wasteful when only the first few
     * screens are ever read.
     *
     * @return Collection<int, Post>
     */
    public function candidates(User $user): Collection
    {
        $sourceIds = $this->sourceIds($user);

        if ($sourceIds === []) {
            return collect();
        }

        $friendIds = $user->friendIds();
        $hiddenIds = $this->hiddenPostIds($user);
        $blockedIds = $user->blockedIds();

        return Post::query()
            ->published()
            ->with($this->eagerLoads())
            ->whereIn('user_id', $sourceIds)
            ->where('created_at', '>=', now()->subDays((int) config('feed.lookback_days', 7)))
            ->where(function (Builder $q) use ($user, $friendIds): void {
                $q->where('audience', Audience::Public->value)
                    ->orWhere(fn (Builder $b) => $b->where('audience', Audience::Friends->value)
                        ->whereIn('user_id', $friendIds))
                    ->orWhere('user_id', $user->id);
            })
            ->when($blockedIds !== [], fn (Builder $q) => $q->whereNotIn('user_id', $blockedIds))
            ->when($hiddenIds !== [], fn (Builder $q) => $q->whereNotIn('id', $hiddenIds))
            ->limit((int) config('feed.candidate_limit', 500))
            ->get();
    }

    /**
     * Authors whose posts may appear: the viewer, everyone they follow, groups
     * they have joined and pages they follow.
     *
     * @return list<string>
     */
    public function sourceIds(User $user): array
    {
        return DB::table('friendships')
            ->where('user_id', $user->id)
            ->where('is_following', true)
            ->where(fn ($q) => $q->whereNull('muted_until')->orWhere('muted_until', '<', now()))
            ->pluck('friend_id')
            ->push($user->id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Posts the viewer hid or reported: they must never come back in a later
     * page, even after a cache rebuild.
     *
     * @return list<string>
     */
    public function hiddenPostIds(User $user): array
    {
        return DB::table('newsfeed_hidden')
            ->where('user_id', $user->id)
            ->pluck('post_id')
            ->all();
    }

    /**
     * Hide a post and remember why, so the ranking model can learn from it.
     */
    public function hidePost(User $user, Post $post, string $reason = 'hidden'): void
    {
        DB::table('newsfeed_hidden')->upsert(
            [
                'user_id' => $user->id,
                'post_id' => $post->id,
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['user_id', 'post_id'],
            ['reason', 'updated_at']
        );

        $this->tracking->record($user, 'hide', $post, $post->user_id, 'user');

        $this->cache->forget($user);
    }

    /**
     * "There are N new posts" indicator: posts published since the viewer last
     * pulled the feed.
     */
    public function newPostsCount(User $user, ?\DateTimeInterface $since = null): int
    {
        $sourceIds = $this->sourceIds($user);

        if ($sourceIds === []) {
            return 0;
        }

        $since ??= DB::table('newsfeed_seen')
            ->where('user_id', $user->id)
            ->max('last_seen_at') ?? now()->subHour();

        return Post::query()
            ->published()
            ->whereIn('user_id', $sourceIds)
            ->where('created_at', '>', $since)
            ->where('user_id', '!=', $user->id)
            ->count();
    }

    /**
     * @param  list<array{id: string, score: float}>  $ranked
     */
    private function positionOf(array $ranked, string $postId): ?int
    {
        foreach ($ranked as $index => $item) {
            if ($item['id'] === $postId) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Hydrate a page of ranked ids back into Post models, preserving the
     * ranking order (a naive whereIn() would return them in primary key order).
     *
     * @param  list<array{id: string, score: float}>  $page
     * @return list<Post>
     */
    private function hydrate(array $page): array
    {
        if ($page === []) {
            return [];
        }

        $ids = array_column($page, 'id');
        $order = array_flip($ids);

        return Post::query()
            ->with($this->eagerLoads())
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Post $post): int => $order[$post->id] ?? PHP_INT_MAX)
            ->values()
            ->all();
    }

    /**
     * @param  list<Post>  $posts
     */
    private function trackImpressions(User $user, array $posts): void
    {
        foreach ($posts as $post) {
            DB::table('newsfeed_seen')->upsert(
                [
                    'user_id' => $user->id,
                    'post_id' => $post->id,
                    'impressions' => 1,
                    'dwell_ms' => 0,
                    'last_seen_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                ['user_id', 'post_id'],
                ['impressions' => DB::raw('impressions + 1'), 'last_seen_at' => now()]
            );
        }
    }

    /**
     * Relations the feed card needs. Loading them in one query each avoids the
     * N+1 that would otherwise dominate feed render time.
     *
     * @return list<string>
     */
    private function eagerLoads(): array
    {
        return [
            'author',
            'author.profile',
            'media',
            'link.preview',
            'poll.options',
            'feelings',
            'sharedPost.author',
            'sharedPost.media',
        ];
    }
}

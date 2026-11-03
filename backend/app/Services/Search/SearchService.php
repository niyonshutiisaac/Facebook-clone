<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\Group;
use App\Models\Page;
use App\Models\Post;
use App\Models\SearchHistory;
use App\Models\TrendingTopic;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ranked, tabbed search across people, posts, pages, groups and events.
 *
 * Ranking rule from the spec, applied to people:
 *   friends > friends-of-friends > everyone else
 * implemented as a SQL `FIELD`-style ordering so the database does the work and
 * the result stays paginate-able (re-sorting in PHP would break pagination).
 */
final class SearchService
{
    /**
     * @return array<string, LengthAwarePaginator<mixed>|Collection<int, mixed>>
     */
    public function search(User $user, string $term, string $type = 'all', int $perPage = 20): array
    {
        $this->remember($user, $term);
        $this->bumpTrending($term);

        return match ($type) {
            'people' => ['people' => $this->people($user, $term, $perPage)],
            'posts' => ['posts' => $this->posts($user, $term, $perPage)],
            'pages' => ['pages' => $this->pages($term, $perPage)],
            'groups' => ['groups' => $this->groups($term, $perPage)],
            'events' => ['events' => $this->events($term, $perPage)],
            default => [
                'people' => $this->people($user, $term, 5),
                'posts' => $this->posts($user, $term, 5),
                'pages' => $this->pages($term, 5),
                'groups' => $this->groups($term, 5),
                'events' => $this->events($term, 5),
            ],
        };
    }

    /**
     * Type-ahead suggestions for the header dropdown.
     *
     * @return list<array{id: string, type: string, title: string, subtitle: ?string, avatar_url: string}>
     */
    public function suggestions(User $user, string $term, int $limit = 8): array
    {
        if (mb_strlen(trim($term)) < 2) {
            return [];
        }

        $people = $this->people($user, $term, $limit)
            ->getCollection()
            ->map(fn (User $person): array => [
                'id' => (string) $person->id,
                'type' => 'user',
                'title' => $person->name(),
                // Profiles are addressed by username, so the client needs it to
                // build a link — the UUID alone is not enough.
                'username' => $person->username,
                'subtitle' => $person->profile?->current_city,
                'avatar_url' => $person->avatar_url,
            ])
            ->all();

        $groups = Group::search($term)
            ->query(fn ($q) => $q->visible()->limit(3))
            ->get()
            ->map(fn (Group $group): array => [
                'id' => (string) $group->id,
                'type' => 'group',
                'title' => $group->name,
                'subtitle' => sprintf('%s members', number_format($group->members_count)),
                'avatar_url' => $group->avatar_path ?? '',
            ])
            ->all();

        $pages = Page::search($term)
            ->query(fn ($q) => $q->limit(3))
            ->get()
            ->map(fn (Page $page): array => [
                'id' => (string) $page->id,
                'type' => 'page',
                'title' => $page->name,
                'subtitle' => $page->category,
                'avatar_url' => $page->avatar_path ?? '',
            ])
            ->all();

        return array_slice([...$people, ...$groups, ...$pages], 0, $limit);
    }

    /**
     * @return LengthAwarePaginator<User>
     */
    public function people(User $user, string $term, int $perPage = 20): LengthAwarePaginator
    {
        $friendIds = $user->friendIds();

        $fofIds = $friendIds === [] ? [] : DB::table('friendships')
            ->whereIn('user_id', $friendIds)
            ->whereNotIn('friend_id', [...$friendIds, $user->id])
            ->pluck('friend_id')
            ->unique()
            ->values()
            ->all();

        $query = User::query()
            ->active()
            ->search($term)
            ->with('profile')
            ->orderByRaw(
                sprintf(
                    'CASE WHEN id IN (%s) THEN 0 WHEN id IN (%s) THEN 1 ELSE 2 END',
                    $this->quoted($friendIds),
                    $this->quoted($fofIds)
                )
            )
            ->orderBy('first_name');

        /** @var LengthAwarePaginator<User> $paginator */
        $paginator = $query->paginate($perPage);

        return $paginator;
    }

    /**
     * @return LengthAwarePaginator<Post>
     */
    public function posts(User $user, string $term, int $perPage = 20): LengthAwarePaginator
    {
        $query = Post::query()
            ->published()
            ->visibleTo($user)
            ->with(['author', 'media'])
            ->whereFullText('body', $term)
            ->latest();

        /** @var LengthAwarePaginator<Post> $paginator */
        $paginator = $query->paginate($perPage);

        return $paginator;
    }

    /**
     * @return LengthAwarePaginator<Page>
     */
    public function pages(string $term, int $perPage = 20): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<Page> $paginator */
        $paginator = Page::query()
            ->where('name', 'like', '%'.$this->escape($term).'%')
            ->orWhere('category', 'like', '%'.$this->escape($term).'%')
            ->orderByDesc('followers_count')
            ->paginate($perPage);

        return $paginator;
    }

    /**
     * @return LengthAwarePaginator<Group>
     */
    public function groups(string $term, int $perPage = 20): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<Group> $paginator */
        $paginator = Group::query()
            ->visible()
            ->where('name', 'like', '%'.$this->escape($term).'%')
            ->orWhere('description', 'like', '%'.$this->escape($term).'%')
            ->orderByDesc('members_count')
            ->paginate($perPage);

        return $paginator;
    }

    /**
     * @return LengthAwarePaginator<\App\Models\Event>
     */
    public function events(string $term, int $perPage = 20): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<\App\Models\Event> $paginator */
        $paginator = \App\Models\Event::query()
            ->upcoming()
            ->where('name', 'like', '%'.$this->escape($term).'%')
            ->paginate($perPage);

        return $paginator;
    }

    /**
     * @return Collection<int, SearchHistory>
     */
    public function recent(User $user, int $limit = 10): Collection
    {
        return SearchHistory::query()
            ->where('user_id', $user->id)
            ->orderByDesc('last_searched_at')
            ->limit($limit)
            ->get();
    }

    public function clearRecent(User $user, ?string $id = null): int
    {
        $query = SearchHistory::query()->where('user_id', $user->id);

        if ($id !== null) {
            $query->where('id', $id);
        }

        return $query->delete();
    }

    /**
     * @return Collection<int, TrendingTopic>
     */
    public function trending(int $limit = 10): Collection
    {
        return TrendingTopic::query()
            ->orderByDesc('velocity')
            ->orderByDesc('search_count')
            ->limit($limit)
            ->get();
    }

    // ------------------------------------------------------------- internals

    private function remember(User $user, string $term): void
    {
        $existing = SearchHistory::query()
            ->where('user_id', $user->id)
            ->where('query', $term)
            ->first();

        if ($existing !== null) {
            $existing->increment('hits');
            $existing->forceFill(['last_searched_at' => now()])->save();

            return;
        }

        SearchHistory::create([
            'user_id' => $user->id,
            'query' => $term,
            'last_searched_at' => now(),
        ]);

        // Keep the recent list bounded per user.
        SearchHistory::query()
            ->where('user_id', $user->id)
            ->orderByDesc('last_searched_at')
            ->skip(50)
            ->take(PHP_INT_MAX)
            ->delete();
    }

    private function bumpTrending(string $term): void
    {
        TrendingTopic::query()->upsert(
            [
                'term' => $term,
                'search_count' => 1,
                'last_computed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['term'],
            ['search_count' => DB::raw('search_count + 1'), 'last_computed_at' => now()]
        );
    }

    /**
     * @param  list<string>  $ids
     */
    private function quoted(array $ids): string
    {
        if ($ids === []) {
            return "''";
        }

        return implode(',', array_map(static fn (string $id): string => "'".addslashes($id)."'", $ids));
    }

    private function escape(string $term): string
    {
        return str_replace(['%', '_'], ['\%', '\_'], $term);
    }
}

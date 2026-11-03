<?php

declare(strict_types=1);

namespace App\Http\Controllers\Feed;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\Feed\InteractionTrackingService;
use App\Services\Feed\NewsFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NewsFeedController extends Controller
{
    public function __construct(
        private readonly NewsFeedService $feed,
        private readonly InteractionTrackingService $tracking,
    ) {
    }

    /**
     * Ranked feed, cursor paginated.
     *
     * GET /api/feed?cursor=…&limit=10
     */
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'cursor' => ['nullable', 'string', 'max:512'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'session_depth' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        $page = $this->feed->forUser($user, $validated['cursor'] ?? null, [
            'limit' => $validated['limit'] ?? null,
            'session_depth' => (float) ($validated['session_depth'] ?? 0.0),
        ]);

        return response()->json([
            'data' => PostResource::collection($page['items']),
            'meta' => [
                'cursor' => $page['cursor'],
                'has_more' => $page['has_more'],
                'total' => $page['total'],
            ],
        ]);
    }

    /**
     * Chronological fallback ("Most recent").
     */
    public function recent(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'cursor' => ['nullable', 'string', 'max:512'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $result = $this->feed->recentForUser(
            $user,
            $validated['cursor'] ?? null,
            (int) ($validated['limit'] ?? 20)
        );

        return response()->json([
            'data' => PostResource::collection($result['items']),
            'meta' => [
                'cursor' => $result['next_cursor'],
                'has_more' => $result['next_cursor'] !== null,
                'total' => $result['items']->total(),
            ],
        ]);
    }

    /**
     * "N new posts" pill at the top of the feed.
     */
    public function newPostsCount(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json([
            'count' => $this->feed->newPostsCount($user),
        ]);
    }

    /**
     * Hide a post and learn from it.
     */
    public function hide(Request $request, Post $post): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'in:hidden,reported,snoozed_author,irrelevant'],
        ]);

        $this->feed->hidePost($user, $post, $validated['reason'] ?? 'hidden');

        return response()->json(['message' => 'Post hidden.']);
    }

    /**
     * Snooze an author for 30 days without unfriending them.
     */
    public function snooze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        \App\Models\Friendship::query()
            ->where('user_id', $user->id)
            ->where('friend_id', $validated['user_id'])
            ->update(['muted_until' => now()->addDays((int) ($validated['days'] ?? 30))]);

        return response()->json(['message' => 'Snoozed.']);
    }

    /**
     * Dwell tracking. The client batches "how long was post X on screen"
     * events and flushes them when the page unloads; they are the strongest
     * signal in the affinity model.
     */
    public function trackInteractions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'events' => ['required', 'array', 'max:100'],
            'events.*.post_id' => ['nullable', 'uuid', 'exists:posts,id'],
            'events.*.action' => ['required', 'string', 'max:30'],
            'events.*.duration_ms' => ['nullable', 'integer', 'min:0', 'max:3600000'],
            'events.*.subject_id' => ['nullable', 'uuid'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        foreach ($validated['events'] as $event) {
            $post = isset($event['post_id']) ? Post::find($event['post_id']) : null;

            $this->tracking->record(
                $user,
                (string) $event['action'],
                $post,
                $event['subject_id'] ?? null,
                isset($event['subject_id']) ? 'user' : null,
                (int) ($event['duration_ms'] ?? 0),
                ['post_type' => $post?->type]
            );
        }

        return response()->json(['tracked' => count($validated['events'])]);
    }
}

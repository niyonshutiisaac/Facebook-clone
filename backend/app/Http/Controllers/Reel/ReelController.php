<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reel;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\Reel;
use App\Services\Content\ReelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reels: vertical short-form video with its own (content-based) ranking.
 */
final class ReelController extends Controller
{
    public function __construct(private readonly ReelService $reels)
    {
    }

    public function feed(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'cursor' => ['nullable', 'string', 'max:512'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $page = $this->reels->feed($user, $validated['cursor'] ?? null, (int) ($validated['limit'] ?? 10));

        return response()->json([
            'data' => $page['items']->map(fn (Reel $reel): array => $this->serialize($reel, $user)),
            'meta' => ['cursor' => $page['cursor'], 'has_more' => $page['has_more']],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'video' => ['required', 'file', 'max:524288', 'mimes:mp4,mov,webm'],
            'caption' => ['nullable', 'string', 'max:2200'],
            'audience' => ['nullable', 'string', 'in:public,friends,only_me'],
            'allows_remix' => ['nullable', 'boolean'],
            'audio_id' => ['nullable', 'uuid', 'exists:reel_audio,id'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $reel = $this->reels->create($user, $validated, $request->file('video'));

        return response()->json(['reel' => $this->serialize($reel, $user)], 201);
    }

    public function show(Reel $reel): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = request()->user();

        return response()->json(['reel' => $this->serialize($reel, $user)]);
    }

    public function destroy(Reel $reel): JsonResponse
    {
        $this->authorize('delete', $reel);

        $reel->delete();

        return response()->json(['message' => 'Reel deleted.']);
    }

    public function view(Request $request, Reel $reel): JsonResponse
    {
        $validated = $request->validate([
            'watched_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'replays' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->reels->recordView(
            $reel,
            $user,
            (int) ($validated['watched_seconds'] ?? 0),
            (int) ($validated['replays'] ?? 0)
        );

        return response()->json(['message' => 'View recorded.']);
    }

    public function share(Request $request, Reel $reel): JsonResponse
    {
        $validated = $request->validate([
            'destination' => ['nullable', 'string', 'in:feed,message,story,external'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $reel->shares()->firstOrCreate([
            'user_id' => $user->id,
            'destination' => $validated['destination'] ?? 'feed',
        ]);

        $reel->increment('shares_count');

        return response()->json(['shares_count' => $reel->fresh()->shares_count]);
    }

    public function comments(Request $request, Reel $reel): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return CommentResource::collection(
            $reel->comments()
                ->with('author')
                ->latest()
                ->paginate((int) ($validated['per_page'] ?? 20))
        )->response();
    }

    public function storeComment(Request $request, Reel $reel): JsonResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $comment = $this->reels->comment($reel, $user, $validated['body']);

        return (new CommentResource($comment->load('author')))->response()->setStatusCode(201);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Reel $reel, \App\Models\User $viewer): array
    {
        return [
            'id' => $reel->id,
            'url' => $reel->url,
            'poster_url' => $reel->poster_url,
            'caption' => $reel->caption,
            'duration' => $reel->duration,
            'width' => $reel->width,
            'height' => $reel->height,
            'audio' => $reel->audio !== null ? [
                'id' => $reel->audio->id,
                'title' => $reel->audio->title,
                'artist' => $reel->audio->artist,
            ] : null,
            'creator' => [
                'id' => $reel->creator->id,
                'name' => $reel->creator->name(),
                'username' => $reel->creator->username,
                'avatar_url' => $reel->creator->avatar_url,
                'is_verified' => (bool) $reel->creator->is_verified,
                'is_following' => $reel->creator->isFollowedBy($viewer),
            ],
            'views_count' => (int) $reel->views_count,
            'likes_count' => (int) $reel->likes_count,
            'comments_count' => (int) $reel->comments_count,
            'shares_count' => (int) $reel->shares_count,
            'viewer_reaction' => $reel->reactionBy($viewer)?->type,
            'allows_remix' => (bool) $reel->allows_remix,
            'created_at' => $reel->created_at?->toIso8601String(),
        ];
    }
}

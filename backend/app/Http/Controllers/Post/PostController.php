<?php

declare(strict_types=1);

namespace App\Http\Controllers\Post;

use App\Http\Controllers\Controller;
use App\Http\Requests\Post\StorePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\Content\PostService;
use App\Services\Feed\FeedCacheService;
use App\Services\Media\LinkPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PostController extends Controller
{
    public function __construct(
        private readonly PostService $posts,
        private readonly LinkPreviewService $linkPreview,
        private readonly FeedCacheService $feedCache,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'type' => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $query = Post::query()
            ->published()
            ->with(['author', 'media', 'link.preview', 'poll.options', 'feelings', 'sharedPost.author'])
            ->latest();

        if (isset($validated['user_id'])) {
            $query->timeline(\App\Models\User::findOrFail($validated['user_id']));
        }

        $query->visibleTo($user);

        return response()->json(
            PostResource::collection($query->paginate((int) ($validated['per_page'] ?? 15)))
        );
    }

    public function show(Post $post): JsonResponse
    {
        $this->authorize('view', $post);

        $post->load(['author', 'media', 'link.preview', 'poll.options', 'feelings', 'sharedPost.author', 'group', 'page']);

        return response()->json(new PostResource($post));
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $post = $this->posts->create(
            $user,
            $request->validated(),
            $request->file('media', [])
        );

        // Friends see the new post on their next feed refresh.
        $this->feedCache->forgetForAudienceOf($user);

        return response()->json(new PostResource($post), 201);
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $this->authorize('update', $post);

        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json(
            new PostResource($this->posts->update($post, $request->validated(), $user))
        );
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        $this->posts->delete($post);

        return response()->json(['message' => 'Post deleted.']);
    }

    /**
     * Change the audience of an existing post without editing its body.
     */
    public function audience(Request $request, Post $post): JsonResponse
    {
        $this->authorize('update', $post);

        $validated = $request->validate([
            'audience' => ['required', 'string', 'in:public,friends,friends_except,specific,only_me'],
            'audience_user_ids' => ['nullable', 'array'],
            'audience_user_ids.*' => ['uuid', 'exists:users,id'],
        ]);

        $updated = $this->posts->changeAudience(
            $post,
            \App\Enums\Audience::from($validated['audience']),
            $validated['audience_user_ids'] ?? []
        );

        return response()->json(new PostResource($updated));
    }

    /**
     * Full edit history (owner only).
     */
    public function editHistory(Post $post): JsonResponse
    {
        $this->authorize('update', $post);

        return response()->json([
            'data' => $post->editHistory()->with('editor')->get()->map(fn ($row): array => [
                'id' => $row->id,
                'previous_body' => $row->previous_body,
                'previous_audience' => $row->previous_audience,
                'reason' => $row->reason,
                'editor' => $row->editor?->name(),
                'created_at' => $row->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Share a post to your own timeline, a group or a page.
     */
    public function share(Request $request, Post $post): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:63206'],
            'audience' => ['nullable', 'string', 'in:public,friends,friends_except,specific,only_me'],
            'group_id' => ['nullable', 'uuid', 'exists:groups,id'],
            'page_id' => ['nullable', 'uuid', 'exists:pages,id'],
        ]);

        $shared = $this->posts->share($user, $post, $validated);

        $this->feedCache->forgetForAudienceOf($user);

        return response()->json(new PostResource($shared), 201);
    }

    /**
     * Pin / unpin on your own timeline.
     */
    public function pin(Request $request, Post $post): JsonResponse
    {
        $this->authorize('update', $post);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $user->posts()->where('is_pinned', true)->update(['is_pinned' => false]);

        $post->forceFill(['is_pinned' => $request->boolean('pinned', true)])->save();

        return response()->json(new PostResource($post->fresh()));
    }

    /**
     * Fetch Open Graph metadata for a URL while the composer is still open, so
     * the preview renders before the user hits Post.
     */
    public function linkPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        return response()->json($this->linkPreview->fetch($validated['url']));
    }
}

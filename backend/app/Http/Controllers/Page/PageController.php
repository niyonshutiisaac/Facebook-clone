<?php

declare(strict_types=1);

namespace App\Http\Controllers\Page;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Pages: the basic surface — create, read, follow and publish as the page.
 */
final class PageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'website' => ['nullable', 'url', 'max:255'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $page = $user->ownedPages()->create($validated);

        $page->roles()->create([
            'user_id' => $user->id,
            'role' => 'admin',
            'granted_at' => now(),
        ]);

        return response()->json(['page' => $page->fresh()], 201);
    }

    public function show(Page $page): JsonResponse
    {
        return response()->json([
            'page' => [
                'id' => $page->id,
                'name' => $page->name,
                'slug' => $page->slug,
                'category' => $page->category,
                'description' => $page->description,
                'website' => $page->website,
                'about' => $page->about,
                'avatar_url' => $page->avatar_path !== null
                    ? \App\Support\MediaUrl::image($page->avatar_path, 'avatar', (string) $page->id) : null,
                'cover_url' => $page->cover_path !== null
                    ? \App\Support\MediaUrl::image($page->cover_path, 'cover', (string) $page->id) : null,
                'followers_count' => (int) $page->followers_count,
                'likes_count' => (int) $page->likes_count,
                'is_verified' => (bool) $page->is_verified,
                'is_following' => request()->user() !== null && $page->isFollowedBy(request()->user()),
            ],
        ]);
    }

    public function update(Request $request, Page $page): JsonResponse
    {
        $this->authorize('update', $page);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'about' => ['sometimes', 'nullable', 'array'],
        ]);

        $page->forceFill($validated)->save();

        return response()->json(['page' => $page->fresh()]);
    }

    public function destroy(Page $page): JsonResponse
    {
        $this->authorize('delete', $page);

        $page->delete();

        return response()->json(['message' => 'Page deleted.']);
    }

    public function posts(Page $page): JsonResponse
    {
        return response()->json(PostResource::collection(
            $page->posts()->with(['media', 'link.preview'])->latest()->paginate(15)
        ));
    }

    public function storePost(Request $request, Page $page): JsonResponse
    {
        $this->authorize('update', $page);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $post = app(\App\Services\Content\PostService::class)->create(
            $user,
            array_merge($request->all(), ['page_id' => $page->id]),
            $request->file('media', [])
        );

        $page->increment('posts_count');

        return response()->json(new PostResource($post), 201);
    }

    public function follow(Request $request, Page $page): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $page->followers()->syncWithoutDetaching([$user->id => ['followed_at' => now()]]);
        $page->increment('followers_count');

        return response()->json(['is_following' => true], 201);
    }

    public function unfollow(Request $request, Page $page): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $page->followers()->detach($user->id);
        $page->decrement('followers_count');

        return response()->json(['is_following' => false]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Post;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\SavedCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SavedPostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'collection_id' => ['nullable', 'uuid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $user->savedPosts()
            ->with(['post.author', 'post.media', 'post.link.preview'])
            ->latest();

        if (isset($validated['collection_id'])) {
            $query->where('collection_id', $validated['collection_id']);
        }

        $page = $query->paginate((int) ($validated['per_page'] ?? 20));

        return response()->json([
            'data' => PostResource::collection($page->getCollection()->pluck('post')->filter()),
            'meta' => [
                'total' => $page->total(),
                'per_page' => $page->perPage(),
                'current_page' => $page->currentPage(),
            ],
        ]);
    }

    public function store(Request $request, Post $post): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'collection_id' => ['nullable', 'uuid', 'exists:saved_collections,id'],
        ]);

        $user->savedPosts()->firstOrCreate(
            ['post_id' => $post->id],
            ['collection_id' => $validated['collection_id'] ?? null]
        );

        return response()->json(['message' => 'Saved.', 'is_saved' => true]);
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $user->savedPosts()->where('post_id', $post->id)->delete();

        return response()->json(['message' => 'Removed.', 'is_saved' => false]);
    }

    public function collections(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json([
            'data' => $user->savedCollections()->withCount('items')->get()->map(fn (SavedCollection $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'cover_path' => $c->cover_path,
                'items_count' => $c->items_count,
            ]),
        ]);
    }

    public function storeCollection(Request $request): JsonResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:120']]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json(
            $user->savedCollections()->create($validated),
            201
        );
    }

    public function updateCollection(Request $request, SavedCollection $collection): JsonResponse
    {
        $this->authorize('update', $collection);

        $validated = $request->validate(['name' => ['required', 'string', 'max:120']]);

        $collection->forceFill($validated)->save();

        return response()->json($collection->fresh());
    }

    public function destroyCollection(SavedCollection $collection): JsonResponse
    {
        $this->authorize('delete', $collection);

        $collection->delete();

        return response()->json(['message' => 'Collection deleted.']);
    }
}

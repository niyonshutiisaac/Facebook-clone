<?php

declare(strict_types=1);

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Http\Resources\GroupResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Services\Search\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final class SearchController extends Controller
{
    public function __construct(private readonly SearchService $search)
    {
    }

    /**
     * Tabbed results page.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'q' => ['required', 'string', 'max:200'],
            'type' => ['nullable', 'string', 'in:all,posts,people,photos,videos,pages,groups,events'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $results = $this->search->search(
            $user,
            $validated['q'],
            $validated['type'] ?? 'all',
            (int) ($validated['per_page'] ?? 20)
        );

        $payload = [];

        foreach ($results as $tab => $paginator) {
            $payload[$tab] = match ($tab) {
                'people' => UserResource::collection($paginator),
                'posts' => PostResource::collection($paginator),
                'groups' => GroupResource::collection($paginator),
                default => $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator
                    ? $paginator->toArray()
                    : $paginator,
            };
        }

        return response()->json([
            'query' => $validated['q'],
            'results' => $payload,
        ]);
    }

    /**
     * Type-ahead for the header dropdown (debounced 300ms client side).
     */
    public function suggestions(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'q' => ['required', 'string', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        return response()->json([
            'suggestions' => $this->search->suggestions(
                $user,
                $validated['q'],
                (int) ($validated['limit'] ?? 8)
            ),
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json(['data' => $this->search->recent($user)]);
    }

    public function destroyRecent(Request $request, ?string $id = null): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json(['deleted' => $this->search->clearRecent($user, $id)]);
    }

    public function trending(Request $request): JsonResponse
    {
        $validated = $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:50']]);

        return response()->json(['data' => $this->search->trending((int) ($validated['limit'] ?? 10))]);
    }
}

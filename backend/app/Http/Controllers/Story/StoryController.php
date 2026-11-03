<?php

declare(strict_types=1);

namespace App\Http\Controllers\Story;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoryResource;
use App\Http\Resources\UserResource;
use App\Models\Story;
use App\Services\Content\StoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StoryController extends Controller
{
    public function __construct(private readonly StoryService $stories)
    {
    }

    /**
     * The story tray: one entry per author with unseen stories first.
     */
    public function feed(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json([
            'trays' => $this->stories->trayFor($user)->map(fn (array $tray): array => [
                'user' => new UserResource($tray['user']),
                'stories' => StoryResource::collection($tray['stories']),
                'has_unseen' => $tray['has_unseen'],
                'latest_at' => $tray['latest_at'],
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:photo,video,text'],
            'body' => ['nullable', 'string', 'max:2000'],
            'background' => ['nullable', 'string', 'max:40'],
            'audience' => ['nullable', 'string', 'in:public,friends,close_friends,only_me'],
            'duration' => ['nullable', 'integer', 'min:3', 'max:20'],
            'media' => ['required_if:type,photo,video', 'file', 'max:262144',
                'mimes:jpg,jpeg,png,webp,mp4,mov,webm'],
            'stickers' => ['nullable', 'array', 'max:30'],
            'stickers.*.type' => ['required_with:stickers', 'string', 'max:20'],
            'stickers.*.payload' => ['nullable', 'array'],
            'stickers.*.x_position' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stickers.*.y_position' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stickers.*.scale' => ['nullable', 'numeric', 'min:0.1', 'max:5'],
            'stickers.*.rotation' => ['nullable', 'numeric', 'min:-360', 'max:360'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $story = $this->stories->create(
            $user,
            $validated,
            $request->file('media')
        );

        return response()->json(new StoryResource($story->load('media', 'stickers')), 201);
    }

    public function show(Story $story): JsonResponse
    {
        $this->authorize('view', $story);

        return response()->json(new StoryResource($story->load(['author', 'media', 'stickers'])));
    }

    public function destroy(Story $story): JsonResponse
    {
        $this->authorize('delete', $story);

        $story->delete();

        return response()->json(['message' => 'Story deleted.']);
    }

    public function view(Request $request, Story $story): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->stories->markViewed($story, $user);

        return response()->json(['message' => 'Viewed.']);
    }

    public function react(Request $request, Story $story): JsonResponse
    {
        $validated = $request->validate([
            'reaction' => ['required', 'string', 'in:like,love,care,haha,wow,sad,angry'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->stories->react($story, $user, $validated['reaction']);

        return response()->json(['message' => 'Reaction sent.']);
    }

    public function reply(Request $request, Story $story): JsonResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->stories->reply($story, $user, $validated['body']);

        return response()->json(['message' => 'Reply sent.'], 201);
    }

    public function viewers(Request $request, Story $story): JsonResponse
    {
        $this->authorize('viewInsights', $story);

        return response()->json([
            'data' => UserResource::collection(
                $story->viewers()->with('viewer')->latest('viewed_at')->get()->pluck('viewer')->filter()
            ),
            'meta' => ['total' => (int) $story->views_count],
        ]);
    }
}

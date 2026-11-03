<?php

declare(strict_types=1);

namespace App\Http\Controllers\Friend;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Social\FriendSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FriendSuggestionController extends Controller
{
    public function __construct(private readonly FriendSuggestionService $suggestions)
    {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:50']]);

        $suggestions = $this->suggestions->for($user, (int) ($validated['limit'] ?? 20));

        return response()->json([
            'data' => UserResource::collection($suggestions->pluck('suggested')->filter()),
            'meta' => [
                'mutual_counts' => $suggestions->pluck('mutual_friends_count', 'suggested_user_id'),
            ],
        ]);
    }

    public function dismiss(Request $request, User $user): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $this->suggestions->dismiss($viewer, $user);

        return response()->json(['message' => 'Suggestion dismissed.']);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Friend;

use App\Http\Controllers\Controller;
use App\Http\Resources\FriendRequestResource;
use App\Models\FriendRequest;
use App\Models\User;
use App\Services\Social\FriendshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FriendRequestController extends Controller
{
    public function __construct(private readonly FriendshipService $friendships)
    {
    }

    public function received(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(FriendRequestResource::collection(
            $this->friendships->receivedRequests($user)
        ));
    }

    public function sent(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(FriendRequestResource::collection(
            $this->friendships->sentRequests($user)
        ));
    }

    public function store(Request $request, User $user): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $request->merge(['message' => $request->input('message')]);

        $friendRequest = $this->friendships->sendRequest($viewer, $user, $request->input('message'));

        return response()->json(new FriendRequestResource($friendRequest), 201);
    }

    public function accept(FriendRequest $friendRequest): JsonResponse
    {
        $this->authorize('respond', $friendRequest);

        $this->friendships->accept($friendRequest);

        return response()->json(['message' => 'Friend request accepted.', 'is_friend' => true]);
    }

    public function decline(FriendRequest $friendRequest): JsonResponse
    {
        $this->authorize('respond', $friendRequest);

        $this->friendships->decline($friendRequest);

        return response()->json(['message' => 'Friend request declined.']);
    }

    public function destroy(FriendRequest $friendRequest): JsonResponse
    {
        $this->authorize('cancel', $friendRequest);

        $this->friendships->cancel($friendRequest);

        return response()->json(['message' => 'Friend request cancelled.']);
    }
}

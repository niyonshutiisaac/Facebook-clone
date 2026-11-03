<?php

declare(strict_types=1);

namespace App\Http\Controllers\Messenger;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\Messaging\MessengerService;
use App\Services\Messaging\PresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PresenceController extends Controller
{
    public function __construct(
        private readonly PresenceService $presence,
        private readonly MessengerService $messenger,
    ) {
    }

    /**
     * Heartbeat. The SPA calls this every 30 seconds while a tab is open.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $next = $this->presence->touch($user, $request->input('connection_id'));

        return response()->json([
            'online' => true,
            'next_heartbeat_in' => $next,
            'online_count' => $this->presence->onlineCount(),
        ]);
    }

    public function onlineFriends(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json(UserResource::collection($this->messenger->onlineFriends($user)));
    }

    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'max:200'],
            'user_ids.*' => ['uuid', 'exists:users,id'],
        ]);

        $statuses = [];

        foreach ($validated['user_ids'] as $id) {
            $user = \App\Models\User::query()->find($id);

            $statuses[$id] = $user === null ? null : [
                'is_online' => $this->presence->isOnline($user),
                'label' => $this->presence->statusLabel($user),
                'last_seen_at' => $user->last_seen_at?->toIso8601String(),
            ];
        }

        return response()->json(['statuses' => $statuses]);
    }

    public function disconnect(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->presence->disconnect($user);

        return response()->json(['online' => false]);
    }
}

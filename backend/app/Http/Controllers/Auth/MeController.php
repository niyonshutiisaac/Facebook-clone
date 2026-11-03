<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\UserResource;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MeController extends Controller
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    /**
     * The bootstrap payload the SPA loads once on start-up: who am I, my
     * settings, and the unread badge count for the header.
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $user->load(['profile', 'settings']);

        return response()->json([
            'user' => new UserResource($user),
            'settings' => $user->settings,
            'unread_notifications' => $this->notifications->unreadCount($user),
            'unread_messages' => $user->conversations()
                ->get()
                ->sum(fn ($conversation): int => $conversation->unreadCountFor($user)),
            'pending_friend_requests' => $user->receivedFriendRequests()->pending()->count(),
            'localization' => [
                'locale' => $user->locale,
                'direction' => \App\Support\Localization::direction($user->locale),
                'available' => \App\Support\Localization::available(),
            ],
        ]);
    }

    /**
     * A handful of recent notifications, so the bell is populated before the
     * user opens it for the first time.
     */
    public function notifications(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json(
            NotificationResource::collection($this->notifications->feed($user, false, 5))
        );
    }
}

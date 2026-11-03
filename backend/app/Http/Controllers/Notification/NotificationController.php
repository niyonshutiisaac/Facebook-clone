<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notification;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'filter' => ['nullable', 'string', 'in:all,unread'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(
            NotificationResource::collection(
                $this->notifications->feed(
                    $user,
                    ($validated['filter'] ?? 'all') === 'unread',
                    (int) ($validated['per_page'] ?? 20)
                )
            )
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json(['count' => $this->notifications->unreadCount($user)]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        $this->authorize('update', $notification);

        $this->notifications->markRead($request->user(), $notification->id);

        return response()->json(['message' => 'Marked as read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json(['marked_read' => $this->notifications->markRead($user)]);
    }

    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        $this->authorize('delete', $notification);

        $notification->forceFill(['archived_at' => now()])->save();

        return response()->json(['message' => 'Notification removed.']);
    }

    public function preferences(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($request->isMethod('put') || $request->isMethod('patch')) {
            $validated = $request->validate([
                'preferences' => ['required', 'array'],
                'preferences.*.type' => ['required', 'string', 'max:60'],
                'preferences.*.in_app_enabled' => ['sometimes', 'boolean'],
                'preferences.*.push_enabled' => ['sometimes', 'boolean'],
                'preferences.*.email_enabled' => ['sometimes', 'boolean'],
                'preferences.*.email_frequency' => ['sometimes', 'string', 'in:instant,daily,weekly,off'],
                'preferences.*.quiet_hours_start' => ['nullable', 'date_format:H:i'],
                'preferences.*.quiet_hours_end' => ['nullable', 'date_format:H:i'],
            ]);

            foreach ($validated['preferences'] as $row) {
                $user->notificationPreferences()->updateOrCreate(
                    ['type' => $row['type']],
                    collect($row)->except('type')->all()
                );
            }

            $user->logActivity('notifications.preferences_updated');
        }

        return response()->json([
            'preferences' => $user->notificationPreferences()->get(),
            'available_types' => array_column(NotificationType::cases(), 'value'),
        ]);
    }
}

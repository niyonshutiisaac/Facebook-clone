<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Messaging\PresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SessionController extends Controller
{
    public function __construct(private readonly PresenceService $presence)
    {
    }

    /**
     * "Where you're logged in": device, approximate location and last activity.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $currentSessionId = $request->hasSession() ? $request->session()->getId() : null;

        $sessions = $user->sessions()
            ->latest('last_activity_at')
            ->get()
            ->map(fn ($session): array => [
                'id' => $session->id,
                'device_name' => $session->device_name,
                'device_type' => $session->device_type,
                'platform' => $session->platform,
                'browser' => $session->browser,
                'location' => collect([$session->city, $session->region, $session->country])
                    ->filter()->implode(', '),
                'ip_address' => $session->ip_address,
                'is_current' => $currentSessionId !== null && $session->session_id === $currentSessionId,
                'last_activity_at' => $session->last_activity_at?->toIso8601String(),
            ]);

        return response()->json(['sessions' => $sessions]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $deleted = $user->sessions()->where('id', $id)->delete();

        if ($deleted === 0) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        $user->logActivity('auth.session_revoked', 'session', $id);

        return response()->json(['message' => 'Session revoked.']);
    }

    public function destroyAll(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $currentSessionId = $request->hasSession() ? $request->session()->getId() : null;

        $user->sessions()
            ->when($currentSessionId !== null, fn ($q) => $q->where('session_id', '!=', $currentSessionId))
            ->delete();

        $user->tokens()
            ->when($request->user()->currentAccessToken() !== null,
                fn ($q) => $q->where('id', '!=', $request->user()->currentAccessToken()->id))
            ->delete();

        $this->presence->disconnect($user);

        $user->logActivity('auth.logout_all_devices');

        return response()->json(['message' => 'Signed out of every other device.']);
    }
}

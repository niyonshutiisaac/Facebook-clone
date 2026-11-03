<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Messaging\PresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LogoutController extends Controller
{
    public function __construct(private readonly PresenceService $presence)
    {
    }

    /**
     * Revoke the current token (and optionally every other token).
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $everywhere = $request->boolean('everywhere');

        if ($everywhere) {
            $user->tokens()->delete();
            $user->sessions()->delete();
        } else {
            $user->currentAccessToken()?->delete();

            if ($request->hasSession()) {
                $user->sessions()->where('session_id', $request->session()->getId())->delete();
            }
        }

        $this->presence->disconnect($user);

        $user->logActivity('auth.logout', null, null, ['everywhere' => $everywhere]);

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Signed out.']);
    }
}

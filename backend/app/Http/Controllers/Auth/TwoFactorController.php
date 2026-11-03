<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor)
    {
    }

    /**
     * Start TOTP setup. Returns the secret (for manual entry) and an
     * otpauth:// URL the frontend renders as a QR code.
     */
    public function enable(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json([
            'status' => 'pending_confirmation',
            ...$this->twoFactor->enableTotp($user),
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'size:6']]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! $this->twoFactor->confirm($user, $validated['code'])) {
            return response()->json([
                'message' => 'That code is not correct. Check your authenticator app and try again.',
                'error' => 'invalid_code',
            ], 422);
        }

        return response()->json([
            'status' => 'enabled',
            'enabled_at' => $user->two_factor_confirmed_at?->toIso8601String(),
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:20']]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! $this->twoFactor->disable($user, $validated['code'])) {
            return response()->json([
                'message' => 'That code or recovery code is not valid.',
                'error' => 'invalid_code',
            ], 422);
        }

        return response()->json(['status' => 'disabled']);
    }

    public function sendSms(Request $request): JsonResponse
    {
        $validated = $request->validate(['phone' => ['required', 'string', 'max:30']]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $code = $this->twoFactor->sendSmsChallenge($user, $validated['phone']);

        return response()->json([
            'message' => 'We texted a code to your phone.',
            'code' => app()->environment('local', 'testing') ? $code : null,
        ]);
    }

    public function verifySms(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'size:6']]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! $this->twoFactor->verifySmsChallenge($user, $validated['code'])) {
            return response()->json([
                'message' => 'That code is not valid or has expired.',
                'error' => 'invalid_code',
            ], 422);
        }

        return response()->json(['status' => 'verified']);
    }
}

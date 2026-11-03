<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

final class PasswordResetController extends Controller
{
    /**
     * Start a reset.
     *
     * The response is deliberately identical whether or not the account exists:
     * telling an attacker which identities are registered is half the battle
     * lost (account enumeration).
     */
    public function send(ForgotPasswordRequest $request): JsonResponse
    {
        $identity = (string) $request->validated('identity');

        $user = User::query()
            ->where('email', $identity)
            ->orWhere('phone', $identity)
            ->orWhere('username', $identity)
            ->first();

        if ($user !== null) {
            // Six digit numeric code, matching Facebook's recovery flow.
            $code = (string) random_int(100000, 999999);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token' => Hash::make($code),
                    'created_at' => now(),
                ]
            );

            $user->notify(new \App\Notifications\PasswordResetCode($code));

            $user->logActivity('auth.password_reset_requested');
        }

        return response()->json([
            'message' => 'If that account exists, we have sent a six digit code.',
            // Only used by the local development flow.
            'code' => app()->environment('local', 'testing') ? ($code ?? null) : null,
        ]);
    }

    /**
     * Verify a code and set a new password.
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $identity = (string) $request->validated('identity');
        $code = (string) $request->validated('code');

        $user = User::query()
            ->where('email', $identity)
            ->orWhere('phone', $identity)
            ->orWhere('username', $identity)
            ->firstOrFail();

        $row = DB::table('password_reset_tokens')->where('email', $user->email)->first();

        if ($row === null || ! Hash::check($code, $row->token)) {
            return response()->json([
                'message' => 'That code is not valid or has expired.',
                'error' => 'invalid_code',
            ], 422);
        }

        if (now()->diffInMinutes($row->created_at) > (int) config('auth.passwords.users.expire', 60)) {
            return response()->json([
                'message' => 'That code has expired. Request a new one.',
                'error' => 'code_expired',
            ], 422);
        }

        $user->forceFill(['password' => $request->validated('password')])->save();

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        // Every existing session is revoked: a password reset is an implicit
        // "log me out everywhere" so an attacker keeps nothing.
        $user->tokens()->delete();
        $user->sessions()->delete();

        $user->logActivity('auth.password_reset');

        return response()->json(['message' => 'Your password has been changed.']);
    }

    /**
     * CHANGE password (as opposed to reset): requires the current one.
     */
    public function change(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)
                ->letters()->mixedCase()->numbers()->uncompromised()],
            'revoke_other_sessions' => ['sometimes', 'boolean'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $user->forceFill(['password' => $validated['password']])->save();

        if ($request->boolean('revoke_other_sessions')) {
            $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();
            $user->sessions()->where('is_current', false)->delete();
        }

        $user->logActivity('auth.password_changed');

        return response()->json(['message' => 'Password updated.']);
    }
}

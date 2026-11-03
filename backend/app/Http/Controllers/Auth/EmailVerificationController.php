<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class EmailVerificationController extends Controller
{
    public function verify(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'size:6']]);

        $row = DB::table('email_verifications')
            ->where('user_id', $id)
            ->where('verified_at', null)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($row === null || ! hash_equals($row->code, $validated['code'])) {
            return response()->json([
                'message' => 'This verification code is invalid or has expired.',
                'error' => 'invalid_code',
            ], 422);
        }

        /** @var User $user */
        $user = User::query()->findOrFail($id);

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        DB::table('email_verifications')->where('id', $row->id)->update(['verified_at' => now()]);

        event(new Verified($user));

        $user->logActivity('auth.email_verified');

        return response()->json(['message' => 'Email verified.']);
    }

    /**
     * Resend the six digit code, throttled to one per minute.
     */
    public function resend(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $recent = DB::table('email_verifications')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subMinute())
            ->exists();

        if ($recent) {
            return response()->json([
                'message' => 'Please wait a moment before requesting another code.',
                'error' => 'throttled',
            ], 429);
        }

        $code = (string) random_int(100000, 999999);

        DB::table('email_verifications')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'email' => $user->email,
            'code' => $code,
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user->notify(new \App\Notifications\EmailVerificationCode($code));

        return response()->json([
            'message' => 'We sent a new code to your email address.',
            'code' => app()->environment('local', 'testing') ? $code : null,
        ]);
    }
}

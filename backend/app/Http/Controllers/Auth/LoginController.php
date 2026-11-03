<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class LoginController extends Controller
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $identity = (string) $request->validated('identity');
        $password = (string) $request->validated('password');

        if ($this->auth->isLockedOut($identity, (string) $request->ip())) {
            return response()->json([
                'message' => sprintf(
                    'Too many sign-in attempts. Try again in %d minutes.',
                    max(1, (int) ceil($this->auth->secondsUntilUnlock($identity) / 60))
                ),
                'error' => 'account_locked',
                'retry_after' => $this->auth->secondsUntilUnlock($identity),
            ], 429);
        }

        [$user, $error] = $this->auth->verifyCredentials($identity, $password);

        if ($user === null) {
            $this->auth->recordAttempt(null, $identity, $request, false);

            return response()->json([
                'message' => $error ?? 'The credentials you entered are incorrect.',
                'error' => 'invalid_credentials',
            ], 422);
        }

        $this->auth->recordAttempt($user, $identity, $request, true);
        $this->auth->clearAttempts($identity);
        $this->auth->trackSession($user, $request);

        // A device seen for the first time triggers a "new login" alert email.
        $fingerprint = (string) $request->validated('device_fingerprint', '');
        $knownDevice = $fingerprint !== ''
            && $user->devices()->where('fingerprint', $fingerprint)->exists();

        if (! $knownDevice && $fingerprint !== '') {
            $user->devices()->create([
                'fingerprint' => $fingerprint,
                'device_name' => substr((string) $request->userAgent(), 0, 120),
                'platform' => $request->header('Sec-CH-UA-Platform'),
                'is_trusted' => false,
                'last_used_at' => now(),
            ]);

            $user->notify(new \App\Notifications\NewDeviceLogin(
                (string) $request->ip(),
                substr((string) $request->userAgent(), 0, 200)
            ));
        }

        $user->logActivity('auth.login', null, null, [
            'ip' => $request->ip(),
            'new_device' => ! $knownDevice,
        ]);

        if ($request->boolean('remember')) {
            $request->session()->regenerate();
        }

        $token = $user->createToken('spa', ['*'], now()->addDays(30))->plainTextToken;

        RateLimiter::clear('login:'.$identity);

        return response()->json([
            'user' => new UserResource($user->load('profile', 'settings')),
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => now()->addDays(30)->diffInSeconds(now()),
            'two_factor_required' => $user->two_factor_confirmed_at !== null,
        ]);
    }
}

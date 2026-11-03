<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RegisterController extends Controller
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    /**
     * Create an account and immediately start a session.
     *
     * Rate limited to 5/hour per IP: registration is the single most abused
     * endpoint in a social product.
     */
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = $this->auth->register($request->validated(), $request);

        // Sanctum token for API clients; the browser SPA also gets a session
        // cookie so a hard refresh keeps the user signed in.
        $token = $user->createToken('spa', ['*'], now()->addDays(30))->plainTextToken;

        $this->auth->trackSession($user, $request);

        $user->logActivity('auth.registered');

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'user' => new UserResource($user->load('profile', 'settings')),
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => now()->addDays(30)->diffInSeconds(now()),
            'email_verification_required' => ! $user->hasVerifiedEmail(),
        ], 201);
    }

    /**
     * Real-time availability check used by the signup form's blur handler.
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required_without:phone', 'nullable', 'email', 'max:255'],
            'phone' => ['required_without:email', 'nullable', 'string', 'max:30'],
        ]);

        return response()->json([
            'email_available' => isset($validated['email'])
                ? ! DB::table('users')->where('email', $validated['email'])->exists()
                : null,
            'phone_available' => isset($validated['phone'])
                ? ! DB::table('users')->where('phone', $validated['phone'])->exists()
                : null,
        ]);
    }
}

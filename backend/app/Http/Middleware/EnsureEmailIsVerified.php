<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks unverified accounts with a cooperative JSON payload (instead of a
 * redirect) so the SPA can show its own "check your inbox" screen and offer to
 * resend the code.
 */
final class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next, ?string $guard = null): Response
    {
        $user = $request->user($guard);

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            abort(response()->json([
                'message' => 'Your email address is not verified.',
                'error' => 'email_not_verified',
                'resend_url' => '/api/auth/email/resend',
            ], 403));
        }

        return $next($request);
    }
}

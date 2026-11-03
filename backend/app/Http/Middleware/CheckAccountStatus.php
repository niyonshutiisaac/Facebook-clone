<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\AccountStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suspended and disabled accounts keep their (still valid) tokens until the
 * next request — this middleware is what actually stops them acting.
 */
final class CheckAccountStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $status = AccountStatus::tryFrom((string) $user->account_status) ?? AccountStatus::Active;

            if (! $status->canAuthenticate()) {
                abort(response()->json([
                    'message' => match ($status) {
                        AccountStatus::Suspended => 'Your account has been suspended.',
                        AccountStatus::Disabled => 'Your account has been disabled.',
                        default => 'Your account is not active.',
                    },
                    'error' => 'account_inactive',
                    'status' => $status->value,
                ], 403));
            }
        }

        return $next($request);
    }
}

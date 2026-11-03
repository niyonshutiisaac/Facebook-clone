<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refreshes `last_seen_at`, throttled to one write per minute per user.
 *
 * Without the throttle, a feed that polls every few seconds would write to the
 * users table on every request — the classic way to melt a database.
 */
final class TrackUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if ($user === null) {
            return $response;
        }

        if ($user->last_seen_at === null || $user->last_seen_at->lt(now()->subMinute())) {
            $user->forceFill([
                'last_seen_at' => now(),
                'is_online' => true,
            ])->saveQuietly();
        }

        return $response;
    }
}

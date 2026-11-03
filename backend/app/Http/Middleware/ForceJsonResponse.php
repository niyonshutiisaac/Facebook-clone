<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every request to this application is an API request — the React SPA is the
 * only client. Forcing `Accept: application/json` makes validation failures and
 * auth errors return JSON instead of an HTML redirect to /login, which is what
 * a browser-facing SPA would otherwise get from Laravel's default behaviour.
 */
final class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        if (! $request->headers->has('X-Requested-With')) {
            $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        }

        return $next($request);
    }
}

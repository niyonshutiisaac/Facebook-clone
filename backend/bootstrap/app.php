<?php

declare(strict_types=1);

use App\Http\Middleware\CheckAccountStatus;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withBroadcasting(
        channelRoutePrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global middleware — every hit is treated as a JSON API request
        // because the React SPA never renders server side Blade views.
        $middleware->use([
            ForceJsonResponse::class,
        ]);

        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'account.status'  => CheckAccountStatus::class,
            'verified'        => App\Http\Middleware\EnsureEmailIsVerified::class,
            'track.activity'  => App\Http\Middleware\TrackUserActivity::class,
            'throttle.login'  => \Illuminate\Routing\Middleware\ThrottleRequests::class.':5,1',
        ]);

        // Rate limit every API route by default; sensitive routes opt into
        // a tighter limiter with `->middleware('throttle:login')`.
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();

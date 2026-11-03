<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

final class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /*
         * Named limiters referenced from routes/api.php.
         *
         *  - `login`     : 5 attempts per minute per identity+IP (brute force)
         *  - `api`       : generous default for normal browsing
         *  - `messaging` : tight-ish, so a runaway client cannot spam chat
         */
        RateLimiter::for('login', function (Request $request): Limit {
            $key = $request->input('identity').'|'.$request->ip();

            return Limit::perMinute((int) config('auth.max_login_attempts', 5))->by($key)
                ->response(fn (): \Illuminate\Http\JsonResponse => response()->json([
                    'message' => 'Too many sign-in attempts. Please wait a minute and try again.',
                    'error' => 'too_many_attempts',
                ], 429));
        });

        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(300)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('messaging', fn (Request $request): Limit => Limit::perMinute(120)
            ->by((string) $request->user()?->id ?: $request->ip()));

        RateLimiter::for('uploads', fn (Request $request): Limit => Limit::perMinute(30)
            ->by((string) $request->user()?->id ?: $request->ip()));

        // Sanctum tokens issued by the SPA live for 30 days.
        Sanctum::usePersonalAccessTokenModel(\Laravel\Sanctum\PersonalAccessToken::class);
    }
}

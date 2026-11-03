<?php

declare(strict_types=1);

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'sanctum'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        /*
         * `web` is used for stateful SPA authentication (Sanctum cookie based).
         * `sanctum` guards bearer-token API traffic — the React app uses both:
         * cookies in the browser preview, tokens for tests and mobile clients.
         */
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

    /*
     * Lock an account for 15 minutes after this many failed login attempts.
     */
    'max_login_attempts' => env('AUTH_MAX_LOGIN_ATTEMPTS', 5),

    'lockout_duration' => env('AUTH_LOCKOUT_MINUTES', 15),

    /*
     * Minimum age required to hold an account (Facebook's rule: 13+).
     */
    'minimum_age' => 13,
];

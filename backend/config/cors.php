<?php

declare(strict_types=1);

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    /*
     * Sanctum requires credentials for cookie based SPA auth, so origins are
     * an explicit allowlist rather than a wildcard.
     */
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'SANCTUM_STATEFUL_DOMAINS',
        'http://localhost:5173,http://127.0.0.1:5173,http://localhost:3000'
    ))))),

    'allowed_origins_patterns' => [
        // Local tunnels and the Arena/ephemeral preview domains.
        '#^https?://([\w-]+\.)?(e2b\.app|ngrok\.io|ngrok-free\.app|trycloudflare\.com|localhost|127\.0\.0\.1)(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-Request-Id'],

    'max_age' => 86400,

    'supports_credentials' => true,
];

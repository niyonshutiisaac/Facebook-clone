<?php

declare(strict_types=1);

return [

    'default' => env('BROADCAST_DRIVER', 'pusher'),

    'connections' => [

        /*
         * Soketi is a self hosted, Pusher protocol compatible WebSocket server
         * (see the `soketi` service in docker-compose.yml). Swapping to the
         * hosted Pusher service only requires changing the env credentials.
         */
        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY', 'facebook-key'),
            'secret' => env('PUSHER_APP_SECRET', 'facebook-secret'),
            'app_id' => env('PUSHER_APP_ID', 'facebook'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
                'host' => env('PUSHER_HOST', 'soketi'),
                'port' => env('PUSHER_PORT', 6001),
                'scheme' => env('PUSHER_SCHEME', 'http'),
                'encrypted' => false,
                'useTLS' => env('PUSHER_SCHEME', 'http') === 'https',
            ],
            'client_options' => [
                'verify' => env('PUSHER_VERIFY_TLS', false),
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],
];

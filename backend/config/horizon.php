<?php

declare(strict_types=1);

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Redis connection
    |--------------------------------------------------------------------------
    */

    'use' => 'queue',

    'prefix' => env('HORIZON_PREFIX', Str::slug(env('APP_NAME', 'avimoney'), '_').'_horizon:'),

    /*
    |--------------------------------------------------------------------------
    | Queue worker configuration
    |--------------------------------------------------------------------------
    |
    | Queues are separated by cost: chat and notifications must never wait
    | behind a two-minute video transcode. `media` gets the longest timeout
    | and the fewest processes; `default` (chat, notifications, fan-out) gets
    | the most so real-time traffic stays snappy.
    |
    */

    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default', 'notifications', 'feed'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 12,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 2,
            'timeout' => 60,
            'nice' => 0,
        ],
        'supervisor-media' => [
            'connection' => 'redis',
            'queue' => ['media'],
            'balance' => 'simple',
            'processes' => 3,
            'maxTime' => 0,
            'memory' => 512,
            'tries' => 2,
            // Video transcoding is slow by nature.
            'timeout' => 1800,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'maxProcesses' => 12,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-media' => [
                'processes' => 4,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'maxProcesses' => 3,
            ],
            'supervisor-media' => [
                'processes' => 1,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Horizon dashboard access
    |--------------------------------------------------------------------------
    */

    'middleware' => ['web'],

    'waits' => [
        'redis:default' => 5,
        'redis:notifications' => 5,
        'redis:media' => 60,
    ],

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 30,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [
        // Silence nothing: failed jobs in a social product are worth seeing.
    ],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,

    'memory_limit' => 64,

    /*
     * Only allow the dashboard in local/staging, or for users the `viewHorizon`
     * gate authorises (see App\Providers\HorizonServiceProvider).
     */
    'route' => [
        'domain' => null,
        'prefix' => 'horizon',
        'middleware' => ['web', 'can:viewHorizon'],
    ],
];

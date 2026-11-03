<?php

declare(strict_types=1);

return [

    'driver' => env('SCOUT_DRIVER', 'meilisearch'),

    'prefix' => env('SCOUT_PREFIX', ''),

    'queue' => [
        'connection' => env('SCOUT_QUEUE_CONNECTION', 'redis'),
        'queue' => env('SCOUT_QUEUE', 'scout'),
    ],

    /*
     * Indexes are rebuilt in chunks; Meilisearch handles the heavy lifting.
     */
    'chunk' => [
        'searchable' => 500,
        'unsearchable' => 500,
    ],

    'soft_delete' => true,

    'identify' => env('SCOUT_IDENTIFY', false),

    'after_commit' => true,

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://meilisearch:7700'),
        'key' => env('MEILISEARCH_KEY', 'masterKeyChangeMe'),
        'index-settings' => [
            App\Models\User::class => [
                'filterableAttributes' => ['city', 'country', 'is_verified'],
                'sortableAttributes' => ['created_at'],
            ],
            App\Models\Post::class => [
                'filterableAttributes' => ['user_id', 'audience', 'type', 'created_at'],
            ],
        ],
    ],
];

<?php

declare(strict_types=1);

return [

    'default' => env('FILESYSTEM_DISK', 'public'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        /*
         * User generated media (avatars, covers, post photos, reels). In local
         * development this is MinIO, which speaks the S3 protocol, so the same
         * code path works against AWS S3 or Cloudflare R2 in production.
         */
        'public' => [
            'driver' => env('MEDIA_DISK', 'local'),
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID', 'facebook'),
            'secret' => env('AWS_SECRET_ACCESS_KEY', 'facebooksecret'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('AWS_BUCKET', 'facebook-clone'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT', 'http://minio:9000'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
            'throw' => false,
            'report' => false,
        ],

        /*
         * Temporary holding area for chunked uploads before virus scanning and
         * image processing move the file to its permanent disk.
         */
        'tmp' => [
            'driver' => 'local',
            'root' => storage_path('app/tmp'),
            'throw' => false,
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];

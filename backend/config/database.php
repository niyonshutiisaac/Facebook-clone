<?php

declare(strict_types=1);

use Illuminate\Support\Str;

return [

    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => 'WAL',
            'synchronous' => null,
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'mysql'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'facebook_clone'),
            'username' => env('DB_USERNAME', 'facebook'),
            'password' => env('DB_PASSWORD', 'secret'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
            'options' => extension_loaded('pdo_mysql')
                ? array_filter([
                    PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                    PDO::ATTR_EMULATE_PREPARES => false,
                ])
                : [],
        ],

        /*
         * Read replica — Laravel automatically routes SELECT queries here and
         * keeps writes on the `mysql` connection (read/write splitting).
         */
        'mysql_read' => [
            'driver' => 'mysql',
            'host' => env('DB_READ_HOST', 'mysql-repl'),
            'port' => env('DB_READ_PORT', '3306'),
            'database' => env('DB_DATABASE', 'facebook_clone'),
            'username' => env('DB_READ_USERNAME', env('DB_USERNAME', 'facebook')),
            'password' => env('DB_READ_PASSWORD', env('DB_PASSWORD', 'secret')),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ],

    ],

    /*
     * Read/write splitting: `mysql_read` is used for SELECTs, `mysql` for the
     * rest. Sticky connections keep reads on the writer for one request after
     * a write so "create post then read it back" never hits replica lag.
     */
    'read' => [
        'mysql' => ['mysql_read'],
    ],

    'sticky' => true,

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [

        'client' => env('REDIS_CLIENT', 'predis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'facebook-clone'), '_').'_database_'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        /*
         * Distinct logical databases keep concerns isolated so a `FLUSHDB` on
         * the cache never wipes presence or queue payloads.
         */
        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', 'redis'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'host' => env('REDIS_HOST', 'redis'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

        'queue' => [
            'host' => env('REDIS_HOST', 'redis'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_QUEUE_DB', '2'),
        ],

        'presence' => [
            'host' => env('REDIS_HOST', 'redis'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_PRESENCE_DB', '3'),
        ],

    ],
];

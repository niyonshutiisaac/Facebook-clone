<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [
    'name' => env('APP_NAME', 'Facebook Clone'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    'asset_url' => env('ASSET_URL'),

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
     * Supported locales for the i18n layer (RTL locales are detected by the
     * frontend through App\Support\Localization::isRtl()).
     */
    'available_locales' => ['en', 'ar', 'fr', 'es'],

    'rtl_locales' => ['ar', 'he', 'fa'],

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => array_filter(explode(',', (string) env('APP_PREVIOUS_KEYS', ''))),

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    'providers' => ServiceProvider::defaultProviders()->merge([
        /*
         * Package service providers that are not auto discovered. Every other
         * provider (Sanctum, Scout, etc.) is discovered from composer.json.
         */
    ])->toArray(),

    'aliases' => Facade::defaultAliases()->toArray(),
];

<?php

declare(strict_types=1);

return [

    'mail' => [
        'transport' => env('MAIL_MAILER', 'smtp'),
        'host' => env('MAIL_HOST', 'mailpit'),
        'port' => env('MAIL_PORT', 1025),
        'username' => env('MAIL_USERNAME'),
        'password' => env('MAIL_PASSWORD'),
    ],

    /*
     * Third party integrations used by optional features.
     */
    'giphy' => [
        'key' => env('GIPHY_API_KEY'),
    ],

    'tenor' => [
        'key' => env('TENOR_API_KEY'),
    ],

    'fcm' => [
        'key' => env('FCM_SERVER_KEY'),
        'sender_id' => env('FCM_SENDER_ID'),
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],

    'maps' => [
        'key' => env('GOOGLE_MAPS_KEY'),
    ],
];

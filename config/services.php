<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'k1' => [
        'base_url' => env('K1_BASE_URL', env('K1_API_URL', 'https://akademik.fayra.my.id/api')),
        'url' => env('K1_API_URL', env('K1_BASE_URL', 'https://akademik.fayra.my.id/api')),
        'login_url' => env('K1_LOGIN_URL', 'https://akademik.fayra.my.id/auth/login'),
        'key' => env('K1_API_KEY', ''),
    ],

    'k3' => [
        'url' => env('K3_API_URL', ''),
        'key' => env('K3_API_KEY', ''),
    ],

];

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

    'initial_admin' => [
        'name' => env('INITIAL_ADMIN_NAME'),
        'email' => env('INITIAL_ADMIN_EMAIL'),
        'password' => env('INITIAL_ADMIN_PASSWORD'),
    ],

    'swalasms' => [
        'base_url' => env('SWALASMS_BASE_URL', 'https://api.swalasms.com'),
        'requests_per_minute' => (int) env('SWALASMS_REQUESTS_PER_MINUTE', 30),
        'bulk_batch_size' => max(1, min(1000, (int) env('SWALASMS_BULK_BATCH_SIZE', 500))),
    ],

    // Legacy Beem setting retained only so existing deployments keep their .env value; nothing reads it.
    'beem' => [
        'requests_per_minute' => env('BEEM_SMS_REQUESTS_PER_MINUTE', 30),
    ],

];

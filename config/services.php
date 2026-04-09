<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'copart_iaai' => [
        'key' => env('COPART_IAAI_RAPIDAPI_KEY'),
        'host' => env('COPART_IAAI_RAPIDAPI_HOST', 'api-for-copart-and-iaai.p.rapidapi.com'),
        'base_url' => env('COPART_IAAI_RAPIDAPI_BASE_URL', 'https://api-for-copart-and-iaai.p.rapidapi.com'),
        'timeout' => (int) env('COPART_IAAI_TIMEOUT', 30),
        'rate_limit_per_minute' => (int) env('COPART_IAAI_RATE_LIMIT_PER_MINUTE', 30),
    ],

];

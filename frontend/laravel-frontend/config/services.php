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
    'backend' => [
        // Lấy URL Gateway từ .env
        'gateway_url' => env('API_GATEWAY_URL', 'http://127.0.0.1:80'),
        
        // Ép kiểu float để tránh lỗi TypeError ở ApiService
        'timeout'     => (float) env('API_TIMEOUT', 15.0),
    ],
    
    'postmark' => [
        'key' => env('POSTMARK_API_KEY', null),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY', null),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID', null),
        'secret' => env('AWS_SECRET_ACCESS_KEY', null),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN', null),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL', null),
        ],
    ],

];

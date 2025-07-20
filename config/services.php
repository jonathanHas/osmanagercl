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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'udea' => [
        'base_uri' => env('UDEA_BASE_URI', 'https://www.udea.nl'),
        'username' => env('UDEA_USERNAME'),
        'password' => env('UDEA_PASSWORD'),
        'timeout' => env('UDEA_TIMEOUT', 30),
        'rate_limit_delay' => env('UDEA_RATE_LIMIT_DELAY', 2),
        'cache_ttl' => env('UDEA_CACHE_TTL', 3600),
    ],

];

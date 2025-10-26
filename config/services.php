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

    'alphavantage' => [
        'base' => env('ALPHA_VANTAGE_BASE', 'https://www.alphavantage.co/query'),
        'key'  => env('ALPHA_VANTAGE_KEY'),
    ],

    'market' => [
        'source' => env('MARKET_DATA_SOURCE', 'api'), // 'api' | 'scraper'
        'brvm' => [
            'base_url' => env('BRVM_BASE_URL', ''),
            'user_agent' => env('SCRAPER_USER_AGENT', 'SGI-Scraper/1.0'),
        ],
    ],

];


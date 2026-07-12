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

    'toyyibpay' => [
        'base_url' => env('TOYYIBPAY_BASE_URL', 'https://toyyibpay.com'),
        'sandbox' => (bool) env('TOYYIBPAY_SANDBOX', false),
        'secret_key' => env('TOYYIBPAY_SECRET_KEY'),
        'category_code' => env('TOYYIBPAY_CATEGORY_CODE'),
        'dnqr_enabled' => env('TOYYIBPAY_DNQR_ENABLED', false),
        'timeout' => env('TOYYIBPAY_TIMEOUT', 15),
    ],

    'cloudflare_ai' => [
        'enabled' => filter_var(env('CHATME_AI_ENABLED', false), FILTER_VALIDATE_BOOL),
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'token' => env('CLOUDFLARE_AI_TOKEN'),
        'model' => env('CLOUDFLARE_AI_MODEL', '@cf/qwen/qwen3-30b-a3b-fp8'),
        'timeout' => (int) env('CLOUDFLARE_AI_TIMEOUT', 8),
        'max_tokens' => (int) env('CLOUDFLARE_AI_MAX_TOKENS', 220),
    ],

];

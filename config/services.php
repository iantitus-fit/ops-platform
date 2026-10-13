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

    'jev' => [
        'base_url' => env('JEV_BASE_URL', 'https://openrouter.ai/api/v1'),
        'key' => env('JEV_API_KEY'),
        'model' => env('JEV_MODEL', 'typesafe/jev-1.13'),
        'connect_timeout' => 3,
        'timeout' => 8,
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
        'webhook_url' => env('TWILIO_WEBHOOK_URL'),
        'status_callback_url' => env('TWILIO_STATUS_CALLBACK_URL'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];

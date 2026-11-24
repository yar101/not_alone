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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'payments' => [
        'mock_purchases' => (bool) env('PAYMENTS_MOCK_PURCHASES', false),
        'platform_fee_percent' => (float) env('PLATFORM_FEE_PERCENT', 12.0),
        'order_customer_service_fee_percent' => (float) env('ORDER_CUSTOMER_SERVICE_FEE_PERCENT', 5.0),
        'deposit_fee_percent' => (float) env('DEPOSIT_FEE_PERCENT', 0.0),
        'withdrawal_fee_percent' => (float) env('WITHDRAWAL_FEE_PERCENT', 0.0),
    ],

    'mandarin' => [
        'mid' => env('MANDARIN_MID', ''),
        'secret' => env('MANDARIN_SECRET', ''),
        'api_url' => env('MANDARIN_API_URL', 'https://secure-app.mandarin.io'),
        'webhook_secret' => env('MANDARIN_WEBHOOK_SECRET', ''),
        'self_employed_url' => env('MANDARIN_SELF_EMPLOYED_URL', 'https://api.psp.io/self-employed-2/v1'),
        'self_employed_token' => env('MANDARIN_SELF_EMPLOYED_TOKEN', ''),
        'nominal_account' => env('MANDARIN_NOMINAL_ACCOUNT', ''),
        'is_sandbox' => (bool) env('MANDARIN_IS_SANDBOX', true),
    ],
];

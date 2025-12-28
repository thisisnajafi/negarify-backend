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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'melipayamak' => [
        'username' => env('MELIPAYAMAK_USERNAME'),
        'password' => env('MELIPAYAMAK_PASSWORD'),
        'from' => env('MELIPAYAMAK_FROM'),
        'base_url' => env('MELIPAYAMAK_BASE_URL', 'https://rest.payamak-panel.com/api/SendSMS/SendSMS'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],

    'stability' => [
        'api_key' => env('STABILITY_API_KEY'),
        'base_url' => env('STABILITY_BASE_URL', 'https://api.stability.ai'),
    ],

    'replicate' => [
        'api_key' => env('REPLICATE_API_KEY'),
        'base_url' => env('REPLICATE_BASE_URL', 'https://api.replicate.com/v1'),
    ],

    'stripe' => [
        'public_key' => env('STRIPE_PUBLIC_KEY'),
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'mode' => env('PAYPAL_MODE', 'sandbox'), // sandbox or live
    ],

    'segmind' => [
        'api_key' => env('SEGMIND_API_KEY'),
        'api_base_url' => env('SEGMIND_API_BASE_URL', 'https://api.segmind.com'),
    ],

    'zarinpal' => [
        'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
        'sandbox' => env('ZARINPAL_SANDBOX', false),
        'callback_url' => env('ZARINPAL_CALLBACK_URL', env('APP_URL') . '/api/v1/tokens/purchase/callback'),
    ],

];
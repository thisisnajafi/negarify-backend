<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Melipayamak SMS Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Melipayamak SMS gateway integration.
    | Supports both token-based and username/password authentication.
    | Template/Pattern SMS is preferred for OTP delivery.
    |
    */

    'enabled' => env('MELIPAYAMAK_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Authentication Mode
    |--------------------------------------------------------------------------
    |
    | 'token' - Use API token (recommended)
    | 'classic' - Use username and password
    |
    */

    'auth_mode' => env('MELIPAYAMAK_AUTH_MODE', 'classic'),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | Melipayamak REST API base URL
    |
    */

    'base_url' => env('MELIPAYAMAK_BASE_URL', 'https://rest.payamak-panel.com/api'),

    /*
    |--------------------------------------------------------------------------
    | Token-Based Authentication
    |--------------------------------------------------------------------------
    |
    | Used when auth_mode is 'token'
    | Token can be used as both username and password
    |
    */

    'token' => env('MELIPAYAMAK_AUTH_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Classic Authentication (Username/Password)
    |--------------------------------------------------------------------------
    |
    | Used when auth_mode is 'classic'
    |
    */

    'username' => env('MELIPAYAMAK_USERNAME'),
    'password' => env('MELIPAYAMAK_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | OTP Configuration
    |--------------------------------------------------------------------------
    |
    | OTP sending mode and template settings
    |
    */

    'otp' => [
        // Preferred: 'pattern' (template SMS) or 'sms' (plain SMS fallback)
        'mode' => env('MELIPAYAMAK_OTP_MODE', 'pattern'),

        // Template/Pattern SMS (preferred)
        'template_id' => env('MELIPAYAMAK_OTP_TEMPLATE_ID', '372382'),

        // Plain SMS fallback (if template fails)
        'from_number' => env('MELIPAYAMAK_FROM_NUMBER', '50002710008883'),
        'message_text' => env('MELIPAYAMAK_OTP_MESSAGE_TEXT', 'کد ورود شما: {CODE} این کد 5 دقیقه اعتبار دارد سروکست'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sender Number
    |--------------------------------------------------------------------------
    |
    | Approved sender number from Melipayamak panel
    | Required for plain SMS fallback
    |
    */

    'from' => env('MELIPAYAMAK_FROM', env('MELIPAYAMAK_FROM_NUMBER', '50002710008883')),

    /*
    |--------------------------------------------------------------------------
    | Timeout Settings
    |--------------------------------------------------------------------------
    |
    | HTTP request timeout in seconds
    |
    */

    'timeout' => env('MELIPAYAMAK_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Retry Settings
    |--------------------------------------------------------------------------
    |
    | Retry configuration for failed requests
    |
    */

    'retry' => [
        'max_attempts' => env('MELIPAYAMAK_RETRY_MAX_ATTEMPTS', 3),
        'delay_seconds' => env('MELIPAYAMAK_RETRY_DELAY_SECONDS', 2),
    ],
];


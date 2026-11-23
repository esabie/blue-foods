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

    'payments' => [
        // sandbox (local testing only) or paystack
        'driver' => env('PAYMENT_DRIVER', 'sandbox'),
    ],

    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
    ],

    'sms' => [
        // log (local) or mnotify
        'driver' => env('SMS_DRIVER', 'log'),
        'mnotify' => [
            'key' => env('MNOTIFY_API_KEY'),
            'sender' => env('MNOTIFY_SENDER', 'BLUE FOODS'),
            'base_url' => env('MNOTIFY_BASE_URL', 'https://api.mnotify.com/api'),
        ],
        /*
        | Also text staff for high-priority bell alerts (demo / off-desk).
        | Turn off or narrow STAFF_SMS_ALERT_TYPES when you need to save credits.
        */
        'staff_alerts' => (bool) env('STAFF_SMS_ALERTS', false),
        'staff_alert_types' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'STAFF_SMS_ALERT_TYPES',
                'order_to_pack,refund_needed,low_stock,lot_expired,return_requested,stock_requested,stock_request_declined,stock_request_fulfilled',
            )),
        ))),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];

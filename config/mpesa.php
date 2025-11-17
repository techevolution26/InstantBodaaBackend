<?php

return [

    /*
    |--------------------------------------------------------------------------
    | M-Pesa Configuration
    |--------------------------------------------------------------------------
    |
    | These settings are used to authenticate and communicate with the Safaricom
    | M-Pesa API, especially for STK Push (Lipa na M-Pesa Online).
    |
    */

    'env' => env('MPESA_ENV', 'sandbox'), // 'sandbox' or 'production'

    'consumer_key' => env('MPESA_CONSUMER_KEY', ''),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET', ''),

    'shortcode' => env('MPESA_SHORTCODE', ''), // Paybill/Till number
    'passkey' => env('MPESA_PASSKEY', ''),

    'callback_url' => env('MPESA_CALLBACK_URL', ' https://6d40b66ef8c3.ngrok-free.app /api/mpesa/callback'), //hosted

    'base_url' => env('MPESA_BASE_URL', 'https://sandbox.safaricom.co.ke'),

    'initiator_name' => env('MPESA_INITIATOR', ''),
    'initiator_password' => env('MPESA_INITIATOR_PASSWORD', ''), // Only for B2C/C2B

    // Optional: timeout URL, result URL for B2C/C2B
    'timeout_url' => env('MPESA_TIMEOUT_URL', ''),
    'result_url' => env('MPESA_RESULT_URL', ''),
];

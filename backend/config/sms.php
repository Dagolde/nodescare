<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default SMS Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "twilio", "vonage", "africastalking", "null"
    |
    */
    'driver' => env('SMS_DRIVER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Twilio Configuration
    |--------------------------------------------------------------------------
    */
    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token'  => env('TWILIO_AUTH_TOKEN'),
        'from'        => env('TWILIO_FROM'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Vonage (Nexmo) Configuration
    |--------------------------------------------------------------------------
    */
    'vonage' => [
        'api_key'    => env('VONAGE_API_KEY'),
        'api_secret' => env('VONAGE_API_SECRET'),
        'from'       => env('VONAGE_FROM', 'HHMS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Africa's Talking Configuration
    |--------------------------------------------------------------------------
    */
    'africastalking' => [
        'api_key'  => env('AFRICASTALKING_API_KEY'),
        'username' => env('AFRICASTALKING_USERNAME', 'sandbox'),
        'from'     => env('AFRICASTALKING_FROM'),
    ],
];

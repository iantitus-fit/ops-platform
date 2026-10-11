<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Text Channel Driver
    |--------------------------------------------------------------------------
    |
    | Which driver sends and receives texts: "fake" (stores and logs, used
    | until Twilio is live) or "twilio". The fake webhook route only answers
    | while this is "fake".
    |
    */

    'text' => [
        'driver' => env('TEXT_CHANNEL', 'fake'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Phone Region
    |--------------------------------------------------------------------------
    |
    | The region used to read phone numbers written without a country code,
    | such as "(202) 555-0123". Stored numbers are always full E.164.
    |
    */

    'phone_region' => env('PHONE_REGION', 'US'),

];

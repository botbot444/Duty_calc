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

    /*
     * Product identification (the AI engineer's service). It only ranks catalogue IDs that
     * Laravel sends it; it never sets a rate. 'fake' needs no network and is used in tests
     * and local development until the real endpoint exists.
     */
    'identification' => [
        'driver' => env('IDENTIFICATION_DRIVER', 'fake'),   // fake | http
        'url' => env('IDENTIFICATION_URL'),
        'key' => env('IDENTIFICATION_API_KEY'),
        'model' => env('IDENTIFICATION_MODEL'),
        'timeout' => (int) env('IDENTIFICATION_TIMEOUT', 15),   // seconds
        'retries' => 1,
        'max_candidates' => 30,          // send the whole catalogue when it is this small
        'max_clarification_rounds' => 2,
    ],

];

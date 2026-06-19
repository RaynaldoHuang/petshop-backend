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

    'rajaongkir' => [
        'key' => env('RAJAONGKIR_API_KEY'),
        'base_url' => env('RAJAONGKIR_BASE_URL', 'https://api.rajaongkir.com/starter'),
        'origin_city_id' => env('RAJAONGKIR_ORIGIN_CITY_ID'),
        'default_item_weight' => env('RAJAONGKIR_DEFAULT_ITEM_WEIGHT', 1000),
    ],

    'midtrans_reviewer' => [
        'bypass_otp' => env('MIDTRANS_REVIEWER_BYPASS_OTP', false),
        'phone' => env('MIDTRANS_REVIEWER_PHONE'),
    ],

    'fazpass' => [
        'enabled' => env('FAZPASS_OTP_ENABLED', false),
        'merchant_key' => env('FAZPASS_MERCHANT_KEY', env('FAZPASS_API_KEY')),
        'gateway_key' => env('FAZPASS_GATEWAY_KEY'),
        'base_url' => env('FAZPASS_BASE_URL', 'https://api.fazpass.com'),
        'send_path' => env('FAZPASS_SEND_PATH', '/v1/otp/send'),
        'name_tag' => env('FAZPASS_NAME_TAG', 'name'),
        'test_code' => env('FAZPASS_TEST_CODE', '8888'),
        'code_length' => env('FAZPASS_CODE_LENGTH', 6),
        'timeout' => env('FAZPASS_TIMEOUT', 15),
        'callback_secret' => env('FAZPASS_CALLBACK_SECRET'),
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

];

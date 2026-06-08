<?php

declare(strict_types=1);

return [
    'models' => [
        // App\Models\User::class,
    ],

    'model_namespace' => 'App\\Models',

    'route' => [
        'prefix'     => 'dynamic-workflows',
        'middleware' => ['web', 'auth'],
        'name'       => 'dynamic-workflows.index',
    ],

    'whatsapp' => [
        'api_url'          => env('WHATSAPP_API_URL', ''),
        'api_token'        => env('WHATSAPP_API_TOKEN', ''),
        'user_phone_field' => env('WHATSAPP_USER_PHONE_FIELD', 'phone'),
    ],

    'sms' => [
        'api_url'          => env('SMS_API_URL', 'https://sms.comtele.com.br/api/v2/send'),
        'api_key'          => env('SMS_API_KEY', ''),
        'sender'           => env('SMS_SENDER', ''),
        'user_phone_field' => env('SMS_USER_PHONE_FIELD', 'phone'),
    ],
];

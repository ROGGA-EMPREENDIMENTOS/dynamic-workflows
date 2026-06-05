<?php

declare(strict_types=1);

return [
    'models' => [
        // App\Models\User::class,
    ],

    'model_namespace' => 'App\\Models',

    'route' => [
        'prefix'     => 'dynamic-workflows',
        'middleware' => ['web'],
        'name'       => 'dynamic-workflows.index',
    ],

    'whatsapp' => [
        'api_url'   => env('WHATSAPP_API_URL', ''),
        'api_token' => env('WHATSAPP_API_TOKEN', ''),
    ],
];

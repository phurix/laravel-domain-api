<?php

return [
    'api' => [
        'token' => env('DOMAIN_API_TOKEN'),
        'test' => env('DOMAIN_API_TEST'),
    ],
    'opensrs' => [
        'live' => [
            'username' => env('OSRS_USERNAME'),
            'key' => env('OSRS_KEY'),
        ],
        'test' => [
            'username' => env('OSRS_TEST_USERNAME'),
            'key' => env('OSRS_TEST_KEY'),
        ],
    ],
    'routes' => [
        'prefix' => env('DOMAIN_API_PREFIX', 'domain-api'),
        'middleware' => [env('DOMAIN_API_MIDDLEWARE', 'api_token')],
        'name' => env('DOMAIN_API_NAME', 'domain-api.'),
    ],
];

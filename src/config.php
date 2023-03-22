<?php

return [
    'api' => [
        'token' => env('API_TOKEN'),
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
        'prefix' => 'domain-api',
        'middleware' => ['auth:api'],
        'name' => 'domain-api.',
    ],
];

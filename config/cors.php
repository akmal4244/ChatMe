<?php

return [
    'paths' => [
        'api/chatbots/*/chat',
        'api/chatbots/*/config',
    ],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization'],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => false,
];

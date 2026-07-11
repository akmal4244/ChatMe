<?php

return [
    'admin' => [
        'name' => env('ADMIN_NAME'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],
    'homepage_chatbot' => [
        'slug' => env('CHATME_HOMEPAGE_CHATBOT_SLUG', 'chatme-homepage'),
        'allowed_domains' => env('CHATME_HOMEPAGE_CHATBOT_DOMAINS', 'chatme.akmalmarvis.com'),
    ],
    'health' => [
        'storage_path' => storage_path(),
    ],
];

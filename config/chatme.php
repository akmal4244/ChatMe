<?php

return [
    'timezone' => env('CHATME_TIMEZONE', 'Asia/Kuala_Lumpur'),

    'admin' => [
        'name' => env('ADMIN_NAME'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],
    'homepage_chatbot' => [
        'slug' => env('CHATME_HOMEPAGE_CHATBOT_SLUG', 'chatme-homepage'),
        'allowed_domains' => env('CHATME_HOMEPAGE_CHATBOT_DOMAINS', 'chatme.akmalmarvis.com'),
        'legacy_chatbot_id' => env('CHATME_HOMEPAGE_LEGACY_CHATBOT_ID'),
    ],
    'health' => [
        'storage_path' => storage_path(),
    ],
];

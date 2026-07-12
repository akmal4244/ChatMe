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
    'quota' => [
        'reservation_ttl_seconds' => (int) env('CHATME_QUOTA_RESERVATION_TTL_SECONDS', 120),
    ],
    'tester' => [
        'daily_ai_limit' => (int) env('CHATME_TESTER_DAILY_AI_LIMIT', 20),
    ],
    'messaging' => [
        'limits' => [
            'owner_per_minute' => (int) env('CHATME_OWNER_MESSAGE_PER_MINUTE', 600),
            'owner_daily' => (int) env('CHATME_OWNER_MESSAGE_DAILY', 5000),
        ],
    ],
    'chatbots' => [
        'absolute_limit' => (int) env('CHATME_CHATBOT_ABSOLUTE_LIMIT', 50),
        'limits' => [
            'creations_per_hour' => (int) env('CHATME_CHATBOT_CREATIONS_PER_HOUR', 10),
        ],
    ],
    'knowledge' => [
        'absolute_limit' => (int) env('CHATME_KNOWLEDGE_ABSOLUTE_LIMIT', 5000),
        'matcher_candidate_limit' => (int) env('CHATME_KNOWLEDGE_MATCH_CANDIDATE_LIMIT', 250),
    ],
    'developer_api' => [
        'limits' => [
            'ip_per_minute' => (int) env('CHATME_DEVELOPER_API_IP_PER_MINUTE', 60),
            'token_per_minute' => (int) env('CHATME_DEVELOPER_API_TOKEN_PER_MINUTE', 180),
            'token_daily' => (int) env('CHATME_DEVELOPER_API_TOKEN_DAILY', 5000),
        ],
    ],
    'widget' => [
        'ticket_ttl_seconds' => (int) env('CHATME_WIDGET_TICKET_TTL_SECONDS', 600),
        'limits' => [
            'bootstrap_per_minute' => (int) env('CHATME_WIDGET_BOOTSTRAP_PER_MINUTE', 30),
            'ingress_ip_per_minute' => (int) env('CHATME_WIDGET_INGRESS_IP_PER_MINUTE', 60),
            'ingress_bot_per_minute' => (int) env('CHATME_WIDGET_INGRESS_BOT_PER_MINUTE', 600),
            'ticket_per_minute' => (int) env('CHATME_WIDGET_TICKET_PER_MINUTE', 10),
            'chatbot_ip_per_minute' => (int) env('CHATME_WIDGET_CHATBOT_IP_PER_MINUTE', 30),
            'bot_per_minute' => (int) env('CHATME_WIDGET_BOT_PER_MINUTE', 180),
            'bot_daily_unlimited' => (int) env('CHATME_WIDGET_BOT_DAILY_UNLIMITED', 5000),
        ],
    ],
    'health' => [
        'storage_path' => storage_path(),
    ],
];

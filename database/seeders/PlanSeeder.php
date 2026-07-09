<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'price' => 0.00,
                'chatbot_limit' => 1,
                'knowledge_limit' => 50,
                'monthly_messages' => 500,
                'custom_domain' => false,
                'remove_branding' => false,
                'api_access' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price' => 49.00,
                'chatbot_limit' => 5,
                'knowledge_limit' => 500,
                'monthly_messages' => 10000,
                'custom_domain' => true,
                'remove_branding' => false,
                'api_access' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'price' => 149.00,
                'chatbot_limit' => -1,
                'knowledge_limit' => -1,
                'monthly_messages' => -1,
                'custom_domain' => true,
                'remove_branding' => true,
                'api_access' => true,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
        
        echo "Seeded " . count($plans) . " plans.\n";
    }
}

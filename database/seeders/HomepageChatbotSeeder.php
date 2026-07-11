<?php

namespace Database\Seeders;

use App\Models\Chatbot;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class HomepageChatbotSeeder extends Seeder
{
    public function run(): void
    {
        $knowledge = require database_path('data/homepage_chatbot_knowledge.php');

        if (count($knowledge) !== 33) {
            throw new RuntimeException('The homepage chatbot must contain exactly 33 knowledge items.');
        }

        DB::transaction(function () use ($knowledge): void {
            $slug = (string) config('chatme.homepage_chatbot.slug', 'chatme-homepage');
            $allowedDomains = (string) config(
                'chatme.homepage_chatbot.allowed_domains',
                'chatme.akmalmarvis.com',
            );

            $chatbot = Chatbot::query()
                ->where('slug', $slug)
                ->orWhere(function ($query): void {
                    $query->where('name', 'ChatMe Assistant')
                        ->whereHas('user', fn ($user) => $user->where('is_admin', true));
                })
                ->lockForUpdate()
                ->first();

            if (! $chatbot) {
                $owner = User::query()->firstOrCreate(
                    ['email' => 'homepage-bot@chatme.invalid'],
                    [
                        'name' => 'ChatMe Homepage',
                        'password' => Str::password(64),
                        'is_admin' => true,
                    ],
                );
                $enterprise = Plan::query()->where('slug', 'enterprise')->firstOrFail();

                Subscription::query()->firstOrCreate(
                    ['provider_reference' => 'homepage-chatbot-system'],
                    [
                        'user_id' => $owner->id,
                        'plan_id' => $enterprise->id,
                        'provider' => 'system',
                        'status' => 'active',
                        'starts_at' => now(),
                        'ends_at' => now()->addYears(100),
                    ],
                );

                $chatbot = $owner->chatbots()->create([
                    'name' => 'ChatMe Assistant',
                    'slug' => $slug,
                ]);
            }

            $chatbot->update([
                'name' => 'ChatMe Assistant',
                'slug' => $slug,
                'avatar_url' => 'akmal3d.png',
                'primary_color' => '#4F46E5',
                'secondary_color' => '#ffffff',
                'position' => 'bottom-right',
                'welcome_message' => 'Helo! Selamat datang ke ChatMe. Apakah yang boleh saya bantu?',
                'placeholder_text' => 'Taip mesej anda...',
                'bot_name' => 'Pembantu ChatMe',
                'system_prompt' => 'Gunakan Bahasa Melayu Malaysia yang jelas, sopan dan ringkas.',
                'fallback_message' => 'Maaf, maklumat itu belum tersedia. Cuba tanya tentang pelan, pembayaran atau fungsi ChatMe.',
                'is_active' => true,
                'domain_whitelist' => $allowedDomains,
            ]);

            $chatbot->knowledgeItems()->delete();
            $chatbot->knowledgeItems()->createMany(array_map(
                fn (array $item): array => [...$item, 'is_active' => true],
                $knowledge,
            ));
        });
    }
}

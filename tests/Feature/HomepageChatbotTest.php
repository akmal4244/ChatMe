<?php

namespace Tests\Feature;

use App\Models\Chatbot;
use App\Models\ChatLog;
use App\Models\KnowledgeItem;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\HomepageChatbotSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageChatbotTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_reuses_the_official_chatbot_and_preserves_its_identity_and_logs(): void
    {
        [$chatbot, $owner, $chatLog] = $this->existingOfficialChatbot();

        $this->seed(HomepageChatbotSeeder::class);
        $this->seed(HomepageChatbotSeeder::class);

        $chatbot->refresh();

        $this->assertSame('chatme-homepage', $chatbot->slug);
        $this->assertSame('TEST_KEY', $chatbot->api_key);
        $this->assertSame($owner->id, $chatbot->user_id);
        $this->assertSame('chatme.akmalmarvis.com', $chatbot->domain_whitelist);
        $this->assertTrue($chatbot->is_active);
        $this->assertDatabaseHas('chat_logs', ['id' => $chatLog->id, 'chatbot_id' => $chatbot->id]);
        $this->assertSame(1, Chatbot::query()->where('slug', 'chatme-homepage')->count());
        $this->assertSame(33, $chatbot->knowledgeItems()->count());
        $this->assertSame(33, $chatbot->knowledgeItems()->where('is_active', true)->count());

        $copy = $chatbot->knowledgeItems()
            ->get(['question', 'answer'])
            ->map(fn (KnowledgeItem $item): string => $item->question.' '.$item->answer)
            ->implode("\n");

        $this->assertDoesNotMatchRegularExpression(
            '/\b(?:tak|nak|je|ni|tu|lepas tu|website|setup|support|custom|coding|client|ready|upgrade|plan)\b/i',
            $copy,
        );
    }

    public function test_homepage_loads_only_an_active_database_backed_widget(): void
    {
        [$chatbot] = $this->existingOfficialChatbot();
        $this->seed(HomepageChatbotSeeder::class);

        $widgetUrl = route('widget.script', ['chatbot' => 'TEST_KEY']);
        $this->get('/')->assertOk()->assertSee($widgetUrl, false);

        $source = file_get_contents(resource_path('views/landing.blade.php'));
        $this->assertIsString($source);
        $this->assertStringNotContainsString('TEST_KEY', $source);
        $this->assertStringNotContainsString('cm_', $source);

        $chatbot->refresh()->update(['is_active' => false]);
        $this->get('/')->assertOk()->assertDontSee($widgetUrl, false);
    }

    public function test_homepage_chatbot_accepts_the_real_origin_and_rejects_other_sites(): void
    {
        [$chatbot] = $this->existingOfficialChatbot();
        $this->seed(HomepageChatbotSeeder::class);
        $chatbot->refresh();

        $this->withHeader('Origin', 'https://chatme.akmalmarvis.com')
            ->getJson(route('api.widget.config', $chatbot->api_key))
            ->assertOk();

        $this->withHeader('Origin', 'https://attacker.test')
            ->getJson(route('api.widget.config', $chatbot->api_key))
            ->assertForbidden()
            ->assertExactJson(['error' => 'Domain ini tidak dibenarkan.']);
    }

    public function test_fresh_install_creates_a_stable_system_owner_and_long_lived_entitlement(): void
    {
        $this->seed(PlanSeeder::class);
        $this->seed(HomepageChatbotSeeder::class);

        $chatbot = Chatbot::query()->where('slug', 'chatme-homepage')->firstOrFail();
        $owner = $chatbot->user;
        $subscription = Subscription::query()
            ->where('provider_reference', 'homepage-chatbot-system')
            ->firstOrFail();
        $originalEnd = $subscription->ends_at->toISOString();

        $this->assertSame('homepage-bot@chatme.invalid', $owner->email);
        $this->assertTrue($owner->is_admin);
        $this->assertSame($owner->id, $subscription->user_id);
        $this->assertSame('enterprise', $subscription->plan->slug);
        $this->assertSame('active', $subscription->status);
        $this->assertTrue($subscription->ends_at->greaterThan(now()->addYears(99)));

        $this->travel(1)->year();
        $this->seed(HomepageChatbotSeeder::class);

        $this->assertSame(
            $originalEnd,
            $subscription->fresh()->ends_at->toISOString(),
            'Rerunning the seeder must not extend the system entitlement.',
        );
        $this->assertSame(1, User::query()->where('email', 'homepage-bot@chatme.invalid')->count());
    }

    /** @return array{Chatbot, User, ChatLog} */
    private function existingOfficialChatbot(): array
    {
        $this->seed(PlanSeeder::class);
        $owner = User::factory()->create(['is_admin' => true]);
        $chatbot = Chatbot::create([
            'user_id' => $owner->id,
            'name' => 'ChatMe Assistant',
            'slug' => 'chatme-assistant-old',
            'api_key' => 'TEST_KEY',
            'domain_whitelist' => null,
        ]);
        KnowledgeItem::create([
            'chatbot_id' => $chatbot->id,
            'question' => 'Old question',
            'answer' => 'Old answer',
        ]);
        $chatLog = ChatLog::create([
            'chatbot_id' => $chatbot->id,
            'session_id' => 'preserved-session',
            'message' => 'Preserve this log',
            'role' => 'user',
        ]);

        return [$chatbot, $owner, $chatLog];
    }
}

<?php

namespace Tests\Feature;

use App\Models\Chatbot;
use App\Models\ChatLog;
use App\Models\KnowledgeItem;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\HomepageChatbotSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class HomepageChatbotTest extends TestCase
{
    use RefreshDatabase;

    public function test_arbitrary_admin_chatbot_and_knowledge_are_never_adopted_by_name(): void
    {
        $this->seed(PlanSeeder::class);
        $owner = User::factory()->create(['is_admin' => true]);
        $chatbot = Chatbot::create([
            'user_id' => $owner->id,
            'name' => 'ChatMe Assistant',
            'slug' => 'customer-chatme-assistant',
            'api_key' => 'CUSTOMER_KEY',
            'welcome_message' => 'Customer welcome message',
        ]);
        $knowledge = KnowledgeItem::create([
            'chatbot_id' => $chatbot->id,
            'question' => 'Customer question',
            'answer' => 'Customer answer',
        ]);
        $chatbotBefore = $chatbot->fresh()->getAttributes();
        $knowledgeBefore = $knowledge->fresh()->getAttributes();

        $this->seed(HomepageChatbotSeeder::class);

        $chatbot->refresh();
        $knowledge->refresh();
        $this->assertSame($chatbotBefore, $chatbot->getAttributes());
        $this->assertSame($knowledgeBefore, $knowledge->getAttributes());
        $this->assertSame($owner->id, $chatbot->user_id);
        $this->assertSame('customer-chatme-assistant', $chatbot->slug);
        $this->assertSame('CUSTOMER_KEY', $chatbot->api_key);
        $this->assertSame('Customer welcome message', $chatbot->welcome_message);
        $this->assertNull($chatbot->system_role);
        $this->assertDatabaseHas('knowledge_items', [
            'id' => $knowledge->id,
            'chatbot_id' => $chatbot->id,
            'question' => 'Customer question',
            'answer' => 'Customer answer',
            'source_key' => null,
        ]);
        $this->assertNotSame(
            $chatbot->id,
            Chatbot::query()->where('system_role', 'homepage_chatbot')->firstOrFail()->id,
        );
    }

    public function test_unmarked_official_slug_fails_closed_without_explicit_legacy_id(): void
    {
        $this->seed(PlanSeeder::class);
        $owner = User::factory()->create();
        $chatbot = Chatbot::create([
            'user_id' => $owner->id,
            'name' => 'Legacy homepage bot',
            'slug' => 'chatme-homepage',
            'api_key' => 'LEGACY_COLLISION_KEY',
        ]);
        $knowledge = KnowledgeItem::create([
            'chatbot_id' => $chatbot->id,
            'question' => 'Legacy customer question',
            'answer' => 'Must survive collision',
        ]);

        $this->assertHomepageSeederFails('legacy chatbot ID');

        $this->assertDatabaseHas('chatbots', [
            'id' => $chatbot->id,
            'user_id' => $owner->id,
            'api_key' => 'LEGACY_COLLISION_KEY',
            'system_role' => null,
        ]);
        $this->assertDatabaseHas('knowledge_items', [
            'id' => $knowledge->id,
            'answer' => 'Must survive collision',
            'source_key' => null,
        ]);
        $this->assertDatabaseMissing('users', ['system_role' => 'homepage_owner']);
    }

    public function test_preclaimed_homepage_owner_email_fails_without_entitlement_or_password_reset(): void
    {
        $this->seed(PlanSeeder::class);
        $preclaim = User::factory()->create([
            'email' => 'homepage-bot@chatme.invalid',
            'password' => 'preclaim-password',
            'is_admin' => false,
        ]);

        $this->assertHomepageSeederFails('reserved homepage owner');

        $preclaim->refresh();
        $this->assertFalse($preclaim->is_admin);
        $this->assertNull($preclaim->system_role);
        $this->assertTrue(Hash::check('preclaim-password', $preclaim->password));
        $this->assertDatabaseCount('chatbots', 0);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_explicit_legacy_adoption_preserves_key_logs_and_unmarked_knowledge(): void
    {
        [$chatbot, $legacyOwner, $customKnowledge, $legacyKnowledgeIds] = $this->legacyProductionShape();
        config()->set('chatme.homepage_chatbot.legacy_chatbot_id', $chatbot->id);

        $this->seed(HomepageChatbotSeeder::class);
        $this->seed(HomepageChatbotSeeder::class);

        $chatbot->refresh();
        $systemOwner = User::query()->where('system_role', 'homepage_owner')->firstOrFail();

        $this->assertSame('homepage_chatbot', $chatbot->system_role);
        $this->assertSame($systemOwner->id, $chatbot->user_id);
        $this->assertNotSame($legacyOwner->id, $chatbot->user_id);
        $this->assertFalse($systemOwner->is_admin);
        $this->assertSame('TEST_KEY', $chatbot->api_key);
        $this->assertSame(200, $chatbot->chatLogs()->count());
        $this->assertDatabaseHas('knowledge_items', [
            'id' => $customKnowledge->id,
            'chatbot_id' => $chatbot->id,
            'question' => 'CUSTOMER_KNOWLEDGE_MARKER',
            'answer' => 'CUSTOMER_ANSWER_MARKER',
            'source_key' => null,
        ]);
        $this->assertSame(34, $chatbot->knowledgeItems()->count());
        $this->assertSame(33, $chatbot->knowledgeItems()->whereNotNull('source_key')->count());
        $this->assertSame(33, $chatbot->knowledgeItems()->distinct()->count('source_key'));
        foreach ($legacyKnowledgeIds as $sourceKey => $legacyKnowledgeId) {
            $this->assertDatabaseHas('knowledge_items', [
                'id' => $legacyKnowledgeId,
                'chatbot_id' => $chatbot->id,
                'source_key' => $sourceKey,
            ]);
        }

        $systemEntitlement = Subscription::query()
            ->where('provider_reference', 'homepage-chatbot-system')
            ->firstOrFail();
        $this->assertSame($systemOwner->id, $systemEntitlement->user_id);
        $this->assertSame('enterprise', $systemEntitlement->plan->slug);
        $this->assertSame('system', $systemEntitlement->provider);
        $this->assertSame('active', $systemEntitlement->status);
    }

    public function test_explicit_adoption_rejects_a_conflicting_reserved_entitlement(): void
    {
        [$chatbot, $legacyOwner, $customKnowledge] = $this->legacyProductionShape();
        $entitlementOwner = User::factory()->create();
        $enterprise = Plan::query()->where('slug', 'enterprise')->firstOrFail();
        $conflictingEntitlement = Subscription::create([
            'user_id' => $entitlementOwner->id,
            'plan_id' => $enterprise->id,
            'provider' => 'system',
            'provider_reference' => 'homepage-chatbot-system',
            'status' => 'active',
            'starts_at' => now()->subYear(),
            'ends_at' => now()->addYears(99),
        ]);
        config()->set('chatme.homepage_chatbot.legacy_chatbot_id', $chatbot->id);

        $this->assertHomepageSeederFails('entitlement conflicts');

        $this->assertDatabaseHas('chatbots', [
            'id' => $chatbot->id,
            'user_id' => $legacyOwner->id,
            'api_key' => 'TEST_KEY',
            'system_role' => null,
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'id' => $conflictingEntitlement->id,
            'user_id' => $entitlementOwner->id,
            'provider_reference' => 'homepage-chatbot-system',
        ]);
        $this->assertDatabaseHas('knowledge_items', [
            'id' => $customKnowledge->id,
            'source_key' => null,
        ]);
        $this->assertSame(200, ChatLog::query()->where('chatbot_id', $chatbot->id)->count());
        $this->assertDatabaseMissing('users', ['system_role' => 'homepage_owner']);
    }

    public function test_official_knowledge_has_unique_keys_that_do_not_depend_on_dataset_order(): void
    {
        $knowledge = require database_path('data/homepage_chatbot_knowledge.php');

        foreach ($knowledge as $item) {
            $this->assertArrayHasKey('source_key', $item);
            $this->assertMatchesRegularExpression('/^homepage:\d{3}$/', $item['source_key']);
        }

        $sourceKeys = array_column($knowledge, 'source_key');
        $this->assertCount(count($knowledge), array_unique($sourceKeys));

        $byQuestion = collect($knowledge)
            ->mapWithKeys(fn (array $item): array => [$item['question'] => $item['source_key']])
            ->sortKeys()
            ->all();
        $reorderedByQuestion = collect(array_reverse($knowledge))
            ->mapWithKeys(fn (array $item): array => [$item['question'] => $item['source_key']])
            ->sortKeys()
            ->all();

        $this->assertSame($byQuestion, $reorderedByQuestion);
    }

    public function test_explicit_legacy_id_must_reference_the_exact_official_slug(): void
    {
        $this->seed(PlanSeeder::class);
        $owner = User::factory()->create();
        $chatbot = Chatbot::create([
            'user_id' => $owner->id,
            'name' => 'Wrong bot',
            'slug' => 'not-the-homepage',
            'api_key' => 'WRONG_KEY',
        ]);
        config()->set('chatme.homepage_chatbot.legacy_chatbot_id', $chatbot->id);

        $this->assertHomepageSeederFails('official slug');

        $this->assertDatabaseHas('chatbots', [
            'id' => $chatbot->id,
            'user_id' => $owner->id,
            'slug' => 'not-the-homepage',
            'system_role' => null,
        ]);
        $this->assertDatabaseMissing('users', ['system_role' => 'homepage_owner']);
    }

    public function test_fresh_install_and_rerun_keep_stable_marked_identity_and_user_knowledge(): void
    {
        $this->seed(PlanSeeder::class);
        $this->seed(HomepageChatbotSeeder::class);

        $chatbot = Chatbot::query()->where('system_role', 'homepage_chatbot')->firstOrFail();
        $owner = $chatbot->user;
        $subscription = Subscription::query()
            ->where('provider_reference', 'homepage-chatbot-system')
            ->firstOrFail();
        $originalPassword = $owner->password;
        $originalApiKey = $chatbot->api_key;
        $originalEnd = $subscription->ends_at->toISOString();
        $customKnowledge = KnowledgeItem::create([
            'chatbot_id' => $chatbot->id,
            'question' => 'User-added question',
            'answer' => 'User-added answer',
        ]);

        $this->travel(1)->year();
        $this->seed(HomepageChatbotSeeder::class);

        $chatbot->refresh();
        $owner->refresh();
        $subscription->refresh();
        $this->assertSame('homepage-bot@chatme.invalid', $owner->email);
        $this->assertSame('homepage_owner', $owner->system_role);
        $this->assertFalse($owner->is_admin);
        $this->assertSame('homepage_chatbot', $chatbot->system_role);
        $this->assertSame($owner->id, $chatbot->user_id);
        $this->assertSame($originalPassword, $owner->password);
        $this->assertSame($originalApiKey, $chatbot->api_key);
        $this->assertSame($originalEnd, $subscription->ends_at->toISOString());
        $this->assertDatabaseHas('knowledge_items', [
            'id' => $customKnowledge->id,
            'source_key' => null,
        ]);
        $this->assertSame(34, $chatbot->knowledgeItems()->count());
        $this->assertSame(33, $chatbot->knowledgeItems()->whereNotNull('source_key')->count());
        $this->assertSame(1, User::query()->where('system_role', 'homepage_owner')->count());
        $this->assertSame(1, Chatbot::query()->where('system_role', 'homepage_chatbot')->count());
    }

    public function test_homepage_loads_only_an_active_database_backed_widget(): void
    {
        $chatbot = $this->freshHomepageChatbot();
        $widgetUrl = route('widget.script', ['chatbot' => $chatbot->api_key]);

        $this->get('/')->assertOk()
            ->assertSee($widgetUrl, false)
            ->assertSee($widgetUrl.'?v=', false);

        $source = file_get_contents(resource_path('views/landing.blade.php'));
        $this->assertIsString($source);
        $this->assertStringNotContainsString($chatbot->api_key, $source);

        $chatbot->update(['is_active' => false]);
        $this->get('/')->assertOk()->assertDontSee($widgetUrl, false);
    }

    public function test_homepage_chatbot_accepts_the_real_origin_and_rejects_other_sites(): void
    {
        $chatbot = $this->freshHomepageChatbot();

        $this->withHeader('Origin', 'https://chatme.akmalmarvis.com')
            ->getJson(route('api.widget.config', $chatbot->api_key))
            ->assertOk();

        $this->withHeader('Origin', 'https://attacker.test')
            ->getJson(route('api.widget.config', $chatbot->api_key))
            ->assertForbidden()
            ->assertExactJson(['error' => 'Domain ini tidak dibenarkan.']);
    }

    /** @return array{Chatbot, User, KnowledgeItem, array<string, int>} */
    private function legacyProductionShape(): array
    {
        $this->seed(PlanSeeder::class);
        $legacyOwner = User::factory()->create([
            'email' => 'legacy-owner@example.test',
            'is_admin' => true,
        ]);
        $chatbot = Chatbot::create([
            'user_id' => $legacyOwner->id,
            'name' => 'Legacy Production Homepage',
            'slug' => 'chatme-homepage',
            'api_key' => 'TEST_KEY',
            'domain_whitelist' => null,
        ]);

        $knowledge = require database_path('data/homepage_chatbot_knowledge.php');
        $legacyKnowledgeIds = [];
        foreach ($knowledge as $item) {
            $sourceKey = $item['source_key'];
            unset($item['source_key']);

            $legacyKnowledge = $chatbot->knowledgeItems()->create([...$item, 'is_active' => true]);
            $legacyKnowledgeIds[$sourceKey] = $legacyKnowledge->id;
        }
        $customKnowledge = $chatbot->knowledgeItems()->create([
            'question' => 'CUSTOMER_KNOWLEDGE_MARKER',
            'answer' => 'CUSTOMER_ANSWER_MARKER',
            'is_active' => true,
        ]);

        $now = now();
        ChatLog::query()->insert(array_map(
            fn (int $number): array => [
                'chatbot_id' => $chatbot->id,
                'session_id' => 'legacy-session-'.$number,
                'message' => 'Legacy log '.$number,
                'role' => $number % 2 === 0 ? 'bot' : 'user',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            range(1, 200),
        ));

        return [$chatbot, $legacyOwner, $customKnowledge, $legacyKnowledgeIds];
    }

    private function freshHomepageChatbot(): Chatbot
    {
        $this->seed(PlanSeeder::class);
        $this->seed(HomepageChatbotSeeder::class);

        return Chatbot::query()->where('system_role', 'homepage_chatbot')->firstOrFail();
    }

    private function assertHomepageSeederFails(string $messageFragment): void
    {
        $caught = null;

        try {
            $this->seed(HomepageChatbotSeeder::class);
        } catch (RuntimeException $exception) {
            $caught = $exception;
        }

        $this->assertNotNull($caught, 'Homepage seeding should have failed closed.');
        $this->assertStringContainsString($messageFragment, $caught->getMessage());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Chatbot;
use App\Models\ChatLog;
use App\Models\KnowledgeItem;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DeveloperApiTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC_ERROR = 'Akses API tidak dibenarkan.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        Cache::flush();
    }

    public function test_paid_owner_generates_a_hash_only_token_that_is_displayed_once(): void
    {
        [$user, $chatbot] = $this->chatbotForPlan('pro');

        $response = $this->actingAs($user)
            ->post(route('chatbots.developer-token', $chatbot), [
                'current_password' => 'password',
            ])
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-store, private')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSessionMissing('developer_token')
            ->assertSee('Token ini hanya dipaparkan sekali.');

        preg_match('/cm_live_[A-Za-z0-9_-]+/', $response->getContent(), $matches);
        $raw = $matches[0] ?? null;
        $this->assertIsString($raw);
        $this->assertStringStartsWith('cm_live_', $raw);
        $this->assertStringNotContainsString($raw, route('chatbots.developer-token', $chatbot));

        $chatbot->refresh();
        $this->assertSame(hash('sha256', $raw), $chatbot->developer_api_token_hash);
        $this->assertSame(substr($raw, 0, 16), $chatbot->developer_api_token_prefix);
        $this->assertDatabaseMissing('chatbots', ['developer_api_token_hash' => $raw]);
        $this->assertDatabaseMissing('chatbots', ['developer_api_token_prefix' => $raw]);

        $this->get(route('chatbots.embed', $chatbot))
            ->assertOk()
            ->assertDontSee($raw)
            ->assertSee($chatbot->developer_api_token_prefix)
            ->assertSee('name="current_password"', false)
            ->assertSee('autocomplete="current-password"', false);
    }

    public function test_free_owner_cannot_generate_a_developer_token_and_sees_upgrade_copy(): void
    {
        [$user, $chatbot] = $this->chatbotForPlan('free');

        $this->actingAs($user)
            ->post(route('chatbots.developer-token', $chatbot))
            ->assertForbidden();

        $this->assertNull($chatbot->fresh()->developer_api_token_hash);

        $this->get(route('chatbots.embed', $chatbot))
            ->assertOk()
            ->assertSee('Pelan anda tidak mempunyai akses API pembangun.')
            ->assertDontSee('Jana token API pembangun');
    }

    public function test_paid_owner_must_confirm_the_current_password_before_rotating_a_developer_token(): void
    {
        [$user, $chatbot] = $this->chatbotForPlan('pro');

        foreach ([null, 'password-salah'] as $currentPassword) {
            $this->actingAs($user)
                ->post(route('chatbots.developer-token', $chatbot), [
                    'current_password' => $currentPassword,
                ])
                ->assertSessionHasErrors('current_password');
        }

        $this->assertNull($chatbot->fresh()->developer_api_token_hash);
    }

    public function test_other_user_cannot_rotate_a_chatbot_developer_token(): void
    {
        [, $chatbot] = $this->chatbotForPlan('pro');

        $this->actingAs(User::factory()->create())
            ->post(route('chatbots.developer-token', $chatbot))
            ->assertForbidden();
    }

    public function test_developer_token_hash_is_never_serialized_from_the_chatbot_model(): void
    {
        [, $chatbot] = $this->chatbotForPlan('pro');
        $chatbot->rotateDeveloperApiToken();

        $serialized = $chatbot->fresh()->toArray();

        $this->assertArrayNotHasKey('developer_api_token_hash', $serialized);
        $this->assertArrayHasKey('developer_api_token_prefix', $serialized);
    }

    public function test_paid_token_can_chat_and_rotation_invalidates_the_old_token_immediately(): void
    {
        [, $chatbot] = $this->chatbotForPlan('pro');
        $oldToken = $chatbot->rotateDeveloperApiToken();

        $this->withToken($oldToken)
            ->postJson(route('api.developer.chat'), [
                'message' => 'Apakah waktu operasi?',
                'session_id' => 'developer-old-token',
            ])
            ->assertOk()
            ->assertExactJson([
                'response' => 'Kami buka setiap hari.',
                'session_id' => 'developer-old-token',
            ]);

        $newToken = $chatbot->rotateDeveloperApiToken();

        $this->withToken($oldToken)
            ->postJson(route('api.developer.chat'), ['message' => 'Apakah waktu operasi?'])
            ->assertUnauthorized()
            ->assertExactJson(['error' => self::GENERIC_ERROR]);

        $this->withToken($newToken)
            ->postJson(route('api.developer.chat'), [
                'message' => 'Apakah waktu operasi?',
                'session_id' => 'developer-new-token',
            ])
            ->assertOk()
            ->assertJsonPath('response', 'Kami buka setiap hari.');

        $this->assertDatabaseHas('chat_logs', [
            'session_id' => 'developer-new-token',
            'role' => 'bot',
        ]);
    }

    public function test_missing_invalid_inactive_free_and_expired_access_fail_generically_without_logs(): void
    {
        $this->postJson(route('api.developer.chat'), ['message' => 'test'])
            ->assertUnauthorized()
            ->assertExactJson(['error' => self::GENERIC_ERROR]);

        $this->withToken('cm_live_invalid')
            ->postJson(route('api.developer.chat'), ['message' => 'test'])
            ->assertUnauthorized()
            ->assertExactJson(['error' => self::GENERIC_ERROR]);

        [, $inactive] = $this->chatbotForPlan('pro', chatbotAttributes: ['is_active' => false]);
        $inactiveToken = $this->forceToken($inactive);
        $this->withToken($inactiveToken)
            ->postJson(route('api.developer.chat'), ['message' => 'test'])
            ->assertUnauthorized()
            ->assertExactJson(['error' => self::GENERIC_ERROR]);

        [, $free] = $this->chatbotForPlan('free');
        $freeToken = $this->forceToken($free);
        $this->withToken($freeToken)
            ->postJson(route('api.developer.chat'), ['message' => 'test'])
            ->assertForbidden()
            ->assertExactJson(['error' => self::GENERIC_ERROR]);

        [, $expired] = $this->chatbotForPlan('enterprise', expired: true);
        $expiredToken = $this->forceToken($expired);
        $this->withToken($expiredToken)
            ->postJson(route('api.developer.chat'), ['message' => 'test'])
            ->assertForbidden()
            ->assertExactJson(['error' => self::GENERIC_ERROR]);

        $this->assertDatabaseCount('chat_logs', 0);
    }

    public function test_developer_api_validates_input_and_enforces_monthly_quota_without_writes(): void
    {
        [, $chatbot] = $this->chatbotForCustomPlan(monthlyMessages: 1);
        $token = $chatbot->rotateDeveloperApiToken();

        $this->withToken($token)
            ->postJson(route('api.developer.chat'), ['message' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');

        ChatLog::create([
            'chatbot_id' => $chatbot->id,
            'session_id' => 'quota-used',
            'message' => 'Sudah digunakan',
            'role' => 'user',
        ]);
        Log::spy();

        $this->withToken($token)
            ->postJson(route('api.developer.chat'), [
                'message' => 'Apakah waktu operasi?',
                'session_id' => 'quota-rejected',
            ])
            ->assertStatus(429)
            ->assertExactJson(['error' => 'Had mesej bulanan telah dicapai.']);

        $this->assertDatabaseCount('chat_logs', 1);
        $this->assertDatabaseMissing('chat_logs', ['session_id' => 'quota-rejected']);
        Log::shouldHaveReceived('notice')
            ->once()
            ->with('Monthly message quota exceeded.', [
                'user_id' => $chatbot->user_id,
                'chatbot_id' => $chatbot->id,
                'channel' => 'developer_api',
            ]);
    }

    public function test_developer_api_is_rate_limited_per_token_and_ip(): void
    {
        config()->set('app.debug', false);
        Http::fake();
        [, $chatbot] = $this->chatbotForPlan('pro');
        $token = $chatbot->rotateDeveloperApiToken();

        foreach (range(1, 60) as $requestNumber) {
            $this->withToken($token)
                ->postJson(route('api.developer.chat'), [
                    'message' => 'Apakah waktu operasi?',
                    'session_id' => "rate-{$requestNumber}",
                ])
                ->assertOk();
        }

        $logCountBeforeDeniedRequest = ChatLog::count();
        $this->withToken($token)
            ->postJson(route('api.developer.chat'), [
                'message' => 'Apakah waktu operasi?',
                'session_id' => 'rate-denied',
            ])
            ->assertStatus(429)
            ->assertExactJson(['error' => 'Terlalu banyak permintaan. Sila cuba lagi sebentar lagi.']);

        $this->assertSame($logCountBeforeDeniedRequest, ChatLog::count());
        $this->assertDatabaseMissing('chat_logs', ['session_id' => 'rate-denied']);
        $this->assertDatabaseCount('message_quota_reservations', 0);
        Http::assertNothingSent();
    }

    public function test_developer_api_token_rate_limit_cannot_be_bypassed_by_rotating_ip_addresses(): void
    {
        config()->set('app.debug', false);
        config()->set('chatme.developer_api.limits', [
            'ip_per_minute' => 2,
            'token_per_minute' => 3,
            'token_daily' => 100,
        ]);
        [, $chatbot] = $this->chatbotForPlan('enterprise');
        $token = $chatbot->rotateDeveloperApiToken();

        foreach (['203.0.113.10', '203.0.113.10', '203.0.113.11'] as $requestNumber => $ipAddress) {
            $this->withServerVariables(['REMOTE_ADDR' => $ipAddress])
                ->withToken($token)
                ->postJson(route('api.developer.chat'), [
                    'message' => 'Apakah waktu operasi?',
                    'session_id' => 'distributed-rate-'.($requestNumber + 1),
                ])
                ->assertOk();
        }

        $this->fakeEnabledAi();

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.12'])
            ->withToken($token)
            ->postJson(route('api.developer.chat'), [
                'message' => 'Boleh jelaskan waktu yang sesuai?',
                'session_id' => 'distributed-rate-denied',
            ])
            ->assertStatus(429)
            ->assertExactJson(['error' => 'Terlalu banyak permintaan. Sila cuba lagi sebentar lagi.']);

        $this->assertStringNotContainsString($token, $response->getContent());
        Http::assertNothingSent();
        $this->assertDatabaseCount('chat_logs', 6);
        $this->assertDatabaseCount('message_quota_reservations', 0);
        $this->assertDatabaseMissing('chat_logs', ['session_id' => 'distributed-rate-denied']);
    }

    public function test_unlimited_plan_developer_token_has_a_daily_hard_safety_cap(): void
    {
        config()->set('app.debug', false);
        config()->set('chatme.developer_api.limits', [
            'ip_per_minute' => 10,
            'token_per_minute' => 10,
            'token_daily' => 2,
        ]);
        [, $chatbot] = $this->chatbotForPlan('enterprise');
        $token = $chatbot->rotateDeveloperApiToken();

        foreach (['198.51.100.20', '198.51.100.21'] as $requestNumber => $ipAddress) {
            $this->withServerVariables(['REMOTE_ADDR' => $ipAddress])
                ->withToken($token)
                ->postJson(route('api.developer.chat'), [
                    'message' => 'Apakah waktu operasi?',
                    'session_id' => 'daily-cap-'.($requestNumber + 1),
                ])
                ->assertOk();
        }

        $this->fakeEnabledAi();

        $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.22'])
            ->withToken($token)
            ->postJson(route('api.developer.chat'), [
                'message' => 'Boleh jelaskan waktu yang sesuai?',
                'session_id' => 'daily-cap-denied',
            ])
            ->assertStatus(429)
            ->assertExactJson(['error' => 'Terlalu banyak permintaan. Sila cuba lagi sebentar lagi.']);

        $this->assertStringNotContainsString($token, $response->getContent());
        Http::assertNothingSent();
        $this->assertDatabaseCount('chat_logs', 4);
        $this->assertDatabaseCount('message_quota_reservations', 0);
        $this->assertDatabaseMissing('chat_logs', ['session_id' => 'daily-cap-denied']);
    }

    public function test_invalid_developer_tokens_are_rate_limited_by_ip_before_authentication(): void
    {
        config()->set('app.debug', false);

        foreach (range(1, 30) as $attempt) {
            $this->withToken('cm_live_invalid_'.$attempt)
                ->postJson(route('api.developer.chat'), ['message' => 'test'])
                ->assertUnauthorized();
        }

        $this->withToken('cm_live_invalid_blocked')
            ->postJson(route('api.developer.chat'), ['message' => 'test'])
            ->assertStatus(429)
            ->assertJsonPath('error', 'Terlalu banyak permintaan. Sila cuba lagi sebentar lagi.');

        $this->assertDatabaseCount('chat_logs', 0);
    }

    public function test_developer_api_has_no_cors_and_widget_api_reflects_only_the_allowed_origin(): void
    {
        [, $chatbot] = $this->chatbotForPlan('pro');
        $token = $chatbot->rotateDeveloperApiToken();

        $developerResponse = $this->withHeaders([
            'Origin' => 'https://untrusted.example',
            'Authorization' => 'Bearer '.$token,
        ])->postJson(route('api.developer.chat'), ['message' => 'Apakah waktu operasi?'])
            ->assertOk();

        $this->assertNull($developerResponse->headers->get('Access-Control-Allow-Origin'));

        $this->withHeader('Origin', 'https://site.example')
            ->getJson(route('api.widget.config', $chatbot->api_key))
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://site.example')
            ->assertHeader('Vary', 'Origin');
    }

    public function test_developer_api_bounds_untrusted_user_agent_before_persisting(): void
    {
        [, $chatbot] = $this->chatbotForPlan('pro');
        $token = $chatbot->rotateDeveloperApiToken();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'User-Agent' => str_repeat('B', 1000),
        ])->postJson(route('api.developer.chat'), [
            'message' => 'Apakah waktu operasi?',
            'session_id' => 'bounded-developer-agent',
        ])->assertOk();

        $userLog = ChatLog::query()
            ->where('session_id', 'bounded-developer-agent')
            ->where('role', 'user')
            ->firstOrFail();

        $this->assertSame(255, strlen((string) $userLog->user_agent));
    }

    /** @return array{User, Chatbot} */
    private function chatbotForPlan(
        string $slug,
        bool $expired = false,
        array $chatbotAttributes = [],
    ): array {
        $user = User::factory()->create();
        $plan = Plan::where('slug', $slug)->firstOrFail();

        if ($slug !== 'free') {
            $user->subscriptions()->create([
                'plan_id' => $plan->id,
                'provider' => 'system',
                'status' => 'active',
                'starts_at' => now()->subMonth(),
                'ends_at' => $expired ? now()->subMinute() : now()->addMonth(),
            ]);
        }

        return [$user, $this->chatbotWithKnowledge($user, $chatbotAttributes)];
    }

    /** @return array{User, Chatbot} */
    private function chatbotForCustomPlan(int $monthlyMessages): array
    {
        $plan = Plan::create([
            'name' => 'API Limited',
            'slug' => 'api-limited-'.str()->random(6),
            'price' => 1,
            'chatbot_limit' => 1,
            'knowledge_limit' => 10,
            'monthly_messages' => $monthlyMessages,
            'api_access' => true,
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->subscriptions()->create([
            'plan_id' => $plan->id,
            'provider' => 'system',
            'status' => 'active',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addMonth(),
        ]);

        return [$user, $this->chatbotWithKnowledge($user)];
    }

    private function chatbotWithKnowledge(User $user, array $attributes = []): Chatbot
    {
        $chatbot = Chatbot::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Developer API Bot',
        ], $attributes));
        KnowledgeItem::create([
            'chatbot_id' => $chatbot->id,
            'question' => 'Apakah waktu operasi?',
            'answer' => 'Kami buka setiap hari.',
            'tags' => 'waktu,operasi',
            'is_active' => true,
        ]);

        return $chatbot;
    }

    private function forceToken(Chatbot $chatbot): string
    {
        $raw = 'cm_live_'.str()->random(48);
        $chatbot->forceFill([
            'developer_api_token_hash' => hash('sha256', $raw),
            'developer_api_token_prefix' => substr($raw, 0, 16),
        ])->save();

        return $raw;
    }

    private function fakeEnabledAi(): void
    {
        config()->set('services.cloudflare_ai', [
            'enabled' => true,
            'account_id' => 'developer-rate-limit-account',
            'token' => 'developer-rate-limit-provider-token',
            'model' => '@cf/qwen/qwen3-30b-a3b-fp8',
            'timeout' => 8,
            'max_tokens' => 220,
        ]);
        Http::fake(['*' => Http::response([
            'success' => true,
            'errors' => [],
            'messages' => [],
            'result' => ['response' => 'Jawapan AI yang tidak patut dipanggil.'],
        ])]);
    }
}

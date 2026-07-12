<?php

namespace Tests\Feature;

use App\Models\Chatbot;
use App\Models\ChatLog;
use App\Models\KnowledgeItem;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductionNamedRateLimiterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.debug', false);
        Cache::flush();
        Http::fake();
    }

    public function test_widget_bootstrap_limit_returns_a_safe_malay_429_without_side_effects(): void
    {
        config()->set('chatme.widget.limits.bootstrap_per_minute', 1);
        $chatbot = $this->publicChatbot();

        $this->widgetConfig($chatbot, '203.0.113.10')->assertOk();

        $this->widgetConfig($chatbot, '203.0.113.10')
            ->assertStatus(429)
            ->assertExactJson($this->tooManyRequestsResponse());

        $this->assertNoMessagingSideEffects();
    }

    public function test_widget_chat_ip_limit_returns_a_safe_malay_429_without_side_effects(): void
    {
        config()->set('chatme.widget.limits.ingress_ip_per_minute', 1);
        config()->set('chatme.widget.limits.ingress_bot_per_minute', 100);
        $chatbot = $this->publicChatbot();

        $this->invalidWidgetChat($chatbot, '203.0.113.20')->assertUnprocessable();

        $this->invalidWidgetChat($chatbot, '203.0.113.20')
            ->assertStatus(429)
            ->assertExactJson($this->tooManyRequestsResponse());

        $this->assertNoMessagingSideEffects();
    }

    public function test_widget_chat_bot_limit_returns_a_safe_malay_429_without_side_effects(): void
    {
        config()->set('chatme.widget.limits.ingress_ip_per_minute', 100);
        config()->set('chatme.widget.limits.ingress_bot_per_minute', 1);
        $chatbot = $this->publicChatbot();

        $this->invalidWidgetChat($chatbot, '203.0.113.30')->assertUnprocessable();

        $this->invalidWidgetChat($chatbot, '203.0.113.31')
            ->assertStatus(429)
            ->assertExactJson($this->tooManyRequestsResponse());

        $this->assertNoMessagingSideEffects();
    }

    public function test_owner_minute_limit_is_shared_across_widget_bots_and_token_rotation(): void
    {
        config()->set('chatme.messaging.limits.owner_per_minute', 1);
        config()->set('chatme.messaging.limits.owner_daily', 100);
        config()->set('chatme.widget.limits.ingress_ip_per_minute', 100);
        config()->set('chatme.widget.limits.ingress_bot_per_minute', 100);
        config()->set('chatme.widget.limits.ticket_per_minute', 100);
        config()->set('chatme.widget.limits.chatbot_ip_per_minute', 100);
        config()->set('chatme.widget.limits.bot_per_minute', 100);
        config()->set('chatme.widget.limits.bot_daily_unlimited', 100);
        config()->set('chatme.developer_api.limits', [
            'ip_per_minute' => 100,
            'token_per_minute' => 100,
            'token_daily' => 100,
        ]);
        [, $widgetChatbot, $developerChatbot] = $this->paidOwnerWithTwoChatbots();
        $developerChatbot->rotateDeveloperApiToken();

        $origin = 'https://owner-limit.example.test';
        $widgetIp = '203.0.113.40';
        $config = $this->widgetConfig($widgetChatbot, $widgetIp, $origin)
            ->assertOk();
        $this->sendWidgetMessage($widgetChatbot, $origin, $widgetIp, [
            'ticket' => $config->json('widget_ticket'),
            'session' => $config->json('widget_session_id'),
        ])->assertOk();

        $widgetChatbot->delete();
        $newToken = $developerChatbot->rotateDeveloperApiToken();
        $logCountBeforeDeniedRequest = ChatLog::count();

        $this->developerChat($newToken, '203.0.113.42', 'owner-minute-denied')
            ->assertStatus(429)
            ->assertExactJson($this->tooManyRequestsResponse());

        $this->assertSame($logCountBeforeDeniedRequest, ChatLog::count());
        $this->assertDatabaseMissing('chat_logs', ['session_id' => 'owner-minute-denied']);
        $this->assertDatabaseCount('message_quota_reservations', 0);
        Http::assertNothingSent();
    }

    public function test_invalid_widget_origin_and_ticket_do_not_consume_the_owner_limit(): void
    {
        config()->set('chatme.messaging.limits.owner_per_minute', 1);
        config()->set('chatme.messaging.limits.owner_daily', 100);
        config()->set('chatme.widget.limits.ingress_ip_per_minute', 100);
        config()->set('chatme.widget.limits.ingress_bot_per_minute', 100);
        config()->set('chatme.widget.limits.ticket_per_minute', 100);
        config()->set('chatme.widget.limits.chatbot_ip_per_minute', 100);
        config()->set('chatme.widget.limits.bot_per_minute', 100);
        config()->set('chatme.widget.limits.bot_daily_unlimited', 100);
        [, $chatbot] = $this->paidOwnerWithTwoChatbots();
        $chatbot->update(['domain_whitelist' => 'valid-owner-limit.example.test']);
        $origin = 'https://valid-owner-limit.example.test';
        $ip = '203.0.113.45';
        $config = $this->widgetConfig($chatbot, $ip, $origin)->assertOk();
        $ticket = [
            'ticket' => $config->json('widget_ticket'),
            'session' => $config->json('widget_session_id'),
        ];

        $this->sendWidgetMessage($chatbot, 'https://invalid-origin.example.test', $ip, $ticket)
            ->assertForbidden();
        $this->sendWidgetMessage($chatbot, $origin, $ip, [
            'ticket' => 'invalid-ticket',
            'session' => $ticket['session'],
        ])->assertUnauthorized();

        $this->sendWidgetMessage($chatbot, $origin, $ip, $ticket)
            ->assertOk();
        $logsBeforeDeniedRequest = ChatLog::count();

        $this->sendWidgetMessage($chatbot, $origin, $ip, $ticket)
            ->assertStatus(429)
            ->assertExactJson($this->tooManyRequestsResponse());

        $this->assertSame($logsBeforeDeniedRequest, ChatLog::count());
        $this->assertDatabaseCount('message_quota_reservations', 0);
        Http::assertNothingSent();
    }

    public function test_owner_limit_does_not_share_counters_with_another_owner(): void
    {
        config()->set('chatme.messaging.limits.owner_per_minute', 1);
        config()->set('chatme.messaging.limits.owner_daily', 100);
        config()->set('chatme.developer_api.limits', [
            'ip_per_minute' => 100,
            'token_per_minute' => 100,
            'token_daily' => 100,
        ]);
        [, $firstOwnerChatbot] = $this->paidOwnerWithTwoChatbots();
        [, $secondOwnerChatbot] = $this->paidOwnerWithTwoChatbots();
        $firstToken = $firstOwnerChatbot->rotateDeveloperApiToken();
        $secondToken = $secondOwnerChatbot->rotateDeveloperApiToken();

        $this->developerChat($firstToken, '192.0.2.10', 'first-owner-allowed')
            ->assertOk();
        $this->developerChat($secondToken, '192.0.2.10', 'second-owner-allowed')
            ->assertOk();

        $logCountBeforeDeniedRequest = ChatLog::count();
        $this->developerChat($firstOwnerChatbot->rotateDeveloperApiToken(), '192.0.2.11', 'first-owner-denied')
            ->assertStatus(429)
            ->assertExactJson($this->tooManyRequestsResponse());

        $this->assertSame($logCountBeforeDeniedRequest, ChatLog::count());
        $this->assertDatabaseMissing('chat_logs', ['session_id' => 'first-owner-denied']);
        Http::assertNothingSent();
    }

    public function test_owner_daily_hard_cap_survives_switching_to_another_bot(): void
    {
        config()->set('chatme.messaging.limits.owner_per_minute', 100);
        config()->set('chatme.messaging.limits.owner_daily', 1);
        config()->set('chatme.developer_api.limits', [
            'ip_per_minute' => 100,
            'token_per_minute' => 100,
            'token_daily' => 100,
        ]);
        [, $firstChatbot, $secondChatbot] = $this->paidOwnerWithTwoChatbots();

        $firstToken = $firstChatbot->rotateDeveloperApiToken();
        $this->developerChat($firstToken, '198.51.100.10', 'owner-daily-allowed')
            ->assertOk();

        $secondToken = $secondChatbot->rotateDeveloperApiToken();
        $logCountBeforeDeniedRequest = ChatLog::count();

        $this->developerChat($secondToken, '198.51.100.11', 'owner-daily-denied')
            ->assertStatus(429)
            ->assertExactJson($this->tooManyRequestsResponse());

        $this->assertSame($logCountBeforeDeniedRequest, ChatLog::count());
        $this->assertDatabaseMissing('chat_logs', ['session_id' => 'owner-daily-denied']);
        $this->assertDatabaseCount('message_quota_reservations', 0);
        Http::assertNothingSent();
    }

    public function test_chatbot_creation_burst_is_limited_per_user_and_ip_without_an_extra_write(): void
    {
        config()->set('chatme.chatbots.limits.creations_per_hour', 2);
        [$owner, $firstChatbot, $secondChatbot] = $this->paidOwnerWithTwoChatbots();
        $firstChatbot->delete();
        $secondChatbot->delete();

        foreach (range(1, 2) as $number) {
            $this->actingAs($owner)
                ->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.(49 + $number)])
                ->postJson(route('chatbots.store'), ['name' => 'Burst Bot '.$number])
                ->assertRedirect(route('chatbots.index'));
        }

        $this->actingAs($owner)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.52'])
            ->postJson(route('chatbots.store'), ['name' => 'Burst Bot Denied'])
            ->assertStatus(429)
            ->assertExactJson($this->tooManyRequestsResponse());

        $this->assertDatabaseCount('chatbots', 2);
        $this->assertDatabaseMissing('chatbots', ['name' => 'Burst Bot Denied']);
        Http::assertNothingSent();
    }

    public function test_chatbot_creation_ip_limit_is_shared_across_authenticated_users(): void
    {
        config()->set('chatme.chatbots.limits.creations_per_hour', 2);
        $owners = collect(range(1, 3))->map(function (): User {
            [$owner, $firstChatbot, $secondChatbot] = $this->paidOwnerWithTwoChatbots();
            $firstChatbot->delete();
            $secondChatbot->delete();

            return $owner;
        });

        foreach ($owners->take(2) as $number => $owner) {
            $this->actingAs($owner)
                ->withServerVariables(['REMOTE_ADDR' => '198.51.100.50'])
                ->postJson(route('chatbots.store'), ['name' => 'Shared IP Bot '.($number + 1)])
                ->assertRedirect(route('chatbots.index'));
        }

        $this->actingAs($owners[2])
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.50'])
            ->postJson(route('chatbots.store'), ['name' => 'Shared IP Bot Denied'])
            ->assertStatus(429)
            ->assertExactJson($this->tooManyRequestsResponse());

        $this->assertDatabaseCount('chatbots', 2);
        $this->assertDatabaseMissing('chatbots', ['name' => 'Shared IP Bot Denied']);
        Http::assertNothingSent();
    }

    public function test_chatbot_creation_limit_returns_to_the_form_with_a_popup_message_for_browser_users(): void
    {
        config()->set('chatme.chatbots.limits.creations_per_hour', 1);
        [$owner, $firstChatbot, $secondChatbot] = $this->paidOwnerWithTwoChatbots();
        $firstChatbot->delete();
        $secondChatbot->delete();

        $this->actingAs($owner)
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.60'])
            ->post(route('chatbots.store'), ['name' => 'Browser Bot Allowed'])
            ->assertRedirect(route('chatbots.index'));

        $this->actingAs($owner)
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.60'])
            ->from(route('chatbots.create'))
            ->post(route('chatbots.store'), ['name' => 'Browser Bot Denied'])
            ->assertRedirect(route('chatbots.create'))
            ->assertSessionHas('error', 'Terlalu banyak percubaan mencipta chatbot. Sila cuba semula kemudian.');

        $this->assertDatabaseCount('chatbots', 1);
        $this->assertDatabaseMissing('chatbots', ['name' => 'Browser Bot Denied']);
    }

    private function publicChatbot(): Chatbot
    {
        return Chatbot::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Rate Limit Widget',
            'domain_whitelist' => '*',
        ]);
    }

    /** @return array{User, Chatbot, Chatbot} */
    private function paidOwnerWithTwoChatbots(): array
    {
        $plan = Plan::create([
            'name' => 'Shared Owner Limit',
            'slug' => 'shared-owner-limit-'.str()->random(8),
            'price' => 149,
            'chatbot_limit' => -1,
            'knowledge_limit' => -1,
            'monthly_messages' => -1,
            'api_access' => true,
            'is_active' => true,
        ]);
        $owner = User::factory()->create();
        $owner->subscriptions()->create([
            'plan_id' => $plan->id,
            'provider' => 'system',
            'status' => 'active',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addMonth(),
        ]);

        $chatbots = collect(range(1, 2))->map(function (int $number) use ($owner): Chatbot {
            $chatbot = Chatbot::create([
                'user_id' => $owner->id,
                'name' => 'Shared Owner Bot '.$number,
                'domain_whitelist' => '*',
            ]);
            KnowledgeItem::create([
                'chatbot_id' => $chatbot->id,
                'question' => 'Apakah waktu operasi?',
                'answer' => 'Kami buka setiap hari.',
                'tags' => 'waktu,operasi',
                'is_active' => true,
            ]);

            return $chatbot;
        });

        return [$owner, $chatbots[0], $chatbots[1]];
    }

    private function widgetConfig(
        Chatbot $chatbot,
        string $ip,
        string $origin = 'https://widget-rate-limit.example.test',
    ) {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeader('Origin', $origin)
            ->getJson(route('api.widget.config', $chatbot->api_key));
    }

    private function invalidWidgetChat(Chatbot $chatbot, string $ip)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeader('Origin', 'https://widget-rate-limit.example.test')
            ->postJson(route('api.chat', $chatbot->api_key), [
                'message' => '',
                'session_id' => 'rate-limit-session',
            ]);
    }

    /** @param array{ticket: string, session: string} $ticket */
    private function sendWidgetMessage(
        Chatbot $chatbot,
        string $origin,
        string $ip,
        array $ticket,
    ) {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeader('Origin', $origin)
            ->postJson(route('api.chat', $chatbot->api_key), [
                'message' => 'Apakah waktu operasi?',
                'session_id' => $ticket['session'],
                'widget_ticket' => $ticket['ticket'],
            ]);
    }

    private function developerChat(string $token, string $ip, string $sessionId)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withToken($token)
            ->postJson(route('api.developer.chat'), [
                'message' => 'Apakah waktu operasi?',
                'session_id' => $sessionId,
            ]);
    }

    /** @return array{error: string} */
    private function tooManyRequestsResponse(): array
    {
        return ['error' => 'Terlalu banyak permintaan. Sila cuba lagi sebentar lagi.'];
    }

    private function assertNoMessagingSideEffects(): void
    {
        Http::assertNothingSent();
        $this->assertDatabaseCount('chat_logs', 0);
        $this->assertDatabaseCount('message_quota_reservations', 0);
        $this->assertDatabaseCount('tester_ai_usages', 0);
    }
}

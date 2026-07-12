<?php

namespace Tests\Feature;

use App\Models\Chatbot;
use App\Models\KnowledgeItem;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WidgetTicketSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_issues_an_origin_bound_ticket_and_reflects_only_the_exact_origin(): void
    {
        $chatbot = $this->unlimitedChatbot('trusted.example');
        $origin = 'https://support.trusted.example';

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->withHeader('Origin', $origin)
            ->getJson(route('api.widget.config', $chatbot->api_key))
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Vary', 'Origin')
            ->assertJsonStructure([
                'widget_ticket',
                'widget_session_id',
                'ticket_expires_at',
            ]);

        $this->assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertIsString($response->json('widget_ticket'));
        $this->assertStringNotContainsString($response->json('widget_ticket'), route('api.widget.config', $chatbot->api_key));
    }

    public function test_chat_requires_a_valid_ticket_and_writes_one_atomic_pair(): void
    {
        $chatbot = $this->unlimitedChatbot('*');
        $origin = 'https://site.example.test';
        $ip = '203.0.113.11';

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeader('Origin', $origin)
            ->postJson(route('api.chat', $chatbot->api_key), [
                'message' => 'waktu operasi',
                'session_id' => 'missing-ticket',
            ])
            ->assertUnauthorized();

        $config = $this->widgetConfig($chatbot, $origin, $ip);
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeader('Origin', $origin)
            ->postJson(route('api.chat', $chatbot->api_key), [
                'message' => 'waktu operasi',
                'session_id' => $config['session'],
                'widget_ticket' => $config['ticket'],
            ])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertExactJson([
                'response' => 'Kami buka setiap hari.',
                'session_id' => $config['session'],
            ]);

        $this->assertDatabaseCount('chat_logs', 2);
        $this->assertDatabaseCount('message_quota_reservations', 0);
    }

    public function test_fake_expired_and_mismatched_ticket_bindings_are_rejected_without_logs(): void
    {
        $chatbot = $this->unlimitedChatbot('*');
        $origin = 'https://site.example.test';
        $ip = '203.0.113.12';

        $this->assertTicketRejected($chatbot, $origin, $ip, [
            'ticket' => 'ticket-palsu',
            'session' => 'session-palsu',
        ]);

        $expired = $this->widgetConfig($chatbot, $origin, $ip);
        $this->travel(11)->minutes();
        $this->assertTicketRejected($chatbot, $origin, $ip, $expired);
        $this->travelBack();

        $wrongOrigin = $this->widgetConfig($chatbot, $origin, $ip);
        $this->assertTicketRejected($chatbot, 'https://other.example.test', $ip, $wrongOrigin);

        $wrongIp = $this->widgetConfig($chatbot, $origin, $ip);
        $this->assertTicketRejected($chatbot, $origin, '203.0.113.99', $wrongIp);

        $wrongSession = $this->widgetConfig($chatbot, $origin, $ip);
        $wrongSession['session'] = 'different-session';
        $this->assertTicketRejected($chatbot, $origin, $ip, $wrongSession);

        $this->assertDatabaseCount('chat_logs', 0);
        $this->assertDatabaseCount('message_quota_reservations', 0);
    }

    public function test_ticket_and_bot_global_limiters_apply_even_when_ips_are_distributed(): void
    {
        config()->set('chatme.widget.limits.ticket_per_minute', 2);
        config()->set('chatme.widget.limits.bot_per_minute', 2);
        config()->set('chatme.widget.limits.bot_daily_unlimited', 100);
        $chatbot = $this->unlimitedChatbot('*');
        $origin = 'https://site.example.test';

        foreach (['203.0.113.21', '203.0.113.22'] as $index => $ip) {
            $config = $this->widgetConfig($chatbot, $origin, $ip);
            $this->sendWithTicket($chatbot, $origin, $ip, $config, 'allowed-'.$index)
                ->assertOk();
        }

        $thirdIp = '203.0.113.23';
        $third = $this->widgetConfig($chatbot, $origin, $thirdIp);
        $this->sendWithTicket($chatbot, $origin, $thirdIp, $third, 'globally-limited')
            ->assertStatus(429)
            ->assertExactJson(['error' => 'Terlalu banyak permintaan. Sila cuba lagi sebentar lagi.']);

        config()->set('chatme.widget.limits.bot_per_minute', 100);
        $sameIp = '203.0.113.24';
        $sameTicket = $this->widgetConfig($chatbot, $origin, $sameIp);
        $this->sendWithTicket($chatbot, $origin, $sameIp, $sameTicket, 'ticket-1')->assertOk();
        $this->sendWithTicket($chatbot, $origin, $sameIp, $sameTicket, 'ticket-2')->assertOk();
        $this->sendWithTicket($chatbot, $origin, $sameIp, $sameTicket, 'ticket-3')
            ->assertStatus(429);
    }

    public function test_plan_aware_daily_bot_limit_applies_across_tickets_and_resets_in_kuala_lumpur(): void
    {
        config()->set('chatme.widget.limits.ticket_per_minute', 100);
        config()->set('chatme.widget.limits.chatbot_ip_per_minute', 100);
        config()->set('chatme.widget.limits.bot_per_minute', 100);
        config()->set('chatme.widget.limits.bot_daily_unlimited', 2);
        $chatbot = $this->unlimitedChatbot('*');
        $origin = 'https://daily-limit.example.test';

        foreach (['203.0.113.31', '203.0.113.32'] as $index => $ip) {
            $ticket = $this->widgetConfig($chatbot, $origin, $ip);
            $this->sendWithTicket($chatbot, $origin, $ip, $ticket, 'daily-'.$index)
                ->assertOk();
        }

        $blockedIp = '203.0.113.33';
        $blockedTicket = $this->widgetConfig($chatbot, $origin, $blockedIp);
        $this->sendWithTicket($chatbot, $origin, $blockedIp, $blockedTicket, 'daily-blocked')
            ->assertStatus(429)
            ->assertExactJson(['error' => 'Terlalu banyak permintaan. Sila cuba lagi sebentar lagi.']);

        $this->travelTo(now('Asia/Kuala_Lumpur')->addDay()->startOfDay()->addSecond());
        $nextDayIp = '203.0.113.34';
        $nextDayTicket = $this->widgetConfig($chatbot, $origin, $nextDayIp);
        $this->sendWithTicket($chatbot, $origin, $nextDayIp, $nextDayTicket, 'daily-reset')
            ->assertOk();
    }

    public function test_preflight_and_routes_use_exact_widget_security_middleware(): void
    {
        $chatbot = $this->unlimitedChatbot('trusted.example');
        $origin = 'https://trusted.example';

        $this->withHeader('Origin', $origin)
            ->withHeader('Access-Control-Request-Method', 'POST')
            ->options(route('api.chat.options', $chatbot->api_key))
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->assertHeader('Vary', 'Origin');

        $configRoute = Route::getRoutes()->getByName('api.widget.config');
        $chatRoute = Route::getRoutes()->getByName('api.chat');
        $this->assertContains('throttle:widget-bootstrap', $configRoute->gatherMiddleware());
        $this->assertContains('throttle:widget-chat-ingress', $chatRoute->gatherMiddleware());
    }

    private function assertTicketRejected(
        Chatbot $chatbot,
        string $origin,
        string $ip,
        array $ticket,
    ): void {
        $this->sendWithTicket($chatbot, $origin, $ip, $ticket, 'rejected')
            ->assertUnauthorized()
            ->assertExactJson([
                'error' => 'Sesi widget tidak sah atau telah tamat. Muat semula chatbot.',
            ]);
    }

    private function sendWithTicket(
        Chatbot $chatbot,
        string $origin,
        string $ip,
        array $ticket,
        string $message,
    ) {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeader('Origin', $origin)
            ->postJson(route('api.chat', $chatbot->api_key), [
                'message' => $message,
                'session_id' => $ticket['session'],
                'widget_ticket' => $ticket['ticket'],
            ]);
    }

    /** @return array{ticket: string, session: string} */
    private function widgetConfig(Chatbot $chatbot, string $origin, string $ip): array
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeader('Origin', $origin)
            ->getJson(route('api.widget.config', $chatbot->api_key))
            ->assertOk();

        return [
            'ticket' => $response->json('widget_ticket'),
            'session' => $response->json('widget_session_id'),
        ];
    }

    private function unlimitedChatbot(string $whitelist): Chatbot
    {
        $plan = Plan::create([
            'name' => 'Unlimited Widget',
            'slug' => 'unlimited-widget-'.str()->random(8),
            'price' => 149,
            'chatbot_limit' => -1,
            'knowledge_limit' => -1,
            'monthly_messages' => -1,
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
        $chatbot = Chatbot::create([
            'user_id' => $user->id,
            'name' => 'Ticketed Widget',
            'domain_whitelist' => $whitelist,
        ]);
        KnowledgeItem::create([
            'chatbot_id' => $chatbot->id,
            'question' => 'Apakah waktu operasi?',
            'answer' => 'Kami buka setiap hari.',
            'tags' => 'waktu,operasi',
            'is_active' => true,
        ]);

        return $chatbot;
    }
}

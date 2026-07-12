<?php

namespace Tests\Feature;

use App\Models\Chatbot;
use App\Models\ChatLog;
use App\Models\KnowledgeItem;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ChatbotAiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.cloudflare_ai', [
            'enabled' => true,
            'account_id' => 'integration-account',
            'token' => 'integration-token',
            'model' => '@cf/qwen/qwen3-30b-a3b-fp8',
            'timeout' => 8,
            'max_tokens' => 220,
        ]);
    }

    public function test_public_chat_reserves_quota_before_ai_and_writes_two_logs_after_the_provider_returns(): void
    {
        $chatbot = $this->chatbotWithUncertainKnowledge();
        $baselineTransactionLevel = DB::transactionLevel();
        Http::fake(function (Request $request) use ($baselineTransactionLevel) {
            $this->assertSame(
                $baselineTransactionLevel,
                DB::transactionLevel(),
                'Cloudflare must not run inside the quota transaction.',
            );
            $this->assertDatabaseCount('message_quota_reservations', 1);
            $this->assertDatabaseCount('chat_logs', 0);

            return Http::response([
                'success' => true,
                'errors' => [],
                'messages' => [],
                'result' => ['response' => 'Jawapan AI berasaskan knowledge.'],
            ]);
        });

        $this->postWidgetJson($chatbot, [
            'message' => 'Boleh jelaskan waktu yang sesuai?',
        ], $publicSession)
            ->assertOk()
            ->assertExactJson([
                'response' => 'Jawapan AI berasaskan knowledge.',
                'session_id' => $publicSession,
            ]);

        Http::assertSentCount(1);
        $this->assertDatabaseCount('message_quota_reservations', 0);
        $this->assertDatabaseCount('chat_logs', 2);
        $this->assertDatabaseHas('chat_logs', [
            'session_id' => $publicSession,
            'message' => 'Jawapan AI berasaskan knowledge.',
            'role' => 'bot',
        ]);
    }

    public function test_quota_rejection_never_calls_ai_or_writes_a_partial_chat_pair(): void
    {
        $chatbot = $this->chatbotWithUncertainKnowledge();
        ChatLog::create([
            'chatbot_id' => $chatbot->id,
            'session_id' => 'quota-already-used',
            'message' => 'Slot telah digunakan',
            'role' => 'user',
        ]);
        Http::fake();

        $this->postWidgetJson($chatbot, [
            'message' => 'Boleh jelaskan waktu yang sesuai?',
        ], $rejectedSession)
            ->assertStatus(429)
            ->assertExactJson(['error' => 'Had mesej bulanan telah dicapai.']);

        Http::assertNothingSent();
        $this->assertDatabaseCount('message_quota_reservations', 0);
        $this->assertDatabaseCount('chat_logs', 1);
        $this->assertDatabaseMissing('chat_logs', [
            'session_id' => $rejectedSession,
        ]);
    }

    public function test_owner_tester_uses_real_ai_path_without_quota_or_log_writes(): void
    {
        $chatbot = $this->chatbotWithUncertainKnowledge();
        $user = $chatbot->user;
        ChatLog::create([
            'chatbot_id' => $chatbot->id,
            'session_id' => 'already-at-limit',
            'message' => 'Sudah digunakan',
            'role' => 'user',
        ]);
        Http::fake(['*' => Http::response([
            'success' => true,
            'errors' => [],
            'messages' => [],
            'result' => ['response' => 'Jawapan AI untuk pemilik.'],
        ])]);

        $this->actingAs($user)
            ->postJson(route('chatbots.test-message', $chatbot), [
                'message' => 'Boleh jelaskan waktu yang sesuai?',
            ])
            ->assertOk()
            ->assertExactJson(['response' => 'Jawapan AI untuk pemilik.']);

        Http::assertSentCount(1);
        $this->assertDatabaseCount('chat_logs', 1);
        $this->assertDatabaseHas('tester_ai_usages', [
            'user_id' => $user->id,
            'attempts' => 1,
        ]);
    }

    public function test_provider_failure_still_returns_successful_stable_fallback(): void
    {
        $chatbot = $this->chatbotWithUncertainKnowledge([
            'fallback_message' => 'Maklumat belum tersedia.',
        ]);
        Http::fake(['*' => Http::response(['success' => false, 'errors' => []], 500)]);

        $this->postWidgetJson($chatbot, [
            'message' => 'Boleh jelaskan waktu yang sesuai?',
        ], $fallbackSession)
            ->assertOk()
            ->assertJsonPath('response', 'Maklumat belum tersedia.');

        $this->assertDatabaseHas('chat_logs', [
            'session_id' => $fallbackSession,
            'message' => 'Maklumat belum tersedia.',
            'role' => 'bot',
        ]);
    }

    private function chatbotWithUncertainKnowledge(array $attributes = []): Chatbot
    {
        $plan = Plan::create([
            'name' => 'One Message',
            'slug' => 'one-message-'.str()->random(6),
            'price' => 0,
            'chatbot_limit' => 1,
            'knowledge_limit' => 10,
            'monthly_messages' => 1,
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->subscriptions()->create([
            'plan_id' => $plan->id,
            'provider' => 'system',
            'status' => 'active',
            'starts_at' => now()->subMinute(),
            'ends_at' => null,
        ]);
        $chatbot = Chatbot::create(array_merge([
            'user_id' => $user->id,
            'name' => 'AI Integration Bot',
        ], $attributes));
        KnowledgeItem::create([
            'chatbot_id' => $chatbot->id,
            'question' => 'Apakah waktu operasi?',
            'answer' => 'Kami beroperasi setiap hari.',
            'tags' => 'waktu,operasi,jadual',
            'is_active' => true,
        ]);

        return $chatbot;
    }

    private function postWidgetJson(
        Chatbot $chatbot,
        array $payload,
        ?string &$sessionId = null,
    ): TestResponse {
        $origin = 'https://widget.example.test';
        $config = $this->withHeader('Origin', $origin)
            ->getJson(route('api.widget.config', $chatbot->api_key))
            ->assertOk();
        $sessionId = $config->json('widget_session_id');
        $payload['session_id'] = $sessionId;
        $payload['widget_ticket'] = $config->json('widget_ticket');

        return $this->withHeader('Origin', $origin)
            ->postJson(route('api.chat', $chatbot->api_key), $payload);
    }
}

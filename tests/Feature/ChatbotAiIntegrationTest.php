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

    public function test_public_chat_calls_ai_before_the_quota_transaction_and_writes_two_logs(): void
    {
        $chatbot = $this->chatbotWithUncertainKnowledge();
        $baselineTransactionLevel = DB::transactionLevel();
        Http::fake(function (Request $request) use ($baselineTransactionLevel) {
            $this->assertSame(
                $baselineTransactionLevel,
                DB::transactionLevel(),
                'Cloudflare must not run inside the quota transaction.',
            );

            return Http::response([
                'success' => true,
                'errors' => [],
                'messages' => [],
                'result' => ['response' => 'Jawapan AI berasaskan knowledge.'],
            ]);
        });

        $this->postJson(route('api.chat', $chatbot->api_key), [
            'message' => 'Boleh jelaskan waktu yang sesuai?',
            'session_id' => 'ai-public-session',
        ])
            ->assertOk()
            ->assertExactJson([
                'response' => 'Jawapan AI berasaskan knowledge.',
                'session_id' => 'ai-public-session',
            ]);

        Http::assertSentCount(1);
        $this->assertDatabaseCount('chat_logs', 2);
        $this->assertDatabaseHas('chat_logs', [
            'session_id' => 'ai-public-session',
            'message' => 'Jawapan AI berasaskan knowledge.',
            'role' => 'bot',
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
    }

    public function test_provider_failure_still_returns_successful_stable_fallback(): void
    {
        $chatbot = $this->chatbotWithUncertainKnowledge([
            'fallback_message' => 'Maklumat belum tersedia.',
        ]);
        Http::fake(['*' => Http::response(['success' => false, 'errors' => []], 500)]);

        $this->postJson(route('api.chat', $chatbot->api_key), [
            'message' => 'Boleh jelaskan waktu yang sesuai?',
            'session_id' => 'ai-fallback-session',
        ])
            ->assertOk()
            ->assertJsonPath('response', 'Maklumat belum tersedia.');

        $this->assertDatabaseHas('chat_logs', [
            'session_id' => 'ai-fallback-session',
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
}

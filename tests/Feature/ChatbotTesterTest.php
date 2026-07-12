<?php

namespace Tests\Feature;

use App\Models\Chatbot;
use App\Models\ChatLog;
use App\Models\KnowledgeItem;
use App\Models\User;
use App\Services\ChatbotResponseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotTesterTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_response_service_returns_the_best_match_and_stable_fallback(): void
    {
        $chatbot = $this->chatbotWithKnowledge();
        $responses = app(ChatbotResponseService::class);

        $this->assertSame(
            'Kami buka setiap hari.',
            $responses->respond($chatbot, 'waktu operasi')->answer,
        );
        $this->assertSame(
            $chatbot->fallbackResponse(),
            $responses->respond($chatbot, 'soalan yang tiada padanan')->answer,
        );
    }

    public function test_owner_can_test_an_inactive_chatbot_without_logs_or_quota(): void
    {
        $user = User::factory()->create();
        $chatbot = $this->chatbotWithKnowledge($user, ['is_active' => false]);

        $this->actingAs($user)
            ->postJson(route('chatbots.test-message', $chatbot), [
                'message' => 'waktu operasi',
            ])
            ->assertOk()
            ->assertExactJson(['response' => 'Kami buka setiap hari.']);

        $this->assertSame(0, ChatLog::count());
    }

    public function test_other_user_cannot_test_the_chatbot(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $chatbot = $this->chatbotWithKnowledge($owner);

        $this->actingAs($otherUser)
            ->postJson(route('chatbots.test-message', $chatbot), [
                'message' => 'waktu operasi',
            ])
            ->assertForbidden();

        $this->assertSame(0, ChatLog::count());
    }

    public function test_admin_can_test_a_managed_chatbot(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $chatbot = $this->chatbotWithKnowledge($owner, ['is_active' => false]);

        $this->actingAs($admin)
            ->postJson(route('chatbots.test-message', $chatbot), [
                'message' => 'waktu operasi',
            ])
            ->assertOk()
            ->assertJsonPath('response', 'Kami buka setiap hari.');

        $this->assertSame(0, ChatLog::count());
    }

    public function test_test_message_requires_one_to_one_thousand_characters(): void
    {
        $user = User::factory()->create();
        $chatbot = $this->chatbotWithKnowledge($user);

        $this->actingAs($user)
            ->postJson(route('chatbots.test-message', $chatbot), ['message' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');

        $this->actingAs($user)
            ->postJson(route('chatbots.test-message', $chatbot), [
                'message' => str_repeat('a', 1001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');

        $this->assertSame(0, ChatLog::count());
    }

    public function test_guest_cannot_use_the_test_endpoint(): void
    {
        $chatbot = $this->chatbotWithKnowledge();

        $this->postJson(route('chatbots.test-message', $chatbot), [
            'message' => 'waktu operasi',
        ])->assertUnauthorized();

        $this->assertSame(0, ChatLog::count());
    }

    public function test_owner_tester_is_rate_limited_per_user_and_chatbot(): void
    {
        config()->set('app.debug', false);
        $user = User::factory()->create();
        $chatbot = $this->chatbotWithKnowledge($user);

        foreach (range(1, 20) as $attempt) {
            $this->actingAs($user)
                ->postJson(route('chatbots.test-message', $chatbot), [
                    'message' => 'waktu operasi',
                ])
                ->assertOk();
        }

        $this->actingAs($user)
            ->postJson(route('chatbots.test-message', $chatbot), [
                'message' => 'waktu operasi',
            ])
            ->assertStatus(429)
            ->assertJsonPath('error', 'Terlalu banyak permintaan. Sila cuba lagi sebentar lagi.');

        $this->assertDatabaseCount('chat_logs', 0);
    }

    public function test_tester_ai_is_limited_daily_without_blocking_deterministic_answers(): void
    {
        $this->enableFakeAi();
        config()->set('chatme.tester.daily_ai_limit', 2);
        $user = User::factory()->create();
        $chatbot = $this->chatbotWithKnowledge($user);
        Http::fake(['*' => Http::response([
            'success' => true,
            'errors' => [],
            'messages' => [],
            'result' => ['response' => 'Jawapan AI tester.'],
        ])]);

        foreach (range(1, 2) as $attempt) {
            $this->actingAs($user)
                ->postJson(route('chatbots.test-message', $chatbot), [
                    'message' => 'Boleh jelaskan waktu yang sesuai?',
                ])
                ->assertOk()
                ->assertExactJson(['response' => 'Jawapan AI tester.']);
        }

        $this->actingAs($user)
            ->postJson(route('chatbots.test-message', $chatbot), [
                'message' => 'Boleh jelaskan waktu yang sesuai?',
            ])
            ->assertOk()
            ->assertExactJson([
                'response' => $chatbot->fallbackResponse(),
                'notice' => 'Had ujian AI harian telah dicapai. Padanan biasa masih digunakan.',
            ]);

        $this->actingAs($user)
            ->postJson(route('chatbots.test-message', $chatbot), [
                'message' => 'waktu operasi',
            ])
            ->assertOk()
            ->assertExactJson(['response' => 'Kami buka setiap hari.']);

        Http::assertSentCount(2);
        $this->assertDatabaseHas('tester_ai_usages', [
            'user_id' => $user->id,
            'attempts' => 2,
        ]);
        $this->assertDatabaseCount('chat_logs', 0);
    }

    public function test_tester_ai_limit_resets_on_the_next_kuala_lumpur_day(): void
    {
        $this->enableFakeAi();
        config()->set('chatme.tester.daily_ai_limit', 1);
        $user = User::factory()->create();
        $chatbot = $this->chatbotWithKnowledge($user);
        Http::fake(['*' => Http::response([
            'success' => true,
            'errors' => [],
            'messages' => [],
            'result' => ['response' => 'Jawapan AI tester.'],
        ])]);

        $this->actingAs($user)
            ->postJson(route('chatbots.test-message', $chatbot), [
                'message' => 'Boleh jelaskan waktu yang sesuai?',
            ])
            ->assertOk();

        $this->travel(1)->day();

        $this->actingAs($user)
            ->postJson(route('chatbots.test-message', $chatbot), [
                'message' => 'Boleh jelaskan waktu yang sesuai?',
            ])
            ->assertOk()
            ->assertExactJson(['response' => 'Jawapan AI tester.']);

        Http::assertSentCount(2);
        $this->assertDatabaseCount('tester_ai_usages', 2);
        $this->assertDatabaseCount('chat_logs', 0);
    }

    private function chatbotWithKnowledge(?User $user = null, array $attributes = []): Chatbot
    {
        $user ??= User::factory()->create();
        $chatbot = Chatbot::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Tester Bot',
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

    private function enableFakeAi(): void
    {
        config()->set('services.cloudflare_ai', [
            'enabled' => true,
            'account_id' => 'tester-account',
            'token' => 'tester-token',
            'model' => '@cf/qwen/qwen3-30b-a3b-fp8',
            'timeout' => 8,
            'max_tokens' => 220,
        ]);
    }
}

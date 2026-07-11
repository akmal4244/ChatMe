<?php

namespace Tests\Unit;

use App\Contracts\AiAnswerProvider;
use App\Models\Chatbot;
use App\Models\KnowledgeItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CloudflareWorkersAiProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.cloudflare_ai', [
            'enabled' => true,
            'account_id' => 'account-123',
            'token' => 'secret-cloudflare-token',
            'model' => '@cf/qwen/qwen3-30b-a3b-fp8',
            'timeout' => 8,
            'max_tokens' => 220,
        ]);
        Cache::flush();
    }

    public function test_disabled_provider_returns_null_without_an_http_request(): void
    {
        config()->set('services.cloudflare_ai.enabled', false);
        Http::fake();

        $result = app(AiAnswerProvider::class)->answer(
            $this->chatbot(),
            'Apakah harga pelan?',
            new Collection,
        );

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_success_sends_only_three_candidates_and_returns_answer_with_latency(): void
    {
        $chatbot = $this->chatbot();
        $candidates = $this->candidates($chatbot, 4);
        Http::fake(['api.cloudflare.com/*' => Http::response([
            'success' => true,
            'errors' => [],
            'messages' => [],
            'result' => ['response' => 'Pelan Pro berharga RM49 sebulan.'],
        ])]);

        $result = app(AiAnswerProvider::class)->answer(
            $chatbot,
            'Berapa harga pelan yang sesuai?',
            $candidates,
        );

        $this->assertSame('Pelan Pro berharga RM49 sebulan.', $result?->answer);
        $this->assertGreaterThanOrEqual(0, $result?->latencyMs);

        Http::assertSent(function (Request $request) use ($chatbot): bool {
            $body = json_encode($request->data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $system = $request['messages'][0]['content'] ?? '';

            return $request->url() === 'https://api.cloudflare.com/client/v4/accounts/account-123/ai/run/@cf/qwen/qwen3-30b-a3b-fp8'
                && $request->hasHeader('Authorization', 'Bearer secret-cloudflare-token')
                && $request['max_tokens'] === 220
                && $request['temperature'] === 0.2
                && count($request['messages']) === 2
                && str_contains($system, 'Gunakan hanya konteks soal jawab yang dibekalkan')
                && str_contains($system, 'Jawab secara profesional dan mesra.')
                && str_contains($body, 'Jawapan 1')
                && str_contains($body, 'Jawapan 3')
                && ! str_contains($body, 'Jawapan 4')
                && ! str_contains($body, $chatbot->user->email);
        });
        Http::assertSentCount(1);
    }

    public function test_no_answer_sentinel_and_blank_output_use_local_fallback_signal(): void
    {
        $chatbot = $this->chatbot();

        Http::fakeSequence()
            ->push(['success' => true, 'errors' => [], 'messages' => [], 'result' => ['response' => '__CHATME_NO_ANSWER__']])
            ->push(['success' => true, 'errors' => [], 'messages' => [], 'result' => ['response' => '   ']]);

        $provider = app(AiAnswerProvider::class);

        $this->assertNull($provider->answer($chatbot, 'Soalan pertama', $this->candidates($chatbot, 1)));
        $this->assertNull($provider->answer($chatbot, 'Soalan kedua', $this->candidates($chatbot, 1)));
        Http::assertSentCount(2);
    }

    public function test_transport_http_and_malformed_responses_fail_closed_without_throwing(): void
    {
        $chatbot = $this->chatbot();
        $candidates = $this->candidates($chatbot, 1);
        $provider = app(AiAnswerProvider::class);

        Http::fake(fn () => Http::failedConnection('simulated timeout'));
        $this->assertNull($provider->answer($chatbot, 'Transport failure', $candidates));

        Http::fake(['*' => Http::response(['success' => false, 'errors' => [['code' => 1000]]], 429)]);
        $this->assertNull($provider->answer($chatbot, 'Rate limited', $candidates));

        Http::fake(['*' => Http::response(['success' => false, 'errors' => []], 500)]);
        $this->assertNull($provider->answer($chatbot, 'Server failure', $candidates));

        Http::fake(['*' => Http::response(['success' => true, 'result' => ['unexpected' => true]])]);
        $this->assertNull($provider->answer($chatbot, 'Malformed response', $candidates));
    }

    public function test_five_consecutive_failures_open_the_circuit_for_five_minutes(): void
    {
        $chatbot = $this->chatbot();
        $candidates = $this->candidates($chatbot, 1);
        Http::fake(['*' => Http::response(['success' => false, 'errors' => []], 500)]);
        $provider = app(AiAnswerProvider::class);

        foreach (range(1, 5) as $_) {
            $this->assertNull($provider->answer($chatbot, 'Provider failure', $candidates));
        }

        $this->assertTrue(Cache::has('chatme:ai:circuit-open'));
        $this->assertNull($provider->answer($chatbot, 'Circuit is open', $candidates));
        Http::assertSentCount(5);
    }

    public function test_failure_logs_safe_metadata_without_message_token_or_provider_body(): void
    {
        Log::spy();
        Http::fake(['*' => Http::response([
            'success' => false,
            'errors' => [['message' => 'provider raw secret body']],
        ], 500)]);
        $chatbot = $this->chatbot();

        app(AiAnswerProvider::class)->answer(
            $chatbot,
            'private visitor message',
            $this->candidates($chatbot, 1),
        );

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
            $logged = $message.json_encode($context);

            return str_contains($message, 'Cloudflare AI request failed')
                && isset($context['chatbot_id'], $context['category'], $context['latency_ms'])
                && ! str_contains($logged, 'private visitor message')
                && ! str_contains($logged, 'secret-cloudflare-token')
                && ! str_contains($logged, 'provider raw secret body');
        });
    }

    public function test_circuit_bookkeeping_failure_never_breaks_the_local_fallback(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'errors' => []], 500)]);
        $chatbot = $this->chatbot();
        $lock = Cache::lock('chatme:ai:circuit-lock', 10);
        $this->assertTrue($lock->get());

        try {
            $this->assertNull(app(AiAnswerProvider::class)->answer(
                $chatbot,
                'Provider dan lock gagal',
                $this->candidates($chatbot, 1),
            ));
        } finally {
            $lock->release();
        }
    }

    private function chatbot(): Chatbot
    {
        $user = User::factory()->create();

        return Chatbot::create([
            'user_id' => $user->id,
            'name' => 'Cloudflare Bot',
            'system_prompt' => 'Jawab secara profesional dan mesra.',
        ]);
    }

    /** @return Collection<int, KnowledgeItem> */
    private function candidates(Chatbot $chatbot, int $count): Collection
    {
        return collect(range(1, $count))->map(fn (int $number): KnowledgeItem => KnowledgeItem::create([
            'chatbot_id' => $chatbot->id,
            'question' => "Soalan {$number}",
            'answer' => "Jawapan {$number}",
            'tags' => 'ujian',
            'is_active' => true,
        ]));
    }
}

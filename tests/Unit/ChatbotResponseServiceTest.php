<?php

namespace Tests\Unit;

use App\Contracts\AiAnswerProvider;
use App\Models\Chatbot;
use App\Models\KnowledgeItem;
use App\Models\User;
use App\Services\ChatbotResponseService;
use App\ValueObjects\AiProviderResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ChatbotResponseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_high_confidence_answer_does_not_call_ai(): void
    {
        $chatbot = $this->chatbotWithKnowledge();
        $provider = Mockery::mock(AiAnswerProvider::class);
        $provider->shouldNotReceive('answer');
        $this->app->instance(AiAnswerProvider::class, $provider);

        $response = app(ChatbotResponseService::class)
            ->respond($chatbot, 'Apakah waktu operasi?');

        $this->assertSame('deterministic', $response->source);
        $this->assertSame('Kami buka setiap hari.', $response->answer);
        $this->assertSame(1.0, $response->score);
        $this->assertNull($response->providerLatencyMs);
    }

    public function test_uncertain_answer_uses_ai(): void
    {
        $chatbot = $this->chatbotWithKnowledge();
        $provider = Mockery::mock(AiAnswerProvider::class);
        $provider->shouldReceive('answer')->once()
            ->andReturn(new AiProviderResult('Jawapan AI.', 120));
        $this->app->instance(AiAnswerProvider::class, $provider);

        $response = app(ChatbotResponseService::class)
            ->respond($chatbot, 'Boleh jelaskan waktu yang sesuai?');

        $this->assertSame('cloudflare', $response->source);
        $this->assertSame('Jawapan AI.', $response->answer);
        $this->assertSame(120, $response->providerLatencyMs);
    }

    public function test_provider_failure_uses_stable_fallback(): void
    {
        $chatbot = $this->chatbotWithKnowledge([
            'fallback_message' => 'Sila cuba soalan lain.',
        ]);
        $provider = Mockery::mock(AiAnswerProvider::class);
        $provider->shouldReceive('answer')->once()->andReturnNull();
        $this->app->instance(AiAnswerProvider::class, $provider);

        $response = app(ChatbotResponseService::class)
            ->respond($chatbot, 'Boleh jelaskan waktu yang sesuai?');

        $this->assertSame('fallback', $response->source);
        $this->assertSame('Sila cuba soalan lain.', $response->answer);
    }

    public function test_no_candidates_or_disabled_ai_never_calls_provider(): void
    {
        $chatbot = $this->chatbotWithKnowledge();
        $provider = Mockery::mock(AiAnswerProvider::class);
        $provider->shouldNotReceive('answer');
        $this->app->instance(AiAnswerProvider::class, $provider);

        $noMatch = app(ChatbotResponseService::class)
            ->respond($chatbot, 'ramalan cuaca esok');
        $disabled = app(ChatbotResponseService::class)
            ->respond($chatbot, 'Boleh jelaskan waktu yang sesuai?', allowAi: false);

        $this->assertSame('fallback', $noMatch->source);
        $this->assertSame('fallback', $disabled->source);
    }

    private function chatbotWithKnowledge(array $attributes = []): Chatbot
    {
        $chatbot = Chatbot::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'name' => 'Response Bot',
        ], $attributes));

        KnowledgeItem::create([
            'chatbot_id' => $chatbot->id,
            'question' => 'Apakah waktu operasi?',
            'answer' => 'Kami buka setiap hari.',
            'tags' => 'waktu,operasi,jadual',
            'is_active' => true,
        ]);

        return $chatbot;
    }
}

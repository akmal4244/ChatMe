<?php

namespace Tests\Feature;

use App\Models\Chatbot;
use App\Models\KnowledgeItem;
use App\Models\User;
use App\Services\ChatbotResponseMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatbotTesterTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_matcher_returns_the_existing_best_match_and_fallback(): void
    {
        $chatbot = $this->chatbotWithKnowledge();
        $matcher = app(ChatbotResponseMatcher::class);

        $this->assertSame(
            'Kami buka setiap hari.',
            $matcher->respond($chatbot, 'waktu operasi'),
        );
        $this->assertContains(
            $matcher->respond($chatbot, 'soalan yang tiada padanan'),
            [
                'Maaf, saya belum pasti jawapannya. Cuba tanya dengan cara lain atau berikan maklumat yang lebih khusus.',
                'Soalan yang bagus! Boleh berikan sedikit lagi maklumat supaya saya dapat membantu?',
                'Saya sedia membantu. Boleh jelaskan dengan lebih lanjut perkara yang anda ingin tahu?',
                'Maaf, saya belum menemui jawapan yang tepat. Cuba gunakan perkataan lain.',
            ],
        );
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
}

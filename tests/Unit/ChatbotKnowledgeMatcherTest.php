<?php

namespace Tests\Unit;

use App\Models\Chatbot;
use App\Models\KnowledgeItem;
use App\Models\User;
use App\Services\ChatbotKnowledgeMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatbotKnowledgeMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_fifty_malay_variants_select_the_expected_intent_or_no_match(): void
    {
        $fixture = require base_path('tests/Fixtures/chatbot_query_corpus.php');
        $chatbot = $this->chatbotWithKnowledge($fixture['knowledge']);
        $matcher = app(ChatbotKnowledgeMatcher::class);

        $this->assertCount(50, $fixture['cases']);

        foreach ($fixture['cases'] as [$expectedIntent, $query]) {
            $result = $matcher->match($chatbot, $query);

            if ($expectedIntent === null) {
                $this->assertFalse($result->hasCandidates(), $query);

                continue;
            }

            $this->assertSame(
                $fixture['knowledge'][$expectedIntent]['answer'],
                $result->winner?->answer,
                $query,
            );
        }
    }

    public function test_production_pricing_regression_is_high_confidence_and_not_the_intro(): void
    {
        $fixture = require base_path('tests/Fixtures/chatbot_query_corpus.php');
        $chatbot = $this->chatbotWithKnowledge($fixture['knowledge']);

        $result = app(ChatbotKnowledgeMatcher::class)
            ->match($chatbot, 'Berapa harga pelan ChatMe?');

        $this->assertTrue($result->isHighConfidence());
        $this->assertSame($fixture['knowledge']['pricing']['answer'], $result->winner?->answer);
        $this->assertNotSame($fixture['knowledge']['intro']['answer'], $result->winner?->answer);
    }

    public function test_empty_short_and_generic_tags_never_match_every_message(): void
    {
        $chatbot = $this->chatbotWithKnowledge([
            'wrong' => [
                'question' => 'Pengenalan ChatMe',
                'answer' => 'Salah',
                'tags' => ' ,a,,chatme,bot',
            ],
            'pricing' => [
                'question' => 'Berapakah harga pelan?',
                'answer' => 'Betul',
                'tags' => 'harga,pelan',
            ],
        ]);

        $result = app(ChatbotKnowledgeMatcher::class)
            ->match($chatbot, 'Berapa harga pelan ChatMe?');

        $this->assertSame('Betul', $result->winner?->answer);
    }

    public function test_inactive_knowledge_is_never_returned(): void
    {
        $chatbot = $this->chatbotWithKnowledge([
            'active' => [
                'question' => 'Apakah waktu operasi?',
                'answer' => 'Jawapan aktif.',
                'tags' => 'waktu,operasi',
            ],
        ]);
        $chatbot->knowledgeItems()->create([
            'question' => 'Apakah waktu operasi?',
            'answer' => 'Jawapan tidak aktif.',
            'tags' => 'waktu,operasi',
            'is_active' => false,
        ]);

        $result = app(ChatbotKnowledgeMatcher::class)
            ->match($chatbot, 'Apakah waktu operasi?');

        $this->assertSame('Jawapan aktif.', $result->winner?->answer);
        $this->assertNotContains(
            'Jawapan tidak aktif.',
            $result->candidates->pluck('answer')->all(),
        );
    }

    /** @param array<string, array{question:string, answer:string, tags:string}> $knowledge */
    private function chatbotWithKnowledge(array $knowledge): Chatbot
    {
        $chatbot = Chatbot::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Matcher Bot',
        ]);

        foreach ($knowledge as $item) {
            KnowledgeItem::create([
                'chatbot_id' => $chatbot->id,
                ...$item,
                'is_active' => true,
            ]);
        }

        return $chatbot;
    }
}

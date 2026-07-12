<?php

namespace Tests\Unit;

use App\Models\Chatbot;
use App\Models\KnowledgeItem;
use App\Models\User;
use App\Services\ChatbotKnowledgeMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_matching_hydrates_only_the_configured_number_of_candidates(): void
    {
        config()->set('chatme.knowledge.matcher_candidate_limit', 5);
        $chatbot = Chatbot::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Bounded Matcher Bot',
        ]);
        $now = now();
        KnowledgeItem::query()->insert(array_map(
            fn (int $number): array => [
                'chatbot_id' => $chatbot->id,
                'question' => "Apakah waktu operasi cawangan {$number}?",
                'answer' => "Waktu operasi {$number}.",
                'tags' => 'waktu,operasi',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            range(1, 30),
        ));

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains(strtolower($query->sql), 'knowledge_items')) {
                $queries[] = strtolower($query->sql);
            }
        });

        app(ChatbotKnowledgeMatcher::class)->match($chatbot, 'waktu operasi');

        $candidateQuery = collect($queries)
            ->first(fn (string $sql): bool => str_contains($sql, 'is_active'));
        $this->assertIsString($candidateQuery);
        $this->assertMatchesRegularExpression('/\blimit\s+5\b/', $candidateQuery);
    }

    public function test_exact_newer_answer_is_not_crowded_out_by_older_common_term_decoys(): void
    {
        config()->set('chatme.knowledge.matcher_candidate_limit', 5);
        $chatbot = $this->chatbotWithKnowledge([
            'decoy-1' => ['question' => 'Soalan satu', 'answer' => 'Salah 1', 'tags' => 'harga'],
            'decoy-2' => ['question' => 'Soalan dua', 'answer' => 'Salah 2', 'tags' => 'harga'],
            'decoy-3' => ['question' => 'Soalan tiga', 'answer' => 'Salah 3', 'tags' => 'harga'],
            'decoy-4' => ['question' => 'Soalan empat', 'answer' => 'Salah 4', 'tags' => 'harga'],
            'decoy-5' => ['question' => 'Soalan lima', 'answer' => 'Salah 5', 'tags' => 'harga'],
            'target' => [
                'question' => 'Harga pelan khas',
                'answer' => 'Jawapan tepat',
                'tags' => 'harga,pelan,khas',
            ],
        ]);

        $result = app(ChatbotKnowledgeMatcher::class)->match($chatbot, 'Harga pelan khas');

        $this->assertSame('Jawapan tepat', $result->winner?->answer);
        $this->assertTrue($result->isHighConfidence());
    }

    public function test_single_stop_word_brand_query_can_still_match_within_the_bounded_search(): void
    {
        $chatbot = $this->chatbotWithKnowledge([
            'intro' => [
                'question' => 'Apakah ChatMe?',
                'answer' => 'ChatMe ialah platform chatbot.',
                'tags' => 'chatme,pengenalan',
            ],
        ]);

        $result = app(ChatbotKnowledgeMatcher::class)->match($chatbot, 'ChatMe');

        $this->assertSame('ChatMe ialah platform chatbot.', $result->winner?->answer);
    }

    public function test_single_token_synonyms_are_not_removed_by_the_sql_prefilter(): void
    {
        $chatbot = $this->chatbotWithKnowledge([
            'pay' => ['question' => 'Bayar', 'answer' => 'Jawapan bayaran', 'tags' => ''],
            'cost' => ['question' => 'Kos', 'answer' => 'Jawapan harga', 'tags' => ''],
            'embed' => ['question' => 'Widget', 'answer' => 'Jawapan pemasangan', 'tags' => ''],
        ]);

        foreach ([
            'bayar' => 'Jawapan bayaran',
            'kos' => 'Jawapan harga',
            'widget' => 'Jawapan pemasangan',
        ] as $query => $expected) {
            $this->assertSame(
                $expected,
                app(ChatbotKnowledgeMatcher::class)->match($chatbot, $query)->winner?->answer,
                $query,
            );
        }
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

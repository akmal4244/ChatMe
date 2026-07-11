<?php

namespace App\Services;

use App\Models\Chatbot;
use App\Models\KnowledgeItem;
use App\ValueObjects\KnowledgeMatchResult;
use Illuminate\Support\Collection;

class ChatbotKnowledgeMatcher
{
    private const HIGH_CONFIDENCE = 0.72;

    private const UNCERTAIN_CONFIDENCE = 0.20;

    /** @var list<string> */
    private const STOP_WORDS = [
        'a', 'ada', 'an', 'and', 'are', 'atau', 'awak', 'boleh', 'bot', 'buat',
        'can', 'dan', 'dekat', 'do', 'does', 'for', 'i', 'in', 'ini', 'is',
        'itu', 'je', 'juga', 'kat', 'ke', 'lah', 'mana', 'macam', 'me', 'my',
        'nak', 'ni', 'of', 'on', 'pun', 'saya', 'the', 'to', 'tu', 'untuk',
        'what', 'yang', 'chatbot', 'chatme',
    ];

    /** @var array<string, string> */
    private const SYNONYMS = [
        'automatik' => 'pembayaran',
        'auto' => 'pembayaran',
        'bayar' => 'pembayaran',
        'caj' => 'harga',
        'duitnow' => 'pembayaran',
        'embed' => 'pasang',
        'free' => 'percuma',
        'fpx' => 'pembayaran',
        'fungsinya' => 'fungsi',
        'install' => 'pasang',
        'kos' => 'harga',
        'pakej' => 'pelan',
        'plan' => 'pelan',
        'potong' => 'pembayaran',
        'renew' => 'pembaharuan',
        'toyyibpay' => 'pembayaran',
        'tukar' => 'ubah',
        'website' => 'laman',
        'widget' => 'pasang',
    ];

    public function match(Chatbot $chatbot, string $message): KnowledgeMatchResult
    {
        $normalizedMessage = $this->normalize($message);

        if ($normalizedMessage === '') {
            return $this->noMatch();
        }

        $messageTokens = $this->meaningfulTokens($normalizedMessage);
        $scored = $chatbot->knowledgeItems()
            ->where('is_active', true)
            ->get()
            ->map(function (KnowledgeItem $item) use ($normalizedMessage, $messageTokens): array {
                $question = $this->normalize($item->question);
                $questionTokens = $this->meaningfulTokens($question);
                $tagPhrases = $this->tagPhrases($item->tags);
                $tagTokens = [];
                foreach ($tagPhrases as $tagPhrase) {
                    $tagTokens = [...$tagTokens, ...$this->meaningfulTokens($tagPhrase)];
                }
                $semanticTokens = array_values(array_unique([
                    ...$questionTokens,
                    ...$tagTokens,
                ]));

                $score = $this->score(
                    $normalizedMessage,
                    $messageTokens,
                    $question,
                    $semanticTokens,
                    $tagPhrases,
                );

                return [
                    'item' => $item,
                    'score' => $score,
                    'specificity' => count($questionTokens),
                ];
            })
            ->filter(fn (array $candidate): bool => $candidate['score'] >= self::UNCERTAIN_CONFIDENCE)
            ->sort(function (array $left, array $right): int {
                return $right['score'] <=> $left['score']
                    ?: $right['specificity'] <=> $left['specificity']
                    ?: $left['item']->id <=> $right['item']->id;
            })
            ->values();

        if ($scored->isEmpty()) {
            return $this->noMatch();
        }

        $best = $scored->first();
        $score = round((float) $best['score'], 4);

        return new KnowledgeMatchResult(
            winner: $best['item'],
            candidates: $scored->take(3)->pluck('item')->values(),
            score: $score,
            confidence: $score >= self::HIGH_CONFIDENCE ? 'high' : 'uncertain',
        );
    }

    /** @param list<string> $messageTokens @param list<string> $semanticTokens @param list<string> $tagPhrases */
    private function score(
        string $message,
        array $messageTokens,
        string $question,
        array $semanticTokens,
        array $tagPhrases,
    ): float {
        if ($message === $question) {
            return 1.0;
        }

        if ($message !== '' && $question !== ''
            && (str_contains($message, $question) || str_contains($question, $message))) {
            return min(0.94, 0.86 + (count($semanticTokens) * 0.01));
        }

        $overlap = array_values(array_intersect($messageTokens, $semanticTokens));
        $questionCoverage = $semanticTokens === [] ? 0.0 : count($overlap) / count($semanticTokens);
        $queryCoverage = $messageTokens === [] ? 0.0 : count($overlap) / count($messageTokens);
        $tagMatched = collect($tagPhrases)->contains(
            fn (string $tag): bool => str_contains($message, $tag),
        );

        return (0.55 * $questionCoverage)
            + (0.35 * $queryCoverage)
            + ($tagMatched ? 0.10 : 0.0);
    }

    private function normalize(string $text): string
    {
        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_KC) ?: $text;
        }

        $text = mb_strtolower(str_replace(['’', '‘', '`'], "'", $text));
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;
        $tokens = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $asksAboutChatMe = in_array('chatme', $tokens, true);
        $asksForChatbotCount = in_array('chatbot', $tokens, true)
            && (in_array('berapa', $tokens, true) || in_array('berapakah', $tokens, true));

        $tokens = array_map(function (string $token) use ($asksAboutChatMe, $asksForChatbotCount, $tokens): string {
            if ($asksAboutChatMe && $token === 'siapa') {
                return 'apa';
            }

            if ($asksForChatbotCount && in_array($token, ['berapa', 'berapakah'], true)) {
                return 'had';
            }

            if ($token === 'bayaran'
                && (in_array('berapa', $tokens, true) || in_array('berapakah', $tokens, true) || in_array('sebulan', $tokens, true))) {
                return 'harga';
            }

            if (mb_strlen($token) > 5 && str_ends_with($token, 'kah')) {
                $token = mb_substr($token, 0, -3);
            }

            return self::SYNONYMS[$token] ?? $token;
        }, $tokens);

        return implode(' ', $tokens);
    }

    /** @return list<string> */
    private function meaningfulTokens(string $normalized): array
    {
        $tokens = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $tokens,
            fn (string $token): bool => ! in_array($token, self::STOP_WORDS, true),
        )));
    }

    /** @return list<string> */
    private function tagPhrases(?string $tags): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn (string $tag): string => $this->normalize(trim($tag)), explode(',', (string) $tags)),
            fn (string $tag): bool => mb_strlen($tag) >= 3
                && ! in_array($tag, ['bot', 'chatbot', 'chatme'], true),
        )));
    }

    private function noMatch(): KnowledgeMatchResult
    {
        return new KnowledgeMatchResult(
            winner: null,
            candidates: new Collection,
            score: 0.0,
            confidence: 'none',
        );
    }
}

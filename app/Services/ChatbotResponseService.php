<?php

namespace App\Services;

use App\Contracts\AiAnswerProvider;
use App\Models\Chatbot;
use App\ValueObjects\ChatbotResponse;
use Closure;

class ChatbotResponseService
{
    public function __construct(
        private readonly ChatbotKnowledgeMatcher $matcher,
        private readonly AiAnswerProvider $provider,
    ) {}

    public function respond(
        Chatbot $chatbot,
        string $message,
        bool $allowAi = true,
        ?Closure $beforeProvider = null,
    ): ChatbotResponse {
        $match = $this->matcher->match($chatbot, $message);

        if ($match->isHighConfidence()) {
            return new ChatbotResponse(
                answer: (string) $match->winner?->answer,
                source: 'deterministic',
                score: $match->score,
            );
        }

        if ($allowAi && $match->hasCandidates()) {
            if ($beforeProvider !== null && ! $beforeProvider()) {
                return new ChatbotResponse(
                    answer: $chatbot->fallbackResponse(),
                    source: 'fallback',
                    score: $match->score,
                    aiLimitReached: true,
                );
            }

            $ai = $this->provider->answer($chatbot, $message, $match->candidates->take(3));

            if ($ai !== null) {
                return new ChatbotResponse(
                    answer: $ai->answer,
                    source: 'cloudflare',
                    score: $match->score,
                    providerLatencyMs: $ai->latencyMs,
                );
            }
        }

        return new ChatbotResponse(
            answer: $chatbot->fallbackResponse(),
            source: 'fallback',
            score: $match->score,
        );
    }
}

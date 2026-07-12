<?php

namespace App\ValueObjects;

final readonly class ChatbotResponse
{
    public function __construct(
        public string $answer,
        public string $source,
        public float $score,
        public ?int $providerLatencyMs = null,
        public bool $aiLimitReached = false,
    ) {}
}

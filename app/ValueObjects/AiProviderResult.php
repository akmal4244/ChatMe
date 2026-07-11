<?php

namespace App\ValueObjects;

final readonly class AiProviderResult
{
    public function __construct(
        public string $answer,
        public int $latencyMs,
    ) {}
}

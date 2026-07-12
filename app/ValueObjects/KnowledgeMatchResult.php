<?php

namespace App\ValueObjects;

use App\Models\KnowledgeItem;
use Illuminate\Support\Collection;

final readonly class KnowledgeMatchResult
{
    /** @param Collection<int, KnowledgeItem> $candidates */
    public function __construct(
        public ?KnowledgeItem $winner,
        public Collection $candidates,
        public float $score,
        public string $confidence,
    ) {}

    public function isHighConfidence(): bool
    {
        return $this->confidence === 'high';
    }

    public function hasCandidates(): bool
    {
        return $this->candidates->isNotEmpty();
    }
}

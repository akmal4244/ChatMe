<?php

namespace App\ValueObjects;

use Carbon\CarbonImmutable;

final readonly class MessageQuotaPermit
{
    public function __construct(
        public int $userId,
        public int $chatbotId,
        public string $channel,
        public ?string $reservationToken,
        public CarbonImmutable $reservedAt,
    ) {}
}

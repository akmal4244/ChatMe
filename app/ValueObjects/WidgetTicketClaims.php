<?php

namespace App\ValueObjects;

final readonly class WidgetTicketClaims
{
    public function __construct(
        public string $sessionId,
        public string $fingerprint,
    ) {}
}

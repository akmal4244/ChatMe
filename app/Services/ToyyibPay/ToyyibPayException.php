<?php

namespace App\Services\ToyyibPay;

use RuntimeException;

final class ToyyibPayException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message = 'ToyyibPay request could not be completed.',
    ) {
        parent::__construct($message);
    }
}

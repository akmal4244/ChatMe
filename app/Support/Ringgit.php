<?php

namespace App\Support;

use InvalidArgumentException;

final class Ringgit
{
    public static function decimalToCents(string $amount): int
    {
        if (! preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new InvalidArgumentException('A valid Ringgit amount is required.');
        }

        $whole = (int) $matches[1];
        $fraction = str_pad($matches[2] ?? '', 2, '0');

        return ($whole * 100) + (int) $fraction;
    }
}

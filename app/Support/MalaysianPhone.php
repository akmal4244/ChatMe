<?php

namespace App\Support;

use InvalidArgumentException;

final class MalaysianPhone
{
    public static function normalize(string $value): string
    {
        $phone = preg_replace('/[\s()\-]/', '', trim($value));

        if (! is_string($phone) || $phone === '') {
            throw new InvalidArgumentException('Nombor telefon mudah alih Malaysia yang sah diperlukan.');
        }

        if (str_starts_with($phone, '0060')) {
            $phone = substr($phone, 2);
        } elseif (str_starts_with($phone, '+60')) {
            $phone = substr($phone, 1);
        } elseif (str_starts_with($phone, '0')) {
            $phone = '60'.substr($phone, 1);
        }

        if (! preg_match('/^60(?:11\d{8}|1(?:0|2|3|4|6|7|8|9)\d{7})$/', $phone)) {
            throw new InvalidArgumentException('Nombor telefon mudah alih Malaysia yang sah diperlukan.');
        }

        return $phone;
    }
}

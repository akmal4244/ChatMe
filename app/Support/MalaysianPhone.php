<?php

namespace App\Support;

use InvalidArgumentException;

final class MalaysianPhone
{
    public static function normalize(string $value): string
    {
        $phone = preg_replace('/[\s()\-]/', '', trim($value));

        if (! is_string($phone) || $phone === '') {
            throw new InvalidArgumentException('A valid Malaysian mobile number is required.');
        }

        if (str_starts_with($phone, '0060')) {
            $phone = substr($phone, 2);
        } elseif (str_starts_with($phone, '+60')) {
            $phone = substr($phone, 1);
        } elseif (str_starts_with($phone, '0')) {
            $phone = '60'.substr($phone, 1);
        }

        if (! preg_match('/^601\d{8,9}$/', $phone)) {
            throw new InvalidArgumentException('A valid Malaysian mobile number is required.');
        }

        return $phone;
    }
}

<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Throwable;

final class ToyyibPayTimestamp
{
    public static function parse(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        foreach (['Y-m-d H:i:s', 'd-m-Y H:i:s'] as $format) {
            try {
                $timestamp = CarbonImmutable::createFromFormat(
                    $format,
                    $value,
                    'Asia/Kuala_Lumpur',
                );
            } catch (Throwable) {
                continue;
            }

            if ($timestamp !== null && $timestamp->format($format) === $value) {
                return $timestamp->utc();
            }
        }

        return null;
    }
}

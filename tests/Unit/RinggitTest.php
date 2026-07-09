<?php

namespace Tests\Unit;

use App\Support\Ringgit;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RinggitTest extends TestCase
{
    #[DataProvider('validAmounts')]
    public function test_decimal_amounts_are_converted_without_floats(string $amount, int $expected): void
    {
        $this->assertSame($expected, Ringgit::decimalToCents($amount));
    }

    public static function validAmounts(): array
    {
        return [
            ['0', 0],
            ['0.29', 29],
            ['49.9', 4990],
            ['149.00', 14900],
            ['00049.00', 4900],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_malformed_amounts_are_rejected(string $amount): void
    {
        $this->expectException(InvalidArgumentException::class);

        Ringgit::decimalToCents($amount);
    }

    public static function invalidAmounts(): array
    {
        return [
            [''],
            ['-1.00'],
            ['49.001'],
            ['49,00'],
            ['RM49.00'],
            ['99999999999.00'],
        ];
    }
}

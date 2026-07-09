<?php

namespace Tests\Unit;

use App\Support\MalaysianPhone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MalaysianPhoneTest extends TestCase
{
    #[DataProvider('validNumbers')]
    public function test_it_normalizes_common_malaysian_mobile_formats(string $input, string $expected): void
    {
        $this->assertSame($expected, MalaysianPhone::normalize($input));
    }

    public static function validNumbers(): array
    {
        return [
            ['0123456789', '60123456789'],
            ['011-2345 6789', '601123456789'],
            ['+60 19-876 5432', '60198765432'],
            ['60(12)345-6789', '60123456789'],
            ['0060 13 456 7890', '60134567890'],
        ];
    }

    #[DataProvider('invalidNumbers')]
    public function test_it_rejects_non_mobile_or_malformed_numbers(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        MalaysianPhone::normalize($input);
    }

    public static function invalidNumbers(): array
    {
        return [
            [''],
            ['0312345678'],
            ['010123456'],
            ['6012345678901'],
            ['012-ABC-6789'],
            ['+6512345678'],
        ];
    }
}

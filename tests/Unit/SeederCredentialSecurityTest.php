<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SeederCredentialSecurityTest extends TestCase
{
    public function test_homepage_seeder_generates_a_long_random_password(): void
    {
        $source = file_get_contents(__DIR__.'/../../database/seeders/HomepageChatbotSeeder.php');

        $this->assertDoesNotMatchRegularExpression("/'password'\s*=>\s*'[^']+'/", $source);
        $this->assertStringContainsString("'password' => Str::password(64)", $source);
    }
}

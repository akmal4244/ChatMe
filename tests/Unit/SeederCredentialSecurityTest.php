<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SeederCredentialSecurityTest extends TestCase
{
    public function test_demo_seeder_does_not_contain_a_literal_password(): void
    {
        $source = file_get_contents(__DIR__.'/../../database/seeders/DietKnowledgeSeeder.php');

        $this->assertDoesNotMatchRegularExpression("/Hash::make\('[^']+'\)/", $source);
        $this->assertStringContainsString('Hash::make(Str::password(', $source);
    }
}

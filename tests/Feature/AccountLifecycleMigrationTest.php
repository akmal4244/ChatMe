<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountLifecycleMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rollout_marks_only_existing_unverified_users_and_rollback_is_forward_only(): void
    {
        $unverified = User::factory()->unverified()->create();
        $verified = User::factory()->create(['email_verified_at' => now()->subDay()]);
        $originalVerifiedAt = $verified->email_verified_at->toISOString();
        $path = database_path('migrations/2026_07_12_000001_mark_existing_users_as_verified.php');

        $this->assertFileExists($path);
        $migration = require $path;
        $migration->up();

        $this->assertNotNull($unverified->fresh()->email_verified_at);
        $this->assertSame($originalVerifiedAt, $verified->fresh()->email_verified_at->toISOString());

        $rolloutTimestamp = $unverified->fresh()->email_verified_at->toISOString();
        $migration->down();

        $this->assertSame($rolloutTimestamp, $unverified->fresh()->email_verified_at->toISOString());
    }
}

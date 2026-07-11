<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AdminSeederSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_seeder_does_not_create_a_default_account_without_credentials(): void
    {
        config()->set('chatme.admin.email');
        config()->set('chatme.admin.password');

        $this->seed(AdminSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_admin_seeder_uses_environment_backed_credentials(): void
    {
        $plan = Plan::create([
            'name' => 'Free',
            'slug' => 'free',
            'price' => 0,
        ]);

        config()->set('chatme.admin', [
            'name' => 'Test Administrator',
            'email' => 'administrator@example.test',
            'password' => 'test-only-password',
        ]);

        $this->seed(AdminSeeder::class);

        $admin = User::where('email', 'administrator@example.test')->firstOrFail();

        $this->assertSame('Test Administrator', $admin->name);
        $this->assertTrue($admin->is_admin);
        $this->assertSame('primary_admin', $admin->system_role);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('test-only-password', $admin->password));
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $admin->id,
            'plan_id' => $plan->id,
        ]);
    }

    public function test_admin_seeder_requires_an_explicit_name(): void
    {
        config()->set('chatme.admin', [
            'name' => null,
            'email' => 'administrator@example.test',
            'password' => 'test-only-password',
        ]);

        $this->seed(AdminSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_preclaimed_admin_email_with_an_active_session_is_never_promoted_or_reset(): void
    {
        $plan = Plan::create([
            'name' => 'Free',
            'slug' => 'free',
            'price' => 0,
        ]);
        $preclaim = User::factory()->create([
            'email' => 'administrator@example.test',
            'password' => 'preclaim-password',
            'is_admin' => false,
        ]);
        DB::table('sessions')->insert([
            'id' => 'preclaim-session',
            'user_id' => $preclaim->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'active-session-marker',
            'last_activity' => now()->getTimestamp(),
        ]);
        $this->configureAdmin();

        $this->assertAdminSeederFails('preclaimed');

        $preclaim->refresh();
        $this->assertFalse($preclaim->is_admin);
        $this->assertNull($preclaim->system_role);
        $this->assertTrue(Hash::check('preclaim-password', $preclaim->password));
        $this->assertDatabaseHas('sessions', [
            'id' => 'preclaim-session',
            'user_id' => $preclaim->id,
        ]);
        $this->assertDatabaseMissing('subscriptions', [
            'user_id' => $preclaim->id,
            'plan_id' => $plan->id,
        ]);
    }

    public function test_trusted_admin_rerun_keeps_the_same_password_and_user_id(): void
    {
        Plan::create([
            'name' => 'Free',
            'slug' => 'free',
            'price' => 0,
        ]);
        $this->configureAdmin();
        $this->seed(AdminSeeder::class);
        $admin = User::query()->where('system_role', 'primary_admin')->firstOrFail();
        $originalPassword = $admin->password;

        config()->set('chatme.admin.name', 'Renamed Administrator');
        config()->set('chatme.admin.password', 'must-not-reset-password');
        $this->seed(AdminSeeder::class);

        $admin->refresh();
        $this->assertSame('Renamed Administrator', $admin->name);
        $this->assertSame($originalPassword, $admin->password);
        $this->assertTrue(Hash::check('test-only-password', $admin->password));
        $this->assertFalse(Hash::check('must-not-reset-password', $admin->password));
        $this->assertSame(1, User::query()->where('system_role', 'primary_admin')->count());
    }

    public function test_primary_admin_role_with_a_different_email_fails_closed(): void
    {
        $existing = User::factory()->create([
            'email' => 'different-administrator@example.test',
            'is_admin' => true,
        ]);
        $existing->forceFill(['system_role' => 'primary_admin'])->save();
        $originalPassword = $existing->password;
        $this->configureAdmin();

        $this->assertAdminSeederFails('conflicts');

        $existing->refresh();
        $this->assertSame('different-administrator@example.test', $existing->email);
        $this->assertSame($originalPassword, $existing->password);
        $this->assertSame('primary_admin', $existing->system_role);
        $this->assertDatabaseMissing('users', ['email' => 'administrator@example.test']);
    }

    private function configureAdmin(): void
    {
        config()->set('chatme.admin', [
            'name' => 'Test Administrator',
            'email' => 'administrator@example.test',
            'password' => 'test-only-password',
        ]);
    }

    private function assertAdminSeederFails(string $messageFragment): void
    {
        $caught = null;

        try {
            $this->seed(AdminSeeder::class);
        } catch (RuntimeException $exception) {
            $caught = $exception;
        }

        $this->assertNotNull($caught, 'Administrator seeding should have failed closed.');
        $this->assertStringContainsString($messageFragment, $caught->getMessage());
    }
}

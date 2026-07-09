<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
}

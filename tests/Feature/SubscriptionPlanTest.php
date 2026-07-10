<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SubscriptionPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_plan_seeder_is_idempotent_and_keeps_exact_canonical_plans(): void
    {
        $this->seed(PlanSeeder::class);
        $this->seed(PlanSeeder::class);

        $this->assertDatabaseCount('plans', 3);
        $this->assertDatabaseHas('plans', [
            'slug' => 'free',
            'price' => 0,
            'chatbot_limit' => 1,
            'knowledge_limit' => 50,
            'monthly_messages' => 500,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('plans', [
            'slug' => 'pro',
            'price' => 49,
            'chatbot_limit' => 5,
            'knowledge_limit' => 500,
            'monthly_messages' => 10000,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('plans', [
            'slug' => 'enterprise',
            'price' => 149,
            'chatbot_limit' => -1,
            'knowledge_limit' => -1,
            'monthly_messages' => -1,
            'is_active' => true,
        ]);
    }

    public function test_public_and_authenticated_pricing_hide_legacy_and_unknown_plans(): void
    {
        $this->seed(PlanSeeder::class);
        Plan::create([
            'name' => 'Lifetime',
            'slug' => 'lifetime',
            'price' => 0,
            'is_active' => true,
        ]);
        Plan::create([
            'name' => 'Custom Paid',
            'slug' => 'custom-paid',
            'price' => 25,
            'is_active' => true,
        ]);

        $this->get('/')->assertOk()
            ->assertSeeText('Free')
            ->assertSeeText('Pro')
            ->assertSeeText('Enterprise')
            ->assertDontSeeText('Lifetime')
            ->assertDontSeeText('Custom Paid');

        $this->get('/pricing')->assertOk()
            ->assertSeeText('Free')
            ->assertSeeText('Pro')
            ->assertSeeText('Enterprise')
            ->assertDontSeeText('Lifetime')
            ->assertDontSeeText('Custom Paid');

        $user = User::factory()->create();
        $this->actingAs($user)->get('/')->assertOk()
            ->assertDontSeeText('Lifetime')
            ->assertDontSeeText('Custom Paid');

        $this->actingAs($user)->get(route('subscription.plans'))->assertOk()
            ->assertSeeText('Free')
            ->assertSeeText('Pro')
            ->assertSeeText('Enterprise')
            ->assertDontSeeText('Lifetime')
            ->assertDontSeeText('Custom Paid');
    }

    public function test_unlimited_limits_and_checkout_copy_are_rendered_correctly(): void
    {
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('subscription.plans'));

        $response->assertOk()
            ->assertSeeText('Tanpa had chatbot')
            ->assertSeeText('Tanpa had pengetahuan')
            ->assertSeeText('Tanpa had mesej')
            ->assertSeeText('Nombor telefon mudah alih')
            ->assertSeeText('FPX / DuitNow QR')
            ->assertSeeText('bukan potongan automatik')
            ->assertDontSeeText('-1 chatbot')
            ->assertDontSeeText('-1 item pengetahuan')
            ->assertDontSeeText('-1 mesej');
    }

    public function test_paid_plan_can_be_renewed_and_free_never_posts_to_payment(): void
    {
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();
        $pro = Plan::where('slug', 'pro')->firstOrFail();
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $pro->id,
            'provider' => 'toyyibpay',
            'provider_reference' => 'TP-CURRENT',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $response = $this->actingAs($user)->get(route('subscription.plans'));

        $response->assertOk()
            ->assertSeeText('Pelan semasa')
            ->assertSeeText('Perbaharui sebulan')
            ->assertSeeText('Free akan kembali selepas akses berbayar tamat');

        $html = $response->getContent();
        $this->assertSame(2, preg_match_all('/action="[^"]*\/subscription\/\d+\/checkout"/', $html));
        $this->assertStringNotContainsString('subscription.subscribe', $html);
    }

    public function test_phone_error_is_associated_only_with_the_submitted_plan_form(): void
    {
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();
        $pro = Plan::where('slug', 'pro')->firstOrFail();

        $this->actingAs($user)
            ->from(route('subscription.plans'))
            ->post(route('subscription.checkout', $pro), [
                'checkout_plan' => $pro->id,
                'phone' => '123',
            ])
            ->assertRedirect(route('subscription.plans'))
            ->assertSessionHasErrors('phone');

        $html = $this->get(route('subscription.plans'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'Masukkan nombor telefon mudah alih Malaysia yang sah.'));
        $this->assertSame(1, substr_count($html, 'value="123"'));
    }

    public function test_legacy_billing_routes_and_executable_stripe_code_are_removed(): void
    {
        $this->assertFalse(Route::has('subscription.subscribe'));
        $this->assertFalse(Route::has('subscription.success'));
        $this->assertFileDoesNotExist(app_path('Http/Controllers/WebhookController.php'));
        $this->assertFileDoesNotExist(resource_path('views/subscription/success.blade.php'));

        $controller = file_get_contents(app_path('Http/Controllers/SubscriptionController.php'));
        $this->assertStringNotContainsString('Stripe', $controller);
        $this->assertStringNotContainsString('Cashier', $controller);
        $this->assertStringNotContainsString('newSubscription', $controller);
    }

    public function test_lifetime_data_migration_is_reversible_without_touching_other_plans(): void
    {
        $lifetime = Plan::create([
            'name' => 'Lifetime',
            'slug' => 'lifetime',
            'price' => 0,
            'is_active' => true,
        ]);
        $pro = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 49,
            'is_active' => true,
        ]);
        $migration = require database_path('migrations/2026_07_10_000003_deactivate_legacy_lifetime_plan.php');

        $migration->up();
        $this->assertFalse($lifetime->fresh()->is_active);
        $this->assertTrue($pro->fresh()->is_active);

        $migration->down();
        $this->assertTrue($lifetime->fresh()->is_active);
        $this->assertTrue($pro->fresh()->is_active);
    }

    public function test_admin_seeder_creates_a_system_free_entitlement(): void
    {
        $this->seed(PlanSeeder::class);
        config()->set('chatme.admin', [
            'name' => 'Test Administrator',
            'email' => 'administrator@example.test',
            'password' => 'test-only-password',
        ]);
        Carbon::setTestNow('2026-07-10 12:00:00');

        $this->seed(AdminSeeder::class);

        $admin = User::where('email', 'administrator@example.test')->firstOrFail();
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $admin->id,
            'plan_id' => Plan::where('slug', 'free')->value('id'),
            'provider' => 'system',
            'status' => 'active',
            'starts_at' => '2026-07-10 12:00:00',
            'ends_at' => null,
        ]);
        Carbon::setTestNow();
    }
}

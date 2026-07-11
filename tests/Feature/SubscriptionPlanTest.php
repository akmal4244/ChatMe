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
use Illuminate\Support\Str;
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

    public function test_inactive_grandfathered_lifetime_entitlement_remains_usable_but_hidden_from_sale(): void
    {
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();
        $lifetime = Plan::create([
            'name' => 'Lifetime',
            'slug' => 'lifetime',
            'price' => 0,
            'chatbot_limit' => -1,
            'knowledge_limit' => -1,
            'monthly_messages' => -1,
            'is_active' => false,
        ]);
        $entitlement = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $lifetime->id,
            'provider' => 'legacy_lifetime',
            'status' => 'active',
            'starts_at' => now()->subYear(),
            'ends_at' => null,
        ]);

        $this->assertSame($entitlement->id, $user->activeSubscription()->id);
        $this->assertSame($lifetime->id, $user->currentPlan()->id);
        $this->assertTrue($user->canCreateChatbot());

        $this->actingAs($user)->get(route('subscription.plans'))->assertOk()
            ->assertDontSee('id="plan-'.$lifetime->id.'-name"', false)
            ->assertSeeText('Akses Lifetime lama anda kekal sebagai pelan sandaran.');

        $pro = Plan::where('slug', 'pro')->firstOrFail();
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $pro->id,
            'provider' => 'toyyibpay',
            'provider_reference' => 'TP-GRANDFATHERED-BACKUP',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->actingAs($user)->get(route('subscription.plans'))->assertOk()
            ->assertSeeText('Akses Lifetime lama anda kekal sebagai pelan sandaran.')
            ->assertDontSeeText('Akaun anda akan kembali kepada pelan Free selepas akses berbayar tamat.');
    }

    public function test_unlimited_limits_and_checkout_copy_are_rendered_correctly(): void
    {
        config()->set('services.toyyibpay.dnqr_enabled', true);
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('subscription.plans'));

        $response->assertOk()
            ->assertSeeText('Tanpa had chatbot')
            ->assertSeeText('Soal jawab tanpa had')
            ->assertSeeText('Tanpa had mesej')
            ->assertSeeText('Nombor telefon mudah alih')
            ->assertSeeText('FPX / DuitNow QR')
            ->assertSeeText('Pembaharuan dibuat secara manual setiap bulan; tiada potongan automatik daripada akaun bank.')
            ->assertDontSeeText('-1 chatbot')
            ->assertDontSeeText('-1 soal jawab')
            ->assertDontSeeText('-1 mesej');
    }

    public function test_payment_channel_copy_matches_the_dnqr_capability_flag(): void
    {
        config()->set('services.toyyibpay.dnqr_enabled', false);
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();

        $this->get('/')->assertOk()
            ->assertSeeText('melalui FPX')
            ->assertDontSeeText('DuitNow QR');

        $this->actingAs($user)->get(route('subscription.plans'))->assertOk()
            ->assertSeeText('melalui FPX')
            ->assertSeeText('Langgan melalui FPX')
            ->assertDontSeeText('DuitNow QR');
        $this->get('/terms')->assertOk()->assertDontSeeText('DuitNow QR');
        $this->get('/privacy')->assertOk()->assertDontSeeText('DuitNow QR');

        config()->set('services.toyyibpay.dnqr_enabled', true);

        $this->get('/')->assertOk()->assertSeeText('FPX / DuitNow QR');
        $this->get(route('subscription.plans'))->assertOk()->assertSeeText('FPX / DuitNow QR');
        $this->get('/terms')->assertOk()->assertSeeText('DuitNow QR');
        $this->get('/privacy')->assertOk()->assertSeeText('DuitNow QR');
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
            ->assertSeeText('Perbaharui untuk sebulan')
            ->assertSeeText('Nilai bagi baki tempoh pelan semasa akan digunakan sebagai kredit untuk pelan baharu.')
            ->assertSeeText('Akaun anda akan kembali kepada pelan Free selepas akses berbayar tamat.');

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
                'checkout_key' => (string) Str::uuid(),
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

    public function test_lifetime_data_migration_rollback_does_not_reactivate_data(): void
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
        $this->assertFalse($lifetime->fresh()->is_active);
        $this->assertTrue($pro->fresh()->is_active);
    }

    public function test_free_fallback_must_be_active_and_zero_priced(): void
    {
        $user = User::factory()->create();
        Plan::create([
            'name' => 'Disabled Free',
            'slug' => 'free',
            'price' => 0,
            'is_active' => false,
        ]);

        $this->assertNull($user->currentPlan());

        Plan::where('slug', 'free')->update([
            'price' => 10,
            'is_active' => true,
        ]);

        $this->assertNull($user->fresh()->currentPlan());
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

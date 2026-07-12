<?php

namespace Tests\Feature;

use App\Models\Chatbot;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanEntitlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_widget_branding_is_removed_only_for_an_active_enterprise_plan(): void
    {
        $free = $this->chatbotForPlan('free');
        $pro = $this->chatbotForPlan('pro');
        $enterprise = $this->chatbotForPlan('enterprise');
        $expiredEnterprise = $this->chatbotForPlan('enterprise', expired: true);

        $this->get(route('widget.script', $free->api_key).'?showBranding=false')
            ->assertOk()
            ->assertSee('"showBranding":true', false);

        $this->get(route('widget.script', $pro->api_key))
            ->assertOk()
            ->assertSee('"showBranding":true', false);

        $enterpriseResponse = $this->get(route('widget.script', $enterprise->api_key))
            ->assertOk()
            ->assertSee('"showBranding":false', false);

        $this->assertStringContainsString(
            'no-store',
            (string) $enterpriseResponse->headers->get('Cache-Control'),
        );

        $this->get(route('widget.script', $expiredEnterprise->api_key))
            ->assertOk()
            ->assertSee('"showBranding":true', false);
    }

    public function test_plan_seeder_keeps_the_exact_branding_and_api_entitlements(): void
    {
        $this->assertFalse(Plan::where('slug', 'free')->firstOrFail()->remove_branding);
        $this->assertFalse(Plan::where('slug', 'free')->firstOrFail()->api_access);
        $this->assertFalse(Plan::where('slug', 'pro')->firstOrFail()->remove_branding);
        $this->assertTrue(Plan::where('slug', 'pro')->firstOrFail()->api_access);
        $this->assertTrue(Plan::where('slug', 'enterprise')->firstOrFail()->remove_branding);
        $this->assertTrue(Plan::where('slug', 'enterprise')->firstOrFail()->api_access);
    }

    private function chatbotForPlan(string $slug, bool $expired = false): Chatbot
    {
        $user = User::factory()->create();
        $plan = Plan::where('slug', $slug)->firstOrFail();

        if ($slug !== 'free') {
            $user->subscriptions()->create([
                'plan_id' => $plan->id,
                'provider' => 'system',
                'status' => 'active',
                'starts_at' => now()->subMonth(),
                'ends_at' => $expired ? now()->subMinute() : now()->addMonth(),
            ]);
        }

        return Chatbot::create([
            'user_id' => $user->id,
            'name' => ucfirst($slug).' Branding Bot',
        ]);
    }
}

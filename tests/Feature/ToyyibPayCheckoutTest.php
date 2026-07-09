<?php

namespace Tests\Feature;

use App\Http\Controllers\SubscriptionController;
use App\Models\PaymentOrder;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ToyyibPayCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'https://chatme.test');
        config()->set('services.toyyibpay', [
            'base_url' => 'https://dev.toyyibpay.test',
            'secret_key' => 'checkout-test-secret',
            'category_code' => 'CHECKOUT1',
            'dnqr_enabled' => true,
            'timeout' => 10,
        ]);
    }

    public function test_guest_cannot_start_checkout(): void
    {
        $plan = $this->plan('pro', '49.00');
        Http::fake();

        $this->post("/subscription/{$plan->id}/checkout", [
            'phone' => '0123456789',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('payment_orders', 0);
        Http::assertNothingSent();
    }

    public function test_valid_checkout_creates_order_before_provider_call_and_redirects_to_configured_bill(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('pro', '49.99');
        $observedCreatingOrder = false;
        Http::fake(function ($request) use ($user, $plan, &$observedCreatingOrder) {
            $data = $request->data();
            $order = PaymentOrder::where('external_reference', $data['billExternalReferenceNo'])->first();

            $this->assertNotNull($order);
            $this->assertSame(PaymentOrder::STATUS_CREATING, $order->status);
            $this->assertSame($user->id, $order->user_id);
            $this->assertSame($plan->id, $order->plan_id);
            $this->assertSame(4999, $order->amount_cents);
            $this->assertSame(4999, $data['billAmount']);
            $this->assertSame(
                'https://chatme.test/subscription/orders/'.$order->external_reference.'/return',
                $data['billReturnUrl'],
            );
            $this->assertSame(
                'https://chatme.test/payments/toyyibpay/callback',
                $data['billCallbackUrl'],
            );
            $observedCreatingOrder = true;

            return Http::response([['BillCode' => 'CHECKOUTBILL1']]);
        });

        $this->actingAs($user)
            ->post("/subscription/{$plan->id}/checkout", [
                'phone' => '+60 12-345 6789',
                'amount_cents' => 1,
                'status' => 'paid',
                'bill_code' => 'ATTACKER',
            ])
            ->assertRedirect('https://dev.toyyibpay.test/CHECKOUTBILL1')
            ->assertSessionHasNoErrors();

        $this->assertTrue($observedCreatingOrder);
        $this->assertDatabaseHas('payment_orders', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'amount_cents' => 4999,
            'bill_code' => 'CHECKOUTBILL1',
            'status' => PaymentOrder::STATUS_PENDING,
            'transaction_reference' => null,
            'subscription_id' => null,
        ]);
        Http::assertSentCount(1);
    }

    public function test_enterprise_checkout_uses_its_server_price(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('enterprise', '149.00');
        Http::fake(['*' => Http::response([['BillCode' => 'ENTERPRISE1']])]);

        $this->actingAs($user)
            ->post("/subscription/{$plan->id}/checkout", [
                'phone' => '01123456789',
                'amount_cents' => 49,
            ])
            ->assertRedirect('https://dev.toyyibpay.test/ENTERPRISE1');

        $this->assertDatabaseHas('payment_orders', [
            'plan_id' => $plan->id,
            'amount_cents' => 14900,
            'status' => PaymentOrder::STATUS_PENDING,
        ]);
    }

    public function test_free_inactive_unknown_and_non_positive_plans_are_rejected_before_order_creation(): void
    {
        $user = User::factory()->create();
        $plans = [
            $this->plan('free', '0.00'),
            $this->plan('pro', '49.00', false),
            $this->plan('lifetime', '0.00'),
            $this->plan('custom-paid', '25.00'),
        ];
        Http::fake();

        foreach ($plans as $plan) {
            $this->actingAs($user)
                ->post("/subscription/{$plan->id}/checkout", ['phone' => '0123456789'])
                ->assertSessionHasErrors('plan');
        }

        $this->assertDatabaseCount('payment_orders', 0);
        Http::assertNothingSent();
    }

    public function test_invalid_phone_is_rejected_without_an_order_or_provider_call(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('pro', '49.00');
        Http::fake();

        $this->actingAs($user)
            ->post("/subscription/{$plan->id}/checkout", ['phone' => '0151234567'])
            ->assertSessionHasErrors('phone');

        $this->assertDatabaseCount('payment_orders', 0);
        Http::assertNothingSent();
    }

    public function test_missing_provider_configuration_marks_the_attempt_failed_with_a_safe_reason(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('pro', '49.00');
        config()->set('services.toyyibpay.secret_key', null);
        Http::fake();

        $this->actingAs($user)
            ->from(route('subscription.plans'))
            ->post("/subscription/{$plan->id}/checkout", ['phone' => '0123456789'])
            ->assertRedirect(route('subscription.plans'))
            ->assertSessionHasErrors('payment');

        $this->assertDatabaseHas('payment_orders', [
            'user_id' => $user->id,
            'status' => PaymentOrder::STATUS_FAILED,
            'failure_reason' => 'configuration_error',
            'bill_code' => null,
            'subscription_id' => null,
        ]);
        Http::assertNothingSent();
    }

    public function test_provider_http_failure_is_not_retried_and_never_grants_access(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('pro', '49.00');
        Http::fake(['*' => Http::response('provider secret body', 500)]);

        $this->actingAs($user)
            ->from(route('subscription.plans'))
            ->post("/subscription/{$plan->id}/checkout", ['phone' => '0123456789'])
            ->assertRedirect(route('subscription.plans'))
            ->assertSessionHasErrors('payment');

        $this->assertDatabaseHas('payment_orders', [
            'user_id' => $user->id,
            'status' => PaymentOrder::STATUS_FAILED,
            'failure_reason' => 'http_error',
            'subscription_id' => null,
        ]);
        $this->assertDatabaseCount('subscriptions', 0);
        Http::assertSentCount(1);
    }

    public function test_local_bill_save_failure_preserves_the_bill_code_and_logs_no_sensitive_message(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('pro', '49.00');
        $failOnce = true;
        PaymentOrder::updating(function (PaymentOrder $order) use (&$failOnce): void {
            if ($failOnce && $order->status === PaymentOrder::STATUS_PENDING) {
                $failOnce = false;
                throw new RuntimeException('sensitive local failure checkout-test-secret');
            }
        });
        Http::fake(['*' => Http::response([['BillCode' => 'RECOVERABLE1']])]);
        Log::spy();

        $this->actingAs($user)
            ->from(route('subscription.plans'))
            ->post("/subscription/{$plan->id}/checkout", ['phone' => '0123456789'])
            ->assertRedirect(route('subscription.plans'))
            ->assertSessionHasErrors('payment');

        $this->assertDatabaseHas('payment_orders', [
            'user_id' => $user->id,
            'bill_code' => 'RECOVERABLE1',
            'status' => PaymentOrder::STATUS_FAILED,
            'failure_reason' => 'internal_error',
            'subscription_id' => null,
        ]);
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $serialized = $message.json_encode($context);

                return ! str_contains($serialized, 'checkout-test-secret')
                    && ! str_contains($serialized, 'sensitive local failure')
                    && $context['reason'] === 'internal_error';
            });
    }

    public function test_legacy_cashier_controller_methods_are_removed(): void
    {
        foreach (['subscribe', 'billingPortal', 'cancel', 'resume', 'manage'] as $method) {
            $this->assertFalse(method_exists(SubscriptionController::class, $method));
        }
    }

    private function plan(string $slug, string $price, bool $active = true): Plan
    {
        return Plan::create([
            'name' => Str::headline($slug),
            'slug' => $slug,
            'price' => $price,
            'is_active' => $active,
        ]);
    }
}

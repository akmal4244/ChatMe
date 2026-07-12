<?php

namespace Tests\Feature;

use App\Models\PaymentOrder;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\PaymentActivationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;
use Throwable;

class PaymentActivationTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-01-31 10:15:00');
        Carbon::setTestNow($this->now);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_payment_order_generates_an_opaque_route_uuid_and_ignores_mass_assigned_references(): void
    {
        $user = User::factory()->create();
        $plan = $this->paidPlan();
        $suppliedReference = (string) Str::uuid();

        $first = PaymentOrder::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'external_reference' => $suppliedReference,
            'amount_cents' => $plan->priceInCents(),
            'status' => PaymentOrder::STATUS_CREATING,
        ]);
        $second = $this->paymentOrder($user, $plan);

        $this->assertTrue(Str::isUuid($first->external_reference));
        $this->assertNotSame($suppliedReference, $first->external_reference);
        $this->assertNotSame($first->external_reference, $second->external_reference);
        $this->assertSame('external_reference', $first->getRouteKeyName());
        $this->assertSame($user->id, $first->user->id);
        $this->assertSame($plan->id, $first->plan->id);
        $this->assertIsInt($first->amount_cents);
    }

    public function test_plan_price_is_converted_to_cents_without_floating_point_arithmetic(): void
    {
        $this->assertSame(29, $this->paidPlan('micro', '0.29')->priceInCents());
        $this->assertSame(4999, $this->paidPlan('pro-precision', '49.99')->priceInCents());
        $this->assertSame(14900, $this->paidPlan('enterprise-precision', '149.00')->priceInCents());
        $this->assertSame(9999999999, $this->paidPlan('large-precision', '99999999.99')->priceInCents());
    }

    public function test_month_end_activation_uses_calendar_months_without_overflow(): void
    {
        $scenarios = [
            ['2024-01-31 10:15:00', '2024-02-29 10:15:00'],
            ['2024-03-31 10:15:00', '2024-04-30 10:15:00'],
            ['2024-12-31 10:15:00', '2025-01-31 10:15:00'],
        ];

        foreach ($scenarios as $index => [$activatedAt, $expectedEnd]) {
            Carbon::setTestNow(CarbonImmutable::parse($activatedAt));
            $user = User::factory()->create();
            $plan = $this->paidPlan('boundary-'.$index, '49.00');
            $order = $this->paymentOrder($user, $plan);

            $subscription = $this->activationService()->activate($order, 'TXN-BOUNDARY-'.$index);

            $this->assertTrue(
                $subscription->ends_at->equalTo(CarbonImmutable::parse($expectedEnd)),
                "Expected {$activatedAt} to renew until {$expectedEnd}.",
            );
        }

        Carbon::setTestNow($this->now);
    }

    public function test_first_verified_payment_creates_and_atomically_links_a_one_month_entitlement(): void
    {
        $user = User::factory()->create();
        $plan = $this->paidPlan();
        $order = $this->paymentOrder($user, $plan, PaymentOrder::STATUS_FAILED);
        $providerPaidAt = $this->now->subDay();

        $subscription = $this->activationService()->activate($order, 'TXN-FIRST', $providerPaidAt);

        $this->assertSame($user->id, $subscription->user_id);
        $this->assertSame($plan->id, $subscription->plan_id);
        $this->assertSame('toyyibpay', $subscription->provider);
        $this->assertSame('TXN-FIRST', $subscription->provider_reference);
        $this->assertSame('active', $subscription->status);
        $this->assertTrue($subscription->starts_at->equalTo($this->now));
        $this->assertTrue($subscription->ends_at->equalTo(CarbonImmutable::parse('2026-02-28 10:15:00')));

        $order->refresh();
        $this->assertSame(PaymentOrder::STATUS_PAID, $order->status);
        $this->assertSame('TXN-FIRST', $order->transaction_reference);
        $this->assertSame($subscription->id, $order->subscription_id);
        $this->assertTrue($order->paid_at->equalTo($providerPaidAt));
        $this->assertSame($subscription->id, $order->subscription->id);
    }

    public function test_repeating_a_paid_order_with_a_different_reference_is_a_no_op(): void
    {
        $user = User::factory()->create();
        $plan = $this->paidPlan();
        $order = $this->paymentOrder($user, $plan);

        $first = $this->activationService()->activate($order, 'TXN-ORIGINAL');
        $second = $this->activationService()->activate($order->fresh(), 'TXN-DIFFERENT');

        $this->assertSame($first->id, $second->id);
        $this->assertTrue($second->ends_at->equalTo(CarbonImmutable::parse('2026-02-28 10:15:00')));
        $this->assertSame('TXN-ORIGINAL', $second->provider_reference);
        $this->assertSame('TXN-ORIGINAL', $order->fresh()->transaction_reference);
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_a_paid_order_without_its_entitlement_link_fails_closed(): void
    {
        $user = User::factory()->create();
        $plan = $this->paidPlan();
        $order = $this->paymentOrder($user, $plan);
        $order->forceFill([
            'status' => PaymentOrder::STATUS_PAID,
            'transaction_reference' => 'TXN-ORPHANED',
            'paid_at' => $this->now,
        ])->save();

        $this->expectException(LogicException::class);

        $this->activationService()->activate($order, 'TXN-IGNORED');
    }

    public function test_two_distinct_orders_for_the_same_plan_extend_the_same_term_in_serialized_months(): void
    {
        $user = User::factory()->create();
        $plan = $this->paidPlan();
        $firstOrder = $this->paymentOrder($user, $plan);
        $secondOrder = $this->paymentOrder($user, $plan);

        $first = $this->activationService()->activate($firstOrder, 'TXN-MONTH-ONE');
        $second = $this->activationService()->activate($secondOrder, 'TXN-MONTH-TWO');

        $this->assertSame($first->id, $second->id);
        $this->assertTrue($second->ends_at->equalTo(CarbonImmutable::parse('2026-03-28 10:15:00')));
        $this->assertSame($second->id, $firstOrder->fresh()->subscription_id);
        $this->assertSame($second->id, $secondOrder->fresh()->subscription_id);
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_switching_paid_plans_prorates_the_remaining_value_at_integer_precision(): void
    {
        $user = User::factory()->create();
        $free = Plan::create([
            'name' => 'Free',
            'slug' => 'free',
            'price' => '0.00',
        ]);
        $systemEntitlement = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $free->id,
            'provider' => 'system',
            'status' => 'active',
            'starts_at' => $this->now->subYear(),
            'ends_at' => null,
        ]);
        $pro = $this->paidPlan('pro', '49.00');
        $enterprise = $this->paidPlan('enterprise', '149.00');

        $oldPaid = $this->activationService()->activate(
            $this->paymentOrder($user, $pro),
            'TXN-PRO'
        );
        $remainingSeconds = $oldPaid->ends_at->getTimestamp() - $this->now->getTimestamp();
        $creditSeconds = intdiv(
            $remainingSeconds * $pro->priceInCents(),
            $enterprise->priceInCents(),
        );
        $newPaid = $this->activationService()->activate(
            $this->paymentOrder($user, $enterprise),
            'TXN-ENTERPRISE'
        );

        $this->assertSame('expired', $oldPaid->fresh()->status);
        $this->assertTrue($oldPaid->fresh()->ends_at->equalTo($this->now));
        $this->assertSame('active', $newPaid->status);
        $this->assertTrue($newPaid->starts_at->equalTo($this->now));
        $this->assertTrue($newPaid->ends_at->equalTo(
            $this->now->addMonthNoOverflow()->addSeconds($creditSeconds),
        ));
        $this->assertTrue($newPaid->ends_at->lt(CarbonImmutable::parse('2026-03-28 10:15:00')));
        $this->assertSame('active', $systemEntitlement->fresh()->status);
        $this->assertNull($systemEntitlement->fresh()->ends_at);
        $this->assertSame($newPaid->id, $user->fresh()->activeSubscription()->id);
    }

    public function test_downgrading_converts_enterprise_value_into_the_equivalent_pro_time(): void
    {
        $user = User::factory()->create();
        $enterprise = $this->paidPlan('enterprise-value', '149.00');
        $pro = $this->paidPlan('pro-value', '49.00');
        $enterpriseTerm = $this->activationService()->activate(
            $this->paymentOrder($user, $enterprise),
            'TXN-ENTERPRISE-VALUE',
        );
        $remainingSeconds = $enterpriseTerm->ends_at->getTimestamp() - $this->now->getTimestamp();
        $creditSeconds = intdiv(
            $remainingSeconds * $enterprise->priceInCents(),
            $pro->priceInCents(),
        );

        $proTerm = $this->activationService()->activate(
            $this->paymentOrder($user, $pro),
            'TXN-PRO-VALUE',
        );

        $this->assertSame('expired', $enterpriseTerm->fresh()->status);
        $this->assertTrue($proTerm->ends_at->equalTo(
            $this->now->addMonthNoOverflow()->addSeconds($creditSeconds),
        ));
    }

    public function test_proration_uses_the_price_paid_snapshot_after_catalog_price_changes(): void
    {
        $user = User::factory()->create();
        $enterprise = $this->paidPlan('enterprise-snapshot', '149.00');
        $pro = $this->paidPlan('pro-snapshot', '49.00');
        $enterpriseTerm = $this->activationService()->activate(
            $this->paymentOrder($user, $enterprise),
            'TXN-ENTERPRISE-SNAPSHOT',
        );

        $switchAt = $this->now->addDays(14);
        Carbon::setTestNow($switchAt);
        $remainingSeconds = $enterpriseTerm->ends_at->getTimestamp() - $switchAt->getTimestamp();
        $expectedCreditSeconds = intdiv($remainingSeconds * 14900, 4900);
        $enterprise->update(['price' => '999.00']);

        $proTerm = $this->activationService()->activate(
            $this->paymentOrder($user, $pro),
            'TXN-PRO-SNAPSHOT',
        );

        $this->assertTrue($proTerm->ends_at->equalTo(
            $switchAt->addMonthNoOverflow()->addSeconds($expectedCreditSeconds),
        ));
        $this->assertTrue($proTerm->ends_at->lt($switchAt->addMonthsNoOverflow(4)));
    }

    public function test_pending_and_failed_orders_grant_no_access_but_a_later_verified_success_can_activate(): void
    {
        $user = User::factory()->create();
        $plan = $this->paidPlan();
        $pending = $this->paymentOrder($user, $plan, PaymentOrder::STATUS_PENDING);
        $failed = $this->paymentOrder($user, $plan, PaymentOrder::STATUS_FAILED);

        $this->assertNull($pending->subscription_id);
        $this->assertNull($failed->subscription_id);
        $this->assertNull($user->activeSubscription());
        $this->assertDatabaseCount('subscriptions', 0);

        $subscription = $this->activationService()->activate($failed, 'TXN-RECOVERED');

        $this->assertSame(PaymentOrder::STATUS_PAID, $failed->fresh()->status);
        $this->assertSame($subscription->id, $failed->fresh()->subscription_id);
        $this->assertNull($pending->fresh()->subscription_id);
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_duplicate_transaction_reference_rolls_back_tentative_entitlement_extension(): void
    {
        $user = User::factory()->create();
        $plan = $this->paidPlan();
        $firstOrder = $this->paymentOrder($user, $plan);
        $secondOrder = $this->paymentOrder($user, $plan);
        $subscription = $this->activationService()->activate($firstOrder, 'TXN-UNIQUE');
        $originalEnd = $subscription->ends_at->toImmutable();
        $thrown = null;

        try {
            $this->activationService()->activate($secondOrder, 'TXN-UNIQUE');
        } catch (Throwable $exception) {
            $thrown = $exception;
        }

        $this->assertNotNull($thrown);
        $this->assertInstanceOf(QueryException::class, $thrown);
        $this->assertTrue($subscription->fresh()->ends_at->equalTo($originalEnd));
        $this->assertSame(PaymentOrder::STATUS_PENDING, $secondOrder->fresh()->status);
        $this->assertNull($secondOrder->fresh()->subscription_id);
    }

    public function test_active_subscription_uses_matching_boundaries_and_excludes_ineligible_terms(): void
    {
        $user = User::factory()->create();
        $plan = $this->paidPlan();
        $legacy = $this->subscription($user, $plan, [
            'status' => null,
            'starts_at' => null,
            'ends_at' => $this->now->addMonths(3),
        ]);
        $future = $this->subscription($user, $plan, [
            'status' => 'active',
            'starts_at' => $this->now->addSecond(),
            'ends_at' => $this->now->addMonths(3),
        ]);
        $expiredAtBoundary = $this->subscription($user, $plan, [
            'status' => 'active',
            'starts_at' => $this->now->subMonth(),
            'ends_at' => $this->now,
        ]);
        $cancelled = $this->subscription($user, $plan, [
            'status' => 'cancelled',
            'starts_at' => $this->now->subMonth(),
            'ends_at' => $this->now->addMonth(),
        ]);
        $current = $this->subscription($user, $plan, [
            'status' => 'active',
            'starts_at' => $this->now,
            'ends_at' => $this->now->addMonth(),
        ]);

        $this->assertFalse($legacy->isActive());
        $this->assertFalse($future->isActive());
        $this->assertFalse($expiredAtBoundary->isActive());
        $this->assertFalse($cancelled->isActive());
        $this->assertTrue($current->isActive());
        $this->assertSame($current->id, $user->activeSubscription()->id);

        $current->delete();

        $this->assertNull($user->fresh()->activeSubscription());
    }

    public function test_active_subscription_breaks_equal_dates_by_newest_row_id(): void
    {
        $user = User::factory()->create();
        $plan = $this->paidPlan();
        $attributes = [
            'status' => 'active',
            'starts_at' => $this->now->subDay(),
            'ends_at' => $this->now->addMonth(),
            'created_at' => $this->now->subHour(),
            'updated_at' => $this->now->subHour(),
        ];
        $first = $this->subscription($user, $plan, $attributes);
        $second = $this->subscription($user, $plan, $attributes);

        $this->assertGreaterThan($first->id, $second->id);
        $this->assertSame($second->id, $user->activeSubscription()->id);
    }

    public function test_active_paid_plan_wins_over_a_newer_system_free_entitlement_until_it_expires(): void
    {
        $user = User::factory()->create();
        $paidPlan = $this->paidPlan('pro-priority', '49.00');
        $paid = $this->activationService()->activate(
            $this->paymentOrder($user, $paidPlan),
            'TXN-PAID-PRIORITY',
        );
        $freePlan = Plan::create([
            'name' => 'Free',
            'slug' => 'free-priority',
            'price' => '0.00',
        ]);
        $free = $this->subscription($user, $freePlan, [
            'provider' => 'system',
            'status' => 'active',
            'starts_at' => $this->now,
            'ends_at' => null,
        ]);

        $this->assertGreaterThan($paid->id, $free->id);
        $this->assertSame($paid->id, $user->fresh()->activeSubscription()->id);
        $this->assertSame($paidPlan->id, $user->fresh()->currentPlan()->id);

        Carbon::setTestNow($paid->ends_at);

        $this->assertSame($free->id, $user->fresh()->activeSubscription()->id);
        $this->assertSame($freePlan->id, $user->fresh()->currentPlan()->id);
        Carbon::setTestNow($this->now);
    }

    public function test_bill_codes_are_unique_when_present_but_multiple_nulls_are_allowed(): void
    {
        $user = User::factory()->create();
        $plan = $this->paidPlan();
        $this->paymentOrder($user, $plan, PaymentOrder::STATUS_PENDING, null);
        $this->paymentOrder($user, $plan, PaymentOrder::STATUS_PENDING, null);
        $this->paymentOrder($user, $plan, PaymentOrder::STATUS_PENDING, 'DUPLICATE-BILL');

        $this->expectException(QueryException::class);

        $this->paymentOrder($user, $plan, PaymentOrder::STATUS_PENDING, 'DUPLICATE-BILL');
    }

    private function activationService(): PaymentActivationService
    {
        return app(PaymentActivationService::class);
    }

    private function paidPlan(string $slug = 'pro', string $price = '49.00'): Plan
    {
        return Plan::create([
            'name' => Str::headline($slug),
            'slug' => $slug,
            'price' => $price,
        ]);
    }

    private function paymentOrder(
        User $user,
        Plan $plan,
        string $status = PaymentOrder::STATUS_PENDING,
        ?string $billCode = null,
    ): PaymentOrder {
        return PaymentOrder::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'bill_code' => func_num_args() >= 4 ? $billCode : 'BILL-'.Str::upper(Str::random(12)),
            'provider' => 'toyyibpay',
            'amount_cents' => $plan->priceInCents(),
            'status' => $status,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function subscription(User $user, Plan $plan, array $attributes = []): Subscription
    {
        return Subscription::create(array_merge([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ], $attributes));
    }
}

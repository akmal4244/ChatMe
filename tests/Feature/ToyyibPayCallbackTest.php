<?php

namespace Tests\Feature;

use App\Models\PaymentOrder;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class ToyyibPayCallbackTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'callback-test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.toyyibpay.secret_key', self::SECRET);
        Carbon::setTestNow('2026-07-10 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_invalid_hash_is_rejected_before_any_mutation_and_logs_no_payload_secret(): void
    {
        [$order] = $this->order();
        $payload = $this->payload($order, '1', 'TP-INVALID');
        $payload['hash'] = str_repeat('0', 32);
        $payload['reason'] = 'sensitive reason callback-test-secret';
        Log::spy();

        $this->post('/payments/toyyibpay/callback', $payload)
            ->assertStatus(400)
            ->assertSeeText('INVALID');

        $this->assertSame(PaymentOrder::STATUS_PENDING, $order->fresh()->status);
        $this->assertDatabaseCount('subscriptions', 0);
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context = []): bool {
                $serialized = $message.json_encode($context);

                return ! str_contains($serialized, self::SECRET)
                    && ! str_contains($serialized, 'sensitive reason');
            });
    }

    public function test_unknown_order_bill_mismatch_and_amount_mismatch_never_mutate(): void
    {
        [$order] = $this->order();
        $unknown = $this->payloadFor(
            (string) Str::uuid(),
            'UNKNOWN1',
            '49.00',
            '1',
            'TP-UNKNOWN',
        );

        $this->post('/payments/toyyibpay/callback', $unknown)->assertStatus(422);

        $wrongBill = $this->payload($order, '1', 'TP-WRONG-BILL');
        $wrongBill['billcode'] = 'OTHERBILL';
        $wrongBill['hash'] = $this->hash($wrongBill);
        $this->post('/payments/toyyibpay/callback', $wrongBill)->assertStatus(422);

        $wrongAmount = $this->payload($order, '1', 'TP-WRONG-AMOUNT');
        $wrongAmount['amount'] = '48.99';
        $wrongAmount['hash'] = $this->hash($wrongAmount);
        $this->post('/payments/toyyibpay/callback', $wrongAmount)->assertStatus(422);

        $this->assertSame(PaymentOrder::STATUS_PENDING, $order->fresh()->status);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_verified_success_activates_once_and_duplicate_callback_is_idempotent(): void
    {
        [$order] = $this->order();
        $payload = $this->payload($order, '1', 'TP-SUCCESS-1');
        $payload['transaction_id'] = 'DNQR-INVOICE-1';
        $payload['dnqr_transaction_id'] = 'DNQR-REFERENCE-1';

        $this->post('/payments/toyyibpay/callback', $payload)
            ->assertOk()
            ->assertSeeText('OK');

        $paid = $order->fresh();
        $subscription = $paid->subscription;
        $firstEnd = $subscription->ends_at->toImmutable();
        $firstPaidAt = $paid->paid_at->toImmutable();

        $this->post('/payments/toyyibpay/callback', $payload)->assertOk();

        $paid->refresh();
        $this->assertSame(PaymentOrder::STATUS_PAID, $paid->status);
        $this->assertSame('TP-SUCCESS-1', $paid->transaction_reference);
        $this->assertSame($subscription->id, $paid->subscription_id);
        $this->assertTrue($paid->paid_at->equalTo($firstPaidAt));
        $this->assertTrue($subscription->fresh()->ends_at->equalTo($firstEnd));
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_pending_and_failure_do_not_grant_access_but_later_success_does(): void
    {
        [$order] = $this->order(status: PaymentOrder::STATUS_CREATING);

        $this->post('/payments/toyyibpay/callback', $this->payload($order, '2', 'TP-PENDING'))
            ->assertOk();
        $this->assertSame(PaymentOrder::STATUS_PENDING, $order->fresh()->status);
        $this->assertDatabaseCount('subscriptions', 0);

        $this->post('/payments/toyyibpay/callback', $this->payload($order, '3', 'TP-FAILED'))
            ->assertOk();
        $this->assertSame(PaymentOrder::STATUS_FAILED, $order->fresh()->status);
        $this->assertSame('provider_failed', $order->fresh()->failure_reason);
        $this->assertDatabaseCount('subscriptions', 0);

        $this->post('/payments/toyyibpay/callback', $this->payload($order, '1', 'TP-RECOVERED'))
            ->assertOk();
        $this->assertSame(PaymentOrder::STATUS_PAID, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->subscription_id);
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_pending_or_failure_after_paid_never_downgrades_access(): void
    {
        [$order] = $this->order();
        $this->post('/payments/toyyibpay/callback', $this->payload($order, '1', 'TP-PAID'))
            ->assertOk();
        $paid = $order->fresh();
        $end = $paid->subscription->ends_at->toImmutable();

        $this->post('/payments/toyyibpay/callback', $this->payload($order, '2', 'TP-LATE-PENDING'))
            ->assertOk();
        $this->post('/payments/toyyibpay/callback', $this->payload($order, '3', 'TP-LATE-FAIL'))
            ->assertOk();

        $paid->refresh();
        $this->assertSame(PaymentOrder::STATUS_PAID, $paid->status);
        $this->assertNull($paid->failure_reason);
        $this->assertTrue($paid->subscription->ends_at->equalTo($end));
    }

    public function test_duplicate_transaction_reference_on_another_order_rolls_back_and_returns_server_error(): void
    {
        [$firstOrder, $user, $plan] = $this->order();
        [$secondOrder] = $this->order($user, $plan, billCode: 'SECONDORDER1');
        $reference = 'TP-DUPLICATE';

        $this->post('/payments/toyyibpay/callback', $this->payload($firstOrder, '1', $reference))
            ->assertOk();
        $originalEnd = $firstOrder->fresh()->subscription->ends_at->toImmutable();

        $this->post('/payments/toyyibpay/callback', $this->payload($secondOrder, '1', $reference))
            ->assertStatus(500)
            ->assertSeeText('ERROR');

        $this->assertSame(PaymentOrder::STATUS_PENDING, $secondOrder->fresh()->status);
        $this->assertNull($secondOrder->fresh()->subscription_id);
        $this->assertTrue($firstOrder->fresh()->subscription->ends_at->equalTo($originalEnd));
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_malformed_fields_missing_secret_and_unsupported_methods_fail_closed(): void
    {
        [$order] = $this->order();
        $malformed = $this->payload($order, '1', 'TP-MALFORMED');
        $malformed['amount'] = '49.001';
        $malformed['hash'] = $this->hash($malformed);
        $this->post('/payments/toyyibpay/callback', $malformed)->assertStatus(422);

        $numeric = $this->payload($order, '1', 'TP-NUMERIC');
        $numeric['status'] = 1;
        $this->postJson('/payments/toyyibpay/callback', $numeric)->assertStatus(400);

        $controlReference = $this->payload($order, '1', "TP-CONTROL\nVALUE");
        $this->post('/payments/toyyibpay/callback', $controlReference)->assertStatus(422);

        $valid = $this->payload($order, '1', 'TP-NO-SECRET');
        config()->set('services.toyyibpay.secret_key', null);
        $this->post('/payments/toyyibpay/callback', $valid)->assertStatus(503);

        $this->get('/payments/toyyibpay/callback')->assertStatus(405);
        $this->assertSame(PaymentOrder::STATUS_PENDING, $order->fresh()->status);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_callback_route_has_provider_safe_throttling(): void
    {
        $route = Route::getRoutes()->getByName('payments.toyyibpay.callback');

        $this->assertNotNull($route);
        $this->assertContains('throttle:120,1', $route->gatherMiddleware());
    }

    /** @return array{PaymentOrder, User, Plan} */
    private function order(
        ?User $user = null,
        ?Plan $plan = null,
        string $status = PaymentOrder::STATUS_PENDING,
        ?string $billCode = null,
    ): array {
        $user ??= User::factory()->create();
        $plan ??= Plan::create([
            'name' => 'Pro Callback',
            'slug' => 'callback-'.Str::lower(Str::random(8)),
            'price' => '49.00',
        ]);
        $order = PaymentOrder::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'bill_code' => $billCode ?? 'BILL'.Str::upper(Str::random(12)),
            'provider' => 'toyyibpay',
            'amount_cents' => 4900,
            'status' => $status,
        ]);

        return [$order, $user, $plan];
    }

    /** @return array<string, string> */
    private function payload(PaymentOrder $order, string $status, string $reference): array
    {
        return $this->payloadFor(
            $order->external_reference,
            (string) $order->bill_code,
            $this->decimalAmount($order->amount_cents),
            $status,
            $reference,
        );
    }

    /** @return array<string, string> */
    private function payloadFor(
        string $orderId,
        string $billCode,
        string $amount,
        string $status,
        string $reference,
    ): array {
        $payload = [
            'refno' => $reference,
            'status' => $status,
            'reason' => 'Provider status',
            'billcode' => $billCode,
            'order_id' => $orderId,
            'amount' => $amount,
        ];
        $payload['hash'] = $this->hash($payload);

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function hash(array $payload): string
    {
        return md5(self::SECRET.(string) $payload['status'].$payload['order_id'].$payload['refno'].'ok');
    }

    private function decimalAmount(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}

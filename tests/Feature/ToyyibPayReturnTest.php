<?php

namespace Tests\Feature;

use App\Models\PaymentOrder;
use App\Models\Plan;
use App\Models\User;
use App\Services\Payments\PaymentActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class ToyyibPayReturnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'https://chatme.test');
        config()->set('services.toyyibpay', [
            'base_url' => 'https://dev.toyyibpay.test',
            'secret_key' => 'return-test-secret',
            'category_code' => 'RETURN1',
            'dnqr_enabled' => true,
            'timeout' => 10,
        ]);
        Carbon::setTestNow('2026-07-10 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guest_is_redirected_from_result_and_reconciliation(): void
    {
        [$order] = $this->order();
        Http::fake();

        $this->get($this->resultUrl($order))->assertRedirect(route('login'));
        $this->post($this->reconcileUrl($order))->assertRedirect(route('login'));

        Http::assertNothingSent();
    }

    public function test_other_users_and_admins_cannot_view_or_reconcile_an_order(): void
    {
        [$order] = $this->order();
        $other = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        Http::fake();

        foreach ([$other, $admin] as $actor) {
            $this->actingAs($actor)->get($this->resultUrl($order))->assertNotFound();
            $this->actingAs($actor)->post($this->reconcileUrl($order))->assertNotFound();
        }

        Http::assertNothingSent();
        $this->assertSame(PaymentOrder::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_forged_success_return_query_is_ignored_and_get_never_calls_provider(): void
    {
        [$order, $user] = $this->order();
        Http::fake();

        $this->actingAs($user)
            ->get($this->resultUrl($order).'?status_id=1&billcode=ATTACKER&order_id=ATTACKER')
            ->assertOk()
            ->assertSeeText('Menunggu pembayaran')
            ->assertSeeText($order->external_reference);

        $this->assertSame(PaymentOrder::STATUS_PENDING, $order->fresh()->status);
        $this->assertNull($order->fresh()->subscription_id);
        Http::assertNothingSent();
    }

    public function test_matching_successful_server_transaction_activates_subscription(): void
    {
        [$order, $user] = $this->order();
        Http::fake([
            '*' => Http::response([$this->successfulTransaction($order, 'TP-RETURN-SUCCESS')]),
        ]);

        $this->actingAs($user)
            ->post($this->reconcileUrl($order))
            ->assertRedirect($this->resultUrl($order))
            ->assertSessionHas('success');

        $paid = $order->fresh();
        $this->assertSame(PaymentOrder::STATUS_PAID, $paid->status);
        $this->assertSame('TP-RETURN-SUCCESS', $paid->transaction_reference);
        $this->assertNotNull($paid->subscription_id);
        $this->assertDatabaseCount('subscriptions', 1);
        Http::assertSent(function ($request) use ($order): bool {
            return $request->data() === ['billCode' => $order->bill_code];
        });
    }

    public function test_wrong_reference_amount_or_missing_invoice_never_activates_and_matching_failure_is_retained(): void
    {
        [$order, $user] = $this->order();
        Http::fake(['*' => Http::response([
            array_merge($this->successfulTransaction($order, 'TP-WRONG-REF'), [
                'billExternalReferenceNo' => (string) Str::uuid(),
            ]),
            array_merge($this->successfulTransaction($order, 'TP-WRONG-AMOUNT'), [
                'billpaymentAmount' => '48.99',
            ]),
            array_merge($this->successfulTransaction($order, 'TP-WRONG-BILL'), [
                'billCode' => 'OTHERBILL',
            ]),
            array_diff_key($this->successfulTransaction($order, 'TP-MISSING'), [
                'billpaymentInvoiceNo' => true,
            ]),
            [
                'billpaymentStatus' => '3',
                'billpaymentAmount' => '49.00',
                'billExternalReferenceNo' => $order->external_reference,
                'billpaymentInvoiceNo' => 'TP-FAILED-ROW',
            ],
            [],
        ])]);

        $this->actingAs($user)
            ->post($this->reconcileUrl($order))
            ->assertRedirect($this->resultUrl($order))
            ->assertSessionHasErrors('payment');

        $this->assertSame(PaymentOrder::STATUS_FAILED, $order->fresh()->status);
        $this->assertSame('provider_failed', $order->fresh()->failure_reason);
        $this->assertNull($order->fresh()->subscription_id);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_duplicate_reconciliation_and_callback_then_reconciliation_are_idempotent(): void
    {
        [$order, $user] = $this->order();
        $transaction = $this->successfulTransaction($order, 'TP-IDEMPOTENT');
        Http::fake(['*' => Http::response([$transaction])]);

        $this->actingAs($user)->post($this->reconcileUrl($order))->assertRedirect();
        $firstEnd = $order->fresh()->subscription->ends_at->toImmutable();
        $this->actingAs($user)->post($this->reconcileUrl($order))->assertRedirect();

        $this->assertTrue($order->fresh()->subscription->ends_at->equalTo($firstEnd));
        $this->assertDatabaseCount('subscriptions', 1);

        [$callbackPaidOrder, $callbackUser] = $this->order();
        app(PaymentActivationService::class)->activate($callbackPaidOrder, 'TP-CALLBACK-FIRST');
        $callbackEnd = $callbackPaidOrder->fresh()->subscription->ends_at->toImmutable();
        Http::fake(['*' => Http::response([
            $this->successfulTransaction($callbackPaidOrder, 'TP-DIFFERENT-RETURN'),
        ])]);

        $this->actingAs($callbackUser)
            ->post($this->reconcileUrl($callbackPaidOrder))
            ->assertRedirect();

        $this->assertSame('TP-CALLBACK-FIRST', $callbackPaidOrder->fresh()->transaction_reference);
        $this->assertTrue($callbackPaidOrder->fresh()->subscription->ends_at->equalTo($callbackEnd));
    }

    public function test_paid_order_is_never_downgraded_by_empty_pending_or_failed_results(): void
    {
        [$order, $user] = $this->order();
        app(PaymentActivationService::class)->activate($order, 'TP-ALREADY-PAID');
        $end = $order->fresh()->subscription->ends_at->toImmutable();
        Http::fakeSequence()
            ->push([])
            ->push([[
                'billpaymentStatus' => '2',
                'billpaymentAmount' => '49.00',
                'billExternalReferenceNo' => $order->external_reference,
                'billpaymentInvoiceNo' => 'TP-LATE-PENDING',
            ]])
            ->push([[
                'billpaymentStatus' => '3',
                'billpaymentAmount' => '49.00',
                'billExternalReferenceNo' => $order->external_reference,
                'billpaymentInvoiceNo' => 'TP-LATE-FAIL',
            ]]);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->actingAs($user)->post($this->reconcileUrl($order))->assertRedirect();
        }

        $paid = $order->fresh();
        $this->assertSame(PaymentOrder::STATUS_PAID, $paid->status);
        $this->assertNull($paid->failure_reason);
        $this->assertTrue($paid->subscription->ends_at->equalTo($end));
        Http::assertNothingSent();
    }

    public function test_provider_failure_returns_safe_feedback_and_logs_no_raw_response(): void
    {
        [$order, $user] = $this->order();
        Http::fake(['*' => Http::response('raw return-test-secret payer@example.test', 500)]);
        Log::spy();

        $this->actingAs($user)
            ->from($this->resultUrl($order))
            ->post($this->reconcileUrl($order))
            ->assertRedirect($this->resultUrl($order))
            ->assertSessionHasErrors('payment');

        $this->assertSame(PaymentOrder::STATUS_PENDING, $order->fresh()->status);
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $serialized = $message.json_encode($context);

                return ! str_contains($serialized, 'return-test-secret')
                    && ! str_contains($serialized, 'payer@example.test');
            });
    }

    public function test_result_renders_paid_pending_and_failed_local_states(): void
    {
        [$pending, $pendingUser] = $this->order();
        $this->actingAs($pendingUser)
            ->get($this->resultUrl($pending))
            ->assertOk()
            ->assertSeeText('Menunggu pembayaran');

        [$failed, $failedUser] = $this->order();
        $failed->update([
            'status' => PaymentOrder::STATUS_FAILED,
            'failure_reason' => 'provider_failed',
        ]);
        $this->actingAs($failedUser)
            ->get($this->resultUrl($failed))
            ->assertOk()
            ->assertSeeText('Pembayaran belum berjaya');

        [$paid, $paidUser] = $this->order();
        app(PaymentActivationService::class)->activate($paid, 'TP-PAID-VIEW');
        $this->actingAs($paidUser)
            ->get($this->resultUrl($paid))
            ->assertOk()
            ->assertSeeText('Pembayaran berjaya');
    }

    public function test_reconcile_route_is_throttled(): void
    {
        $route = Route::getRoutes()->getByName('subscription.reconcile');

        $this->assertNotNull($route);
        $this->assertContains('throttle:10,1', $route->gatherMiddleware());
    }

    /** @return array{PaymentOrder, User, Plan} */
    private function order(): array
    {
        $user = User::factory()->create();
        $plan = Plan::create([
            'name' => 'Pro Return',
            'slug' => 'return-'.Str::lower(Str::random(8)),
            'price' => '49.00',
        ]);
        $order = PaymentOrder::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'bill_code' => 'BILL'.Str::upper(Str::random(12)),
            'provider' => 'toyyibpay',
            'amount_cents' => 4900,
            'status' => PaymentOrder::STATUS_PENDING,
        ]);

        return [$order, $user, $plan];
    }

    /** @return array<string, string> */
    private function successfulTransaction(PaymentOrder $order, string $invoice): array
    {
        return [
            'billpaymentStatus' => '1',
            'billpaymentAmount' => '49.00',
            'billpaymentInvoiceNo' => $invoice,
            'billExternalReferenceNo' => $order->external_reference,
            'billpaymentChannel' => 'DuitNow QR',
        ];
    }

    private function resultUrl(PaymentOrder $order): string
    {
        return '/subscription/orders/'.$order->external_reference.'/return';
    }

    private function reconcileUrl(PaymentOrder $order): string
    {
        return '/subscription/orders/'.$order->external_reference.'/reconcile';
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\PaymentOrder;
use App\Models\Plan;
use App\Models\User;
use App\Services\Payments\ToyyibPayReconciliationService;
use App\Services\ToyyibPay\ToyyibPayClient;
use App\Services\ToyyibPay\ToyyibPayException;
use App\Support\MalaysianPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;
use UnexpectedValueException;

class SubscriptionController extends Controller
{
    private const CREATING_STALE_AFTER_MINUTES = 5;

    private const BILL_EXPIRES_AFTER_DAYS = 3;

    public function plans(): View
    {
        $plans = Plan::visibleForSale()->get();
        $checkoutKeys = $plans
            ->reject(fn (Plan $plan): bool => $plan->slug === 'free')
            ->mapWithKeys(fn (Plan $plan): array => [$plan->id => (string) Str::uuid()]);

        return view('subscription.plans', compact('checkoutKeys', 'plans'));
    }

    public function checkout(
        Request $request,
        Plan $plan,
        ToyyibPayClient $client,
    ): RedirectResponse {
        $amountCents = $this->purchasableAmount($plan);

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'checkout_key' => ['required', 'uuid'],
        ]);

        try {
            $phone = MalaysianPhone::normalize((string) $validated['phone']);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'phone' => 'Masukkan nombor telefon mudah alih Malaysia yang sah.',
            ]);
        }

        $user = $request->user();

        try {
            $order = DB::transaction(function () use ($amountCents, $plan, $user, $validated): PaymentOrder {
                $lockedUser = User::query()
                    ->whereKey($user->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedUser->paymentOrders()
                    ->where('provider', 'toyyibpay')
                    ->where('status', PaymentOrder::STATUS_CREATING)
                    ->where('created_at', '<', now()->subMinutes(self::CREATING_STALE_AFTER_MINUTES))
                    ->update([
                        'status' => PaymentOrder::STATUS_FAILED,
                        'failure_reason' => 'stale_creation',
                        'updated_at' => now(),
                    ]);
                $lockedUser->paymentOrders()
                    ->where('provider', 'toyyibpay')
                    ->where('status', PaymentOrder::STATUS_PENDING)
                    ->where('created_at', '<', now()->subDays(self::BILL_EXPIRES_AFTER_DAYS))
                    ->update([
                        'status' => PaymentOrder::STATUS_EXPIRED,
                        'failure_reason' => 'bill_expired',
                        'updated_at' => now(),
                    ]);

                $activeOrder = $lockedUser->paymentOrders()
                    ->where('plan_id', $plan->id)
                    ->where('provider', 'toyyibpay')
                    ->where('amount_cents', $amountCents)
                    ->whereIn('status', [
                        PaymentOrder::STATUS_CREATING,
                        PaymentOrder::STATUS_PENDING,
                    ])
                    ->latest('id')
                    ->first();

                if ($activeOrder) {
                    return $activeOrder;
                }

                $recoverableOrder = $lockedUser->paymentOrders()
                    ->where('plan_id', $plan->id)
                    ->where('provider', 'toyyibpay')
                    ->where('amount_cents', $amountCents)
                    ->where('status', PaymentOrder::STATUS_FAILED)
                    ->where('failure_reason', 'internal_error')
                    ->whereNotNull('bill_code')
                    ->where('created_at', '>=', now()->subDays(self::BILL_EXPIRES_AFTER_DAYS))
                    ->latest('id')
                    ->first();

                if ($recoverableOrder) {
                    $recoverableOrder->forceFill([
                        'status' => PaymentOrder::STATUS_PENDING,
                        'failure_reason' => null,
                    ])->save();

                    return $recoverableOrder;
                }

                return $lockedUser->paymentOrders()->firstOrCreate([
                    'checkout_key' => $validated['checkout_key'],
                ], [
                    'plan_id' => $plan->id,
                    'provider' => 'toyyibpay',
                    'amount_cents' => $amountCents,
                    'status' => PaymentOrder::STATUS_CREATING,
                ]);
            }, 3);
        } catch (Throwable $exception) {
            Log::error('Payment order could not be created.', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'exception_class' => $exception::class,
            ]);

            return $this->checkoutFailureResponse($request);
        }

        if (! $order->wasRecentlyCreated) {
            if ($order->plan_id !== $plan->id
                || $order->provider !== 'toyyibpay'
                || $order->amount_cents !== $amountCents) {
                throw ValidationException::withMessages([
                    'payment' => 'Permintaan pembayaran ini tidak sepadan dengan pelan yang dipilih.',
                ]);
            }

            if ($order->status === PaymentOrder::STATUS_PAID) {
                return redirect()->route('subscription.return', $order)
                    ->with('success', 'Pembayaran telah disahkan dan langganan anda aktif.');
            }

            if ($order->status === PaymentOrder::STATUS_PENDING && filled($order->bill_code)) {
                return redirect()->away($client->paymentUrl((string) $order->bill_code));
            }

            if ($order->status === PaymentOrder::STATUS_CREATING) {
                return redirect()->route('subscription.return', $order)
                    ->with('info', 'Permintaan pembayaran sedang diproses. Sila semak semula sebentar lagi.');
            }

            return $this->checkoutFailureResponse($request);
        }

        $applicationUrl = rtrim((string) config('app.url'), '/');
        $returnUrl = $applicationUrl.'/subscription/orders/'.$order->external_reference.'/return';
        $callbackUrl = $applicationUrl.'/payments/toyyibpay/callback';

        $billCode = null;

        try {
            $billCode = $client->createBill(
                $order,
                $user,
                $phone,
                $returnUrl,
                $callbackUrl,
            );

            $order->forceFill([
                'bill_code' => $billCode,
                'status' => PaymentOrder::STATUS_PENDING,
                'failure_reason' => null,
            ])->save();

            return redirect()->away($client->paymentUrl($billCode));
        } catch (Throwable $exception) {
            $reason = $this->safeFailureReason($exception);

            $failureUpdate = [
                'status' => PaymentOrder::STATUS_FAILED,
                'failure_reason' => $reason,
                'updated_at' => now(),
            ];

            if ($billCode !== null) {
                $failureUpdate['bill_code'] = $billCode;
            }

            try {
                PaymentOrder::query()
                    ->whereKey($order->id)
                    ->where('status', '!=', PaymentOrder::STATUS_PAID)
                    ->update($failureUpdate);
            } catch (Throwable $stateException) {
                Log::error('Payment order failure state could not be persisted.', [
                    'payment_order_id' => $order->id,
                    'external_reference' => $order->external_reference,
                    'exception_class' => $stateException::class,
                ]);
            }

            Log::warning('ToyyibPay checkout could not be started.', [
                'payment_order_id' => $order->id,
                'external_reference' => $order->external_reference,
                'reason' => $reason,
                'exception_class' => $exception::class,
            ]);

            return $this->checkoutFailureResponse($request);
        }
    }

    public function result(Request $request, PaymentOrder $paymentOrder): View
    {
        $this->assertOrderOwner($request, $paymentOrder);
        $paymentOrder->load(['plan', 'subscription']);

        return view('subscription.result', compact('paymentOrder'));
    }

    public function reconcile(
        Request $request,
        PaymentOrder $paymentOrder,
        ToyyibPayClient $client,
        ToyyibPayReconciliationService $reconciliationService,
    ): RedirectResponse {
        $this->assertOrderOwner($request, $paymentOrder);

        if ($paymentOrder->status === PaymentOrder::STATUS_PAID) {
            return redirect()->route('subscription.return', $paymentOrder)
                ->with('success', 'Pembayaran telah disahkan dan langganan anda aktif.');
        }

        if (! $paymentOrder->bill_code) {
            return redirect()->route('subscription.return', $paymentOrder)
                ->withErrors([
                    'payment' => 'Bil pembayaran belum tersedia. Sila mulakan semula pembayaran.',
                ]);
        }

        try {
            $transactions = $client->getBillTransactions($paymentOrder->bill_code);
            $paymentOrder = $reconciliationService->reconcile($paymentOrder, $transactions);
        } catch (ToyyibPayException $exception) {
            Log::warning('ToyyibPay reconciliation could not be completed.', [
                'payment_order_id' => $paymentOrder->id,
                'external_reference' => $paymentOrder->external_reference,
                'exception_class' => $exception::class,
            ]);

            return redirect()->route('subscription.return', $paymentOrder)
                ->withErrors([
                    'payment' => 'Status pembayaran belum dapat disemak. Sila cuba semula sebentar lagi.',
                ]);
        } catch (Throwable $exception) {
            Log::error('Payment reconciliation failed.', [
                'payment_order_id' => $paymentOrder->id,
                'external_reference' => $paymentOrder->external_reference,
                'exception_class' => $exception::class,
            ]);

            return redirect()->route('subscription.return', $paymentOrder)
                ->withErrors([
                    'payment' => 'Status pembayaran belum dapat disemak. Sila cuba semula sebentar lagi.',
                ]);
        }

        if ($paymentOrder->status === PaymentOrder::STATUS_PAID) {
            return redirect()->route('subscription.return', $paymentOrder)
                ->with('success', 'Pembayaran telah disahkan dan langganan anda aktif.');
        }

        if ($paymentOrder->status === PaymentOrder::STATUS_FAILED) {
            return redirect()->route('subscription.return', $paymentOrder)
                ->withErrors([
                    'payment' => 'Pembayaran tidak berjaya. Anda boleh cuba bayar semula.',
                ]);
        }

        return redirect()->route('subscription.return', $paymentOrder)
            ->with('info', 'Pembayaran masih diproses. Sila semak semula sebentar lagi.');
    }

    private function purchasableAmount(Plan $plan): int
    {
        try {
            $amountCents = $plan->priceInCents();
        } catch (UnexpectedValueException) {
            $amountCents = 0;
        }

        if (! $plan->is_active
            || ! in_array($plan->slug, ['pro', 'enterprise'], true)
            || $amountCents <= 0) {
            throw ValidationException::withMessages([
                'plan' => 'Pelan ini tidak tersedia untuk pembayaran.',
            ]);
        }

        return $amountCents;
    }

    private function safeFailureReason(Throwable $exception): string
    {
        if (! $exception instanceof ToyyibPayException) {
            return 'internal_error';
        }

        return in_array($exception->reason, [
            'configuration_error',
            'transport_error',
            'http_error',
            'invalid_response',
            'invalid_request',
        ], true) ? $exception->reason : 'provider_error';
    }

    private function checkoutFailureResponse(Request $request): RedirectResponse
    {
        return back()
            ->withInput($request->only(['phone', 'checkout_plan']))
            ->withErrors([
                'payment' => 'Pembayaran tidak dapat dimulakan sekarang. Sila cuba semula sebentar lagi.',
            ]);
    }

    private function assertOrderOwner(Request $request, PaymentOrder $paymentOrder): void
    {
        abort_unless($request->user()?->id === $paymentOrder->user_id, 404);
    }
}

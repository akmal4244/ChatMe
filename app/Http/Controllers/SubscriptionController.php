<?php

namespace App\Http\Controllers;

use App\Models\PaymentOrder;
use App\Models\Plan;
use App\Services\Payments\ToyyibPayReconciliationService;
use App\Services\ToyyibPay\ToyyibPayClient;
use App\Services\ToyyibPay\ToyyibPayException;
use App\Support\MalaysianPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;
use UnexpectedValueException;

class SubscriptionController extends Controller
{
    public function plans()
    {
        $plans = Plan::visibleForSale()->get();

        return view('subscription.plans', compact('plans'));
    }

    public function checkout(
        Request $request,
        Plan $plan,
        ToyyibPayClient $client,
    ): RedirectResponse {
        $amountCents = $this->purchasableAmount($plan);

        $request->validate([
            'phone' => ['required', 'string', 'max:30'],
        ]);

        try {
            $phone = MalaysianPhone::normalize((string) $request->input('phone'));
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'phone' => 'Masukkan nombor telefon mudah alih Malaysia yang sah.',
            ]);
        }

        $user = $request->user();

        try {
            $order = $user->paymentOrders()->create([
                'plan_id' => $plan->id,
                'provider' => 'toyyibpay',
                'amount_cents' => $amountCents,
                'status' => PaymentOrder::STATUS_CREATING,
            ]);
        } catch (Throwable $exception) {
            Log::error('Payment order could not be created.', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'exception_class' => $exception::class,
            ]);

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

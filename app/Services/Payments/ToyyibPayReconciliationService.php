<?php

namespace App\Services\Payments;

use App\Models\PaymentOrder;
use App\Support\Ringgit;
use App\Support\ToyyibPayTimestamp;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ToyyibPayReconciliationService
{
    public function __construct(
        private readonly PaymentActivationService $activationService,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $transactions
     */
    public function reconcile(PaymentOrder $order, array $transactions): PaymentOrder
    {
        return DB::transaction(function () use ($order, $transactions): PaymentOrder {
            $lockedOrder = PaymentOrder::query()
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            if ($lockedOrder->status === PaymentOrder::STATUS_PAID || ! $lockedOrder->bill_code) {
                return $lockedOrder;
            }

            $nextStatus = null;

            foreach ($transactions as $transaction) {
                if (! $this->matchesOrder($transaction, $lockedOrder)) {
                    continue;
                }

                $status = $transaction['billpaymentStatus'] ?? null;

                if ($status === '1') {
                    $reference = $transaction['billpaymentInvoiceNo'] ?? null;

                    if ($this->isValidReference($reference)) {
                        $this->activationService->activate(
                            $lockedOrder,
                            $reference,
                            ToyyibPayTimestamp::parse($transaction['billPaymentDate'] ?? null),
                        );

                        return $lockedOrder->fresh();
                    }

                    continue;
                }

                if (in_array($status, ['2', '4'], true)) {
                    $nextStatus = PaymentOrder::STATUS_PENDING;
                } elseif ($status === '3' && $nextStatus === null) {
                    $nextStatus = PaymentOrder::STATUS_FAILED;
                }
            }

            if ($nextStatus !== null) {
                $lockedOrder->forceFill([
                    'status' => $nextStatus,
                    'failure_reason' => $nextStatus === PaymentOrder::STATUS_FAILED
                        ? 'provider_failed'
                        : null,
                ])->save();
            }

            return $lockedOrder->fresh();
        });
    }

    /** @param array<string, mixed> $transaction */
    private function matchesOrder(array $transaction, PaymentOrder $order): bool
    {
        if (($transaction['billExternalReferenceNo'] ?? null) !== $order->external_reference) {
            return false;
        }

        foreach (['billCode', 'billcode'] as $billCodeKey) {
            if (array_key_exists($billCodeKey, $transaction)
                && $transaction[$billCodeKey] !== $order->bill_code) {
                return false;
            }
        }

        $amount = $transaction['billpaymentAmount'] ?? null;

        if (! is_string($amount)) {
            return false;
        }

        try {
            return Ringgit::decimalToCents($amount) === $order->amount_cents;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private function isValidReference(mixed $reference): bool
    {
        return is_string($reference)
            && strlen($reference) <= 255
            && preg_match('/^(?=.*\S)[^\x00-\x1F\x7F]+$/u', $reference) === 1;
    }
}

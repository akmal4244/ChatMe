<?php

namespace App\Services\Payments;

use App\Models\PaymentOrder;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class PaymentActivationService
{
    public function activate(
        PaymentOrder $order,
        string $transactionReference,
        ?CarbonInterface $paidAt = null,
    ): Subscription {
        $transactionReference = trim($transactionReference);

        if ($transactionReference === '' || mb_strlen($transactionReference) > 255) {
            throw new InvalidArgumentException('A valid transaction reference is required.');
        }

        $activatedAt = CarbonImmutable::instance(now());
        $recordedPaidAt = $paidAt
            ? CarbonImmutable::instance($paidAt)->utc()
            : $activatedAt;

        return DB::transaction(function () use (
            $order,
            $transactionReference,
            $activatedAt,
            $recordedPaidAt,
        ): Subscription {
            $lockedOrder = PaymentOrder::query()
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            if ($lockedOrder->status === PaymentOrder::STATUS_PAID) {
                if (! $lockedOrder->subscription_id) {
                    throw new LogicException('Paid payment order has no linked subscription.');
                }

                return Subscription::query()->find($lockedOrder->subscription_id)
                    ?? throw new LogicException('Paid payment order references a missing subscription.');
            }

            $user = User::query()
                ->lockForUpdate()
                ->findOrFail($lockedOrder->user_id);

            /** @var Collection<int, Subscription> $eligibleSubscriptions */
            $eligibleSubscriptions = $user->subscriptions()
                ->with('plan')
                ->where('status', 'active')
                ->whereNotNull('starts_at')
                ->where('starts_at', '<=', $activatedAt)
                ->whereNotNull('ends_at')
                ->where('ends_at', '>', $activatedAt)
                ->whereHas('plan', fn ($query) => $query->where('price', '>', 0))
                ->orderByDesc('starts_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            $paidSubscriptions = $eligibleSubscriptions
                ->filter(fn (Subscription $subscription): bool => $this->isPaidEntitlement($subscription));

            if ($lockedOrder->amount_cents <= 0) {
                throw new LogicException('Paid payment order has an invalid amount.');
            }

            /** @var Subscription|null $subscription */
            $subscription = $paidSubscriptions
                ->first(fn (Subscription $candidate): bool => $candidate->plan_id === $lockedOrder->plan_id);

            $samePlanBase = $paidSubscriptions
                ->filter(fn (Subscription $candidate): bool => $candidate->plan_id === $lockedOrder->plan_id)
                ->reduce(
                    function (CarbonImmutable $latest, Subscription $candidate): CarbonImmutable {
                        $candidateEnd = $candidate->ends_at->toImmutable();

                        return $candidateEnd->gt($latest) ? $candidateEnd : $latest;
                    },
                    $activatedAt,
                );
            $proratedCreditSeconds = $paidSubscriptions
                ->reject(fn (Subscription $candidate): bool => $candidate->plan_id === $lockedOrder->plan_id)
                ->reduce(function (int $credit, Subscription $candidate) use ($activatedAt, $lockedOrder): int {
                    $remainingSeconds = max(
                        0,
                        $candidate->ends_at->getTimestamp() - $activatedAt->getTimestamp(),
                    );
                    $sourceMonthlyCents = $candidate->plan->priceInCents();

                    if ($remainingSeconds > intdiv(PHP_INT_MAX, $sourceMonthlyCents)) {
                        throw new LogicException('Prorated subscription credit exceeds the supported range.');
                    }

                    $converted = intdiv(
                        $remainingSeconds * $sourceMonthlyCents,
                        $lockedOrder->amount_cents,
                    );

                    if ($credit > PHP_INT_MAX - $converted) {
                        throw new LogicException('Prorated subscription credit exceeds the supported range.');
                    }

                    return $credit + $converted;
                }, 0);

            foreach ($paidSubscriptions as $paidSubscription) {
                if ($subscription && $paidSubscription->is($subscription)) {
                    continue;
                }

                $paidSubscription->forceFill([
                    'status' => 'expired',
                    'ends_at' => $activatedAt,
                ])->save();
            }

            if ($subscription) {
                $subscription->forceFill([
                    'provider' => 'toyyibpay',
                    'provider_reference' => $transactionReference,
                    'status' => 'active',
                    'starts_at' => $subscription->starts_at ?? $activatedAt,
                    'ends_at' => $samePlanBase
                        ->addMonthNoOverflow()
                        ->addSeconds($proratedCreditSeconds),
                ])->save();
            } else {
                $subscription = $user->subscriptions()->create([
                    'plan_id' => $lockedOrder->plan_id,
                    'provider' => 'toyyibpay',
                    'provider_reference' => $transactionReference,
                    'status' => 'active',
                    'starts_at' => $activatedAt,
                    'ends_at' => $activatedAt
                        ->addMonthNoOverflow()
                        ->addSeconds($proratedCreditSeconds),
                ]);
            }

            $lockedOrder->forceFill([
                'subscription_id' => $subscription->id,
                'status' => PaymentOrder::STATUS_PAID,
                'transaction_reference' => $transactionReference,
                'failure_reason' => null,
                'paid_at' => $recordedPaidAt,
            ])->save();

            return $subscription->fresh();
        });
    }

    private function isPaidEntitlement(Subscription $subscription): bool
    {
        return $subscription->provider !== 'system'
            && $subscription->plan
            && $subscription->plan->priceInCents() > 0;
    }
}

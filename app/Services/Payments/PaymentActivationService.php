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
        $ownerId = (int) PaymentOrder::query()
            ->whereKey($order->getKey())
            ->value('user_id');

        if ($ownerId < 1) {
            throw new LogicException('Payment order owner is missing.');
        }

        return DB::transaction(function () use (
            $order,
            $ownerId,
            $transactionReference,
            $activatedAt,
            $recordedPaidAt,
        ): Subscription {
            $user = User::query()
                ->lockForUpdate()
                ->findOrFail($ownerId);
            $lockedOrder = PaymentOrder::query()
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            if ($lockedOrder->user_id !== $user->id) {
                throw new LogicException('Payment order owner changed during activation.');
            }

            if ($lockedOrder->status === PaymentOrder::STATUS_PAID) {
                if (! $lockedOrder->subscription_id) {
                    throw new LogicException('Paid payment order has no linked subscription.');
                }

                return Subscription::query()->find($lockedOrder->subscription_id)
                    ?? throw new LogicException('Paid payment order references a missing subscription.');
            }

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
                    $sourceMonthlyCents = $this->snapshotMonthlyCents($candidate);

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

            $newEnd = $samePlanBase
                ->addMonthNoOverflow()
                ->addSeconds($proratedCreditSeconds);
            $existingRemainingSeconds = $subscription
                ? max(0, $samePlanBase->getTimestamp() - $activatedAt->getTimestamp())
                : 0;
            $newCoverageSeconds = max(1, $newEnd->getTimestamp() - $samePlanBase->getTimestamp());
            $existingUnitPrice = $subscription
                ? $this->snapshotMonthlyCents($subscription)
                : $lockedOrder->amount_cents;
            $weightedValueSeconds = $this->checkedValueSeconds(
                $existingRemainingSeconds,
                $existingUnitPrice,
            );
            $newValueSeconds = $this->checkedValueSeconds(
                $newCoverageSeconds,
                $lockedOrder->amount_cents,
            );
            if ($weightedValueSeconds > PHP_INT_MAX - $newValueSeconds) {
                throw new LogicException('Prorated subscription value exceeds the supported range.');
            }
            $totalRemainingSeconds = $existingRemainingSeconds + $newCoverageSeconds;
            $weightedUnitPrice = intdiv(
                $weightedValueSeconds + $newValueSeconds,
                $totalRemainingSeconds,
            );

            if ($subscription) {
                $subscription->forceFill([
                    'unit_price_cents' => $weightedUnitPrice,
                    'provider' => 'toyyibpay',
                    'provider_reference' => $transactionReference,
                    'status' => 'active',
                    'starts_at' => $subscription->starts_at ?? $activatedAt,
                    'ends_at' => $newEnd,
                ])->save();
            } else {
                $subscription = $user->subscriptions()->create([
                    'plan_id' => $lockedOrder->plan_id,
                    'unit_price_cents' => $weightedUnitPrice,
                    'provider' => 'toyyibpay',
                    'provider_reference' => $transactionReference,
                    'status' => 'active',
                    'starts_at' => $activatedAt,
                    'ends_at' => $newEnd,
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
        }, 3);
    }

    private function isPaidEntitlement(Subscription $subscription): bool
    {
        return $subscription->provider !== 'system'
            && $subscription->plan
            && $this->snapshotMonthlyCents($subscription) > 0;
    }

    private function snapshotMonthlyCents(Subscription $subscription): int
    {
        $unitPrice = (int) ($subscription->unit_price_cents ?? 0);

        return $unitPrice > 0
            ? $unitPrice
            : (int) $subscription->plan?->priceInCents();
    }

    private function checkedValueSeconds(int $seconds, int $unitPriceCents): int
    {
        if ($seconds < 0 || $unitPriceCents <= 0
            || ($seconds > 0 && $seconds > intdiv(PHP_INT_MAX, $unitPriceCents))) {
            throw new LogicException('Prorated subscription value exceeds the supported range.');
        }

        return $seconds * $unitPriceCents;
    }
}

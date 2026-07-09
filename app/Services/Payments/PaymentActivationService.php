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
            ? CarbonImmutable::instance($paidAt)
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
                ->where(function ($query): void {
                    $query->where('status', 'active')
                        ->orWhereNull('status');
                })
                ->where(function ($query) use ($activatedAt): void {
                    $query->whereNull('starts_at')
                        ->orWhere('starts_at', '<=', $activatedAt);
                })
                ->where(function ($query) use ($activatedAt): void {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>', $activatedAt);
                })
                ->orderByDesc('starts_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            $paidSubscriptions = $eligibleSubscriptions
                ->filter(fn (Subscription $subscription): bool => $this->isPaidEntitlement($subscription));

            /** @var Subscription|null $subscription */
            $subscription = $paidSubscriptions
                ->first(fn (Subscription $candidate): bool => $candidate->plan_id === $lockedOrder->plan_id);

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
                $base = $subscription->ends_at && $subscription->ends_at->gt($activatedAt)
                    ? $subscription->ends_at->toImmutable()
                    : $activatedAt;

                $subscription->forceFill([
                    'provider' => 'toyyibpay',
                    'provider_reference' => $transactionReference,
                    'status' => 'active',
                    'starts_at' => $subscription->starts_at ?? $activatedAt,
                    'ends_at' => $base->addMonthNoOverflow(),
                ])->save();
            } else {
                $subscription = $user->subscriptions()->create([
                    'plan_id' => $lockedOrder->plan_id,
                    'provider' => 'toyyibpay',
                    'provider_reference' => $transactionReference,
                    'status' => 'active',
                    'starts_at' => $activatedAt,
                    'ends_at' => $activatedAt->addMonthNoOverflow(),
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

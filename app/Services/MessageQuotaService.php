<?php

namespace App\Services;

use App\Models\Chatbot;
use App\Models\ChatLog;
use App\Models\MessageQuotaReservation;
use App\Models\MessageUsage;
use App\Models\User;
use App\ValueObjects\MessageQuotaPermit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class MessageQuotaService
{
    public function reserve(Chatbot $chatbot, string $channel): ?MessageQuotaPermit
    {
        if (preg_match('/^[a-z_]{1,32}$/', $channel) !== 1) {
            throw new InvalidArgumentException('The quota channel is invalid.');
        }

        $reservedAt = now()->toImmutable();

        return DB::transaction(function () use ($channel, $chatbot, $reservedAt): ?MessageQuotaPermit {
            $owner = User::query()->lockForUpdate()->findOrFail($chatbot->user_id);
            $plan = $owner->currentPlan();

            if (! $plan || $plan->monthly_messages < -1) {
                return null;
            }

            if ($plan->monthly_messages === -1) {
                return new MessageQuotaPermit(
                    userId: $owner->id,
                    chatbotId: $chatbot->id,
                    channel: $channel,
                    reservationToken: null,
                    reservedAt: $reservedAt,
                );
            }

            [$periodStart, $periodEnd] = $this->quotaPeriod($reservedAt);
            $liveUsage = ChatLog::query()
                ->where('role', 'user')
                ->whereHas('chatbot', fn ($query) => $query->where('user_id', $owner->id))
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->count();
            $usage = MessageUsage::query()
                ->where('user_id', $owner->id)
                ->where('usage_month', $this->usageMonth($reservedAt))
                ->lockForUpdate()
                ->first();
            $used = $liveUsage;
            if ($usage !== null) {
                $used = max((int) $usage->message_count, $liveUsage);
            }

            if (! $usage) {
                $usage = MessageUsage::query()->create([
                    'user_id' => $owner->id,
                    'usage_month' => $this->usageMonth($reservedAt),
                    'message_count' => $used,
                ]);
            } elseif ($usage->message_count < $used) {
                $usage->forceFill(['message_count' => $used])->save();
            }
            $reserved = MessageQuotaReservation::query()
                ->where('user_id', $owner->id)
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->where('expires_at', '>', $reservedAt)
                ->count();

            if ($used + $reserved >= $plan->monthly_messages) {
                return null;
            }

            $token = Str::random(64);
            MessageQuotaReservation::query()->create([
                'user_id' => $owner->id,
                'chatbot_id' => $chatbot->id,
                'token' => $token,
                'channel' => $channel,
                'expires_at' => $reservedAt->addSeconds($this->reservationTtlSeconds()),
            ]);

            return new MessageQuotaPermit(
                userId: $owner->id,
                chatbotId: $chatbot->id,
                channel: $channel,
                reservationToken: $token,
                reservedAt: $reservedAt,
            );
        });
    }

    public function complete(
        MessageQuotaPermit $permit,
        string $sessionId,
        string $userMessage,
        string $botMessage,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        try {
            DB::transaction(function () use ($botMessage, $ipAddress, $permit, $sessionId, $userAgent, $userMessage): void {
                User::query()->lockForUpdate()->findOrFail($permit->userId);
                $chatbot = Chatbot::query()->lockForUpdate()->findOrFail($permit->chatbotId);

                if ($chatbot->user_id !== $permit->userId) {
                    throw new RuntimeException('The chatbot owner changed after quota reservation.');
                }

                $reservation = $this->lockedReservation($permit);
                $logTimestamp = $permit->reservedAt;
                $updatedAt = now();

                $userLog = new ChatLog;
                $userLog->forceFill([
                    'chatbot_id' => $permit->chatbotId,
                    'session_id' => $sessionId,
                    'message' => $userMessage,
                    'role' => 'user',
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'created_at' => $logTimestamp,
                    'updated_at' => $updatedAt,
                ])->save();

                $botLog = new ChatLog;
                $botLog->forceFill([
                    'chatbot_id' => $permit->chatbotId,
                    'session_id' => $sessionId,
                    'message' => $botMessage,
                    'role' => 'bot',
                    'created_at' => $logTimestamp,
                    'updated_at' => $updatedAt,
                ])->save();

                $this->incrementDurableUsage($permit);

                $reservation?->delete();
            });
        } catch (Throwable $exception) {
            $this->releaseAfterFailure($permit);

            throw $exception;
        }
    }

    public function release(MessageQuotaPermit $permit): void
    {
        if ($permit->reservationToken === null) {
            return;
        }

        MessageQuotaReservation::query()
            ->where('token', $permit->reservationToken)
            ->where('user_id', $permit->userId)
            ->where('chatbot_id', $permit->chatbotId)
            ->where('channel', $permit->channel)
            ->delete();
    }

    public function pruneExpired(): int
    {
        return MessageQuotaReservation::query()
            ->where('expires_at', '<=', now())
            ->delete();
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function quotaPeriod(CarbonImmutable $reservedAt): array
    {
        $businessNow = $reservedAt->setTimezone((string) config('chatme.timezone'));

        return [
            $businessNow->startOfMonth()->utc(),
            $businessNow->endOfMonth()->utc(),
        ];
    }

    private function reservationTtlSeconds(): int
    {
        return max(30, min(600, (int) config('chatme.quota.reservation_ttl_seconds', 120)));
    }

    private function usageMonth(CarbonImmutable $at): string
    {
        return $at
            ->setTimezone((string) config('chatme.timezone'))
            ->startOfMonth()
            ->toDateString();
    }

    private function incrementDurableUsage(MessageQuotaPermit $permit): void
    {
        $usage = MessageUsage::query()
            ->where('user_id', $permit->userId)
            ->where('usage_month', $this->usageMonth($permit->reservedAt))
            ->lockForUpdate()
            ->first();

        if (! $usage) {
            MessageUsage::query()->create([
                'user_id' => $permit->userId,
                'usage_month' => $this->usageMonth($permit->reservedAt),
                'message_count' => 1,
            ]);

            return;
        }

        if ($usage->message_count >= PHP_INT_MAX) {
            throw new RuntimeException('The durable message usage counter is out of range.');
        }

        $usage->forceFill(['message_count' => $usage->message_count + 1])->save();
    }

    private function lockedReservation(MessageQuotaPermit $permit): ?MessageQuotaReservation
    {
        if ($permit->reservationToken === null) {
            return null;
        }

        $reservation = MessageQuotaReservation::query()
            ->where('token', $permit->reservationToken)
            ->where('user_id', $permit->userId)
            ->where('chatbot_id', $permit->chatbotId)
            ->where('channel', $permit->channel)
            ->lockForUpdate()
            ->first();

        if (! $reservation || $reservation->expires_at->isPast()) {
            throw new RuntimeException('The message quota reservation is missing or expired.');
        }

        return $reservation;
    }

    private function releaseAfterFailure(MessageQuotaPermit $permit): void
    {
        try {
            $this->release($permit);
        } catch (Throwable) {
            Log::error('Message quota reservation cleanup failed.', [
                'user_id' => $permit->userId,
                'chatbot_id' => $permit->chatbotId,
                'channel' => $permit->channel,
            ]);
        }
    }
}

<?php

namespace App\Services;

use App\Models\Chatbot;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class OwnerMessagingLimiter
{
    public function denied(Chatbot $chatbot): bool
    {
        $ownerKey = (string) $chatbot->user_id;
        $businessNow = now((string) config('chatme.timezone'));
        $secondsUntilTomorrow = (int) max(
            60,
            $businessNow->diffInSeconds($businessNow->copy()->addDay()->startOfDay()),
        );
        $limits = [
            [
                'key' => 'owner-messaging:minute:'.$ownerKey,
                'max' => $this->limit('owner_per_minute', 600),
                'decay' => 60,
            ],
            [
                'key' => 'owner-messaging:day:'.$ownerKey.':'.$businessNow->toDateString(),
                'max' => $this->limit('owner_daily', 5000),
                'decay' => $secondsUntilTomorrow,
            ],
        ];

        try {
            return (bool) Cache::lock('owner-messaging:lock:'.$ownerKey, 5)
                ->block(2, function () use ($limits): bool {
                    foreach ($limits as $limit) {
                        if (RateLimiter::tooManyAttempts($limit['key'], $limit['max'])) {
                            return true;
                        }
                    }

                    foreach ($limits as $limit) {
                        RateLimiter::hit($limit['key'], $limit['decay']);
                    }

                    return false;
                });
        } catch (LockTimeoutException) {
            return true;
        }
    }

    private function limit(string $name, int $default): int
    {
        return max(1, min(100000, (int) config('chatme.messaging.limits.'.$name, $default)));
    }
}

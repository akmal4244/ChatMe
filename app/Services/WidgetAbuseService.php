<?php

namespace App\Services;

use App\Models\Chatbot;
use App\ValueObjects\WidgetTicketClaims;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class WidgetAbuseService
{
    public function deniedBy(
        Request $request,
        Chatbot $chatbot,
        WidgetTicketClaims $claims,
    ): ?string {
        $ipHash = hash('sha256', (string) ($request->ip() ?: 'unknown'));
        $businessNow = now((string) config('chatme.timezone'));
        $secondsUntilTomorrow = (int) max(
            60,
            $businessNow->diffInSeconds($businessNow->copy()->addDay()->startOfDay()),
        );
        $limits = [
            [
                'name' => 'ticket_minute',
                'key' => 'widget:ticket:'.$claims->fingerprint,
                'max' => $this->limit('ticket_per_minute', 10),
                'decay' => 60,
            ],
            [
                'name' => 'chatbot_ip_minute',
                'key' => 'widget:bot-ip:'.$chatbot->id.':'.$ipHash,
                'max' => $this->limit('chatbot_ip_per_minute', 30),
                'decay' => 60,
            ],
            [
                'name' => 'chatbot_minute',
                'key' => 'widget:bot:'.$chatbot->id,
                'max' => $this->limit('bot_per_minute', 180),
                'decay' => 60,
            ],
            [
                'name' => 'chatbot_daily',
                'key' => 'widget:bot-day:'.$chatbot->id.':'.$businessNow->toDateString(),
                'max' => $this->dailyLimit($chatbot),
                'decay' => $secondsUntilTomorrow,
            ],
        ];

        foreach ($limits as $limit) {
            if ($limit['max'] < 1 || RateLimiter::tooManyAttempts($limit['key'], $limit['max'])) {
                Log::notice('Widget request rate limited.', [
                    'chatbot_id' => $chatbot->id,
                    'limiter' => $limit['name'],
                ]);

                return $limit['name'];
            }
        }

        foreach ($limits as $limit) {
            RateLimiter::hit($limit['key'], $limit['decay']);
        }

        return null;
    }

    private function limit(string $name, int $default): int
    {
        return max(0, min(10000, (int) config('chatme.widget.limits.'.$name, $default)));
    }

    private function dailyLimit(Chatbot $chatbot): int
    {
        $monthly = $chatbot->user->currentPlan()?->monthly_messages;
        if ($monthly === -1) {
            return $this->limit('bot_daily_unlimited', 5000);
        }

        if (! is_int($monthly) || $monthly < 1) {
            return 0;
        }

        return max(1, min($monthly, (int) ceil($monthly / 7)));
    }
}

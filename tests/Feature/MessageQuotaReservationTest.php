<?php

namespace Tests\Feature;

use App\Models\Chatbot;
use App\Models\ChatLog;
use App\Models\Plan;
use App\Models\User;
use App\Services\MessageQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class MessageQuotaReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_chatbot_owner_foreign_key_is_cast_to_integer_for_mysql_strict_comparison(): void
    {
        $chatbot = new Chatbot;
        $chatbot->setRawAttributes(['user_id' => '123']);

        $this->assertSame(123, $chatbot->user_id);
    }

    public function test_the_last_limited_slot_is_reserved_before_logs_and_cannot_be_double_reserved(): void
    {
        $chatbot = $this->chatbotWithLimit(1);
        $service = app(MessageQuotaService::class);

        $permit = $service->reserve($chatbot, 'widget');

        $this->assertNotNull($permit);
        $this->assertNotNull($permit->reservationToken);
        $this->assertDatabaseCount('message_quota_reservations', 1);
        $this->assertNull($service->reserve($chatbot, 'developer_api'));

        $service->complete(
            $permit,
            sessionId: 'reserved-session',
            userMessage: 'Soalan ditempah',
            botMessage: 'Jawapan atomik',
            ipAddress: '127.0.0.1',
            userAgent: 'PHPUnit',
        );

        $this->assertDatabaseCount('message_quota_reservations', 0);
        $this->assertDatabaseCount('chat_logs', 2);
        $this->assertDatabaseHas('chat_logs', [
            'session_id' => 'reserved-session',
            'role' => 'user',
            'message' => 'Soalan ditempah',
        ]);
        $this->assertDatabaseHas('chat_logs', [
            'session_id' => 'reserved-session',
            'role' => 'bot',
            'message' => 'Jawapan atomik',
        ]);
        $this->assertNull($service->reserve($chatbot, 'widget'));
    }

    public function test_expired_reservations_do_not_block_and_are_pruned_without_touching_active_ones(): void
    {
        $chatbot = $this->chatbotWithLimit(1);
        $service = app(MessageQuotaService::class);
        $expired = $service->reserve($chatbot, 'widget');

        $this->assertNotNull($expired);
        $this->travel(3)->minutes();

        $active = $service->reserve($chatbot, 'widget');

        $this->assertNotNull($active);
        $this->assertNotSame($expired->reservationToken, $active->reservationToken);
        $this->assertSame(1, $service->pruneExpired());
        $this->assertDatabaseCount('message_quota_reservations', 1);
    }

    public function test_unlimited_plan_uses_atomic_pair_writes_without_a_persisted_reservation(): void
    {
        $chatbot = $this->chatbotWithLimit(-1);
        $service = app(MessageQuotaService::class);

        $permit = $service->reserve($chatbot, 'widget');

        $this->assertNotNull($permit);
        $this->assertNull($permit->reservationToken);
        $this->assertDatabaseCount('message_quota_reservations', 0);

        $service->complete(
            $permit,
            sessionId: 'unlimited-session',
            userMessage: 'Soalan unlimited',
            botMessage: 'Jawapan unlimited',
        );

        $this->assertDatabaseCount('chat_logs', 2);
    }

    public function test_bot_log_failure_rolls_back_the_pair_and_releases_the_reservation(): void
    {
        $chatbot = $this->chatbotWithLimit(1);
        $service = app(MessageQuotaService::class);
        $permit = $service->reserve($chatbot, 'widget');
        $event = 'eloquent.creating: '.ChatLog::class;

        Event::listen($event, function (ChatLog $log): void {
            if ($log->role === 'bot') {
                throw new RuntimeException('Injected bot log failure');
            }
        });

        try {
            $service->complete(
                $permit,
                sessionId: 'failed-session',
                userMessage: 'Tidak boleh separuh',
                botMessage: 'Tidak boleh separuh',
            );
            $this->fail('The injected bot-log failure should be rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected bot log failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }

        $this->assertDatabaseCount('chat_logs', 0);
        $this->assertDatabaseCount('message_quota_reservations', 0);
    }

    public function test_completed_monthly_usage_survives_chatbot_deletion_and_blocks_a_replacement_bot(): void
    {
        $chatbot = $this->chatbotWithLimit(1);
        $owner = $chatbot->user;
        $service = app(MessageQuotaService::class);
        $permit = $service->reserve($chatbot, 'widget');

        $this->assertNotNull($permit);
        $service->complete(
            $permit,
            sessionId: 'durable-usage-session',
            userMessage: 'Mesej yang telah digunakan',
            botMessage: 'Jawapan yang telah dihantar',
        );
        $chatbot->delete();
        $this->assertDatabaseCount('chat_logs', 0);

        $replacement = Chatbot::create([
            'user_id' => $owner->id,
            'name' => 'Replacement Bot',
        ]);

        $this->assertNull($service->reserve($replacement, 'widget'));
    }

    private function chatbotWithLimit(int $monthlyMessages): Chatbot
    {
        $plan = Plan::create([
            'name' => 'Reservation Plan',
            'slug' => 'reservation-'.str()->random(8),
            'price' => 10,
            'chatbot_limit' => 10,
            'knowledge_limit' => 100,
            'monthly_messages' => $monthlyMessages,
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->subscriptions()->create([
            'plan_id' => $plan->id,
            'provider' => 'system',
            'status' => 'active',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addMonth(),
        ]);

        return Chatbot::create([
            'user_id' => $user->id,
            'name' => 'Reservation Bot',
        ]);
    }
}

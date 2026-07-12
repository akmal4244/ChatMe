<?php

namespace Tests\Feature;

use App\Models\Chatbot;
use App\Models\ChatLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PlanLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_plan_allows_the_first_chatbot(): void
    {
        $user = $this->freePlanUser();

        $this->actingAs($user)
            ->post(route('chatbots.store'), ['name' => 'First Bot'])
            ->assertRedirect(route('chatbots.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('chatbots', [
            'user_id' => $user->id,
            'name' => 'First Bot',
        ]);
    }

    public function test_chatbot_creation_persists_the_domain_whitelist_from_the_form(): void
    {
        $user = $this->freePlanUser();

        $this->actingAs($user)
            ->post(route('chatbots.store'), [
                'name' => 'Restricted Bot',
                'domain_whitelist' => 'example.com, support.example.com',
            ])
            ->assertRedirect(route('chatbots.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('chatbots', [
            'user_id' => $user->id,
            'name' => 'Restricted Bot',
            'domain_whitelist' => 'example.com, support.example.com',
        ]);
    }

    public function test_free_plan_rejects_a_second_chatbot_with_name_validation_feedback(): void
    {
        $user = $this->freePlanUser();
        Chatbot::create([
            'user_id' => $user->id,
            'name' => 'Existing Bot',
        ]);

        $this->actingAs($user)
            ->post(route('chatbots.store'), ['name' => 'Over Limit Bot'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('chatbots', 1);
        $this->assertDatabaseMissing('chatbots', [
            'user_id' => $user->id,
            'name' => 'Over Limit Bot',
        ]);
    }

    public function test_chatbot_limit_is_rechecked_after_the_owner_lock_is_acquired(): void
    {
        $user = $this->freePlanUser();
        $injectCompetingChatbot = true;

        User::retrieved(function (User $retrievedUser) use ($user, &$injectCompetingChatbot): void {
            if (! $injectCompetingChatbot || $retrievedUser->isNot($user)) {
                return;
            }

            $injectCompetingChatbot = false;
            Chatbot::create([
                'user_id' => $user->id,
                'name' => 'Competing Bot',
            ]);
        });

        $this->actingAs($user)
            ->post(route('chatbots.store'), ['name' => 'Too Late Bot'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('chatbots', 1);
        $this->assertDatabaseHas('chatbots', [
            'user_id' => $user->id,
            'name' => 'Competing Bot',
        ]);
        $this->assertDatabaseMissing('chatbots', [
            'user_id' => $user->id,
            'name' => 'Too Late Bot',
        ]);
    }

    public function test_unlimited_plan_still_obeys_the_absolute_chatbot_safety_limit(): void
    {
        config()->set('chatme.chatbots.absolute_limit', 2);
        $plan = Plan::create([
            'name' => 'Unlimited Chatbots',
            'slug' => 'unlimited-chatbots',
            'chatbot_limit' => -1,
        ]);
        $user = User::factory()->create();
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'provider' => 'system',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => null,
        ]);
        Chatbot::create(['user_id' => $user->id, 'name' => 'Bot One']);
        Chatbot::create(['user_id' => $user->id, 'name' => 'Bot Two']);

        $this->assertFalse($user->canCreateChatbot());
        $this->actingAs($user)
            ->post(route('chatbots.store'), ['name' => 'Unsafe Third Bot'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('chatbots', 2);
    }

    public function test_one_message_plan_rejects_chat_when_current_month_quota_is_exhausted_without_writes(): void
    {
        $user = $this->subscribedUserWithMonthlyMessageLimit(1);
        $countedChatbot = $this->chatbotFor($user);
        $targetChatbot = Chatbot::create([
            'user_id' => $user->id,
            'name' => 'Target Quota Bot',
        ]);
        ChatLog::create([
            'chatbot_id' => $countedChatbot->id,
            'session_id' => 'existing-session',
            'message' => 'Already counted',
            'role' => 'user',
        ]);
        Log::spy();

        $this->postWidgetJson($targetChatbot, [
            'message' => 'Over limit',
        ], $newSession)
            ->assertStatus(429)
            ->assertJson(['error' => 'Had mesej bulanan telah dicapai.']);

        $this->assertDatabaseCount('chat_logs', 1);
        $this->assertDatabaseMissing('chat_logs', [
            'session_id' => $newSession,
        ]);
        Log::shouldHaveReceived('notice')
            ->once()
            ->with('Monthly message quota exceeded.', [
                'user_id' => $user->id,
                'chatbot_id' => $targetChatbot->id,
                'channel' => 'widget',
            ]);
    }

    public function test_message_limit_is_checked_after_the_owner_lock_before_reservation(): void
    {
        $user = $this->subscribedUserWithMonthlyMessageLimit(1);
        $chatbot = $this->chatbotFor($user);
        $matchingOwnerRetrievals = 0;
        $injectCompetingMessage = true;

        User::retrieved(function (User $retrievedUser) use ($user, $chatbot, &$matchingOwnerRetrievals, &$injectCompetingMessage): void {
            if (! $injectCompetingMessage || $retrievedUser->isNot($user)) {
                return;
            }

            $matchingOwnerRetrievals++;

            if ($matchingOwnerRetrievals !== 1) {
                return;
            }

            $injectCompetingMessage = false;
            ChatLog::create([
                'chatbot_id' => $chatbot->id,
                'session_id' => 'competing-session',
                'message' => 'Competing request used the final slot',
                'role' => 'user',
            ]);
        });

        $this->postWidgetJson($chatbot, [
            'message' => 'Too late',
        ], $tooLateSession)
            ->assertStatus(429)
            ->assertJson(['error' => 'Had mesej bulanan telah dicapai.']);

        $this->assertSame(1, $matchingOwnerRetrievals);
        $this->assertDatabaseCount('chat_logs', 1);
        $this->assertDatabaseHas('chat_logs', [
            'session_id' => 'competing-session',
            'role' => 'user',
        ]);
        $this->assertDatabaseMissing('chat_logs', [
            'session_id' => $tooLateSession,
        ]);
    }

    public function test_chat_log_writes_roll_back_when_response_matching_fails(): void
    {
        $user = $this->subscribedUserWithMonthlyMessageLimit(1);
        $chatbot = $this->chatbotFor($user);
        $failBotWrite = true;

        ChatLog::creating(function (ChatLog $chatLog) use (&$failBotWrite): void {
            if (! $failBotWrite || $chatLog->role !== 'bot') {
                return;
            }

            $failBotWrite = false;
            throw new \RuntimeException('Simulated response write failure.');
        });

        $this->withoutExceptionHandling();

        try {
            $this->postWidgetJson($chatbot, [
                'message' => 'Atomic request',
            ], $atomicSession);

            $this->fail('The simulated response write failure was not raised.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated response write failure.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('chat_logs', [
            'session_id' => $atomicSession,
        ]);
    }

    public function test_admin_does_not_bypass_monthly_message_quota(): void
    {
        $user = $this->subscribedUserWithMonthlyMessageLimit(1);
        $user->update(['is_admin' => true]);
        $chatbot = $this->chatbotFor($user);
        ChatLog::create([
            'chatbot_id' => $chatbot->id,
            'session_id' => 'admin-existing-session',
            'message' => 'Already counted',
            'role' => 'user',
        ]);

        $this->postWidgetJson($chatbot, [
            'message' => 'Admin over limit',
        ])
            ->assertStatus(429)
            ->assertJson(['error' => 'Had mesej bulanan telah dicapai.']);

        $this->assertDatabaseCount('chat_logs', 1);
    }

    public function test_monthly_quota_counts_only_current_month_user_role_rows_across_owned_chatbots(): void
    {
        $user = $this->subscribedUserWithMonthlyMessageLimit(1);
        $otherChatbot = $this->chatbotFor($user);
        $targetChatbot = Chatbot::create([
            'user_id' => $user->id,
            'name' => 'Second Quota Bot',
        ]);

        $oldUserLog = ChatLog::create([
            'chatbot_id' => $otherChatbot->id,
            'session_id' => 'old-user-session',
            'message' => 'Last month',
            'role' => 'user',
        ]);
        $oldUserLog->forceFill([
            'created_at' => now()->subMonthNoOverflow()->startOfMonth(),
        ])->saveQuietly();

        ChatLog::create([
            'chatbot_id' => $targetChatbot->id,
            'session_id' => 'bot-session',
            'message' => 'Bot messages do not consume quota',
            'role' => 'bot',
        ]);

        $this->postWidgetJson($targetChatbot, [
            'message' => 'First counted message this month',
        ], $allowedSession)->assertOk();

        $this->assertDatabaseCount('chat_logs', 4);
        $this->assertDatabaseHas('chat_logs', [
            'chatbot_id' => $targetChatbot->id,
            'session_id' => $allowedSession,
            'role' => 'user',
        ]);
        $this->assertDatabaseHas('chat_logs', [
            'chatbot_id' => $targetChatbot->id,
            'session_id' => $allowedSession,
            'role' => 'bot',
        ]);
        $this->assertFalse($user->canSendChatMessage());
    }

    public function test_monthly_quota_resets_at_midnight_in_kuala_lumpur(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-31 16:30:00', 'UTC'));

        try {
            $user = $this->subscribedUserWithMonthlyMessageLimit(1);
            $chatbot = $this->chatbotFor($user);

            $previousMonthLog = ChatLog::create([
                'chatbot_id' => $chatbot->id,
                'session_id' => 'july-business-month',
                'message' => 'Mesej bulan Julai',
                'role' => 'user',
            ]);
            $previousMonthLog->forceFill([
                'created_at' => Carbon::parse('2026-07-15 12:00:00', 'UTC'),
            ])->saveQuietly();

            $this->assertTrue($user->canSendChatMessage());

            $currentMonthLog = ChatLog::create([
                'chatbot_id' => $chatbot->id,
                'session_id' => 'august-business-month',
                'message' => 'Mesej bulan Ogos',
                'role' => 'user',
            ]);
            $currentMonthLog->forceFill([
                'created_at' => Carbon::parse('2026-07-31 16:15:00', 'UTC'),
            ])->saveQuietly();

            $this->assertFalse($user->canSendChatMessage());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_unlimited_message_plan_allows_chat_and_writes_one_user_and_one_bot_row(): void
    {
        $user = $this->subscribedUserWithMonthlyMessageLimit(-1);
        $chatbot = $this->chatbotFor($user);

        $this->postWidgetJson($chatbot, [
            'message' => 'Unlimited message',
        ], $unlimitedSession)->assertOk();

        $this->assertDatabaseCount('chat_logs', 2);
        $this->assertDatabaseHas('chat_logs', [
            'chatbot_id' => $chatbot->id,
            'session_id' => $unlimitedSession,
            'role' => 'user',
        ]);
        $this->assertDatabaseHas('chat_logs', [
            'chatbot_id' => $chatbot->id,
            'session_id' => $unlimitedSession,
            'role' => 'bot',
        ]);
    }

    public function test_widget_chat_bounds_untrusted_user_agent_before_persisting(): void
    {
        $user = $this->subscribedUserWithMonthlyMessageLimit(-1);
        $chatbot = $this->chatbotFor($user);

        $this->postWidgetJson(
            $chatbot,
            ['message' => 'Metadata bounded'],
            $boundedSession,
            ['User-Agent' => str_repeat('A', 1000)],
        )->assertOk();

        $userLog = ChatLog::query()
            ->where('session_id', $boundedSession)
            ->where('role', 'user')
            ->firstOrFail();

        $this->assertSame(255, strlen((string) $userLog->user_agent));
    }

    private function freePlanUser(): User
    {
        Plan::create([
            'name' => 'Free',
            'slug' => 'free',
            'chatbot_limit' => 1,
        ]);

        return User::factory()->create();
    }

    private function subscribedUserWithMonthlyMessageLimit(int $monthlyMessages): User
    {
        $plan = Plan::create([
            'name' => 'Message Plan '.$monthlyMessages,
            'slug' => 'message-plan-'.$monthlyMessages,
            'monthly_messages' => $monthlyMessages,
        ]);
        $user = User::factory()->create();

        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'provider' => 'system',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        return $user;
    }

    private function chatbotFor(User $user): Chatbot
    {
        return Chatbot::create([
            'user_id' => $user->id,
            'name' => 'Quota Bot',
        ]);
    }

    private function postWidgetJson(
        Chatbot $chatbot,
        array $payload,
        ?string &$sessionId = null,
        array $headers = [],
    ): TestResponse {
        $origin = 'https://widget.example.test';
        $config = $this->withHeader('Origin', $origin)
            ->getJson(route('api.widget.config', $chatbot->api_key))
            ->assertOk();
        $sessionId = $config->json('widget_session_id');
        $payload['session_id'] = $sessionId;
        $payload['widget_ticket'] = $config->json('widget_ticket');

        return $this->withHeaders(['Origin' => $origin, ...$headers])
            ->postJson(route('api.chat', $chatbot->api_key), $payload);
    }
}

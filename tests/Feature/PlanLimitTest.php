<?php

namespace Tests\Feature;

use App\Models\Chatbot;
use App\Models\ChatLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->postJson(route('api.chat', $targetChatbot->api_key), [
            'message' => 'Over limit',
            'session_id' => 'new-session',
        ])
            ->assertStatus(429)
            ->assertJson(['error' => 'Monthly message limit reached']);

        $this->assertDatabaseCount('chat_logs', 1);
        $this->assertDatabaseMissing('chat_logs', [
            'session_id' => 'new-session',
        ]);
    }

    public function test_message_limit_is_rechecked_after_the_owner_lock_is_acquired(): void
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

            if ($matchingOwnerRetrievals !== 2) {
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

        $this->postJson(route('api.chat', $chatbot->api_key), [
            'message' => 'Too late',
            'session_id' => 'too-late-session',
        ])
            ->assertStatus(429)
            ->assertJson(['error' => 'Monthly message limit reached']);

        $this->assertSame(2, $matchingOwnerRetrievals);
        $this->assertDatabaseCount('chat_logs', 1);
        $this->assertDatabaseHas('chat_logs', [
            'session_id' => 'competing-session',
            'role' => 'user',
        ]);
        $this->assertDatabaseMissing('chat_logs', [
            'session_id' => 'too-late-session',
        ]);
    }

    public function test_chat_log_writes_roll_back_when_response_matching_fails(): void
    {
        $user = $this->subscribedUserWithMonthlyMessageLimit(1);
        $chatbot = $this->chatbotFor($user);
        $failBotWrite = true;

        ChatLog::creating(function (ChatLog $chatLog) use (&$failBotWrite): void {
            if (! $failBotWrite || $chatLog->session_id !== 'atomic-session' || $chatLog->role !== 'bot') {
                return;
            }

            $failBotWrite = false;
            throw new \RuntimeException('Simulated response write failure.');
        });

        $this->withoutExceptionHandling();

        try {
            $this->postJson(route('api.chat', $chatbot->api_key), [
                'message' => 'Atomic request',
                'session_id' => 'atomic-session',
            ]);

            $this->fail('The simulated response write failure was not raised.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated response write failure.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('chat_logs', [
            'session_id' => 'atomic-session',
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

        $this->postJson(route('api.chat', $chatbot->api_key), [
            'message' => 'Admin over limit',
            'session_id' => 'admin-over-limit-session',
        ])
            ->assertStatus(429)
            ->assertJson(['error' => 'Monthly message limit reached']);

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

        $this->postJson(route('api.chat', $targetChatbot->api_key), [
            'message' => 'First counted message this month',
            'session_id' => 'allowed-session',
        ])->assertOk();

        $this->assertDatabaseCount('chat_logs', 4);
        $this->assertDatabaseHas('chat_logs', [
            'chatbot_id' => $targetChatbot->id,
            'session_id' => 'allowed-session',
            'role' => 'user',
        ]);
        $this->assertDatabaseHas('chat_logs', [
            'chatbot_id' => $targetChatbot->id,
            'session_id' => 'allowed-session',
            'role' => 'bot',
        ]);
        $this->assertFalse($user->canSendChatMessage());
    }

    public function test_unlimited_message_plan_allows_chat_and_writes_one_user_and_one_bot_row(): void
    {
        $user = $this->subscribedUserWithMonthlyMessageLimit(-1);
        $chatbot = $this->chatbotFor($user);

        $this->postJson(route('api.chat', $chatbot->api_key), [
            'message' => 'Unlimited message',
            'session_id' => 'unlimited-session',
        ])->assertOk();

        $this->assertDatabaseCount('chat_logs', 2);
        $this->assertDatabaseHas('chat_logs', [
            'chatbot_id' => $chatbot->id,
            'session_id' => 'unlimited-session',
            'role' => 'user',
        ]);
        $this->assertDatabaseHas('chat_logs', [
            'chatbot_id' => $chatbot->id,
            'session_id' => 'unlimited-session',
            'role' => 'bot',
        ]);
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
}

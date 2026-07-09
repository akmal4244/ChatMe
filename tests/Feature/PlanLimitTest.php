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

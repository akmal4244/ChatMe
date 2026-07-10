<?php

namespace Tests\Feature;

use App\Models\Chatbot;
use App\Models\KnowledgeItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatbotAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_edit_another_users_chatbot(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $chatbot = Chatbot::create(['user_id' => $owner->id, 'name' => 'Private Bot']);

        $this->actingAs($attacker)
            ->get(route('chatbots.edit', $chatbot))
            ->assertForbidden();
    }

    public function test_user_cannot_update_another_users_chatbot(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $chatbot = Chatbot::create(['user_id' => $owner->id, 'name' => 'Private Bot']);

        $this->actingAs($attacker)
            ->put(route('chatbots.update', $chatbot), ['name' => 'Taken Over'])
            ->assertForbidden();

        $this->assertDatabaseHas('chatbots', [
            'id' => $chatbot->id,
            'user_id' => $owner->id,
            'name' => 'Private Bot',
        ]);
    }

    public function test_user_cannot_delete_another_users_chatbot(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $chatbot = Chatbot::create(['user_id' => $owner->id, 'name' => 'Private Bot']);

        $this->actingAs($attacker)
            ->delete(route('chatbots.destroy', $chatbot))
            ->assertForbidden();

        $this->assertDatabaseHas('chatbots', ['id' => $chatbot->id]);
    }

    public function test_user_cannot_access_another_users_knowledge_base(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $chatbot = Chatbot::create(['user_id' => $owner->id, 'name' => 'Private Bot']);

        $this->actingAs($attacker)
            ->get(route('knowledge.index', $chatbot))
            ->assertForbidden();

        $this->actingAs($attacker)
            ->post(route('knowledge.store', $chatbot), [
                'question' => 'Stolen?',
                'answer' => 'No.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('knowledge_items', 0);
    }

    public function test_knowledge_search_never_returns_items_from_another_chatbot(): void
    {
        $user = User::factory()->create();
        $otherOwner = User::factory()->create();
        $chatbot = Chatbot::create(['user_id' => $user->id, 'name' => 'My Bot']);
        $otherChatbot = Chatbot::create(['user_id' => $otherOwner->id, 'name' => 'Other Bot']);
        KnowledgeItem::create([
            'chatbot_id' => $otherChatbot->id,
            'question' => 'Unrelated question',
            'answer' => 'CROSS_TENANT_SECRET_NEEDLE',
        ]);

        $this->actingAs($user)
            ->get(route('knowledge.index', ['chatbot' => $chatbot, 'search' => 'SECRET_NEEDLE']))
            ->assertOk()
            ->assertDontSee('CROSS_TENANT_SECRET_NEEDLE');
    }

    public function test_admin_can_manage_another_users_chatbot(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $chatbot = Chatbot::create(['user_id' => $owner->id, 'name' => 'Managed Bot']);

        $this->actingAs($admin)
            ->get(route('chatbots.edit', $chatbot))
            ->assertOk();

        $this->actingAs($admin)
            ->put(route('chatbots.update', $chatbot), ['name' => 'Admin Updated'])
            ->assertRedirect(route('chatbots.index'));

        $this->assertDatabaseHas('chatbots', [
            'id' => $chatbot->id,
            'name' => 'Admin Updated',
        ]);
    }

    public function test_user_cannot_use_secondary_actions_on_another_users_chatbot(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $chatbot = Chatbot::create(['user_id' => $owner->id, 'name' => 'Private Bot']);

        $this->actingAs($attacker)->get(route('chatbots.show', $chatbot))->assertForbidden();
        $this->actingAs($attacker)->post(route('chatbots.toggle', $chatbot))->assertForbidden();
        $this->actingAs($attacker)->get(route('chatbots.embed', $chatbot))->assertForbidden();
        $this->actingAs($attacker)->post(route('chatbots.regenerate-key', $chatbot))->assertForbidden();
    }

    public function test_owner_can_regenerate_a_prefixed_api_key_and_the_old_widget_url_stops_working(): void
    {
        $owner = User::factory()->create();
        $chatbot = Chatbot::create(['user_id' => $owner->id, 'name' => 'Rotating Bot']);
        $oldKey = $chatbot->api_key;

        $this->get(route('widget.script', $oldKey))->assertOk();

        $this->actingAs($owner)
            ->post(route('chatbots.regenerate-key', $chatbot))
            ->assertRedirect()
            ->assertSessionHas('success');

        $newKey = $chatbot->fresh()->api_key;

        $this->assertNotSame($oldKey, $newKey);
        $this->assertMatchesRegularExpression('/^cm_[A-Za-z0-9]{32}$/', $newKey);
        $this->get(route('widget.script', $oldKey))->assertNotFound();
        $this->get(route('widget.script', $newKey))->assertOk();
    }

    public function test_user_cannot_mutate_an_item_through_a_different_chatbot(): void
    {
        $user = User::factory()->create();
        $otherOwner = User::factory()->create();
        $chatbot = Chatbot::create(['user_id' => $user->id, 'name' => 'My Bot']);
        $otherChatbot = Chatbot::create(['user_id' => $otherOwner->id, 'name' => 'Other Bot']);
        $otherItem = KnowledgeItem::create([
            'chatbot_id' => $otherChatbot->id,
            'question' => 'Original question',
            'answer' => 'Original answer',
        ]);

        $this->actingAs($user)
            ->put(route('knowledge.update', [$chatbot, $otherItem]), [
                'question' => 'Changed question',
                'answer' => 'Changed answer',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('knowledge_items', [
            'id' => $otherItem->id,
            'question' => 'Original question',
            'answer' => 'Original answer',
        ]);
    }

    public function test_owner_can_update_an_item_in_their_chatbot(): void
    {
        $user = User::factory()->create();
        $chatbot = Chatbot::create(['user_id' => $user->id, 'name' => 'My Bot']);
        $item = KnowledgeItem::create([
            'chatbot_id' => $chatbot->id,
            'question' => 'Old question',
            'answer' => 'Old answer',
        ]);

        $this->actingAs($user)
            ->put(route('knowledge.update', [$chatbot, $item]), [
                'question' => 'New question',
                'answer' => 'New answer',
            ])
            ->assertRedirect(route('knowledge.index', $chatbot));

        $this->assertDatabaseHas('knowledge_items', [
            'id' => $item->id,
            'question' => 'New question',
            'answer' => 'New answer',
        ]);
    }
}

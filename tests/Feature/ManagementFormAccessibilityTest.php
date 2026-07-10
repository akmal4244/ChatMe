<?php

namespace Tests\Feature;

use App\Models\Chatbot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementFormAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_chatbot_validation_error_is_linked_to_its_field(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('chatbots.create'))
            ->post(route('chatbots.store'), ['name' => ''])
            ->assertRedirect(route('chatbots.create'))
            ->assertSessionHasErrors('name');

        $this->get(route('chatbots.create'))->assertOk()
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="name-error"', false)
            ->assertSee('id="name-error"', false);
    }

    public function test_chatbot_required_display_fields_reject_blank_values_and_edit_can_deactivate(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('chatbots.store'), [
            'name' => 'Invalid Display Bot',
            'bot_name' => '',
            'welcome_message' => '',
        ])->assertSessionHasErrors(['bot_name', 'welcome_message']);

        $chatbot = Chatbot::create(['user_id' => $user->id, 'name' => 'Active Bot']);

        $this->actingAs($user)->put(route('chatbots.update', $chatbot), [
            'name' => 'Inactive Bot',
            'is_active' => '0',
        ])->assertRedirect(route('chatbots.index'));

        $this->assertFalse($chatbot->fresh()->is_active);
    }

    public function test_knowledge_validation_error_reopens_the_right_dialog_and_links_fields(): void
    {
        $user = User::factory()->create();
        $chatbot = Chatbot::create(['user_id' => $user->id, 'name' => 'Accessible Bot']);

        $this->actingAs($user)
            ->from(route('knowledge.index', $chatbot))
            ->post(route('knowledge.store', $chatbot), [
                'knowledge_form' => 'add',
                'question' => '',
                'answer' => '',
            ])
            ->assertRedirect(route('knowledge.index', $chatbot))
            ->assertSessionHasErrors(['question', 'answer']);

        $this->get(route('knowledge.index', $chatbot))->assertOk()
            ->assertSee('aria-describedby="add-question-error"', false)
            ->assertSee('id="add-question-error"', false)
            ->assertSee('id="add-answer-error"', false)
            ->assertSee("failedForm === 'add'", false);
    }

    public function test_admin_role_change_requires_a_named_confirmation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['name' => 'Pengguna Sasaran']);

        $this->actingAs($admin)->get(route('admin.users'))->assertOk()
            ->assertSee('onsubmit="return confirm(', false)
            ->assertSee('Jadikan Pengguna Sasaran sebagai pentadbir?', false);

        $this->assertFalse((bool) $target->is_admin);
    }
}

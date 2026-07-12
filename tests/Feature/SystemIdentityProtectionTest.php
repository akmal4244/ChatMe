<?php

namespace Tests\Feature;

use App\Models\Chatbot;
use App\Models\KnowledgeItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemIdentityProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_cannot_change_roles_on_system_users(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);

        foreach (['primary_admin', 'homepage_owner'] as $role) {
            $systemUser = User::factory()->create(['is_admin' => $role === 'primary_admin']);
            $systemUser->forceFill(['system_role' => $role])->save();
            $originalAdmin = $systemUser->is_admin;

            $this->actingAs($actor)
                ->post(route('admin.users.toggle-admin', $systemUser))
                ->assertRedirect()
                ->assertSessionHas('error', 'Peranan akaun sistem tidak boleh diubah melalui panel pentadbir.');

            $this->assertSame($originalAdmin, $systemUser->fresh()->is_admin);
        }
    }

    public function test_system_user_email_is_immutable_while_other_profile_fields_remain_editable(): void
    {
        config(['chatme.admin.email' => 'primary.admin@example.test']);
        $admin = User::factory()->create([
            'email' => 'primary.admin@example.test',
            'password' => 'password',
            'is_admin' => true,
        ]);
        $admin->forceFill(['system_role' => 'primary_admin'])->save();

        $this->actingAs($admin)->from(route('profile.edit'))->patch(route('profile.update'), [
            'name' => 'Cubaan Ubah Identiti',
            'email' => 'other@example.test',
            'current_password' => 'password',
            'company' => null,
            'website' => null,
        ])->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors([
                'email' => 'Alamat e-mel akaun sistem tidak boleh diubah.',
            ]);

        $this->assertSame('primary.admin@example.test', $admin->fresh()->email);
    }

    public function test_system_chatbot_is_readable_but_immutable_through_management_routes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $chatbot = Chatbot::create(['user_id' => $owner->id, 'name' => 'Homepage ChatMe']);
        $chatbot->forceFill(['system_role' => 'homepage_chatbot'])->save();
        $knowledge = KnowledgeItem::create([
            'chatbot_id' => $chatbot->id,
            'question' => 'Soalan sistem',
            'answer' => 'Jawapan sistem',
        ]);

        $this->actingAs($admin)->get(route('chatbots.edit', $chatbot))->assertOk();

        $this->put(route('chatbots.update', $chatbot), ['name' => 'Diubah'])->assertForbidden();
        $this->post(route('chatbots.toggle', $chatbot))->assertForbidden();
        $this->post(route('chatbots.regenerate-key', $chatbot))->assertForbidden();
        $this->post(route('chatbots.developer-token', $chatbot), [
            'current_password' => 'password',
        ])->assertForbidden();
        $this->delete(route('knowledge.destroy', [$chatbot, $knowledge]))->assertForbidden();
        $this->delete(route('chatbots.destroy', $chatbot))->assertForbidden();

        $this->assertSame('Homepage ChatMe', $chatbot->fresh()->name);
        $this->assertDatabaseHas('knowledge_items', ['id' => $knowledge->id]);
        $this->assertDatabaseHas('chatbots', ['id' => $chatbot->id]);
    }
}

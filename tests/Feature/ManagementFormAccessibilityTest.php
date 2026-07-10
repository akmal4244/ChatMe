<?php

namespace Tests\Feature;

use App\Models\Chatbot;
use App\Models\KnowledgeItem;
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
            ->assertSee('Jadikan Pengguna Sasaran sebagai pentadbir? Pengguna ini akan mendapat akses ke panel pentadbir.', false);

        $this->assertFalse((bool) $target->is_admin);
    }

    public function test_management_table_actions_use_named_icons(): void
    {
        $user = User::factory()->create();
        $chatbot = Chatbot::create([
            'user_id' => $user->id,
            'name' => 'Chatbot Ikon',
        ]);
        KnowledgeItem::create([
            'chatbot_id' => $chatbot->id,
            'question' => 'Apakah waktu operasi?',
            'answer' => 'Kami beroperasi setiap hari.',
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('aria-label="Sunting chatbot Chatbot Ikon"', false)
            ->assertSee('aria-label="Pasang Chatbot Ikon di laman web"', false)
            ->assertSee('aria-label="Urus soal jawab Chatbot Ikon"', false)
            ->assertSee('class="ph ph-pencil-simple"', false)
            ->assertSee('class="ph ph-code"', false)
            ->assertSee('class="ph ph-books"', false);

        $this->get(route('chatbots.index'))->assertOk()
            ->assertSee('aria-label="Sunting chatbot Chatbot Ikon"', false)
            ->assertSee('aria-label="Urus soal jawab Chatbot Ikon"', false)
            ->assertSee('aria-label="Pasang Chatbot Ikon di laman web"', false)
            ->assertSee('aria-label="Padam chatbot Chatbot Ikon"', false)
            ->assertSee('class="ph ph-trash"', false);

        $this->get(route('knowledge.index', $chatbot))->assertOk()
            ->assertSee('aria-label="Sunting soal jawab: Apakah waktu operasi?"', false)
            ->assertSee('aria-label="Padam soal jawab: Apakah waktu operasi?"', false)
            ->assertSee('class="ph ph-pencil-simple"', false)
            ->assertSee('class="ph ph-trash"', false);

        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['name' => 'Pengguna Sasaran']);

        $this->actingAs($admin)->get(route('admin.users'))->assertOk()
            ->assertSee('aria-label="Jadikan Pengguna Sasaran sebagai pentadbir"', false)
            ->assertSee('class="ph ph-shield-plus"', false);

        $target->update(['is_admin' => true]);

        $this->get(route('admin.users'))->assertOk()
            ->assertSee('aria-label="Buang peranan pentadbir daripada Pengguna Sasaran"', false)
            ->assertSee('class="ph ph-shield-slash"', false);
    }

    public function test_management_views_use_consistent_plain_malay_terms(): void
    {
        $files = [
            resource_path('views/layouts/app.blade.php'),
            resource_path('views/dashboard.blade.php'),
            ...glob(resource_path('views/chatbots/*.blade.php')),
            ...glob(resource_path('views/knowledge/*.blade.php')),
            ...glob(resource_path('views/admin/*.blade.php')),
        ];
        $source = implode("\n", array_map(fn (string $file): string => file_get_contents($file), $files));

        foreach ([
            'Papan Pemuka',
            'Chatbot Baru',
            'Edit Chatbot',
            'Teks Placeholder',
            'URL Avatar',
            'Senarai Putih Domain',
            'Kod Benam',
            'Pangkalan Pengetahuan',
            'Item Pengetahuan',
            'Buang Admin',
            'Jadikan Admin',
            "'N/A'",
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }

        foreach ([
            'Papan pemuka',
            'Cipta chatbot baharu',
            'Sunting chatbot',
            'Teks petunjuk dalam kotak mesej',
            'Pautan gambar profil',
            'Laman web yang dibenarkan',
            'Kod pemasangan',
            'Soal jawab chatbot',
            'Pentadbir',
            'Tiada maklumat',
        ] as $required) {
            $this->assertStringContainsString($required, $source);
        }
    }

    public function test_standalone_knowledge_forms_link_every_validation_error_to_its_field(): void
    {
        $user = User::factory()->create();
        $chatbot = Chatbot::create(['user_id' => $user->id, 'name' => 'Accessible Bot']);
        $item = KnowledgeItem::create([
            'chatbot_id' => $chatbot->id,
            'question' => 'Soalan asal',
            'answer' => 'Jawapan asal',
        ]);

        $errors = [
            'question' => ['Soalan diperlukan.'],
            'answer' => ['Jawapan diperlukan.'],
            'category' => ['Kategori terlalu panjang.'],
            'tags' => ['Tag terlalu panjang.'],
        ];
        $create = $this->withViewErrors($errors)
            ->view('knowledge.create', compact('chatbot'));

        foreach (['question', 'answer', 'category', 'tags'] as $field) {
            $create
                ->assertSee('aria-invalid="true"', false)
                ->assertSee('aria-describedby="'.$field.'-error"', false)
                ->assertSee('id="'.$field.'-error"', false);
        }

        $edit = $this->withViewErrors($errors)
            ->view('knowledge.edit', ['chatbot' => $chatbot, 'knowledge' => $item]);

        foreach (['question', 'answer', 'category', 'tags'] as $field) {
            $edit
                ->assertSee('aria-invalid="true"', false)
                ->assertSee('aria-describedby="'.$field.'-error"', false)
                ->assertSee('id="'.$field.'-error"', false);
        }
    }
}

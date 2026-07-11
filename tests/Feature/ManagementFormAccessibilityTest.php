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
            ->assertSee('data-confirm-title="Jadikan pentadbir?"', false)
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
            ->assertSee('aria-label="Uji chatbot Chatbot Ikon"', false)
            ->assertSee('aria-label="Sunting chatbot Chatbot Ikon"', false)
            ->assertSee('aria-label="Pasang Chatbot Ikon di laman web"', false)
            ->assertSee('aria-label="Urus soal jawab Chatbot Ikon"', false)
            ->assertSee('aria-label="Padam chatbot Chatbot Ikon"', false)
            ->assertSee('data-confirm-title="Padam chatbot?"', false)
            ->assertSee('data-confirm-text="Padam chatbot"', false)
            ->assertSee('class="ph ph-pencil-simple"', false)
            ->assertSee('class="ph ph-code"', false)
            ->assertSee('class="ph ph-books"', false)
            ->assertSee('class="ph ph-trash"', false);

        $this->get(route('chatbots.index'))->assertOk()
            ->assertSee('aria-label="Uji chatbot Chatbot Ikon"', false)
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

    public function test_chatbot_lists_render_the_owner_test_action_and_popup_contract(): void
    {
        $user = User::factory()->create();
        $chatbot = Chatbot::create([
            'user_id' => $user->id,
            'name' => 'Chatbot Ujian',
            'bot_name' => 'Pembantu Ujian',
            'welcome_message' => 'Selamat datang ke mod ujian.',
        ]);
        $partialPath = resource_path('views/partials/chatbot-tester.blade.php');

        $this->assertFileExists($partialPath);

        foreach ([route('dashboard'), route('chatbots.index')] as $url) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('data-chatbot-test', $html);
            $this->assertStringContainsString('aria-label="Uji chatbot Chatbot Ujian"', $html);
            $this->assertStringContainsString('data-test-url="'.route('chatbots.test-message', $chatbot).'"', $html);
            $this->assertStringContainsString('class="ph ph-chat-circle-dots"', $html);
            $this->assertStringContainsString('id="chatbot-tester-modal"', $html);
            $this->assertStringContainsString('Mod ujian — mesej tidak dikira dalam kuota', $html);
            $this->assertSame(1, substr_count($html, 'id="chatbot-tester-modal"'));
        }

        $source = file_get_contents($partialPath);
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $this->assertStringContainsString("@push('modals')", $source);
        $this->assertStringContainsString("@stack('modals')", $layout);
        $this->assertStringContainsString("document.addEventListener('click'", $source);
        $this->assertStringContainsString("closest('[data-chatbot-test]')", $source);
        $this->assertStringContainsString("'X-CSRF-TOKEN'", $source);
        $this->assertStringContainsString('requestBusy', $source);
        $this->assertStringContainsString('fetch(endpoint', $source);
        $this->assertStringContainsString('textContent', $source);
        $this->assertStringContainsString('window.showToast', $source);
        $this->assertStringContainsString("event.key === 'Escape'", $source);
        $this->assertStringContainsString('returnFocus?.focus()', $source);
        $this->assertStringNotContainsString('.innerHTML', $source);
    }

    public function test_risky_management_actions_use_the_shared_confirmation_contract(): void
    {
        $sources = implode("\n", array_map('file_get_contents', [
            resource_path('views/dashboard.blade.php'),
            resource_path('views/chatbots/index.blade.php'),
            resource_path('views/knowledge/index.blade.php'),
            resource_path('views/admin/users.blade.php'),
            resource_path('views/chatbots/embed.blade.php'),
        ]));

        $this->assertStringNotContainsString('window.confirm(', $sources);
        $this->assertStringNotContainsString('onsubmit="return confirm(', $sources);
        $this->assertGreaterThanOrEqual(5, substr_count($sources, 'data-confirm-title='));

        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $this->assertStringContainsString("document.addEventListener('submit'", $layout);
        $this->assertStringContainsString("form.matches('form[data-confirm-title]')", $layout);
        $this->assertStringContainsString("form.dataset.confirmed = 'true'", $layout);
        $this->assertStringContainsString('form.requestSubmit()', $layout);
    }

    public function test_embed_copy_feedback_uses_global_popup_notifications(): void
    {
        $source = file_get_contents(resource_path('views/chatbots/embed.blade.php'));

        $this->assertStringContainsString("window.showToast('Teks berjaya disalin.', 'success')", $source);
        $this->assertStringContainsString("window.showToast('Teks tidak dapat disalin. Sila salin secara manual.', 'error')", $source);
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

<?php

namespace Tests\Feature;

use App\Models\Chatbot;
use App\Models\User;
use Database\Seeders\HomepageChatbotSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatbotSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_store_and_update_bounded_ai_style_and_fallback_settings(): void
    {
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('chatbots.store'), [
                'name' => 'AI Bot',
                'system_prompt' => 'Jawab dengan nada profesional dan mesra.',
                'fallback_message' => 'Maaf, maklumat itu belum tersedia.',
            ])
            ->assertRedirect(route('chatbots.index'))
            ->assertSessionHasNoErrors();

        $chatbot = Chatbot::where('name', 'AI Bot')->firstOrFail();
        $this->assertSame('Jawab dengan nada profesional dan mesra.', $chatbot->system_prompt);
        $this->assertSame('Maaf, maklumat itu belum tersedia.', $chatbot->fallback_message);
        $this->assertSame('Maaf, maklumat itu belum tersedia.', $chatbot->fallbackResponse());

        $this->actingAs($user)
            ->put(route('chatbots.update', $chatbot), [
                'name' => 'AI Bot',
                'system_prompt' => 'Jawab secara ringkas.',
                'fallback_message' => 'Sila cuba soalan lain.',
                'is_active' => true,
            ])
            ->assertRedirect(route('chatbots.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Jawab secara ringkas.', $chatbot->fresh()->system_prompt);
        $this->assertSame('Sila cuba soalan lain.', $chatbot->fresh()->fallbackResponse());
    }

    public function test_ai_style_and_fallback_lengths_are_rejected_at_the_boundary(): void
    {
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('chatbots.store'), [
                'name' => 'Oversized Bot',
                'system_prompt' => str_repeat('a', 1001),
                'fallback_message' => str_repeat('b', 501),
            ])
            ->assertSessionHasErrors(['system_prompt', 'fallback_message']);

        $this->assertDatabaseMissing('chatbots', ['name' => 'Oversized Bot']);
    }

    public function test_chatbot_forms_describe_the_ai_style_as_bounded_by_active_knowledge(): void
    {
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();
        $chatbot = Chatbot::create(['user_id' => $user->id, 'name' => 'Copy Bot']);

        $this->actingAs($user)
            ->get(route('chatbots.create'))
            ->assertOk()
            ->assertSee('Gaya jawapan AI')
            ->assertSee('Fakta tetap terhad kepada soal jawab aktif.')
            ->assertSee('Jawapan apabila tiada padanan')
            ->assertDontSee('Cara chatbot perlu menjawab');

        $this->actingAs($user)
            ->get(route('chatbots.edit', $chatbot))
            ->assertOk()
            ->assertSee('Gaya jawapan AI')
            ->assertSee('Fakta tetap terhad kepada soal jawab aktif.')
            ->assertSee('Jawapan apabila tiada padanan')
            ->assertDontSee('Cara chatbot perlu menjawab');
    }

    public function test_homepage_seeder_sets_a_stable_local_fallback(): void
    {
        $this->seed(PlanSeeder::class);
        $this->seed(HomepageChatbotSeeder::class);

        $chatbot = Chatbot::where('slug', 'chatme-homepage')->firstOrFail();

        $this->assertSame(
            'Maaf, maklumat itu belum tersedia. Cuba tanya tentang pelan, pembayaran atau fungsi ChatMe.',
            $chatbot->fallbackResponse(),
        );
    }

    public function test_blank_fallback_uses_one_repeatable_default(): void
    {
        $chatbot = Chatbot::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Default Fallback Bot',
            'fallback_message' => '   ',
        ]);

        $expected = 'Maaf, saya belum menemui jawapan yang tepat. Cuba gunakan perkataan lain.';

        $this->assertSame($expected, $chatbot->fallbackResponse());
        $this->assertSame($expected, $chatbot->fallbackResponse());
    }
}

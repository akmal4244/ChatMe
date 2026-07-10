<?php

namespace Tests\Feature;

use App\Models\Chatbot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MalayDefaultMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_exact_english_defaults_are_localized_and_rollback_is_forward_only(): void
    {
        $user = User::factory()->create();
        $todayDefault = Chatbot::create([
            'user_id' => $user->id,
            'name' => 'Today Default',
            'welcome_message' => 'Hello! How can I help you today?',
            'placeholder_text' => 'Type your message...',
        ]);
        $shortDefault = Chatbot::create([
            'user_id' => $user->id,
            'name' => 'Short Default',
            'welcome_message' => 'Hello! How can I help you?',
            'placeholder_text' => 'Type your message...',
        ]);
        $custom = Chatbot::create([
            'user_id' => $user->id,
            'name' => 'Custom Copy',
            'welcome_message' => 'Welcome to our custom support desk.',
            'placeholder_text' => 'Ask our team here...',
        ]);

        $migration = require database_path('migrations/2026_07_10_000004_localize_chatbot_defaults.php');
        $migration->up();

        foreach ([$todayDefault, $shortDefault] as $chatbot) {
            $chatbot->refresh();
            $this->assertSame('Helo! Bagaimana saya boleh membantu anda?', $chatbot->welcome_message);
            $this->assertSame('Taip mesej anda...', $chatbot->placeholder_text);
        }

        $custom->refresh();
        $this->assertSame('Welcome to our custom support desk.', $custom->welcome_message);
        $this->assertSame('Ask our team here...', $custom->placeholder_text);

        $migration->down();

        $this->assertSame('Helo! Bagaimana saya boleh membantu anda?', $todayDefault->fresh()->welcome_message);
        $this->assertSame('Taip mesej anda...', $todayDefault->fresh()->placeholder_text);
        $this->assertSame('Welcome to our custom support desk.', $custom->fresh()->welcome_message);
        $this->assertSame('Ask our team here...', $custom->fresh()->placeholder_text);
    }
}

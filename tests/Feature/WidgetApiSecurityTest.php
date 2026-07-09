<?php

namespace Tests\Feature;

use App\Models\Chatbot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidgetApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_whitelist_rejects_a_lookalike_origin(): void
    {
        $chatbot = $this->chatbotWithWhitelist('trusted.example');

        $this->withHeader('Origin', 'https://trusted.example.attacker.test')
            ->getJson(route('api.widget.config', $chatbot->api_key))
            ->assertForbidden()
            ->assertJson(['error' => 'Domain not allowed']);
    }

    public function test_domain_whitelist_accepts_the_exact_host_and_its_subdomains(): void
    {
        $chatbot = $this->chatbotWithWhitelist('trusted.example');

        $this->withHeader('Origin', 'https://trusted.example')
            ->getJson(route('api.widget.config', $chatbot->api_key))
            ->assertOk();

        $this->withHeader('Origin', 'https://support.trusted.example')
            ->getJson(route('api.widget.config', $chatbot->api_key))
            ->assertOk();
    }

    public function test_chat_endpoint_rejects_a_forbidden_origin_without_logging_messages(): void
    {
        $chatbot = $this->chatbotWithWhitelist('trusted.example');

        $this->withHeader('Origin', 'https://attacker.test')
            ->postJson(route('api.chat', $chatbot->api_key), [
                'message' => 'Hello',
                'session_id' => 'test-session',
            ])
            ->assertForbidden()
            ->assertJson(['error' => 'Domain not allowed']);

        $this->assertDatabaseCount('chat_logs', 0);
    }

    private function chatbotWithWhitelist(string $whitelist): Chatbot
    {
        $user = User::factory()->create();

        return Chatbot::create([
            'user_id' => $user->id,
            'name' => 'Secured Widget',
            'domain_whitelist' => $whitelist,
        ]);
    }
}

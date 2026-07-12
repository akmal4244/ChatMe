<?php

namespace Tests\Feature;

use App\Models\Chatbot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
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
            ->assertJson(['error' => 'Domain ini tidak dibenarkan.']);
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
            ->assertJson(['error' => 'Domain ini tidak dibenarkan.']);

        $this->assertDatabaseCount('chat_logs', 0);
    }

    public function test_external_avatar_url_is_not_prefixed_with_the_storage_path(): void
    {
        $chatbot = $this->chatbotWithWhitelist('*');
        $chatbot->update(['avatar_url' => 'https://cdn.example.test/avatar.png']);

        $this->withHeader('Origin', 'https://site.example.test')
            ->getJson(route('api.widget.config', $chatbot->api_key))
            ->assertOk()
            ->assertJsonPath('avatar_url', 'https://cdn.example.test/avatar.png');

        $this->get(route('widget.script', $chatbot->api_key))
            ->assertOk()
            ->assertSee('https://cdn.example.test/avatar.png', false)
            ->assertDontSee('/storage/https://', false);
    }

    public function test_public_relative_avatar_uses_the_public_asset_url(): void
    {
        $chatbot = $this->chatbotWithWhitelist('*');
        $chatbot->update(['avatar_url' => 'akmal3d.png']);

        $avatarUrl = $this->withHeader('Origin', 'https://site.example.test')
            ->getJson(route('api.widget.config', $chatbot->api_key))
            ->assertOk()
            ->json('avatar_url');

        $this->assertStringEndsWith('/akmal3d.png', $avatarUrl);
        $this->assertStringNotContainsString('/storage/akmal3d.png', $avatarUrl);
    }

    public function test_default_avatar_does_not_force_https_in_an_http_environment(): void
    {
        config()->set('app.url', 'http://chatme.test');
        $chatbot = $this->chatbotWithWhitelist('*');

        $avatarUrl = $this->withHeader('Origin', 'https://site.example.test')
            ->getJson(route('api.widget.config', $chatbot->api_key))
            ->assertOk()
            ->json('avatar_url');

        $this->assertStringStartsWith('http://', $avatarUrl);
        $this->assertStringEndsWith('/akmal3d.png', $avatarUrl);
    }

    public function test_production_api_not_found_response_is_safe_and_in_malay(): void
    {
        config()->set('app.debug', false);

        $response = $this->getJson('/api/sumber-yang-tidak-wujud')
            ->assertNotFound()
            ->assertExactJson(['error' => 'Sumber yang diminta tidak ditemui.']);

        $json = $response->getContent();
        $this->assertStringNotContainsString('NotFoundHttpException', $json);
        $this->assertStringNotContainsString('vendor\\laravel', $json);
        $this->assertStringNotContainsString('trace', $json);
    }

    public function test_production_api_server_error_response_hides_exception_details(): void
    {
        config()->set('app.debug', false);
        Route::get('/api/ujian-ralat-selamat', function (): never {
            throw new RuntimeException('Maklumat dalaman yang tidak boleh didedahkan.');
        });

        $response = $this->getJson('/api/ujian-ralat-selamat')
            ->assertServerError()
            ->assertExactJson(['error' => 'Sistem menghadapi masalah. Sila cuba lagi sebentar lagi.']);

        $json = $response->getContent();
        $this->assertStringNotContainsString('RuntimeException', $json);
        $this->assertStringNotContainsString('Maklumat dalaman', $json);
        $this->assertStringNotContainsString('trace', $json);
    }

    public function test_widget_script_route_has_a_public_rate_limit(): void
    {
        $route = Route::getRoutes()->getByName('widget.script');

        $this->assertNotNull($route);
        $this->assertContains('throttle:120,1', $route->gatherMiddleware());
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

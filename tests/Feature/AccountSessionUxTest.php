<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AccountSessionUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_page_exposes_the_same_server_session_deadline_in_header_and_dom(): void
    {
        $this->travelTo(Carbon::parse('2026-07-12 10:00:00'));
        config()->set('session.lifetime', 120);
        $user = User::factory()->create();
        $expectedDeadline = now()->addMinutes(120)->timestamp;

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertHeader('X-Session-Expires-At', (string) $expectedDeadline)
            ->assertSee('id="session-expiry-config"', false)
            ->assertSee('data-expires-at="'.$expectedDeadline.'"', false)
            ->assertSee('data-warning-seconds="300"', false)
            ->assertSee('data-header-name="X-Session-Expires-At"', false)
            ->assertSee('data-login-url="'.route('login', ['session_expired' => 1]).'"', false);
    }

    public function test_session_countdown_uses_safe_text_updates_same_origin_fetch_refresh_and_expiry_redirect(): void
    {
        $source = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('.toast-message', $source);
        $this->assertStringContainsString('.textContent =', $source);
        $this->assertStringContainsString('Sesi anda akan tamat dalam', $source);
        $this->assertStringContainsString("window.showToast('Sesi anda akan tamat tidak lama lagi.', 'info', { duration: 0 })", $source);
        $this->assertStringContainsString('window.fetch = async', $source);
        $this->assertStringContainsString('requestUrl.origin === window.location.origin', $source);
        $this->assertStringContainsString('response.ok', $source);
        $this->assertStringContainsString('response.headers.get(headerName)', $source);
        $this->assertStringContainsString('removeWarningToast()', $source);
        $this->assertStringContainsString('window.location.replace(loginUrl)', $source);
        $this->assertStringContainsString('window.setInterval(updateSessionCountdown, 1000)', $source);
        $this->assertStringNotContainsString('.innerHTML', $source);
    }

    public function test_expired_login_query_and_419_page_expose_the_malay_recovery_message(): void
    {
        $this->get(route('login', ['session_expired' => 1]))
            ->assertOk()
            ->assertSee('Sesi anda telah tamat. Sila log masuk semula.');

        Route::get('/_test/419', fn () => response()->view('errors.419', [], 419));

        $this->get('/_test/419')->assertStatus(419)
            ->assertSee('Sesi telah tamat')
            ->assertSee('href="'.route('login', ['session_expired' => 1]).'"', false);
    }
}

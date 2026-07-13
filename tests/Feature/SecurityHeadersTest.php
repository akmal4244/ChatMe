<?php

namespace Tests\Feature;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_html_responses_use_a_nonce_csp_and_safe_browser_headers(): void
    {
        Plan::create(['name' => 'Free', 'slug' => 'free', 'price' => 0]);

        $response = $this->get('/')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-([^']+)'/", $csp);

        preg_match('/script-src ([^;]+)/', $csp, $scriptDirective);
        $this->assertStringNotContainsString("'unsafe-inline'", $scriptDirective[1] ?? '');

        preg_match("/'nonce-([^']+)'/", $csp, $nonceMatch);
        $nonce = $nonceMatch[1] ?? '';
        $this->assertGreaterThanOrEqual(22, strlen($nonce));

        preg_match_all('/<script\b([^>]*)>/i', $response->getContent(), $scripts);
        $this->assertNotEmpty($scripts[1]);
        foreach ($scripts[1] as $attributes) {
            $this->assertStringContainsString('nonce="'.$nonce.'"', $attributes);
        }
    }

    public function test_layouts_do_not_load_fonts_or_icons_from_third_party_cdns(): void
    {
        foreach (['app', 'guest'] as $layout) {
            $source = file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));

            $this->assertStringNotContainsString('fonts.googleapis.com', $source);
            $this->assertStringNotContainsString('fonts.gstatic.com', $source);
            $this->assertStringNotContainsString('unpkg.com', $source);
        }

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString("@import '@phosphor-icons/web/regular';", $css);
    }

    public function test_google_callback_never_leaks_oauth_query_values_through_cache_or_referrers(): void
    {
        config()->set('services.google.enabled', false);

        $response = $this->get(route('auth.google.callback', [
            'code' => 'synthetic-one-time-code',
            'state' => 'synthetic-one-time-state',
        ]));

        $response->assertRedirect(route('login'))
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $cacheControl = (string) $response->headers->get('Cache-Control');
        foreach (['no-store', 'private', 'max-age=0', 'must-revalidate'] as $directive) {
            $this->assertStringContainsString($directive, $cacheControl);
        }
    }
}

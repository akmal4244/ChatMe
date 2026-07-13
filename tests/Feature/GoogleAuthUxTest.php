<?php

namespace Tests\Feature;

use Tests\TestCase;

class GoogleAuthUxTest extends TestCase
{
    public function test_google_button_is_visible_on_login_and_register_only_when_configuration_is_ready(): void
    {
        $this->readyGoogleConfiguration();

        foreach (['/login', '/register'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSeeText('Teruskan dengan Google')
                ->assertSeeText('atau teruskan dengan e-mel')
                ->assertSee('class="google-auth-button"', false)
                ->assertSee('href="'.route('auth.google.redirect').'"', false)
                ->assertSee('src="'.asset('images/google-g-logo.svg').'"', false);
        }
    }

    public function test_google_button_is_hidden_from_both_auth_pages_for_every_not_ready_state(): void
    {
        $notReadyConfigurations = [
            'feature disabled' => [
                'enabled' => false,
                'client_id' => 'ux-client-id',
                'client_secret' => 'ux-client-secret',
                'redirect' => 'https://chatme.test/auth/google/callback',
            ],
            'client id missing' => [
                'enabled' => true,
                'client_id' => null,
                'client_secret' => 'ux-client-secret',
                'redirect' => 'https://chatme.test/auth/google/callback',
            ],
            'client secret missing' => [
                'enabled' => true,
                'client_id' => 'ux-client-id',
                'client_secret' => null,
                'redirect' => 'https://chatme.test/auth/google/callback',
            ],
            'callback missing' => [
                'enabled' => true,
                'client_id' => 'ux-client-id',
                'client_secret' => 'ux-client-secret',
                'redirect' => null,
            ],
            'callback invalid' => [
                'enabled' => true,
                'client_id' => 'ux-client-id',
                'client_secret' => 'ux-client-secret',
                'redirect' => 'http://chatme.test/auth/google/callback',
            ],
        ];

        foreach ($notReadyConfigurations as $configuration) {
            config()->set('services.google', $configuration);

            foreach (['/login', '/register'] as $path) {
                $this->get($path)
                    ->assertOk()
                    ->assertDontSeeText('Teruskan dengan Google')
                    ->assertDontSeeText('atau teruskan dengan e-mel')
                    ->assertDontSee('href="'.route('auth.google.redirect').'"', false);
            }
        }
    }

    public function test_google_button_uses_a_local_non_executable_asset_without_remote_scripts_or_avatars(): void
    {
        $assetPath = public_path('images/google-g-logo.svg');

        $this->assertFileExists($assetPath);
        $svg = file_get_contents($assetPath);
        $this->assertIsString($svg);
        $this->assertMatchesRegularExpression('/<svg\b/i', $svg);
        $this->assertDoesNotMatchRegularExpression('/<script\b|<image\b/i', $svg);
        $this->assertDoesNotMatchRegularExpression('/(?:href|xlink:href)\s*=\s*["\']https?:/i', $svg);
        $this->assertStringContainsString('data-google-asset-version="2026-07-07"', $svg);
        $this->assertStringContainsString('conic-gradient', $svg);
        $this->assertSame(
            'cd71b652f7fa3c4540544f14ef384b1bbd1ab1c20769e8afc208ba0d05cd84f5',
            hash_file('sha256', $assetPath),
        );

        $fontPath = public_path('fonts/google-sans-medium-latin.woff2');
        $this->assertFileExists($fontPath);
        $font = file_get_contents($fontPath);
        $this->assertIsString($font);
        $this->assertStringStartsWith('wOF2', $font);
        $this->assertSame(
            'cfe998b7bf24e745210ca78289bfc0cc8c08022647d051e0ac235ec0648d8f5b',
            hash_file('sha256', $fontPath),
        );

        $this->readyGoogleConfiguration();

        foreach (['login', 'register'] as $view) {
            $source = file_get_contents(resource_path("views/auth/{$view}.blade.php"));
            $this->assertIsString($source);
            $this->assertStringContainsString("asset('images/google-g-logo.svg')", $source);
            $this->assertStringContainsString('aria-hidden="true"', $source);
            $this->assertStringContainsString('alt=""', $source);
            $this->assertStringNotContainsString('avatar', strtolower($source));

            $html = $this->get("/{$view}")->assertOk()->getContent();
            $this->assertStringContainsString(asset('images/google-g-logo.svg'), $html);
            $this->assertDoesNotMatchRegularExpression(
                '/<(?:script|img)\b[^>]*(?:accounts\.google\.com|apis\.google\.com|googleusercontent\.com|gstatic\.com)[^>]*>/i',
                $html,
            );
        }
    }

    public function test_google_auth_styles_provide_a_44_pixel_target_visible_focus_and_fit_a_320_pixel_viewport(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertIsString($css);

        $this->assertMatchesRegularExpression(
            '/\.google-auth-button\s*\{[^}]*min-height:\s*44px[^}]*\}/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.google-auth-button\s*\{[^}]*width:\s*100%[^}]*max-width:\s*100%[^}]*\}/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.google-auth-button:focus-visible\s*\{[^}]*(?:outline|box-shadow):\s*(?!none)[^;}]+[;}][^}]*\}/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.google-auth-button\s*\{[^}]*gap:\s*10px[^}]*padding:\s*1px 12px[^}]*font-family:\s*[\'\"]Google Sans[\'\"][^}]*font-weight:\s*500[^}]*\}/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.google-auth-button:focus-visible\s*\{[^}]*outline:\s*3px solid #4F46E5[^}]*\}/s',
            $css,
        );
        $this->assertStringContainsString("url('/fonts/google-sans-medium-latin.woff2')", $css);

        $this->assertStringContainsString('*, *::before, *::after { box-sizing: border-box; }', $css);
        $this->assertMatchesRegularExpression('/body\s*\{[^}]*overflow-x:\s*hidden/s', $css);
        $this->assertMatchesRegularExpression('/\.auth-panel\s*\{[^}]*width:\s*min\(460px,\s*100%\)/s', $css);
        $this->assertMatchesRegularExpression('/@media\s*\(max-width:\s*360px\).*?\.auth-card[^}]*padding:/s', $css);
    }

    public function test_mobile_auth_inputs_remain_16_pixels_to_prevent_browser_auto_zoom(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertIsString($css);

        $this->assertMatchesRegularExpression(
            '/@media\s*\(max-width:\s*640px\).*?\.form-field input.*?font-size:\s*16px/s',
            $css,
        );
    }

    public function test_google_authentication_copy_is_professional_bahasa_melayu(): void
    {
        $this->readyGoogleConfiguration();

        foreach (['/login', '/register'] as $path) {
            $response = $this->get($path)->assertOk();

            $response
                ->assertSeeText('Teruskan dengan Google')
                ->assertSeeText('atau teruskan dengan e-mel')
                ->assertDontSeeText('Continue with Google')
                ->assertDontSeeText('or continue with email');
        }
    }

    public function test_privacy_and_terms_explain_google_identity_fields_and_token_non_retention(): void
    {
        foreach (['/privacy', '/terms'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSeeText('nama, alamat e-mel, status pengesahan e-mel dan ID akaun Google yang unik')
                ->assertSeeText('ChatMe tidak menyimpan token akses atau token muat semula Google.');
        }
    }

    public function test_readme_and_runbook_document_google_environment_callback_and_kill_switch(): void
    {
        $documentation = [
            file_get_contents(base_path('README.md')),
            file_get_contents(base_path('docs/operations/production-runbook.md')),
        ];

        foreach ($documentation as $source) {
            $this->assertIsString($source);

            foreach ([
                'GOOGLE_AUTH_ENABLED',
                'GOOGLE_CLIENT_ID',
                'GOOGLE_CLIENT_SECRET',
                'GOOGLE_REDIRECT_URI',
            ] as $environmentName) {
                $this->assertStringContainsString($environmentName, $source);
            }

            $this->assertStringContainsString('OAuth client jenis **Web application**', $source);
            $this->assertStringContainsString('authorized domain `akmalmarvis.com`', $source);
            $this->assertStringContainsString(
                'https://chatme.akmalmarvis.com/auth/google/callback',
                $source,
            );
            $this->assertStringContainsString(
                'Hidupkan `GOOGLE_AUTH_ENABLED=true` hanya selepas smoke test',
                $source,
            );
            $this->assertStringContainsString(
                'Untuk mematikan segera, tetapkan `GOOGLE_AUTH_ENABLED=false`',
                $source,
            );
            $this->assertStringContainsString('php artisan optimize:clear', $source);
            $this->assertStringContainsString('php artisan config:cache', $source);
        }
    }

    private function readyGoogleConfiguration(): void
    {
        config()->set('services.google', [
            'enabled' => true,
            'client_id' => 'ux-client-id',
            'client_secret' => 'ux-client-secret',
            'redirect' => 'https://chatme.test/auth/google/callback',
        ]);
    }
}

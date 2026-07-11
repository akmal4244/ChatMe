<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LightThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_stylesheet_exposes_the_light_design_tokens(): void
    {
        $css = strtolower(file_get_contents(public_path('css/app.css')));

        $this->assertStringContainsString('--canvas:#f7f6f1', $css);
        $this->assertStringContainsString('--surface:#fff', $css);
        $this->assertStringContainsString('--text:#171717', $css);
        $this->assertStringContainsString('--muted:#67655f', $css);
        $this->assertStringContainsString('--subtle:#67655f', $css);
        $this->assertStringContainsString('--border:#e2ded5', $css);
        $this->assertStringContainsString('--accent:#4f46e5', $css);
        $this->assertStringContainsString('prefers-reduced-motion:reduce', $css);
    }

    public function test_each_layout_has_one_real_main_landmark_and_no_dark_theme_override(): void
    {
        foreach (['guest', 'app'] as $layout) {
            $source = file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));

            $this->assertSame(1, substr_count($source, '<main'));
            $this->assertSame(1, substr_count($source, 'id="main-content"'));
            $this->assertStringContainsString('href="#main-content"', $source);
            $this->assertStringNotContainsString('background:#050505', $source);
            $this->assertStringNotContainsString('d.innerHTML=cfg.desc', $source);
        }
    }

    public function test_public_pages_render_inside_the_light_accessible_shell(): void
    {
        $this->seed(PlanSeeder::class);

        foreach (['/', '/pricing', '/login', '/register', '/privacy', '/terms'] as $uri) {
            $response = $this->get($uri)->assertOk();
            $response->assertSee('id="main-content"', false)
                ->assertSee('css/app.css', false);
            $this->assertMatchesRegularExpression('/css\/app\.css\?v=[a-f0-9]{12}/', $response->getContent());
        }
    }

    public function test_authenticated_shell_renders_with_the_same_main_landmark(): void
    {
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('id="main-content"', false)
            ->assertSee('css/app.css?v=', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('aria-label="Papan pemuka"', false)
            ->assertSee('<title>Papan pemuka — ChatMe</title>', false)
            ->assertSee('aria-label="Menu akaun untuk', false);
    }

    public function test_mobile_navigation_source_manages_focus_background_and_escape(): void
    {
        $source = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('appShell.inert = open', $source);
        $this->assertStringContainsString("sidebar.querySelector('a[href]')?.focus()", $source);
        $this->assertStringContainsString("sidebar.classList.contains('mobile-open')", $source);
        $this->assertStringContainsString('setSidebarState(false, true)', $source);
    }

    public function test_mobile_viewport_is_locked_and_form_controls_do_not_trigger_auto_zoom(): void
    {
        $viewport = 'width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover';

        foreach (['guest', 'app'] as $layout) {
            $source = file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));
            $this->assertStringContainsString('content="'.$viewport.'"', $source);
        }

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('touch-action: manipulation', $css);
        $this->assertMatchesRegularExpression('/@media\s*\(max-width:\s*640px\).*?font-size:\s*16px/s', $css);
    }

    public function test_both_layouts_load_the_shared_popup_notification_system(): void
    {
        foreach (['guest', 'app'] as $layout) {
            $source = file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));
            $this->assertStringContainsString("@include('partials.toasts')", $source);
        }

        $partialPath = resource_path('views/partials/toasts.blade.php');
        $this->assertFileExists($partialPath);
        $partial = file_get_contents($partialPath);
        $this->assertStringContainsString('id="initial-notifications"', $partial);
        $this->assertStringContainsString('window.showToast', $partial);
        $this->assertStringContainsString("toast.setAttribute('role', normalizedType === 'error' ? 'alert' : 'status')", $partial);
        $this->assertStringContainsString('aria-label="Tutup notifikasi"', $partial);
    }

    public function test_session_and_validation_feedback_render_as_popup_data(): void
    {
        $this->withSession(['success' => 'Chatbot berjaya dikemas kini.'])
            ->get('/login')->assertOk()
            ->assertSee('Chatbot berjaya dikemas kini.', false)
            ->assertSee('id="initial-notifications"', false);

        $this->from('/login')->post('/login', ['email' => '', 'password' => ''])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email', 'password']);

        $this->get('/login')->assertOk()
            ->assertSee('Sila semak medan yang bertanda sebelum meneruskan.', false);
    }

    public function test_landing_uses_real_plan_data_and_manual_renewal_copy(): void
    {
        $source = file_get_contents(resource_path('views/landing.blade.php'));

        $this->assertStringNotContainsString('RM199', $source);
        $this->assertStringNotContainsString('chatbots_limit', $source);
        $this->assertStringNotContainsString('messages_limit', $source);
        $this->assertStringContainsString('FPX', $source);
        $this->assertStringContainsString('DuitNow QR', $source);
        $this->assertStringContainsString('tiada potongan automatik', $source);
    }

    public function test_authentication_fields_have_programmatic_labels_and_error_links(): void
    {
        foreach (['login', 'register'] as $view) {
            $source = file_get_contents(resource_path("views/auth/{$view}.blade.php"));

            $this->assertStringContainsString('<label', $source);
            $this->assertStringContainsString('for="email"', $source);
            $this->assertStringContainsString('id="email"', $source);
            $this->assertStringContainsString('aria-describedby=', $source);
        }
    }

    public function test_user_content_is_not_interpolated_into_inline_javascript_handlers(): void
    {
        $source = file_get_contents(resource_path('views/knowledge/index.blade.php'));

        $this->assertStringNotContainsString('onclick="openEdit(', $source);
        $this->assertStringNotContainsString('addslashes(', $source);
    }

    public function test_obsolete_dark_templates_are_removed(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/layouts/app.blade.php.bak'));
        $this->assertFileDoesNotExist(resource_path('views/welcome.blade.php'));
    }
}

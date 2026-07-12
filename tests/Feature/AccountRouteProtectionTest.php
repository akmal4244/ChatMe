<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AccountRouteProtectionTest extends TestCase
{
    public function test_unused_api_user_scaffold_is_absent_and_returns_localized_json_not_found(): void
    {
        config()->set('app.debug', false);

        $this->getJson('/api/user')->assertNotFound()
            ->assertExactJson(['error' => __('chatme.api.not_found')]);

        $uris = collect(Route::getRoutes())->map(fn ($route): string => $route->uri());
        $this->assertNotContains('api/user', $uris);
    }

    public function test_all_authenticated_account_saas_and_admin_routes_publish_session_deadlines(): void
    {
        $routeNames = [
            'logout',
            'verification.notice',
            'verification.send',
            'verification.verify',
            'profile.edit',
            'profile.update',
            'profile.password.update',
            'dashboard',
            'chatbots.index',
            'knowledge.index',
            'subscription.plans',
            'admin.dashboard',
        ];

        foreach ($routeNames as $name) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertContains(
                'session.deadline',
                $middleware,
                "Route [{$name}] must publish the authenticated session deadline.",
            );
            $this->assertContains(
                'auth.session',
                $middleware,
                "Route [{$name}] must reject sessions whose credential hash has changed.",
            );
        }

        $this->assertContains(
            'throttle:profile-update',
            Route::getRoutes()->getByName('profile.update')->gatherMiddleware(),
        );
        foreach (['profile.password.update', 'chatbots.developer-token', 'chatbots.regenerate-key'] as $name) {
            $this->assertContains(
                'throttle:sensitive-account',
                Route::getRoutes()->getByName($name)->gatherMiddleware(),
                "Route [{$name}] must throttle sensitive credential changes.",
            );
        }
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_endpoint_reports_all_required_checks_without_sensitive_values(): void
    {
        config()->set('services.toyyibpay.secret_key', 'health-secret-value');
        config()->set('services.toyyibpay.category_code', 'HEALTH01');
        config()->set('services.cloudflare_ai.enabled', false);
        config()->set('services.google.enabled', false);

        $response = $this->getJson('/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'checks' => [
                    'application' => 'ok',
                    'database' => 'ok',
                    'queue' => 'ok',
                    'storage' => 'ok',
                    'payments' => 'ok',
                    'ai' => 'disabled',
                    'google_auth' => 'disabled',
                ],
            ]);

        $payload = $response->getContent();
        $this->assertStringNotContainsString('health-secret-value', $payload);
        $this->assertStringNotContainsString((string) config('app.key'), $payload);
    }

    public function test_missing_critical_payment_or_ai_configuration_fails_closed(): void
    {
        config()->set('services.toyyibpay.secret_key', null);
        config()->set('services.toyyibpay.category_code', null);
        config()->set('services.cloudflare_ai.enabled', true);
        config()->set('services.cloudflare_ai.account_id', null);
        config()->set('services.cloudflare_ai.token', null);
        config()->set('services.google.enabled', false);

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertExactJson([
                'status' => 'failed',
                'checks' => [
                    'application' => 'ok',
                    'database' => 'ok',
                    'queue' => 'ok',
                    'storage' => 'ok',
                    'payments' => 'failed',
                    'ai' => 'failed',
                    'google_auth' => 'disabled',
                ],
            ]);
    }

    public function test_ready_google_auth_is_reported_as_ok_without_exposing_oauth_values(): void
    {
        config()->set('services.toyyibpay.secret_key', 'payment-health-secret');
        config()->set('services.toyyibpay.category_code', 'HEALTH01');
        config()->set('services.cloudflare_ai.enabled', false);
        config()->set('services.google', [
            'enabled' => true,
            'client_id' => 'health-google-client-id',
            'client_secret' => 'health-google-client-secret',
            'redirect' => 'https://chatme.test/auth/google/callback',
        ]);

        $response = $this->getJson('/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'checks' => [
                    'application' => 'ok',
                    'database' => 'ok',
                    'queue' => 'ok',
                    'storage' => 'ok',
                    'payments' => 'ok',
                    'ai' => 'disabled',
                    'google_auth' => 'ok',
                ],
            ]);

        $payload = $response->getContent();
        $this->assertStringNotContainsString('health-google-client-id', $payload);
        $this->assertStringNotContainsString('health-google-client-secret', $payload);
        $this->assertStringNotContainsString('https://chatme.test/auth/google/callback', $payload);
        $this->assertStringNotContainsString('payment-health-secret', $payload);
        $this->assertStringNotContainsString((string) config('app.key'), $payload);
    }

    public function test_enabled_but_incomplete_google_auth_fails_readiness_without_exposing_partial_configuration(): void
    {
        config()->set('services.toyyibpay.secret_key', 'payment-health-secret');
        config()->set('services.toyyibpay.category_code', 'HEALTH01');
        config()->set('services.cloudflare_ai.enabled', false);
        config()->set('services.google', [
            'enabled' => true,
            'client_id' => 'partial-google-client-id',
            'client_secret' => null,
            'redirect' => 'https://chatme.test/auth/google/callback',
        ]);

        $response = $this->getJson('/health')
            ->assertStatus(503)
            ->assertExactJson([
                'status' => 'failed',
                'checks' => [
                    'application' => 'ok',
                    'database' => 'ok',
                    'queue' => 'ok',
                    'storage' => 'ok',
                    'payments' => 'ok',
                    'ai' => 'disabled',
                    'google_auth' => 'failed',
                ],
            ]);

        $payload = $response->getContent();
        $this->assertStringNotContainsString('partial-google-client-id', $payload);
        $this->assertStringNotContainsString('https://chatme.test/auth/google/callback', $payload);
        $this->assertStringNotContainsString('payment-health-secret', $payload);
        $this->assertStringNotContainsString((string) config('app.key'), $payload);
    }
}

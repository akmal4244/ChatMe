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
                ],
            ]);
    }
}

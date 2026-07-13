<?php

namespace Tests\Unit;

use App\Services\GoogleAuthConfiguration;
use Tests\TestCase;

class GoogleAuthConfigurationTest extends TestCase
{
    public function test_google_auth_is_disabled_when_the_feature_flag_is_off(): void
    {
        config()->set('services.google.enabled', false);

        $configuration = app(GoogleAuthConfiguration::class);

        $this->assertFalse($configuration->isEnabled());
        $this->assertFalse($configuration->isReady());
        $this->assertSame('disabled', $configuration->status());
    }

    public function test_enabled_google_auth_requires_every_oauth_value(): void
    {
        config()->set('services.google', [
            'enabled' => true,
            'client_id' => 'client-id',
            'client_secret' => null,
            'redirect' => 'https://chatme.test/auth/google/callback',
        ]);

        $configuration = app(GoogleAuthConfiguration::class);

        $this->assertTrue($configuration->isEnabled());
        $this->assertFalse($configuration->isReady());
        $this->assertSame('failed', $configuration->status());
    }

    public function test_complete_google_auth_configuration_is_ready(): void
    {
        config()->set('services.google', [
            'enabled' => true,
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'redirect' => 'https://chatme.test/auth/google/callback',
        ]);

        $configuration = app(GoogleAuthConfiguration::class);

        $this->assertTrue($configuration->isReady());
        $this->assertSame('ok', $configuration->status());
    }

    public function test_production_google_auth_rejects_a_noncanonical_callback(): void
    {
        config()->set('app.env', 'production');
        config()->set('services.google', [
            'enabled' => true,
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'redirect' => 'http://chatme.akmalmarvis.com/auth/google/callback',
        ]);

        $configuration = app(GoogleAuthConfiguration::class);

        $this->assertFalse($configuration->isReady());
        $this->assertSame('failed', $configuration->status());
    }

    public function test_production_google_auth_accepts_only_the_canonical_callback(): void
    {
        config()->set('app.env', 'production');
        config()->set('services.google', [
            'enabled' => true,
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'redirect' => 'https://chatme.akmalmarvis.com/auth/google/callback',
        ]);

        $configuration = app(GoogleAuthConfiguration::class);

        $this->assertTrue($configuration->isReady());
        $this->assertSame('ok', $configuration->status());
    }

    public function test_local_google_auth_accepts_the_documented_relative_callback(): void
    {
        config()->set('app.env', 'local');
        config()->set('services.google', [
            'enabled' => true,
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'redirect' => '/auth/google/callback',
        ]);

        $configuration = app(GoogleAuthConfiguration::class);

        $this->assertTrue($configuration->isReady());
        $this->assertSame('ok', $configuration->status());
    }
}

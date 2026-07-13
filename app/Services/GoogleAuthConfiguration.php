<?php

namespace App\Services;

final class GoogleAuthConfiguration
{
    private const CALLBACK_PATH = '/auth/google/callback';

    private const PRODUCTION_CALLBACK = 'https://chatme.akmalmarvis.com/auth/google/callback';

    public function isEnabled(): bool
    {
        return (bool) config('services.google.enabled');
    }

    public function isReady(): bool
    {
        return $this->isEnabled()
            && filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && $this->hasValidRedirect();
    }

    public function status(): string
    {
        return ! $this->isEnabled() ? 'disabled' : ($this->isReady() ? 'ok' : 'failed');
    }

    private function hasValidRedirect(): bool
    {
        $redirect = config('services.google.redirect');

        if (! is_string($redirect)) {
            return false;
        }

        $redirect = trim($redirect);
        $isProduction = config('app.env') === 'production';

        if ($redirect === self::CALLBACK_PATH) {
            return ! $isProduction;
        }

        $parts = parse_url($redirect);

        if (! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || ! filled($parts['host'] ?? null)
            || ($parts['path'] ?? null) !== self::CALLBACK_PATH
        ) {
            return false;
        }

        foreach (['user', 'pass', 'port', 'query', 'fragment'] as $forbiddenPart) {
            if (array_key_exists($forbiddenPart, $parts)) {
                return false;
            }
        }

        return ! $isProduction || hash_equals(self::PRODUCTION_CALLBACK, $redirect);
    }
}

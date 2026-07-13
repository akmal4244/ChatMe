<?php

namespace App\Services;

final class GoogleAuthConfiguration
{
    public function isEnabled(): bool
    {
        return (bool) config('services.google.enabled');
    }

    public function isReady(): bool
    {
        return $this->isEnabled()
            && filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    public function status(): string
    {
        return ! $this->isEnabled() ? 'disabled' : ($this->isReady() ? 'ok' : 'failed');
    }
}

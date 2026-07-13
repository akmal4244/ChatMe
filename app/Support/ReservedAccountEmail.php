<?php

namespace App\Support;

use Illuminate\Support\Str;

final class ReservedAccountEmail
{
    private const HOMEPAGE_OWNER_EMAIL = 'homepage-bot@chatme.invalid';

    public function contains(string $email): bool
    {
        $email = Str::lower(trim($email));
        $configuredAdmin = config('chatme.admin.email');
        $reserved = [self::HOMEPAGE_OWNER_EMAIL];

        if (is_string($configuredAdmin) && trim($configuredAdmin) !== '') {
            $reserved[] = Str::lower(trim($configuredAdmin));
        }

        return in_array($email, $reserved, true);
    }
}

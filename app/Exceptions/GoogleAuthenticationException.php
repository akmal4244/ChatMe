<?php

namespace App\Exceptions;

use RuntimeException;

final class GoogleAuthenticationException extends RuntimeException
{
    private function __construct(
        private readonly string $failureReason,
    ) {
        parent::__construct('Google authentication could not be completed.');
    }

    public static function invalidIdentity(): self
    {
        return new self('invalid_identity');
    }

    public static function unverifiedEmail(): self
    {
        return new self('unverified_email');
    }

    public static function ownershipChallengeRequired(): self
    {
        return new self('ownership_challenge_required');
    }

    public static function reservedIdentity(): self
    {
        return new self('reserved_identity');
    }

    public static function identityConflict(): self
    {
        return new self('identity_conflict');
    }

    public function reason(): string
    {
        return $this->failureReason;
    }
}

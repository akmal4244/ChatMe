<?php

namespace App\ValueObjects;

use App\Exceptions\GoogleAuthenticationException;
use Illuminate\Support\Str;

final readonly class GoogleIdentity
{
    private const HOSTNAME_PATTERN = '/\A(?=.{1,253}\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/D';

    private function __construct(
        public string $subject,
        public string $email,
        public string $name,
        public bool $emailVerified,
        public ?string $hostedDomain,
    ) {}

    public static function fromProvider(
        mixed $subject,
        mixed $email,
        mixed $name,
        mixed $verified,
        mixed $hostedDomain,
    ): self {
        if (! is_string($subject)
            || ! is_string($email)
            || ! is_string($name)
            || (! is_null($hostedDomain) && ! is_string($hostedDomain))
        ) {
            throw GoogleAuthenticationException::invalidIdentity();
        }

        if ($verified !== true) {
            throw GoogleAuthenticationException::unverifiedEmail();
        }

        if (preg_match('/\A[\x21-\x7E]{1,255}\z/D', $subject) !== 1) {
            throw GoogleAuthenticationException::invalidIdentity();
        }

        $email = Str::lower(trim($email));
        if (strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw GoogleAuthenticationException::unverifiedEmail();
        }

        $name = trim($name);
        if ($name === ''
            || ! mb_check_encoding($name, 'UTF-8')
            || mb_strlen($name) > 255
            || preg_match('/[\x00-\x1F\x7F]/u', $name) === 1
        ) {
            throw GoogleAuthenticationException::invalidIdentity();
        }

        $hostedDomain = self::normalizeHostedDomain($hostedDomain);

        return new self($subject, $email, $name, true, $hostedDomain);
    }

    public function isEmailAuthoritative(): bool
    {
        $domain = substr($this->email, (int) strrpos($this->email, '@') + 1);

        return $this->emailVerified
            && ($domain === 'gmail.com' || $this->hostedDomain !== null);
    }

    private static function normalizeHostedDomain(?string $hostedDomain): ?string
    {
        if ($hostedDomain === null) {
            return null;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $hostedDomain) === 1) {
            throw GoogleAuthenticationException::invalidIdentity();
        }

        $hostedDomain = Str::lower(trim($hostedDomain));
        if ($hostedDomain === '') {
            return null;
        }

        if (strlen($hostedDomain) > 253
            || preg_match(self::HOSTNAME_PATTERN, $hostedDomain) !== 1
        ) {
            throw GoogleAuthenticationException::invalidIdentity();
        }

        return $hostedDomain;
    }
}

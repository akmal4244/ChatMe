<?php

namespace App\Services;

use App\Exceptions\GoogleAuthenticationException;
use App\Models\User;
use App\Support\ReservedAccountEmail;
use App\ValueObjects\GoogleIdentity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

final class GoogleAccountService
{
    public function __construct(
        private readonly ReservedAccountEmail $reservedAccountEmail,
        private readonly DatabaseTransactionRunner $transactions,
    ) {}

    public function resolve(GoogleIdentity $identity): User
    {
        $subjectWasMissing = false;

        try {
            return $this->transactions->run(
                function () use ($identity, &$subjectWasMissing): User {
                    return $this->resolveLocked($identity, $subjectWasMissing);
                },
                3,
            );
        } catch (UniqueConstraintViolationException) {
            return $this->transactions->run(
                fn (): User => $this->recoverDuplicate($identity),
                3,
            );
        }
    }

    private function resolveLocked(GoogleIdentity $identity, bool &$subjectWasMissing): User
    {
        $this->assertProviderEmailAllowed($identity);

        $subjectUser = $this->oneOrFailClosed(
            $this->lockedSubjectMatches($identity->subject),
        );

        if ($subjectUser !== null) {
            $this->assertExactSubject($subjectUser, $identity->subject);
            $this->assertUserAllowed($subjectUser);

            if ($subjectWasMissing
                && ! hash_equals($this->normalizedStoredEmail($subjectUser), $identity->email)
            ) {
                throw GoogleAuthenticationException::identityConflict();
            }

            return $subjectUser;
        }

        $subjectWasMissing = true;

        if (! $identity->isEmailAuthoritative()) {
            throw GoogleAuthenticationException::ownershipChallengeRequired();
        }

        $emailUser = $this->oneOrFailClosed(
            $this->lockedEmailMatches($identity->email),
        );

        if ($emailUser !== null) {
            $this->assertUserAllowed($emailUser);
            $storedSubject = $emailUser->getRawOriginal('google_sub');

            if (filled($storedSubject)) {
                if (! is_string($storedSubject) || ! hash_equals($storedSubject, $identity->subject)) {
                    throw GoogleAuthenticationException::identityConflict();
                }

                return $emailUser;
            }

            $emailUser->forceFill([
                'google_sub' => $identity->subject,
                'google_linked_at' => now(),
                'email_verified_at' => $emailUser->email_verified_at ?? now(),
            ])->save();

            return $emailUser;
        }

        $user = new User;
        $user->forceFill([
            'name' => $identity->name,
            'email' => $identity->email,
            'email_verified_at' => now(),
            'password' => null,
            'is_admin' => false,
            'system_role' => null,
            'google_sub' => $identity->subject,
            'google_linked_at' => now(),
        ])->save();

        return $user;
    }

    private function recoverDuplicate(GoogleIdentity $identity): User
    {
        $this->assertProviderEmailAllowed($identity);

        $subjectUser = $this->oneOrFailClosed(
            $this->lockedSubjectMatches($identity->subject),
        );

        if ($subjectUser !== null) {
            $this->assertExactSubject($subjectUser, $identity->subject);
            $this->assertUserAllowed($subjectUser);

            if (! hash_equals($this->normalizedStoredEmail($subjectUser), $identity->email)) {
                throw GoogleAuthenticationException::identityConflict();
            }

            return $subjectUser;
        }

        if (! $identity->isEmailAuthoritative()) {
            throw GoogleAuthenticationException::ownershipChallengeRequired();
        }

        $emailUser = $this->oneOrFailClosed(
            $this->lockedEmailMatches($identity->email),
        );

        if ($emailUser === null) {
            throw GoogleAuthenticationException::identityConflict();
        }

        $this->assertUserAllowed($emailUser);
        $storedSubject = $emailUser->getRawOriginal('google_sub');

        if (! is_string($storedSubject) || ! hash_equals($storedSubject, $identity->subject)) {
            throw GoogleAuthenticationException::identityConflict();
        }

        return $emailUser;
    }

    /** @return Collection<int, User> */
    private function lockedSubjectMatches(string $subject): Collection
    {
        return User::query()
            ->where('google_sub', $subject)
            ->orderBy('id')
            ->lockForUpdate()
            ->limit(2)
            ->get();
    }

    /** @return Collection<int, User> */
    private function lockedEmailMatches(string $email): Collection
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->orderBy('id')
            ->lockForUpdate()
            ->limit(2)
            ->get();
    }

    /** @param Collection<int, User> $matches */
    private function oneOrFailClosed(Collection $matches): ?User
    {
        if ($matches->count() > 1) {
            throw GoogleAuthenticationException::identityConflict();
        }

        $user = $matches->get(0);

        return $user instanceof User ? $user : null;
    }

    private function assertProviderEmailAllowed(GoogleIdentity $identity): void
    {
        if ($this->reservedAccountEmail->contains($identity->email)) {
            throw GoogleAuthenticationException::reservedIdentity();
        }
    }

    private function assertUserAllowed(User $user): void
    {
        if ($user->is_admin
            || filled($user->getRawOriginal('system_role'))
            || $this->reservedAccountEmail->contains((string) $user->email)
        ) {
            throw GoogleAuthenticationException::reservedIdentity();
        }
    }

    private function assertExactSubject(User $user, string $expectedSubject): void
    {
        $storedSubject = $user->getRawOriginal('google_sub');

        if (! is_string($storedSubject) || ! hash_equals($storedSubject, $expectedSubject)) {
            throw GoogleAuthenticationException::identityConflict();
        }
    }

    private function normalizedStoredEmail(User $user): string
    {
        return Str::lower(trim((string) $user->email));
    }
}

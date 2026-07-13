<?php

namespace Tests\Feature;

use App\Exceptions\GoogleAuthenticationException;
use App\Models\User;
use App\Services\DatabaseTransactionRunner;
use App\Services\GoogleAccountService;
use App\Support\ReservedAccountEmail;
use App\ValueObjects\GoogleIdentity;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoogleAccountServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_identity_preserves_subject_and_normalizes_profile_claims(): void
    {
        $identity = GoogleIdentity::fromProvider(
            'Subject-A',
            ' USER@Example.test ',
            ' Nama Google ',
            true,
            ' EXAMPLE.TEST ',
        );

        $this->assertSame('Subject-A', $identity->subject);
        $this->assertSame('user@example.test', $identity->email);
        $this->assertSame('Nama Google', $identity->name);
        $this->assertTrue($identity->emailVerified);
        $this->assertSame('example.test', $identity->hostedDomain);
        $this->assertTrue($identity->isEmailAuthoritative());
    }

    public function test_gmail_email_is_authoritative_case_insensitively_without_a_hosted_domain(): void
    {
        $identity = GoogleIdentity::fromProvider(
            'subject-gmail',
            ' USER@GMAIL.COM ',
            'Pengguna Gmail',
            true,
            null,
        );

        $this->assertSame('user@gmail.com', $identity->email);
        $this->assertNull($identity->hostedDomain);
        $this->assertTrue($identity->isEmailAuthoritative());
    }

    public function test_verified_workspace_hosted_domain_is_authoritative(): void
    {
        $identity = GoogleIdentity::fromProvider(
            'subject-workspace',
            'alias@other-domain.test',
            'Pengguna Workspace',
            true,
            'workspace.example',
        );

        $this->assertTrue($identity->isEmailAuthoritative());
    }

    public function test_verified_third_party_email_without_hosted_domain_is_not_authoritative(): void
    {
        $identity = GoogleIdentity::fromProvider(
            'subject-external',
            'owner@example.test',
            'Pengguna Luaran',
            true,
            '   ',
        );

        $this->assertNull($identity->hostedDomain);
        $this->assertFalse($identity->isEmailAuthoritative());
    }

    #[DataProvider('invalidIdentityClaims')]
    public function test_invalid_identity_claims_are_rejected_without_type_coercion(
        mixed $subject,
        mixed $email,
        mixed $name,
        mixed $hostedDomain,
    ): void {
        $this->assertGoogleException(
            'invalid_identity',
            fn () => GoogleIdentity::fromProvider($subject, $email, $name, true, $hostedDomain),
        );
    }

    /** @return iterable<string, array{mixed, mixed, mixed, mixed}> */
    public static function invalidIdentityClaims(): iterable
    {
        yield 'subject array' => [[], 'user@gmail.com', 'Nama', null];
        yield 'subject object' => [new \stdClass, 'user@gmail.com', 'Nama', null];
        yield 'subject leading space' => [' subject', 'user@gmail.com', 'Nama', null];
        yield 'subject trailing space' => ['subject ', 'user@gmail.com', 'Nama', null];
        yield 'subject trailing newline' => ["subject\n", 'user@gmail.com', 'Nama', null];
        yield 'subject oversized' => [str_repeat('s', 256), 'user@gmail.com', 'Nama', null];
        yield 'subject non ascii' => ['subjek-é', 'user@gmail.com', 'Nama', null];
        yield 'email array' => ['subject', [], 'Nama', null];
        yield 'name object' => ['subject', 'user@gmail.com', new \stdClass, null];
        yield 'name blank' => ['subject', 'user@gmail.com', '   ', null];
        yield 'name oversized' => ['subject', 'user@gmail.com', str_repeat('N', 256), null];
        yield 'hosted domain array' => ['subject', 'user@gmail.com', 'Nama', []];
        yield 'hosted domain invalid label' => ['subject', 'user@gmail.com', 'Nama', 'bad_domain.test'];
        yield 'hosted domain leading hyphen' => ['subject', 'user@gmail.com', 'Nama', '-bad.test'];
        yield 'hosted domain empty label' => ['subject', 'user@gmail.com', 'Nama', 'bad..test'];
        yield 'hosted domain oversized' => ['subject', 'user@gmail.com', 'Nama', str_repeat('a', 254)];
        yield 'hosted domain unicode' => ['subject', 'user@gmail.com', 'Nama', 'contoh.éxample'];
    }

    #[DataProvider('invalidEmails')]
    public function test_invalid_email_claims_are_rejected(mixed $email): void
    {
        $this->assertGoogleException(
            'unverified_email',
            fn () => GoogleIdentity::fromProvider('subject', $email, 'Nama', true, null),
        );
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidEmails(): iterable
    {
        yield 'blank' => ['   '];
        yield 'malformed' => ['bukan-e-mel'];
        yield 'oversized' => [str_repeat('a', 245).'@gmail.com'];
    }

    #[DataProvider('nonLiteralVerificationClaims')]
    public function test_email_verification_claim_must_be_literal_true(mixed $verified): void
    {
        $this->assertGoogleException(
            'unverified_email',
            fn () => GoogleIdentity::fromProvider('subject', 'user@gmail.com', 'Nama', $verified, null),
        );
    }

    /** @return iterable<string, array{mixed}> */
    public static function nonLiteralVerificationClaims(): iterable
    {
        yield 'false' => [false];
        yield 'integer one' => [1];
        yield 'string true' => ['true'];
        yield 'null' => [null];
    }

    public function test_google_authentication_exception_message_never_contains_identity_values(): void
    {
        try {
            GoogleIdentity::fromProvider(
                'secret-subject',
                'secret-owner@example.test',
                'Nama Rahsia',
                false,
                null,
            );
            $this->fail('Exception Google dijangka.');
        } catch (GoogleAuthenticationException $exception) {
            $this->assertSame('unverified_email', $exception->reason());
            $this->assertStringNotContainsString('secret-subject', $exception->getMessage());
            $this->assertStringNotContainsString('secret-owner@example.test', $exception->getMessage());
            $this->assertStringNotContainsString('Nama Rahsia', $exception->getMessage());
        }
    }

    public function test_reserved_account_email_normalizes_homepage_and_configured_admin_addresses(): void
    {
        config()->set('chatme.admin.email', 'Primary.Admin@Example.test');
        $reserved = app(ReservedAccountEmail::class);

        $this->assertTrue($reserved->contains(' HOMEPAGE-BOT@CHATME.INVALID '));
        $this->assertTrue($reserved->contains(' primary.admin@EXAMPLE.TEST '));
        $this->assertFalse($reserved->contains('ordinary@example.test'));
    }

    public function test_authoritative_google_identity_creates_one_verified_google_only_user(): void
    {
        $identity = GoogleIdentity::fromProvider(
            'sub-create',
            ' USER@Example.test ',
            'Nama Google',
            true,
            'example.test',
        );

        $user = app(GoogleAccountService::class)->resolve($identity);

        $this->assertSame('user@example.test', $user->email);
        $this->assertSame('Nama Google', $user->name);
        $this->assertSame('sub-create', $user->getRawOriginal('google_sub'));
        $this->assertNotNull($user->google_linked_at);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNull($user->getRawOriginal('password'));
        $this->assertFalse($user->is_admin);
        $this->assertNull($user->system_role);
        $this->assertArrayNotHasKey('google_sub', $user->toArray());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_authoritative_email_links_a_legacy_mixed_case_user_without_changing_profile_or_password(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'Owner@Example.test',
            'name' => 'Nama Tempatan',
            'password' => 'local-password',
            'company' => 'Syarikat Tempatan',
            'website' => 'https://example.test',
        ]);
        $originalPassword = $user->getRawOriginal('password');

        $resolved = app(GoogleAccountService::class)->resolve(
            GoogleIdentity::fromProvider(
                'sub-link',
                ' OWNER@example.test ',
                'Nama Provider',
                true,
                'example.test',
            ),
        )->refresh();

        $this->assertTrue($resolved->is($user));
        $this->assertSame('Owner@Example.test', $resolved->email);
        $this->assertSame('Nama Tempatan', $resolved->name);
        $this->assertSame('Syarikat Tempatan', $resolved->company);
        $this->assertSame('https://example.test', $resolved->website);
        $this->assertSame($originalPassword, $resolved->getRawOriginal('password'));
        $this->assertTrue(Hash::check('local-password', (string) $resolved->password));
        $this->assertSame('sub-link', $resolved->getRawOriginal('google_sub'));
        $this->assertNotNull($resolved->google_linked_at);
        $this->assertTrue($resolved->hasVerifiedEmail());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_subject_first_login_uses_an_existing_link_after_provider_email_changes(): void
    {
        $linkedAt = now()->subDay();
        $user = User::factory()->create([
            'email' => 'asal@example.test',
            'name' => 'Nama Asal',
            'password' => 'local-password',
        ]);
        $user->forceFill([
            'google_sub' => 'sub-existing',
            'google_linked_at' => $linkedAt,
        ])->save();
        $linkedAt = $user->fresh()->google_linked_at;

        $resolved = app(GoogleAccountService::class)->resolve(
            GoogleIdentity::fromProvider(
                'sub-existing',
                'alamat-baharu@third-party.test',
                'Nama Baharu Provider',
                true,
                null,
            ),
        )->refresh();

        $this->assertTrue($resolved->is($user));
        $this->assertSame('asal@example.test', $resolved->email);
        $this->assertSame('Nama Asal', $resolved->name);
        $this->assertSame('sub-existing', $resolved->getRawOriginal('google_sub'));
        $this->assertTrue($resolved->google_linked_at->equalTo($linkedAt));
        $this->assertTrue(Hash::check('local-password', (string) $resolved->password));
    }

    public function test_non_authoritative_identity_creates_no_user(): void
    {
        $this->assertGoogleException(
            'ownership_challenge_required',
            fn () => app(GoogleAccountService::class)->resolve(
                GoogleIdentity::fromProvider(
                    'sub-untrusted-new',
                    'owner@third-party.test',
                    'Pengguna Luaran',
                    true,
                    null,
                ),
            ),
        );

        $this->assertDatabaseCount('users', 0);
    }

    public function test_non_authoritative_identity_never_auto_links_an_existing_user(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@third-party.test',
            'password' => 'local-password',
        ]);
        $before = $user->fresh()->getAttributes();

        $this->assertGoogleException(
            'ownership_challenge_required',
            fn () => app(GoogleAccountService::class)->resolve(
                GoogleIdentity::fromProvider(
                    'sub-untrusted-existing',
                    'owner@third-party.test',
                    'Pengguna Luaran',
                    true,
                    null,
                ),
            ),
        );

        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 1);
    }

    #[DataProvider('privilegedAccountCases')]
    public function test_privileged_accounts_are_rejected_without_mutation(
        bool $subjectFirst,
        bool $isAdmin,
        ?string $systemRole,
    ): void {
        $email = sprintf(
            '%s-%s@example.test',
            $subjectFirst ? 'subject' : 'email',
            $systemRole ?? ($isAdmin ? 'admin' : 'ordinary'),
        );
        $user = User::factory()->create([
            'email' => $email,
            'is_admin' => $isAdmin,
        ]);
        $subject = 'sub-'.hash('sha256', $email);
        $providerSubject = $subjectFirst ? $subject : 'candidate-'.$subject;
        $user->forceFill(array_filter([
            'system_role' => $systemRole,
            'google_sub' => $subjectFirst ? $subject : null,
            'google_linked_at' => $subjectFirst ? now()->subHour() : null,
        ], fn (mixed $value): bool => $value !== null))->save();
        $before = $user->fresh()->getAttributes();

        $this->assertGoogleException(
            'reserved_identity',
            fn () => app(GoogleAccountService::class)->resolve(
                GoogleIdentity::fromProvider(
                    $providerSubject,
                    $email,
                    'Identiti Privileged',
                    true,
                    'example.test',
                ),
            ),
        );

        $this->assertSame($before, $user->fresh()->getAttributes());
    }

    /** @return iterable<string, array{bool, bool, ?string}> */
    public static function privilegedAccountCases(): iterable
    {
        yield 'admin subject first' => [true, true, null];
        yield 'admin email link' => [false, true, null];
        yield 'primary admin subject first' => [true, true, 'primary_admin'];
        yield 'primary admin email link' => [false, true, 'primary_admin'];
        yield 'homepage owner subject first' => [true, false, 'homepage_owner'];
        yield 'homepage owner email link' => [false, false, 'homepage_owner'];
    }

    public function test_homepage_reserved_provider_email_is_rejected_before_creation(): void
    {
        $this->assertGoogleException(
            'reserved_identity',
            fn () => app(GoogleAccountService::class)->resolve(
                GoogleIdentity::fromProvider(
                    'sub-homepage-reserved',
                    ' HOMEPAGE-BOT@CHATME.INVALID ',
                    'Sistem Homepage',
                    true,
                    'chatme.invalid',
                ),
            ),
        );

        $this->assertDatabaseCount('users', 0);
    }

    public function test_configured_admin_provider_email_is_rejected_before_creation(): void
    {
        config()->set('chatme.admin.email', 'Primary.Admin@Example.test');

        $this->assertGoogleException(
            'reserved_identity',
            fn () => app(GoogleAccountService::class)->resolve(
                GoogleIdentity::fromProvider(
                    'sub-admin-reserved',
                    ' primary.admin@EXAMPLE.TEST ',
                    'Pentadbir',
                    true,
                    'example.test',
                ),
            ),
        );

        $this->assertDatabaseCount('users', 0);
    }

    public function test_subject_first_login_rejects_a_stored_reserved_email_after_provider_email_changes(): void
    {
        $user = User::factory()->create(['email' => 'homepage-bot@chatme.invalid']);
        $user->forceFill([
            'google_sub' => 'sub-stored-reserved',
            'google_linked_at' => now()->subDay(),
        ])->save();
        $before = $user->fresh()->getAttributes();

        $this->assertGoogleException(
            'reserved_identity',
            fn () => app(GoogleAccountService::class)->resolve(
                GoogleIdentity::fromProvider(
                    'sub-stored-reserved',
                    'ordinary@gmail.com',
                    'Pengguna Biasa',
                    true,
                    null,
                ),
            ),
        );

        $this->assertSame($before, $user->fresh()->getAttributes());
    }

    public function test_existing_email_link_rejects_a_different_subject_without_mutation(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.test']);
        $user->forceFill([
            'google_sub' => 'subject-original',
            'google_linked_at' => now()->subDay(),
        ])->save();
        $before = $user->fresh()->getAttributes();

        $this->assertGoogleException(
            'identity_conflict',
            fn () => app(GoogleAccountService::class)->resolve(
                GoogleIdentity::fromProvider(
                    'subject-lain',
                    'owner@example.test',
                    'Penceroboh',
                    true,
                    'example.test',
                ),
            ),
        );

        $this->assertSame($before, $user->fresh()->getAttributes());
    }

    public function test_subject_lookup_is_case_sensitive_and_fails_closed(): void
    {
        $user = User::factory()->create(['email' => 'case@example.test']);
        $user->forceFill([
            'google_sub' => 'Subject-A',
            'google_linked_at' => now()->subDay(),
        ])->save();
        $before = $user->fresh()->getAttributes();

        $this->assertGoogleException(
            'identity_conflict',
            fn () => app(GoogleAccountService::class)->resolve(
                GoogleIdentity::fromProvider(
                    'subject-a',
                    'case@example.test',
                    'Case Berbeza',
                    true,
                    'example.test',
                ),
            ),
        );

        $this->assertSame($before, $user->fresh()->getAttributes());
    }

    public function test_duplicate_legacy_case_variant_emails_fail_closed_without_selecting_a_user(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Fixture legacy case-variant ini khusus untuk database binary/case-sensitive.');
        }

        $first = User::factory()->create(['email' => 'Owner@Example.test']);
        $second = User::factory()->create(['email' => 'owner@example.test']);
        $firstBefore = $first->fresh()->getAttributes();
        $secondBefore = $second->fresh()->getAttributes();

        $this->assertGoogleException(
            'identity_conflict',
            fn () => app(GoogleAccountService::class)->resolve(
                GoogleIdentity::fromProvider(
                    'sub-ambiguous',
                    'owner@example.test',
                    'Nama Provider',
                    true,
                    'example.test',
                ),
            ),
        );

        $this->assertSame($firstBefore, $first->fresh()->getAttributes());
        $this->assertSame($secondBefore, $second->fresh()->getAttributes());
    }

    public function test_resolving_the_same_identity_twice_is_idempotent(): void
    {
        $identity = GoogleIdentity::fromProvider(
            'sub-idempotent',
            'owner@example.test',
            'Nama Provider',
            true,
            'example.test',
        );

        $first = app(GoogleAccountService::class)->resolve($identity);
        $linkedAt = $first->google_linked_at;
        $second = app(GoogleAccountService::class)->resolve($identity)->refresh();

        $this->assertTrue($second->is($first));
        $this->assertTrue($second->google_linked_at->equalTo($linkedAt));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_transaction_retry_rejects_the_same_subject_with_a_different_email(): void
    {
        $identity = GoogleIdentity::fromProvider(
            'sub-retry-conflict',
            'challenger@example.test',
            'Pencabar',
            true,
            'example.test',
        );
        $runner = $this->retryRunnerThatInsertsLinkedUser(
            'sub-retry-conflict',
            'winner@example.test',
        );
        $service = new GoogleAccountService(app(ReservedAccountEmail::class), $runner);

        $this->assertGoogleException(
            'identity_conflict',
            fn () => $service->resolve($identity),
        );

        $winner = User::query()->sole();
        $this->assertSame('winner@example.test', $winner->email);
        $this->assertSame('sub-retry-conflict', $winner->getRawOriginal('google_sub'));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_transaction_retry_accepts_the_same_subject_with_the_same_email(): void
    {
        $identity = GoogleIdentity::fromProvider(
            'sub-retry-idempotent',
            'owner@example.test',
            'Pemilik',
            true,
            'example.test',
        );
        $runner = $this->retryRunnerThatInsertsLinkedUser(
            'sub-retry-idempotent',
            'owner@example.test',
        );
        $service = new GoogleAccountService(app(ReservedAccountEmail::class), $runner);

        $resolved = $service->resolve($identity);

        $this->assertSame('owner@example.test', $resolved->email);
        $this->assertSame('sub-retry-idempotent', $resolved->getRawOriginal('google_sub'));
        $this->assertDatabaseCount('users', 1);
    }

    private function retryRunnerThatInsertsLinkedUser(
        string $subject,
        string $email,
    ): DatabaseTransactionRunner {
        return new class($subject, $email) extends DatabaseTransactionRunner
        {
            public function __construct(
                private readonly string $subject,
                private readonly string $email,
            ) {}

            public function run(Closure $callback, int $attempts = 1): mixed
            {
                try {
                    DB::transaction(function () use ($callback): never {
                        $callback();

                        throw new \RuntimeException('Simulated deadlock retry.');
                    });
                } catch (\RuntimeException $exception) {
                    if ($exception->getMessage() !== 'Simulated deadlock retry.') {
                        throw $exception;
                    }
                }

                $winner = User::factory()->create([
                    'name' => 'Pemenang Retry',
                    'email' => $this->email,
                ]);
                $winner->forceFill([
                    'google_sub' => $this->subject,
                    'google_linked_at' => now(),
                ])->save();

                return DB::transaction($callback, $attempts);
            }
        };
    }

    private function assertGoogleException(string $reason, Closure $callback): void
    {
        try {
            $callback();
            $this->fail('Exception Google dijangka.');
        } catch (GoogleAuthenticationException $exception) {
            $this->assertSame($reason, $exception->reason());
            $this->assertSame('Google authentication could not be completed.', $exception->getMessage());
        }
    }
}

<?php

namespace Tests\Feature;

use App\Exceptions\GoogleAuthenticationException;
use App\Models\User;
use Closure;
use GuzzleHttp\Client;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\Provider as SocialiteProviderContract;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use stdClass;
use Tests\TestCase;
use Throwable;

class GoogleAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    private const CANCELLED_MESSAGE = 'Log masuk Google dibatalkan.';

    private const CONFIGURATION_MESSAGE = 'Log masuk Google tidak tersedia buat sementara waktu.';

    private const LIMIT_MESSAGE = 'Terlalu banyak percubaan log masuk Google. Sila cuba semula kemudian.';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    protected function tearDown(): void
    {
        Socialite::clearResolvedInstance(SocialiteFactory::class);

        parent::tearDown();
    }

    public function test_google_routes_are_get_only_guest_routes_using_the_named_google_limiter(): void
    {
        $expectedRoutes = [
            'auth.google.redirect' => 'auth/google/redirect',
            'auth.google.callback' => 'auth/google/callback',
        ];

        foreach ($expectedRoutes as $name => $uri) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route [{$name}] is missing.");

            if ($route === null) {
                continue;
            }

            $this->assertSame($uri, $route->uri());
            $this->assertSame(['GET', 'HEAD'], $route->methods());
            $this->assertContains('guest', $route->middleware());
            $this->assertContains('throttle:google-auth', $route->middleware());
        }
    }

    #[DataProvider('unavailableGoogleRouteProvider')]
    public function test_disabled_or_incomplete_configuration_fails_before_resolving_socialite(
        string $routeName,
        array $configuration,
    ): void {
        config()->set('services.google', $configuration);
        $factory = new RejectingGoogleSocialiteFactory;
        Socialite::swap($factory);

        $response = $this->withSession([
            'marker' => 'kekal',
            'state' => 'stale-configuration-state',
        ])
            ->get(route($routeName));

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', self::CONFIGURATION_MESSAGE)
            ->assertSessionHas('marker', 'kekal');
        str_ends_with($routeName, '.callback')
            ? $response->assertSessionMissing('state')
            : $response->assertSessionHas('state', 'stale-configuration-state');
        $this->assertSame(0, $factory->driverCalls);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function unavailableGoogleRouteProvider(): array
    {
        $disabled = [
            'enabled' => false,
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'redirect' => 'https://chatme.test/auth/google/callback',
        ];
        $incomplete = [
            'enabled' => true,
            'client_id' => 'client-id',
            'client_secret' => null,
            'redirect' => 'https://chatme.test/auth/google/callback',
        ];

        return [
            'redirect disabled' => ['auth.google.redirect', $disabled],
            'callback disabled' => ['auth.google.callback', $disabled],
            'redirect incomplete' => ['auth.google.redirect', $incomplete],
            'callback incomplete' => ['auth.google.callback', $incomplete],
        ];
    }

    public function test_google_redirect_is_stateful_and_overrides_configured_extra_scopes(): void
    {
        $this->readyGoogleConfiguration([
            'scopes' => ['https://www.googleapis.com/auth/drive.readonly'],
        ]);

        $response = $this->get(route('auth.google.redirect'))->assertRedirect();
        $target = (string) $response->headers->get('Location');
        parse_str((string) parse_url($target, PHP_URL_QUERY), $query);

        $this->assertSame('https', parse_url($target, PHP_URL_SCHEME));
        $this->assertSame('accounts.google.com', parse_url($target, PHP_URL_HOST));
        $this->assertSame('/o/oauth2/auth', parse_url($target, PHP_URL_PATH));
        $this->assertSame('select_account', $query['prompt'] ?? null);
        $this->assertSame('ms', $query['hl'] ?? null);
        $this->assertEqualsCanonicalizing(
            ['openid', 'email', 'profile'],
            explode(' ', (string) ($query['scope'] ?? '')),
        );
        $this->assertSame(
            'https://chatme.test/auth/google/callback',
            $query['redirect_uri'] ?? null,
        );
        $this->assertArrayNotHasKey('access_type', $query);
        $this->assertArrayNotHasKey('include_granted_scopes', $query);
        $this->assertIsString($query['state'] ?? null);
        $this->assertSame(40, strlen((string) $query['state']));
        $this->assertSame($query['state'], Session::get('state'));
    }

    public function test_valid_state_on_the_real_provider_logs_in_and_regenerates_the_session(): void
    {
        $this->readyGoogleConfiguration();
        $provider = null;
        $loggedEvents = [];
        Log::listen(function (MessageLogged $event) use (&$loggedEvents): void {
            $loggedEvents[] = [$event->level, $event->message, $event->context];
        });
        $this->installNetworklessGoogleProvider($provider, $this->validRawGoogleUser([
            'sub' => 'sub-real-state-success',
            'email' => 'real-state@workspace.test',
            'hd' => 'workspace.test',
        ]));
        Route::getRoutes()->getByName('auth.google.callback')
            ?->middleware(CaptureSessionRotation::class);

        $response = $this->withSession([
            'state' => 'one-time-valid-state',
            'marker' => 'dikekalkan',
        ])->get($this->callbackUrl([
            'state' => 'one-time-valid-state',
            'code' => 'networkless-code',
        ]));

        $response
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('marker', 'dikekalkan')
            ->assertSessionMissing('state');
        $this->assertInstanceOf(NetworklessGoogleProvider::class, $provider);
        $this->assertSame(1, $provider->tokenExchangeCalls);
        $this->assertSame(1, $provider->userInfoCalls);
        $beforeRotation = $response->headers->get('X-Test-Session-Before');
        $afterRotation = $response->headers->get('X-Test-Session-After');
        $this->assertIsString($beforeRotation);
        $this->assertIsString($afterRotation);
        $this->assertNotSame('', $beforeRotation);
        $this->assertNotSame('', $afterRotation);
        $this->assertNotSame($beforeRotation, $afterRotation);
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'real-state@workspace.test',
            'google_sub' => 'sub-real-state-success',
            'password' => null,
        ]);
        $user = Auth::user();
        $this->assertInstanceOf(User::class, $user);
        $persistedArtifacts = [
            serialize($response->getSession()->all()),
            serialize($user->fresh()?->toArray()),
            serialize(DB::table('users')->get()->map(fn (stdClass $row): array => (array) $row)->all()),
            serialize($loggedEvents),
            serialize($response->headers->all()),
            (string) $response->getContent(),
        ];

        foreach ($persistedArtifacts as $artifact) {
            $this->assertStringNotContainsString('networkless-access-token', $artifact);
            $this->assertStringNotContainsString('networkless-refresh-token', $artifact);
        }
    }

    #[DataProvider('invalidSuccessfulCallbackStateProvider')]
    public function test_invalid_state_is_rejected_by_the_real_provider_before_token_exchange(
        array $session,
        array $query,
    ): void {
        $this->readyGoogleConfiguration();
        $provider = null;
        $this->installNetworklessGoogleProvider($provider, $this->validRawGoogleUser());

        $response = $this->withSession($session)
            ->get($this->callbackUrl($query));

        $this->assertGenericGoogleFailure($response);
        $response->assertSessionMissing('state');
        $this->assertInstanceOf(NetworklessGoogleProvider::class, $provider);
        $this->assertSame(0, $provider->tokenExchangeCalls);
        $this->assertSame(0, $provider->userInfoCalls);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /** @return array<string, array{array<string, mixed>, array<string, mixed>}> */
    public static function invalidSuccessfulCallbackStateProvider(): array
    {
        return [
            'wrong state' => [
                ['state' => 'expected-state', 'marker' => 'kekal'],
                ['state' => 'wrong-state', 'code' => 'unused-code'],
            ],
            'missing state' => [
                ['state' => 'expected-state', 'marker' => 'kekal'],
                ['code' => 'unused-code'],
            ],
            'array state' => [
                ['state' => 'expected-state', 'marker' => 'kekal'],
                ['state' => ['expected-state'], 'code' => 'unused-code'],
            ],
            'array session state' => [
                ['state' => ['expected-state'], 'marker' => 'kekal'],
                ['state' => 'expected-state', 'code' => 'unused-code'],
            ],
            'missing session state' => [
                ['marker' => 'kekal'],
                ['state' => 'orphan-state', 'code' => 'unused-code'],
            ],
        ];
    }

    public function test_access_denied_with_a_valid_one_time_state_returns_the_specific_cancellation_message(): void
    {
        $this->readyGoogleConfiguration();
        $factory = new RejectingGoogleSocialiteFactory;
        Socialite::swap($factory);

        $response = $this->withSession([
            'state' => 'cancel-state',
            'marker' => 'kekal',
        ])->get($this->callbackUrl([
            'error' => 'access_denied',
            'state' => 'cancel-state',
        ]));

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', self::CANCELLED_MESSAGE)
            ->assertSessionHas('marker', 'kekal')
            ->assertSessionMissing('state');
        $this->assertSame(0, $factory->driverCalls);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    #[DataProvider('invalidAccessDeniedStateProvider')]
    public function test_access_denied_with_invalid_state_is_generic_and_never_resolves_the_provider(
        array $session,
        array $query,
    ): void {
        $this->readyGoogleConfiguration();
        $factory = new RejectingGoogleSocialiteFactory;
        Socialite::swap($factory);

        $response = $this->withSession($session)
            ->get($this->callbackUrl(array_merge(['error' => 'access_denied'], $query)));

        $this->assertGenericGoogleFailure($response);
        $response->assertSessionMissing('state');
        $this->assertSame(0, $factory->driverCalls);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /** @return array<string, array{array<string, mixed>, array<string, mixed>}> */
    public static function invalidAccessDeniedStateProvider(): array
    {
        return [
            'wrong state' => [
                ['state' => 'expected-cancel-state', 'marker' => 'kekal'],
                ['state' => 'wrong-cancel-state'],
            ],
            'missing request state' => [
                ['state' => 'expected-cancel-state', 'marker' => 'kekal'],
                [],
            ],
            'array request state' => [
                ['state' => 'expected-cancel-state', 'marker' => 'kekal'],
                ['state' => ['expected-cancel-state']],
            ],
            'missing session state' => [
                ['marker' => 'kekal'],
                ['state' => 'orphan-cancel-state'],
            ],
        ];
    }

    public function test_access_denied_state_is_consumed_and_cannot_be_replayed(): void
    {
        $this->readyGoogleConfiguration();
        $factory = new RejectingGoogleSocialiteFactory;
        Socialite::swap($factory);
        $callback = $this->callbackUrl([
            'error' => 'access_denied',
            'state' => 'single-use-cancel-state',
        ]);

        $this->withSession(['state' => 'single-use-cancel-state'])
            ->get($callback)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', self::CANCELLED_MESSAGE)
            ->assertSessionMissing('state');

        $replay = $this->get($callback);

        $this->assertGenericGoogleFailure($replay);
        $replay->assertSessionMissing('state');
        $this->assertSame(0, $factory->driverCalls);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_other_oauth_error_consumes_valid_state_without_resolving_the_provider(): void
    {
        $this->readyGoogleConfiguration();
        $factory = new RejectingGoogleSocialiteFactory;
        Socialite::swap($factory);
        Log::spy();

        $response = $this->withSession([
            'state' => 'provider-error-state',
            'marker' => 'kekal',
        ])->get($this->callbackUrl([
            'error' => 'server_error',
            'state' => 'provider-error-state',
            'error_description' => 'provider-private-sentinel',
        ]));

        $this->assertGenericGoogleFailure($response);
        $response
            ->assertSessionHas('marker', 'kekal')
            ->assertSessionMissing('state');
        $this->assertSame(0, $factory->driverCalls);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $serialized = $message.serialize($context);

                return $message === 'Google authentication failed.'
                    && ($context['category'] ?? null) === 'oauth_error'
                    && ($context['exception_type'] ?? null) === RuntimeException::class
                    && is_string($context['request_id'] ?? null)
                    && ! str_contains($serialized, 'provider-private-sentinel')
                    && ! str_contains($serialized, 'server_error');
            });
    }

    #[DataProvider('acceptedVerifiedClaimProvider')]
    public function test_literal_verified_claim_aliases_are_mapped_from_the_raw_payload(array $claims): void
    {
        $this->readyGoogleConfiguration();
        $raw = array_merge([
            'sub' => 'sub-raw-alias',
            'email' => 'raw-alias@gmail.com',
            'name' => 'Pengguna Claim Mentah',
        ], $claims);
        Socialite::fake('google', $this->fakeGoogleUser($raw));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'raw-alias@gmail.com',
            'google_sub' => 'sub-raw-alias',
        ]);
    }

    /** @return array<string, array{array<string, bool>}> */
    public static function acceptedVerifiedClaimProvider(): array
    {
        return [
            'email_verified only' => [['email_verified' => true]],
            'verified_email only' => [['verified_email' => true]],
            'both aliases agree' => [[
                'email_verified' => true,
                'verified_email' => true,
            ]],
        ];
    }

    #[DataProvider('conflictingVerifiedClaimProvider')]
    public function test_conflicting_verified_claim_aliases_fail_closed_without_creating_a_user(array $claims): void
    {
        $this->readyGoogleConfiguration();
        Socialite::fake('google', $this->fakeGoogleUser(array_merge([
            'sub' => 'sub-conflicting-claim',
            'email' => 'conflicting-claim@gmail.com',
            'name' => 'Pengguna Claim Bercanggah',
        ], $claims)));

        $response = $this->get(route('auth.google.callback'));

        $this->assertGenericGoogleFailure($response);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /** @return array<string, array{array<string, bool>}> */
    public static function conflictingVerifiedClaimProvider(): array
    {
        return [
            'canonical true legacy false' => [[
                'email_verified' => true,
                'verified_email' => false,
            ]],
            'canonical false legacy true' => [[
                'email_verified' => false,
                'verified_email' => true,
            ]],
        ];
    }

    #[DataProvider('invalidVerifiedClaimProvider')]
    public function test_missing_unverified_or_non_literal_verified_claims_fail_closed(array $claims): void
    {
        $this->readyGoogleConfiguration();
        Socialite::fake('google', $this->fakeGoogleUser(array_merge([
            'sub' => 'sub-invalid-verified',
            'email' => 'invalid-verified@gmail.com',
            'name' => 'Pengguna Verification Tidak Sah',
        ], $claims)));

        $response = $this->get(route('auth.google.callback'));

        $this->assertGenericGoogleFailure($response);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidVerifiedClaimProvider(): array
    {
        return [
            'missing both aliases' => [[]],
            'literal false' => [['email_verified' => false]],
            'integer one' => [['email_verified' => 1]],
            'string true' => [['verified_email' => 'true']],
            'array true' => [['email_verified' => [true]]],
            'agreeing but non-literal' => [[
                'email_verified' => 1,
                'verified_email' => 1,
            ]],
        ];
    }

    #[DataProvider('nonArrayRawPayloadProvider')]
    public function test_non_array_raw_provider_payloads_fail_closed(mixed $rawPayload): void
    {
        $this->readyGoogleConfiguration();
        Socialite::fake('google', new RawPayloadSocialiteUser(
            rawPayload: $rawPayload,
            id: 'sub-non-array-raw',
            email: 'non-array-raw@gmail.com',
            name: 'Pengguna Raw Tidak Sah',
        ));

        $response = $this->get(route('auth.google.callback'));

        $this->assertGenericGoogleFailure($response);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /** @return array<string, array{mixed}> */
    public static function nonArrayRawPayloadProvider(): array
    {
        return [
            'null' => [null],
            'string' => ['provider-raw-string'],
            'object' => [new stdClass],
        ];
    }

    #[DataProvider('malformedHostedDomainProvider')]
    public function test_malformed_hosted_domain_claims_fail_closed_without_warnings_or_mutation(mixed $hostedDomain): void
    {
        $this->readyGoogleConfiguration();
        Socialite::fake('google', $this->fakeGoogleUser([
            'sub' => 'sub-malformed-hd',
            'email' => 'malformed-hd@workspace.test',
            'name' => 'Pengguna Hosted Domain Tidak Sah',
            'email_verified' => true,
            'hd' => $hostedDomain,
        ]));

        $response = $this->get(route('auth.google.callback'));

        $this->assertGenericGoogleFailure($response);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /** @return array<string, array{mixed}> */
    public static function malformedHostedDomainProvider(): array
    {
        return [
            'array' => [['workspace.test']],
            'object' => [new stdClass],
            'path' => ['workspace.test/path'],
            'oversized' => [str_repeat('a', 254)],
            'leading NUL' => ["\0workspace.test"],
        ];
    }

    public function test_provider_exception_is_logged_with_only_safe_fixed_metadata(): void
    {
        $this->readyGoogleConfiguration([
            'client_secret' => 'client-secret-log-sentinel',
        ]);
        $provider = null;
        $failure = new RuntimeException(
            'provider raw body access-token-log-sentinel refresh-token-log-sentinel private@example.test',
        );
        $this->installNetworklessGoogleProvider(
            $provider,
            $this->validRawGoogleUser(),
            $failure,
        );
        Log::spy();

        $response = $this->withSession(['state' => 'provider-failure-state'])
            ->get($this->callbackUrl([
                'state' => 'provider-failure-state',
                'code' => 'authorization-code-log-sentinel',
            ]));

        $this->assertGenericGoogleFailure($response);
        $this->assertInstanceOf(NetworklessGoogleProvider::class, $provider);
        $this->assertSame(1, $provider->tokenExchangeCalls);
        $this->assertSame(0, $provider->userInfoCalls);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $serialized = $message.serialize($context);
                $allowedKeys = [
                    'category',
                    'exception_type',
                    'request_id',
                ];

                return $message === 'Google authentication failed.'
                    && ($context['category'] ?? null) === 'provider_exception'
                    && ($context['exception_type'] ?? null) === RuntimeException::class
                    && is_string($context['request_id'] ?? null)
                    && $context['request_id'] !== ''
                    && array_diff(array_keys($context), $allowedKeys) === []
                    && ! str_contains($serialized, 'provider raw body')
                    && ! str_contains($serialized, 'authorization-code-log-sentinel')
                    && ! str_contains($serialized, 'access-token-log-sentinel')
                    && ! str_contains($serialized, 'refresh-token-log-sentinel')
                    && ! str_contains($serialized, 'client-secret-log-sentinel')
                    && ! str_contains($serialized, 'private@example.test');
            });
    }

    public function test_non_authoritative_identity_returns_local_ownership_guidance_without_login_or_user_mutation(): void
    {
        $this->readyGoogleConfiguration();
        $subject = 'sub-third-party-private-sentinel';
        $email = 'third-party-private-sentinel@example.test';
        Socialite::fake('google', $this->fakeGoogleUser([
            'sub' => $subject,
            'email' => $email,
            'name' => 'Pengguna E-mel Pihak Ketiga',
            'email_verified' => true,
        ]));
        Log::spy();
        Route::getRoutes()->getByName('auth.google.callback')
            ?->middleware(CaptureSessionRotation::class);

        $response = $this->withSession(['marker' => 'kekal'])
            ->get(route('auth.google.callback'));

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('marker', 'kekal')
            ->assertSessionHas('error');
        $message = $response->getSession()->get('error');
        $this->assertIsString($message);
        $this->assertStringContainsString('daftar atau log masuk', mb_strtolower($message));
        $this->assertStringContainsString('e-mel dan kata laluan', mb_strtolower($message));
        $beforeRotation = $response->headers->get('X-Test-Session-Before');
        $afterRotation = $response->headers->get('X-Test-Session-After');
        $this->assertIsString($beforeRotation);
        $this->assertIsString($afterRotation);
        $this->assertNotSame('', $beforeRotation);
        $this->assertNotSame('', $afterRotation);
        $this->assertSame($beforeRotation, $afterRotation);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($subject, $email): bool {
                $serialized = $message.serialize($context);

                return $message === 'Google authentication failed.'
                    && ($context['category'] ?? null) === 'ownership_challenge_required'
                    && ($context['exception_type'] ?? null) === GoogleAuthenticationException::class
                    && is_string($context['request_id'] ?? null)
                    && array_diff(array_keys($context), [
                        'category',
                        'exception_type',
                        'request_id',
                    ]) === []
                    && ! str_contains($serialized, $subject)
                    && ! str_contains($serialized, $email);
            });
    }

    public function test_provider_tokens_are_never_persisted_in_session_database_model_response_or_logs(): void
    {
        $this->readyGoogleConfiguration();
        $accessToken = 'access-token-storage-sentinel';
        $refreshToken = 'refresh-token-storage-sentinel';
        $loggedEvents = [];
        Log::listen(function (MessageLogged $event) use (&$loggedEvents): void {
            $loggedEvents[] = [$event->level, $event->message, $event->context];
        });
        Socialite::fake('google', $this->fakeGoogleUser(
            [
                'sub' => 'sub-token-storage-check',
                'email' => 'token-storage-check@gmail.com',
                'name' => 'Pengguna Token Storage',
                'email_verified' => true,
            ],
            $accessToken,
            $refreshToken,
        ));

        $response = $this->get(route('auth.google.callback'))
            ->assertRedirect(route('dashboard'));

        $user = Auth::user();
        $this->assertInstanceOf(User::class, $user);
        $artifacts = [
            serialize($response->getSession()->all()),
            serialize($user->fresh()?->toArray()),
            serialize(DB::table('users')->get()->map(fn (stdClass $row): array => (array) $row)->all()),
            serialize($loggedEvents),
            serialize($response->headers->all()),
            (string) $response->getContent(),
        ];

        foreach ($artifacts as $artifact) {
            $this->assertStringNotContainsString($accessToken, $artifact);
            $this->assertStringNotContainsString($refreshToken, $artifact);
        }
        $this->assertDatabaseHas('users', [
            'email' => 'token-storage-check@gmail.com',
            'google_sub' => 'sub-token-storage-check',
        ]);
    }

    #[DataProvider('googleRouteNameProvider')]
    public function test_authenticated_users_cannot_enter_google_guest_routes(string $routeName): void
    {
        $this->readyGoogleConfiguration();
        $user = User::factory()->create();
        $factory = new RejectingGoogleSocialiteFactory;
        Socialite::swap($factory);

        $this->actingAs($user)
            ->get(route($routeName))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, $factory->driverCalls);
        $this->assertDatabaseCount('users', 1);
    }

    /** @return array<string, array{string}> */
    public static function googleRouteNameProvider(): array
    {
        return [
            'redirect' => ['auth.google.redirect'],
            'callback' => ['auth.google.callback'],
        ];
    }

    public function test_redirect_minute_limit_returns_to_login_instead_of_google(): void
    {
        $this->readyGoogleConfiguration();
        $ipAddress = '203.0.113.41';

        foreach (range(1, 30) as $_) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => $ipAddress])
                ->get(route('auth.google.redirect'))
                ->assertRedirect();

            $this->assertSame(
                'accounts.google.com',
                parse_url((string) $response->headers->get('Location'), PHP_URL_HOST),
            );
        }

        $limited = $this->withServerVariables(['REMOTE_ADDR' => $ipAddress])
            ->withHeader('Referer', 'https://accounts.google.com/o/oauth2/auth')
            ->get(route('auth.google.redirect'));

        $limited
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', self::LIMIT_MESSAGE)
            ->assertHeader('Retry-After');
        $this->assertNotSame(
            'accounts.google.com',
            parse_url((string) $limited->headers->get('Location'), PHP_URL_HOST),
        );
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_callback_hour_limit_returns_to_login_without_provider_or_user_mutation(): void
    {
        $this->readyGoogleConfiguration();
        $factory = new RejectingGoogleSocialiteFactory;
        Socialite::swap($factory);
        $ipAddress = '203.0.113.42';

        foreach (range(1, 10) as $attempt) {
            $state = 'cancel-limit-state-'.$attempt;
            $this->withSession(['state' => $state])
                ->withServerVariables(['REMOTE_ADDR' => $ipAddress])
                ->get($this->callbackUrl([
                    'error' => 'access_denied',
                    'state' => $state,
                ]))
                ->assertRedirect(route('login'))
                ->assertSessionHas('error', self::CANCELLED_MESSAGE);
        }

        $limited = $this->withSession(['state' => 'eleventh-state'])
            ->withServerVariables(['REMOTE_ADDR' => $ipAddress])
            ->withHeader('Referer', 'https://accounts.google.com/o/oauth2/auth')
            ->get($this->callbackUrl([
                'error' => 'access_denied',
                'state' => 'eleventh-state',
            ]));

        $limited
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', self::LIMIT_MESSAGE)
            ->assertHeader('Retry-After');
        $this->assertSame(0, $factory->driverCalls);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /** @param array<string, mixed> $overrides */
    private function readyGoogleConfiguration(array $overrides = []): void
    {
        config()->set('app.env', 'testing');
        config()->set('services.google', array_merge([
            'enabled' => true,
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'redirect' => 'https://chatme.test/auth/google/callback',
        ], $overrides));

        $factory = app(SocialiteFactory::class);
        if ($factory instanceof SocialiteManager) {
            $factory->forgetDrivers();
        }
    }

    /**
     * @param  array<string, mixed>  $rawUser
     */
    private function installNetworklessGoogleProvider(
        ?NetworklessGoogleProvider &$resolvedProvider,
        array $rawUser,
        ?Throwable $tokenFailure = null,
        ?array $tokenResponse = null,
    ): void {
        $manager = app(SocialiteFactory::class);
        $this->assertInstanceOf(SocialiteManager::class, $manager);
        $manager->forgetDrivers();
        $resolvedProvider = null;

        $manager->extend(
            'google',
            function (Container $container) use (
                &$resolvedProvider,
                $rawUser,
                $tokenFailure,
                $tokenResponse,
            ): NetworklessGoogleProvider {
                $resolvedProvider = new NetworklessGoogleProvider(
                    $container->make('request'),
                    $rawUser,
                    $tokenFailure,
                    $tokenResponse ?? [
                        'access_token' => 'networkless-access-token',
                        'refresh_token' => 'networkless-refresh-token',
                        'expires_in' => 3600,
                        'scope' => 'openid email profile',
                    ],
                );

                return $resolvedProvider;
            },
        );
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validRawGoogleUser(array $overrides = []): array
    {
        return array_merge([
            'sub' => 'sub-networkless-default',
            'email' => 'networkless-default@gmail.com',
            'name' => 'Pengguna Networkless',
            'email_verified' => true,
        ], $overrides);
    }

    /** @param array<string, mixed> $raw */
    private function fakeGoogleUser(
        array $raw,
        string $accessToken = 'fake-access-token',
        string $refreshToken = 'fake-refresh-token',
    ): SocialiteUser {
        return (new SocialiteUser)
            ->setRaw($raw)
            ->map([
                'id' => $raw['sub'] ?? null,
                'name' => $raw['name'] ?? null,
                'email' => $raw['email'] ?? null,
            ])
            ->setToken($accessToken)
            ->setRefreshToken($refreshToken)
            ->setExpiresIn(3600)
            ->setApprovedScopes(['openid', 'email', 'profile']);
    }

    /** @param array<string, mixed> $query */
    private function callbackUrl(array $query): string
    {
        $url = route('auth.google.callback');

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    private function assertGenericGoogleFailure($response): void
    {
        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');
        $message = $response->getSession()->get('error');
        $this->assertIsString($message);
        $this->assertStringContainsString('Log masuk Google', $message);
        $this->assertNotSame(self::CANCELLED_MESSAGE, $message);
        $this->assertStringNotContainsString('access_token', $message);
        $this->assertStringNotContainsString('client_secret', $message);
    }
}

final class NetworklessGoogleProvider extends GoogleProvider
{
    public int $tokenExchangeCalls = 0;

    public int $userInfoCalls = 0;

    /**
     * @param  array<string, mixed>  $rawUser
     * @param  array<string, mixed>  $tokenResponse
     */
    public function __construct(
        Request $request,
        private readonly array $rawUser,
        private readonly ?Throwable $tokenFailure,
        private readonly array $tokenResponse,
    ) {
        parent::__construct(
            $request,
            'client-id',
            'client-secret',
            'https://chatme.test/auth/google/callback',
        );

        $this->setHttpClient(new Client([
            'handler' => function (): never {
                throw new RuntimeException('Network access is forbidden in this test provider.');
            },
        ]));
    }

    public function getAccessTokenResponse($code)
    {
        $this->tokenExchangeCalls++;

        if ($this->tokenFailure !== null) {
            throw $this->tokenFailure;
        }

        return $this->tokenResponse;
    }

    protected function getUserByToken($token)
    {
        $this->userInfoCalls++;

        return $this->rawUser;
    }
}

final class RejectingGoogleSocialiteFactory implements SocialiteFactory
{
    public int $driverCalls = 0;

    public function driver($driver = null): SocialiteProviderContract
    {
        $this->driverCalls++;

        throw new RuntimeException('Socialite must not be resolved for this request.');
    }
}

final readonly class RawPayloadSocialiteUser implements SocialiteUserContract
{
    public function __construct(
        private mixed $rawPayload,
        private mixed $id,
        private mixed $email,
        private mixed $name,
    ) {}

    public function getRaw(): mixed
    {
        return $this->rawPayload;
    }

    public function getId(): mixed
    {
        return $this->id;
    }

    public function getNickname(): null
    {
        return null;
    }

    public function getName(): mixed
    {
        return $this->name;
    }

    public function getEmail(): mixed
    {
        return $this->email;
    }

    public function getAvatar(): null
    {
        return null;
    }
}

final class CaptureSessionRotation
{
    public function handle(Request $request, Closure $next): mixed
    {
        $before = $request->session()->getId();
        $response = $next($request);

        $response->headers->set('X-Test-Session-Before', $before);
        $response->headers->set('X-Test-Session-After', $request->session()->getId());

        return $response;
    }
}

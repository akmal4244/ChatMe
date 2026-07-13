<?php

namespace Tests\Feature;

use App\Models\User;
use Closure;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class GooglePasswordSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_only_user_can_send_a_password_setup_link_only_to_their_own_email(): void
    {
        Notification::fake();
        $owner = User::factory()->create([
            'email' => 'owner@example.test',
            'password' => null,
        ]);
        $attacker = User::factory()->create(['email' => 'attacker@example.test']);

        $this->actingAs($owner)->post(route('profile.password.setup-link'), [
            'email' => $attacker->email,
            'user_id' => $attacker->getKey(),
        ])->assertRedirect(route('profile.edit'))
            ->assertSessionHas(
                'success',
                'Pautan tetapkan kata laluan telah dihantar ke e-mel anda.',
            );

        Notification::assertSentTo(
            $owner,
            ResetPassword::class,
            fn (ResetPassword $notification): bool => $notification->locale === 'ms',
        );
        Notification::assertNotSentTo($attacker, ResetPassword::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $owner->email]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $attacker->email]);
    }

    public function test_guest_cannot_request_an_authenticated_password_setup_link(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'owner@example.test',
            'password' => null,
        ]);

        $this->post(route('profile.password.setup-link'), [
            'email' => $user->email,
        ])->assertRedirect(route('login'));

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_local_password_user_gets_a_no_op_without_a_token_or_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'local@example.test',
            'password' => 'kata-laluan-tempatan',
        ]);
        $originalPassword = $user->getRawOriginal('password');
        $originalRememberToken = $user->remember_token;

        $this->actingAs($user)->post(route('profile.password.setup-link'), [
            'email' => 'attacker@example.test',
        ])->assertRedirect(route('profile.edit'))
            ->assertSessionHas('info', 'Akaun anda sudah mempunyai kata laluan tempatan.');

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertSame($originalPassword, $user->fresh()->getRawOriginal('password'));
        $this->assertSame($originalRememberToken, $user->fresh()->remember_token);
    }

    public function test_notification_exception_returns_safe_feedback_and_logs_only_redacted_context(): void
    {
        $email = 'rahsia-setup@example.test';
        $user = User::factory()->create([
            'email' => $email,
            'password' => null,
        ]);
        $this->bindFailingNotificationDispatcher();
        Log::spy();

        $this->actingAs($user)->post(route('profile.password.setup-link'), [
            'email' => 'attacker@example.test',
        ])->assertRedirect(route('profile.edit'))
            ->assertSessionHas('error', 'Pautan tidak dapat dihantar. Sila cuba semula.');

        $this->assertAuthenticatedAs($user);
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context) use ($email): bool {
                $serialized = $message.json_encode($context);

                return $message === 'Account notification delivery failed.'
                    && $context['event'] === 'google_password_setup'
                    && $context['email_hash'] === hash('sha256', $email)
                    && $context['exception_type'] === RuntimeException::class
                    && ! str_contains($serialized, $email)
                    && ! str_contains($serialized, 'provider-token-SECRET');
            });
    }

    public function test_password_setup_route_has_the_full_authenticated_session_middleware_stack(): void
    {
        $this->assertTrue(Route::has('profile.password.setup-link'));
        $route = Route::getRoutes()->getByName('profile.password.setup-link');

        $this->assertNotNull($route);
        $this->assertSame(['POST'], $route->methods());
        $middleware = $route->gatherMiddleware();

        foreach (['auth', 'auth.session', 'session.deadline', 'throttle:google-password-setup'] as $name) {
            $this->assertContains($name, $middleware);
        }

        $this->assertNotContains('verified', $middleware);
    }

    public function test_password_setup_limiter_has_minute_and_hour_caps_keyed_by_user_and_ip_not_body(): void
    {
        $firstUser = User::factory()->create(['password' => null]);
        $secondUser = User::factory()->create(['password' => null]);

        $sameIdentityFirstBody = $this->passwordSetupLimits(
            $firstUser,
            '203.0.113.10',
            'attacker-one@example.test',
        );
        $sameIdentitySecondBody = $this->passwordSetupLimits(
            $firstUser,
            '203.0.113.10',
            'attacker-two@example.test',
        );
        $differentUser = $this->passwordSetupLimits(
            $secondUser,
            '203.0.113.10',
            'attacker-one@example.test',
        );
        $differentIp = $this->passwordSetupLimits(
            $firstUser,
            '203.0.113.11',
            'attacker-one@example.test',
        );

        $this->assertCount(2, $sameIdentityFirstBody);
        $this->assertSame(
            [60, 3600],
            collect($sameIdentityFirstBody)->pluck('decaySeconds')->sort()->values()->all(),
        );
        $this->assertSame(
            $this->limitKeys($sameIdentityFirstBody),
            $this->limitKeys($sameIdentitySecondBody),
        );
        $this->assertNotSame(
            $this->limitKeys($sameIdentityFirstBody),
            $this->limitKeys($differentUser),
        );
        $this->assertNotSame(
            $this->limitKeys($sameIdentityFirstBody),
            $this->limitKeys($differentIp),
        );

        foreach ($sameIdentityFirstBody as $limit) {
            $key = (string) $limit->key;

            $this->assertGreaterThan(0, $limit->maxAttempts);
            $this->assertStringContainsString((string) $firstUser->getKey(), $key);
            $this->assertStringContainsString('203.0.113.10', $key);
            $this->assertStringNotContainsString('attacker-one@example.test', $key);
        }
    }

    public function test_password_setup_minute_limit_cannot_be_bypassed_with_request_body_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => null]);
        $limits = $this->passwordSetupLimits($user, '198.51.100.10', 'ignored@example.test');
        $minuteLimit = collect($limits)->firstWhere('decaySeconds', 60);

        $this->assertInstanceOf(Limit::class, $minuteLimit);
        $this->assertLessThanOrEqual(10, $minuteLimit->maxAttempts);

        foreach (range(1, $minuteLimit->maxAttempts) as $attempt) {
            $this->actingAs($user)
                ->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
                ->post(route('profile.password.setup-link'), [
                    'email' => "attacker-{$attempt}@example.test",
                ])->assertRedirect(route('profile.edit'))
                ->assertHeaderMissing('Retry-After');
        }

        $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->post(route('profile.password.setup-link'), [
                'email' => 'another-attacker@example.test',
            ])->assertRedirect(route('profile.edit'))
            ->assertHeader('Retry-After')
            ->assertSessionHas(
                'error',
                'Terlalu banyak permintaan pautan. Sila cuba semula kemudian.',
            );
    }

    public function test_google_only_profile_renders_setup_panel_without_any_current_password_input(): void
    {
        $user = User::factory()->create([
            'password' => null,
        ]);
        $user->forceFill([
            'google_sub' => 'profile-google-subject',
            'google_linked_at' => now(),
        ])->save();

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Kata laluan tempatan belum ditetapkan')
            ->assertSee('Hantar pautan tetapkan kata laluan')
            ->assertSee('action="'.route('profile.password.setup-link').'"', false)
            ->assertSee('readonly aria-readonly="true"', false)
            ->assertDontSee('id="password-form"', false)
            ->assertDontSee('name="current_password"', false)
            ->assertDontSee('autocomplete="current-password"', false);
    }

    public function test_local_password_profile_keeps_the_existing_password_change_form(): void
    {
        $user = User::factory()->create(['password' => 'kata-laluan-tempatan']);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('id="password-form"', false)
            ->assertSee('name="current_password"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertSee('autocomplete="new-password"', false)
            ->assertDontSee('Hantar pautan tetapkan kata laluan');
    }

    public function test_google_only_user_cannot_change_email_before_setting_a_local_password(): void
    {
        $user = User::factory()->create([
            'email' => 'google-only@example.test',
            'password' => null,
        ]);

        $this->actingAs($user)->from(route('profile.edit'))->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'changed@example.test',
            'current_password' => 'cannot-exist',
            'company' => $user->company,
            'website' => $user->website,
        ])->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('current_password');

        $this->assertSame('google-only@example.test', $user->fresh()->email);
        $this->assertNull($user->fresh()->getRawOriginal('password'));
    }

    public function test_authenticated_google_only_user_can_open_and_complete_their_own_secure_reset_link(): void
    {
        $user = User::factory()->create([
            'email' => 'google-reset@example.test',
            'password' => null,
        ]);
        $token = Password::createToken($user);
        $resetUrl = route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]);

        $resetPage = $this->actingAs($user)->get($resetUrl);
        $resetPage
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $cacheControl = (string) $resetPage->headers->get('Cache-Control');
        foreach (['no-store', 'private', 'max-age=0', 'must-revalidate'] as $directive) {
            $this->assertStringContainsString($directive, $cacheControl);
        }

        $this->from($resetUrl)->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'PasswordTempatan123!',
            'password_confirmation' => 'PasswordTempatan123!',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->assertGuest();
        $this->assertTrue(Hash::check('PasswordTempatan123!', (string) $user->fresh()->password));
    }

    public function test_authenticated_user_cannot_reset_a_different_accounts_password(): void
    {
        $currentUser = User::factory()->create(['email' => 'current@example.test']);
        $otherUser = User::factory()->create([
            'email' => 'other@example.test',
            'password' => 'OriginalPassword123!',
        ]);
        $token = Password::createToken($otherUser);
        $resetUrl = route('password.reset', [
            'token' => $token,
            'email' => $otherUser->email,
        ]);

        $this->actingAs($currentUser)->get($resetUrl)
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('error');

        $this->from($resetUrl)->post(route('password.update'), [
            'token' => $token,
            'email' => $otherUser->email,
            'password' => 'HijackedPassword123!',
            'password_confirmation' => 'HijackedPassword123!',
        ])->assertRedirect(route('profile.edit'))
            ->assertSessionHas('error');

        $this->assertAuthenticatedAs($currentUser);
        $this->assertTrue(Hash::check('OriginalPassword123!', (string) $otherUser->fresh()->password));
    }

    /** @return array<int, Limit> */
    private function passwordSetupLimits(User $user, string $ipAddress, string $bodyEmail): array
    {
        $resolver = RateLimiter::limiter('google-password-setup');
        $this->assertInstanceOf(Closure::class, $resolver);
        $request = Request::create(
            '/profil/kata-laluan/pautan-tetapan',
            'POST',
            ['email' => $bodyEmail],
            [],
            [],
            ['REMOTE_ADDR' => $ipAddress],
        );
        $request->setUserResolver(fn (): User => $user);
        $limits = $resolver($request);

        $this->assertIsArray($limits);
        foreach ($limits as $limit) {
            $this->assertInstanceOf(Limit::class, $limit);
        }

        return $limits;
    }

    /** @param array<int, Limit> $limits */
    private function limitKeys(array $limits): array
    {
        return array_map(fn (Limit $limit): string => (string) $limit->key, $limits);
    }

    private function bindFailingNotificationDispatcher(): void
    {
        $this->app->instance(Dispatcher::class, new class implements Dispatcher
        {
            public function send($notifiables, $notification): void
            {
                throw new RuntimeException('provider-token-SECRET');
            }

            public function sendNow($notifiables, $notification, ?array $channels = null): void
            {
                throw new RuntimeException('provider-token-SECRET');
            }
        });
    }
}

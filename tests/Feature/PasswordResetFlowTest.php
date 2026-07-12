<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use RuntimeException;
use Tests\TestCase;

class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_known_account_receives_a_malay_reset_notification_with_a_neutral_response(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'pemilik@example.test']);

        $this->post('/lupa-kata-laluan', ['email' => ' PEMILIK@example.test '])
            ->assertRedirect()
            ->assertSessionHas(
                'success',
                'Jika akaun dengan e-mel tersebut wujud, pautan penetapan semula kata laluan akan dihantar.',
            );

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            fn (ResetPassword $notification): bool => $notification->locale === 'ms',
        );
    }

    public function test_unknown_account_gets_the_same_neutral_response_without_a_notification(): void
    {
        Notification::fake();

        $this->post('/lupa-kata-laluan', ['email' => 'tiada@example.test'])
            ->assertRedirect()
            ->assertSessionHas(
                'success',
                'Jika akaun dengan e-mel tersebut wujud, pautan penetapan semula kata laluan akan dihantar.',
            );

        Notification::assertNothingSent();
    }

    public function test_valid_token_resets_the_password_rotates_remember_token_and_revokes_existing_sessions(): void
    {
        config()->set('session.driver', 'database');
        config()->set('session.table', 'sessions');
        $user = User::factory()->create([
            'password' => 'PasswordLama123!',
            'remember_token' => 'token-lama',
        ]);
        DB::table('sessions')->insert([
            'id' => 'compromised-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'stale-authenticated-session',
            'last_activity' => now()->getTimestamp(),
        ]);
        $chatbot = $user->chatbots()->create(['name' => 'API credential']);
        $chatbot->rotateDeveloperApiToken();
        $token = Password::createToken($user);

        $this->post('/tetap-semula-kata-laluan', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'PasswordBaharu123!',
            'password_confirmation' => 'PasswordBaharu123!',
        ])->assertRedirect('/login')
            ->assertSessionHas('success', 'Kata laluan anda berjaya ditetapkan semula. Sila log masuk.');

        $user->refresh();
        $this->assertTrue(Hash::check('PasswordBaharu123!', $user->password));
        $this->assertNotSame('token-lama', $user->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'compromised-session']);
        $this->assertNull($chatbot->fresh()->developer_api_token_hash);
        $this->assertNull($chatbot->fresh()->developer_api_token_prefix);
    }

    public function test_invalid_and_expired_tokens_are_rejected_with_the_same_malay_error(): void
    {
        $user = User::factory()->create();

        $this->post('/tetap-semula-kata-laluan', [
            'token' => 'token-salah',
            'email' => $user->email,
            'password' => 'PasswordBaharu123!',
            'password_confirmation' => 'PasswordBaharu123!',
        ])->assertSessionHasErrors(['email' => __('passwords.token')]);

        $token = Password::createToken($user);
        $this->travel(61)->minutes();

        $this->post('/tetap-semula-kata-laluan', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'PasswordBaharu123!',
            'password_confirmation' => 'PasswordBaharu123!',
        ])->assertSessionHasErrors(['email' => __('passwords.token')]);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_mail_failure_returns_safe_feedback_and_logs_only_redacted_context(): void
    {
        $email = 'rahsia@example.test';
        User::factory()->create(['email' => $email]);
        Log::spy();
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

        $this->post('/lupa-kata-laluan', ['email' => $email])
            ->assertRedirect()
            ->assertSessionHas(
                'success',
                'Jika akaun dengan e-mel tersebut wujud, pautan penetapan semula kata laluan akan dihantar.',
            );

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context) use ($email): bool {
                $serialized = $message.json_encode($context);

                return $message === 'Account notification delivery failed.'
                    && $context['event'] === 'password_reset'
                    && $context['email_hash'] === hash('sha256', $email)
                    && $context['exception_type'] === RuntimeException::class
                    && ! str_contains($serialized, $email)
                    && ! str_contains($serialized, 'SECRET');
            });
    }

    public function test_recovery_forms_and_login_link_are_accessible_and_in_malay(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('Lupa kata laluan?')
            ->assertSee('href="'.url('/lupa-kata-laluan').'"', false);

        $this->get('/lupa-kata-laluan')->assertOk()
            ->assertSee('<h1 id="forgot-password-heading">Lupa kata laluan</h1>', false)
            ->assertSee('for="email"', false)
            ->assertSee('autocomplete="email"', false)
            ->assertSee('Hantar pautan penetapan semula');

        $this->get('/tetap-semula-kata-laluan/token-ujian?email=pemilik%40example.test')->assertOk()
            ->assertSee('<h1 id="reset-password-heading">Tetapkan semula kata laluan</h1>', false)
            ->assertSee('name="token" value="token-ujian"', false)
            ->assertSee('value="pemilik@example.test"', false)
            ->assertSee('autocomplete="new-password"', false);
    }

    public function test_reset_notification_mail_content_is_in_bahasa_melayu(): void
    {
        $user = User::factory()->create(['email' => 'pemilik@example.test']);
        $mail = (new ResetPassword('token-ujian'))->locale('ms')->toMail($user);

        $this->assertSame('Tetapkan semula kata laluan ChatMe', $mail->subject);
        $this->assertSame('Tetapkan semula kata laluan', $mail->actionText);
        $this->assertStringContainsString('/tetap-semula-kata-laluan/token-ujian', $mail->actionUrl);
        $this->assertStringContainsString('pemilik%40example.test', $mail->actionUrl);
        $this->assertStringContainsString(
            'Pautan ini akan tamat dalam 60 minit.',
            implode(' ', [...$mail->introLines, ...$mail->outroLines]),
        );
    }

    public function test_reset_link_uses_the_configured_app_url_even_with_a_forged_host_header(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'host-safe@example.test']);
        $capturedUrl = null;

        $this->withServerVariables(['HTTP_HOST' => 'attacker.example'])
            ->post('/lupa-kata-laluan', ['email' => $user->email])
            ->assertRedirect();

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use ($user, &$capturedUrl): bool {
                $capturedUrl = $notification->toMail($user)->actionUrl;

                return true;
            },
        );

        $this->assertIsString($capturedUrl);
        $this->assertStringStartsWith(rtrim((string) config('app.url'), '/').'/', $capturedUrl);
        $this->assertStringNotContainsString('attacker.example', $capturedUrl);
    }

    public function test_sixth_reset_link_request_for_the_same_email_and_ip_is_throttled(): void
    {
        Notification::fake();

        foreach (range(1, 5) as $_) {
            $this->post('/lupa-kata-laluan', ['email' => 'tiada@example.test'])
                ->assertRedirect()
                ->assertSessionMissing('error');
        }

        $this->post('/lupa-kata-laluan', ['email' => ' TIADA@example.test '])
            ->assertRedirect()
            ->assertHeader('Retry-After')
            ->assertSessionHas(
                'error',
                'Terlalu banyak permintaan pautan. Sila cuba semula kemudian.',
            );
    }

    public function test_reset_link_requests_have_an_ip_wide_ceiling_across_distinct_emails(): void
    {
        Notification::fake();

        foreach (range(1, 20) as $attempt) {
            $this->post('/lupa-kata-laluan', [
                'email' => "unknown-{$attempt}@example.test",
            ])->assertRedirect()
                ->assertHeaderMissing('Retry-After');
        }

        $this->post('/lupa-kata-laluan', ['email' => 'unknown-blocked@example.test'])
            ->assertRedirect()
            ->assertHeader('Retry-After')
            ->assertSessionHas(
                'error',
                'Terlalu banyak permintaan pautan. Sila cuba semula kemudian.',
            );

        Notification::assertNothingSent();
    }

    public function test_link_request_throttle_does_not_block_a_valid_reset_submission(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $token = Password::createToken($user);

        foreach (range(1, 5) as $_) {
            $this->post('/lupa-kata-laluan', ['email' => $user->email])->assertRedirect();
        }

        $this->post('/tetap-semula-kata-laluan', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'PasswordBaharu123!',
            'password_confirmation' => 'PasswordBaharu123!',
        ])->assertRedirect('/login');
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Tests\TestCase;

class EmailVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_an_unverified_user_sends_malay_mail_and_regenerates_session(): void
    {
        Notification::fake();
        Session::start();
        Session::put('marker', 'kekal');
        $before = Session::getId();

        $this->withSession(['marker' => 'kekal'])->post('/register', [
            'name' => 'Pengguna Baharu',
            'email' => 'baharu@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect('/sahkan-e-mel');

        $user = User::where('email', 'baharu@example.test')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, Session::getId());
        $this->assertSame('kekal', Session::get('marker'));
        Notification::assertSentTo(
            $user,
            VerifyEmail::class,
            fn (VerifyEmail $notification): bool => $notification->locale === 'ms',
        );
    }

    public function test_unverified_user_is_redirected_from_saas_routes_but_can_open_notice_and_logout(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'pemilik.rahsia@example.test',
        ]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/sahkan-e-mel');
        $this->get('/sahkan-e-mel')->assertOk()
            ->assertSee('Sahkan e-mel anda')
            ->assertSee('p*************@e******.test')
            ->assertSee('Hantar semula e-mel pengesahan');
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_saas_and_admin_routes_require_verification_but_account_routes_do_not(): void
    {
        foreach (['dashboard', 'chatbots.index', 'knowledge.index', 'subscription.plans', 'admin.dashboard'] as $name) {
            $this->assertContains('verified', Route::getRoutes()->getByName($name)->gatherMiddleware());
        }

        foreach (['logout', 'verification.notice', 'verification.send', 'verification.verify'] as $name) {
            $this->assertNotContains('verified', Route::getRoutes()->getByName($name)->gatherMiddleware());
        }
    }

    public function test_signed_verification_route_is_registered(): void
    {
        $this->assertTrue(Route::has('verification.verify'));
    }

    public function test_valid_signed_link_verifies_the_current_user(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->actingAs($user)->get($url)
            ->assertRedirect('/dashboard')
            ->assertSessionHas('success', 'E-mel anda berjaya disahkan.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class, fn (Verified $event): bool => $event->user->is($user));
    }

    public function test_unverified_user_can_request_a_malay_verification_notification(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post('/sahkan-e-mel/hantar')
            ->assertRedirect()
            ->assertSessionHas(
                'success',
                'Jika akaun anda masih belum disahkan, e-mel pengesahan telah dihantar.',
            );

        Notification::assertSentTo(
            $user,
            VerifyEmail::class,
            fn (VerifyEmail $notification): bool => $notification->locale === 'ms',
        );
    }

    public function test_verified_user_is_not_sent_another_verification_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/sahkan-e-mel/hantar')
            ->assertRedirect('/dashboard');

        Notification::assertNothingSent();
    }

    public function test_verification_mail_content_is_in_bahasa_melayu(): void
    {
        $user = User::factory()->unverified()->create();
        $mail = (new VerifyEmail)->locale('ms')->toMail($user);

        $this->assertSame('Sahkan e-mel ChatMe anda', $mail->subject);
        $this->assertSame('Sahkan e-mel', $mail->actionText);
        $this->assertStringContainsString('/sahkan-e-mel/'.$user->id.'/', $mail->actionUrl);
        $this->assertStringContainsString(
            'Pautan ini akan tamat dalam 60 minit.',
            implode(' ', [...$mail->introLines, ...$mail->outroLines]),
        );
    }

    public function test_wrong_user_hash_and_expired_signed_links_are_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();

        $wrongHashUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->getKey(),
            'hash' => sha1('alamat-salah@example.test'),
        ]);
        $otherUserUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $other->getKey(),
            'hash' => sha1($other->getEmailForVerification()),
        ]);
        $expiredUrl = URL::temporarySignedRoute('verification.verify', now()->addMinute(), [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->actingAs($user)->get($wrongHashUrl)->assertForbidden();
        $this->get($otherUserUrl)->assertForbidden();
        $this->travel(2)->minutes();
        $this->get($expiredUrl)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_seventh_verification_resend_in_one_minute_is_throttled(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        foreach (range(1, 6) as $_) {
            $this->actingAs($user)->post('/sahkan-e-mel/hantar')
                ->assertRedirect()
                ->assertSessionMissing('error');
        }

        $this->post('/sahkan-e-mel/hantar')
            ->assertRedirect()
            ->assertHeader('Retry-After')
            ->assertSessionHas(
                'error',
                'Terlalu banyak permintaan pengesahan. Sila cuba semula dalam satu minit.',
            );
    }

    public function test_verification_resend_mail_failure_is_safe_and_redacted(): void
    {
        $email = 'rahsia-verifikasi@example.test';
        $user = User::factory()->unverified()->create(['email' => $email]);
        $this->bindFailingNotificationDispatcher();
        Log::spy();

        $this->actingAs($user)->post('/sahkan-e-mel/hantar')
            ->assertRedirect()
            ->assertSessionHas(
                'error',
                'E-mel pengesahan tidak dapat dihantar sekarang. Sila cuba semula sebentar lagi.',
            );

        $this->assertRedactedDeliveryLog('email_verification', $email);
    }

    public function test_registration_survives_verification_mail_failure_with_safe_feedback(): void
    {
        $email = 'daftar-rahsia@example.test';
        $this->bindFailingNotificationDispatcher();
        Log::spy();

        $this->post('/register', [
            'name' => 'Pengguna Baharu',
            'email' => $email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect('/sahkan-e-mel')
            ->assertSessionHas(
                'error',
                'Akaun berjaya dicipta, tetapi e-mel pengesahan tidak dapat dihantar. Sila cuba hantar semula.',
            );

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => $email, 'email_verified_at' => null]);
        $this->assertRedactedDeliveryLog('registration_verification', $email);
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

    private function assertRedactedDeliveryLog(string $event, string $email): void
    {
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context) use ($email, $event): bool {
                $serialized = $message.json_encode($context);

                return $message === 'Account notification delivery failed.'
                    && $context['event'] === $event
                    && $context['email_hash'] === hash('sha256', $email)
                    && $context['exception_type'] === RuntimeException::class
                    && ! str_contains($serialized, $email)
                    && ! str_contains($serialized, 'SECRET');
            });
    }
}

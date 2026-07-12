<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use RuntimeException;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_changes_only_the_authenticated_user_and_requires_reverification_for_new_email(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'asal@example.test',
            'company' => 'Syarikat Asal',
        ]);
        $other = User::factory()->create(['email' => 'lain@example.test']);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Nama Baharu',
            'email' => ' BAHARU@example.test ',
            'current_password' => 'password',
            'company' => 'Syarikat Baharu',
            'website' => 'https://example.test',
            'is_admin' => true,
            'user_id' => $other->id,
        ])->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success', 'Profil anda berjaya dikemas kini. Sila sahkan e-mel baharu anda.');

        $user->refresh();
        $this->assertSame('Nama Baharu', $user->name);
        $this->assertSame('baharu@example.test', $user->email);
        $this->assertSame('Syarikat Baharu', $user->company);
        $this->assertSame('https://example.test', $user->website);
        $this->assertFalse($user->is_admin);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('lain@example.test', $other->fresh()->email);
        Notification::assertSentTo(
            $user,
            VerifyEmail::class,
            fn (VerifyEmail $notification): bool => $notification->locale === 'ms',
        );
        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_unchanged_email_preserves_verification_and_does_not_send_mail(): void
    {
        Notification::fake();
        $verifiedAt = now()->subDay()->startOfSecond();
        $user = User::factory()->create([
            'email' => 'KEKAL@EXAMPLE.TEST',
            'email_verified_at' => $verifiedAt,
        ]);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Nama Kekal',
            'email' => ' KEKAL@example.test ',
            'company' => '',
            'website' => '',
        ])->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success', 'Profil anda berjaya dikemas kini.');

        $user->refresh();
        $this->assertTrue($verifiedAt->equalTo($user->email_verified_at));
        $this->assertSame('kekal@example.test', $user->email);
        $this->assertNull($user->company);
        $this->assertNull($user->website);
        Notification::assertNothingSent();
    }

    public function test_duplicate_email_and_non_http_website_are_rejected(): void
    {
        $user = User::factory()->create(['email' => 'pemilik@example.test']);
        User::factory()->create(['email' => 'digunakan@example.test']);

        $this->actingAs($user)->from(route('profile.edit'))->patch(route('profile.update'), [
            'name' => 'Pemilik',
            'email' => 'digunakan@example.test',
            'company' => null,
            'website' => 'ftp://example.test/fail',
        ])->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors(['email', 'website']);

        $this->assertSame('pemilik@example.test', $user->fresh()->email);
    }

    public function test_profile_cannot_preclaim_reserved_or_case_insensitive_existing_email(): void
    {
        config(['chatme.admin.email' => 'Primary.Admin@Example.com']);
        $user = User::factory()->create(['email' => 'pemilik@example.test']);
        User::factory()->create(['email' => 'Already.Used@Example.test']);

        foreach ([
            ' HOMEPAGE-BOT@CHATME.INVALID ',
            ' primary.ADMIN@example.COM ',
            ' already.used@example.TEST ',
        ] as $unavailableEmail) {
            $this->actingAs($user)->from(route('profile.edit'))->patch(route('profile.update'), [
                'name' => 'Pemilik',
                'email' => $unavailableEmail,
                'current_password' => 'password',
                'company' => null,
                'website' => null,
            ])->assertRedirect(route('profile.edit'))
                ->assertSessionHasErrors('email');
        }

        $this->assertSame('pemilik@example.test', $user->fresh()->email);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_email_change_requires_the_current_password_but_ordinary_profile_edits_do_not(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'pemilik@example.test',
            'password' => 'kata-laluan-semasa',
        ]);

        foreach ([null, 'kata-laluan-salah'] as $currentPassword) {
            $this->actingAs($user)->from(route('profile.edit'))->patch(route('profile.update'), [
                'name' => 'Pemilik',
                'email' => 'penyerang@example.test',
                'current_password' => $currentPassword,
                'company' => null,
                'website' => null,
            ])->assertRedirect(route('profile.edit'))
                ->assertSessionHasErrors('current_password');
        }

        $this->assertSame('pemilik@example.test', $user->fresh()->email);
        Notification::assertNothingSent();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Nama Tanpa Tukar E-mel',
            'email' => ' PEMILIK@example.test ',
            'company' => null,
            'website' => null,
        ])->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Nama Tanpa Tukar E-mel', $user->fresh()->name);
    }

    public function test_email_change_current_password_attempts_have_a_cross_ip_user_ceiling(): void
    {
        $user = User::factory()->create([
            'email' => 'pemilik@example.test',
            'password' => 'kata-laluan-semasa',
        ]);

        foreach (range(1, 10) as $attempt) {
            $this->actingAs($user)
                ->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$attempt}"])
                ->patch(route('profile.update'), [
                    'name' => 'Pemilik',
                    'email' => 'baharu@example.test',
                    'current_password' => 'salah',
                    'company' => null,
                    'website' => null,
                ])->assertSessionHasErrors('current_password')
                ->assertHeaderMissing('Retry-After');
        }

        $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->patch(route('profile.update'), [
                'name' => 'Pemilik',
                'email' => 'baharu@example.test',
                'current_password' => 'salah',
                'company' => null,
                'website' => null,
            ])->assertRedirect()
            ->assertHeader('Retry-After')
            ->assertSessionHas(
                'error',
                'Terlalu banyak perubahan profil. Sila cuba semula kemudian.',
            );

        $this->assertSame('pemilik@example.test', $user->fresh()->email);
    }

    public function test_system_account_can_save_profile_when_its_reserved_email_is_unchanged(): void
    {
        config(['chatme.admin.email' => 'primary.admin@example.test']);
        $admin = User::factory()->create([
            'email' => 'primary.admin@example.test',
            'is_admin' => true,
        ]);
        $admin->forceFill(['system_role' => 'primary_admin'])->save();

        $this->actingAs($admin)->patch(route('profile.update'), [
            'name' => 'Pentadbir Utama Dikemas Kini',
            'email' => ' PRIMARY.ADMIN@example.test ',
            'company' => null,
            'website' => null,
        ])->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Pentadbir Utama Dikemas Kini', $admin->fresh()->name);
        $this->assertSame('primary.admin@example.test', $admin->fresh()->email);
    }

    public function test_unverified_user_can_open_accessible_profile_forms_and_resend_verification(): void
    {
        $user = User::factory()->unverified()->create([
            'name' => 'Pemilik Akaun',
            'email' => 'pemilik@example.test',
        ]);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk()
            ->assertSee('Profil akaun')
            ->assertSee('E-mel anda belum disahkan')
            ->assertSee('action="'.route('verification.send').'"', false)
            ->assertSee('id="profile-form"', false)
            ->assertSee('id="password-form"', false)
            ->assertSee('autocomplete="organization"', false)
            ->assertSee('autocomplete="url"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertSee('autocomplete="new-password"', false)
            ->assertSee('aria-describedby="profile-email-hint', false)
            ->assertSee("document.querySelectorAll('form[data-submit-loading]')", false)
            ->assertSee('submitButton.disabled = true', false);

        $routeMiddleware = Route::getRoutes()->getByName('profile.edit')->gatherMiddleware();
        $this->assertContains('auth', $routeMiddleware);
        $this->assertNotContains('verified', $routeMiddleware);
    }

    public function test_all_profile_routes_require_authentication_without_requiring_verification(): void
    {
        foreach (['profile.edit', 'profile.update', 'profile.password.update'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertContains('auth', $middleware);
            $this->assertNotContains('verified', $middleware);
        }

        $this->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->patch(route('profile.update'))->assertRedirect(route('login'));
        $this->put(route('profile.password.update'))->assertRedirect(route('login'));
    }

    public function test_email_change_survives_notification_failure_with_safe_redacted_feedback(): void
    {
        $oldEmail = 'asal-rahsia@example.test';
        $newEmail = 'baharu-rahsia@example.test';
        $user = User::factory()->create(['email' => $oldEmail]);
        $this->bindFailingNotificationDispatcher();
        Log::spy();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $newEmail,
            'current_password' => 'password',
            'company' => null,
            'website' => null,
        ])->assertRedirect(route('profile.edit'))
            ->assertSessionHas(
                'error',
                'Profil berjaya dikemas kini, tetapi e-mel pengesahan tidak dapat dihantar. Sila cuba hantar semula.',
            );

        $user->refresh();
        $this->assertSame($newEmail, $user->email);
        $this->assertNull($user->email_verified_at);
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context) use ($newEmail): bool {
                $serialized = $message.json_encode($context);

                return $message === 'Account notification delivery failed.'
                    && $context['event'] === 'profile_email_verification'
                    && $context['email_hash'] === hash('sha256', $newEmail)
                    && $context['exception_type'] === RuntimeException::class
                    && ! str_contains($serialized, $newEmail)
                    && ! str_contains($serialized, 'SECRET');
            });
    }

    public function test_wrong_current_password_is_rejected_without_changing_credentials(): void
    {
        $user = User::factory()->create([
            'password' => 'kata-laluan-asal',
            'remember_token' => 'token-asal',
        ]);
        $this->actingAs($user)->from(route('profile.edit'))->put(route('profile.password.update'), [
            'current_password' => 'salah',
            'password' => 'KataLaluanBaharu123!',
            'password_confirmation' => 'KataLaluanBaharu123!',
        ])->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('current_password');

        $user->refresh();
        $this->assertTrue(Hash::check('kata-laluan-asal', $user->password));
        $this->assertSame('token-asal', $user->remember_token);
    }

    public function test_sensitive_current_password_attempts_have_a_shared_cross_ip_user_ceiling(): void
    {
        $user = User::factory()->create(['password' => 'kata-laluan-semasa']);

        foreach (range(1, 20) as $attempt) {
            $this->actingAs($user)
                ->withServerVariables(['REMOTE_ADDR' => "198.51.100.{$attempt}"])
                ->put(route('profile.password.update'), [
                    'current_password' => 'salah',
                    'password' => 'KataLaluanBaharu123!',
                    'password_confirmation' => 'KataLaluanBaharu123!',
                ])->assertSessionHasErrors('current_password')
                ->assertHeaderMissing('Retry-After');
        }

        $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])
            ->put(route('profile.password.update'), [
                'current_password' => 'salah',
                'password' => 'KataLaluanBaharu123!',
                'password_confirmation' => 'KataLaluanBaharu123!',
            ])->assertRedirect()
            ->assertHeader('Retry-After')
            ->assertSessionHas(
                'error',
                'Terlalu banyak percubaan tindakan sensitif. Sila cuba semula kemudian.',
            );
    }

    public function test_password_validation_names_the_new_password_field_in_malay(): void
    {
        $user = User::factory()->create(['password' => 'kata-laluan-asal']);

        $response = $this->actingAs($user)->from(route('profile.edit'))
            ->put(route('profile.password.update'), [
                'current_password' => 'kata-laluan-asal',
                'password' => 'pendek',
                'password_confirmation' => 'pendek',
            ])->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('password');

        $this->assertStringContainsString(
            'kata laluan baharu',
            $response->getSession()->get('errors')->first('password'),
        );
    }

    public function test_confirmed_password_update_rotates_tokens_and_session_but_keeps_user_authenticated(): void
    {
        $user = User::factory()->create([
            'password' => 'kata-laluan-asal',
            'remember_token' => 'token-asal',
        ]);
        $chatbot = $user->chatbots()->create(['name' => 'Token perlu dibatalkan']);
        $chatbot->rotateDeveloperApiToken();
        config()->set('session.driver', 'database');
        config()->set('session.table', 'sessions');
        Session::start();
        Session::put('marker', 'kekal');
        $before = Session::getId();
        DB::table('sessions')->insert([
            'id' => $before,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'pre-change-session',
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->actingAs($user)->withSession(['marker' => 'kekal'])
            ->put(route('profile.password.update'), [
                'current_password' => 'kata-laluan-asal',
                'password' => 'KataLaluanBaharu123!',
                'password_confirmation' => 'KataLaluanBaharu123!',
            ])->assertRedirect(route('profile.edit'))
            ->assertSessionHas(
                'success',
                'Kata laluan anda berjaya dikemas kini. Sesi lain telah ditamatkan apabila disokong.',
            );

        $user->refresh();
        $this->assertTrue(Hash::check('KataLaluanBaharu123!', $user->password));
        $this->assertNotSame('token-asal', $user->remember_token);
        $this->assertNull($chatbot->fresh()->developer_api_token_hash);
        $this->assertNull($chatbot->fresh()->developer_api_token_prefix);
        $this->assertNotSame($before, Session::getId());
        $this->assertDatabaseMissing('sessions', ['id' => $before]);
        $this->assertSame('kekal', Session::get('marker'));
        $this->assertAuthenticatedAs($user);
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

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_sixth_failed_login_is_throttled_by_normalized_email_and_ip(): void
    {
        $user = User::factory()->create([
            'email' => 'Pemilik@Example.com',
            'password' => 'password-betul',
        ]);

        foreach (range(1, 5) as $_) {
            $this->post('/login', [
                'email' => ' PEMILIK@example.com ',
                'password' => 'password-salah',
            ])
                ->assertRedirect()
                ->assertSessionHasErrors('email')
                ->assertHeaderMissing('Retry-After');
        }

        $throttled = $this->post('/login', [
            'email' => 'pemilik@example.com',
            'password' => 'password-salah',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('email')
            ->assertHeader('Retry-After');

        $this->assertStringContainsString(
            'Terlalu banyak percubaan log masuk.',
            $throttled->getSession()->get('errors')->first('email'),
        );

        $this->post('/login', [
            'email' => 'alamat-lain@example.com',
            'password' => 'password-salah',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('email')
            ->assertHeaderMissing('Retry-After');

        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_successful_login_clears_the_failed_attempt_counter(): void
    {
        $user = User::factory()->create([
            'email' => 'clear@example.com',
            'password' => 'password-betul',
        ]);
        $key = Str::transliterate('clear@example.com|127.0.0.1');

        foreach (range(1, 4) as $_) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'password-salah',
            ])->assertSessionHasErrors('email');
        }

        $this->assertSame(4, RateLimiter::attempts($key));

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password-betul',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, RateLimiter::attempts($key));
    }

    public function test_failed_login_is_logged_with_a_hash_instead_of_credentials(): void
    {
        User::factory()->create([
            'email' => 'audit@example.com',
            'password' => 'password-betul',
        ]);
        Log::spy();

        $this->post('/login', [
            'email' => ' Audit@Example.com ',
            'password' => 'password-salah-rahsia',
        ])->assertSessionHasErrors('email');

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $serialized = $message.json_encode($context);

                return $message === 'Authentication failed.'
                    && $context['email_hash'] === hash('sha256', 'audit@example.com')
                    && $context['ip_address'] === '127.0.0.1'
                    && ! str_contains($serialized, 'audit@example.com')
                    && ! str_contains($serialized, 'password-salah-rahsia');
            });
    }

    public function test_fourth_registration_submission_from_one_ip_is_throttled(): void
    {
        foreach (range(1, 3) as $_) {
            $this->post('/register', [
                'name' => '',
                'email' => 'invalid',
                'password' => '',
            ])
                ->assertRedirect()
                ->assertSessionHasErrors()
                ->assertHeaderMissing('Retry-After');
        }

        $this->post('/register', [
            'name' => 'Percubaan keempat',
            'email' => 'keempat@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'email' => 'Terlalu banyak percubaan pendaftaran. Sila cuba semula kemudian.',
            ])
            ->assertHeader('Retry-After');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_regenerates_the_session_id_and_preserves_session_data(): void
    {
        Session::start();
        Session::put('marker', 'kekal');
        $before = Session::getId();

        $this->withSession(['marker' => 'kekal'])
            ->post('/register', [
                'name' => 'Pengguna Baharu',
                'email' => 'baharu@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertRedirect(route('onboarding'));

        $this->assertTrue(Auth::check());
        $this->assertNotSame($before, Session::getId());
        $this->assertSame('kekal', Session::get('marker'));
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\GoogleAuthenticationException;
use App\Http\Controllers\Controller;
use App\Services\GoogleAccountService;
use App\Services\GoogleAuthConfiguration;
use App\ValueObjects\GoogleIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use RuntimeException;
use Throwable;

final class GoogleAuthController extends Controller
{
    private const CONFIGURATION_MESSAGE = 'Log masuk Google tidak tersedia buat sementara waktu.';

    private const GENERIC_FAILURE_MESSAGE = 'Log masuk Google tidak dapat diselesaikan. Sila cuba semula.';

    private const OWNERSHIP_MESSAGE = 'Google tidak dapat mengesahkan pemilikan e-mel ini. Sila daftar atau log masuk menggunakan e-mel dan kata laluan ChatMe.';

    public function __construct(
        private readonly GoogleAuthConfiguration $configuration,
        private readonly GoogleAccountService $accounts,
    ) {}

    public function redirect(): RedirectResponse
    {
        if (! $this->configuration->isReady()) {
            return redirect()->route('login')->with('error', self::CONFIGURATION_MESSAGE);
        }

        try {
            $provider = Socialite::driver('google');
            if (! $provider instanceof AbstractProvider) {
                throw new RuntimeException('Unexpected Google provider type.');
            }

            return $provider
                ->setScopes(['openid', 'email', 'profile'])
                ->with([
                    'prompt' => 'select_account',
                    'hl' => 'ms',
                ])
                ->redirect();
        } catch (Throwable $exception) {
            return $this->fail('provider_exception', $exception);
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->configuration->isReady()) {
            $request->session()->forget('state');

            return redirect()->route('login')->with('error', self::CONFIGURATION_MESSAGE);
        }

        if ($request->query->has('error')) {
            return $this->handleProviderError($request, $request->query('error'));
        }

        try {
            $provider = Socialite::driver('google');

            $sessionState = $request->session()->get('state');
            if (($sessionState !== null && ! is_string($sessionState))
                || ($request->query->has('state') && ! is_string($request->query('state')))
            ) {
                $request->session()->pull('state');

                throw new InvalidStateException;
            }

            $providerUser = $provider->user();
            $identity = $this->identityFromProvider($providerUser);
            $user = $this->accounts->resolve($identity);
        } catch (InvalidStateException $exception) {
            return $this->fail('invalid_state', $exception);
        } catch (GoogleAuthenticationException $exception) {
            $message = $exception->reason() === 'ownership_challenge_required'
                ? self::OWNERSHIP_MESSAGE
                : self::GENERIC_FAILURE_MESSAGE;

            return $this->fail($exception->reason(), $exception, $message);
        } catch (Throwable $exception) {
            return $this->fail('provider_exception', $exception);
        } finally {
            $request->session()->forget('state');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    private function handleProviderError(Request $request, mixed $providerError): RedirectResponse
    {
        $expectedState = $request->session()->pull('state');
        $providedState = $request->query('state');

        if (! is_string($expectedState)
            || $expectedState === ''
            || ! is_string($providedState)
            || $providedState === ''
            || ! hash_equals($expectedState, $providedState)
        ) {
            return $this->fail('invalid_state', new InvalidStateException);
        }

        if ($providerError === 'access_denied') {
            return redirect()->route('login')->with('error', 'Log masuk Google dibatalkan.');
        }

        return $this->fail(
            'oauth_error',
            new RuntimeException('Google OAuth provider returned an error.'),
        );
    }

    private function identityFromProvider(mixed $providerUser): GoogleIdentity
    {
        if (! is_object($providerUser)
            || ! method_exists($providerUser, 'getRaw')
            || ! method_exists($providerUser, 'getId')
            || ! method_exists($providerUser, 'getEmail')
            || ! method_exists($providerUser, 'getName')
        ) {
            throw GoogleAuthenticationException::invalidIdentity();
        }

        $raw = $providerUser->getRaw();
        if (! is_array($raw)) {
            throw GoogleAuthenticationException::invalidIdentity();
        }

        $hasCanonicalVerification = array_key_exists('email_verified', $raw);
        $hasLegacyVerification = array_key_exists('verified_email', $raw);

        if ($hasCanonicalVerification
            && $hasLegacyVerification
            && $raw['email_verified'] !== $raw['verified_email']
        ) {
            throw GoogleAuthenticationException::invalidIdentity();
        }

        $verified = $hasCanonicalVerification
            ? $raw['email_verified']
            : ($hasLegacyVerification ? $raw['verified_email'] : null);

        return GoogleIdentity::fromProvider(
            $providerUser->getId(),
            $providerUser->getEmail(),
            $providerUser->getName(),
            $verified,
            $raw['hd'] ?? null,
        );
    }

    private function fail(
        string $category,
        Throwable $exception,
        string $message = self::GENERIC_FAILURE_MESSAGE,
    ): RedirectResponse {
        Log::warning('Google authentication failed.', [
            'category' => $category,
            'exception_type' => $exception::class,
            'request_id' => (string) Str::uuid(),
        ]);

        return redirect()->route('login')->with('error', $message);
    }
}

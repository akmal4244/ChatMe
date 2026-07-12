<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Rules\AccountEmailAvailability;
use App\Support\AccountNotificationFailureLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        private readonly AccountNotificationFailureLogger $notificationFailureLogger,
    ) {}

    /**
     * Show the registration form.
     */
    public function showRegister()
    {
        return view('auth.register');
    }

    /**
     * Handle user registration.
     */
    public function register(Request $request)
    {
        $normalizedEmail = Str::lower(trim((string) $request->input('email')));
        $request->merge(['email' => $normalizedEmail]);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'bail',
                'required',
                'string',
                'email',
                'max:255',
                new AccountEmailAvailability,
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
            'company' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'company' => $request->company,
            'website' => $request->website,
        ]);

        $notificationFailed = false;
        try {
            event(new Registered($user));
        } catch (Throwable $exception) {
            $notificationFailed = true;
            $this->notificationFailureLogger->report('registration_verification', $user->email, $exception);
        }
        Auth::login($user);
        $request->session()->regenerate();

        $redirect = redirect()->route('verification.notice');

        return $notificationFailed
            ? $redirect->with(
                'error',
                'Akaun berjaya dicipta, tetapi e-mel pengesahan tidak dapat dihantar. Sila cuba hantar semula.',
            )
            : $redirect;
    }

    /**
     * Show the login form.
     */
    public function showLogin(Request $request)
    {
        if ($request->boolean('session_expired')) {
            $request->session()->flash('info', 'Sesi anda telah tamat. Sila log masuk semula.');
        }

        return view('auth.login');
    }

    /**
     * Handle user login.
     */
    public function login(LoginRequest $request)
    {
        $request->authenticate();
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Handle user logout.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

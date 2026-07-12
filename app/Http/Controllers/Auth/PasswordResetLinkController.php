<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AccountNotificationFailureLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PasswordResetLinkController extends Controller
{
    public function __construct(
        private readonly AccountNotificationFailureLogger $notificationFailureLogger,
    ) {}

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        try {
            Password::sendResetLink(['email' => $validated['email']]);
        } catch (Throwable $exception) {
            $this->notificationFailureLogger->report('password_reset', $validated['email'], $exception);
        }

        return back()
            ->with(
                'success',
                'Jika akaun dengan e-mel tersebut wujud, pautan penetapan semula kata laluan akan dihantar.',
            )
            ->withInput($request->only('email'));
    }
}

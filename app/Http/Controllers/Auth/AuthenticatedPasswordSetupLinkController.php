<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AccountNotificationFailureLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Throwable;

final class AuthenticatedPasswordSetupLinkController extends Controller
{
    public function __construct(
        private readonly AccountNotificationFailureLogger $notificationFailureLogger,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasLocalPassword()) {
            return redirect()->route('profile.edit')->with(
                'info',
                'Akaun anda sudah mempunyai kata laluan tempatan.',
            );
        }

        try {
            $status = Password::sendResetLink([
                'email' => (string) $user->email,
            ]);
        } catch (Throwable $exception) {
            $this->notificationFailureLogger->report(
                'google_password_setup',
                (string) $user->email,
                $exception,
            );

            return redirect()->route('profile.edit')->with(
                'error',
                'Pautan tidak dapat dihantar. Sila cuba semula.',
            );
        }

        return $status === Password::RESET_LINK_SENT
            ? redirect()->route('profile.edit')->with(
                'success',
                'Pautan tetapkan kata laluan telah dihantar ke e-mel anda.',
            )
            : redirect()->route('profile.edit')->with(
                'error',
                'Pautan tidak dapat dihantar. Sila cuba semula.',
            );
    }
}

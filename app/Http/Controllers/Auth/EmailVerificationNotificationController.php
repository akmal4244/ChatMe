<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AccountNotificationFailureLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class EmailVerificationNotificationController extends Controller
{
    public function __construct(
        private readonly AccountNotificationFailureLogger $notificationFailureLogger,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            $this->notificationFailureLogger->report(
                'email_verification',
                $request->user()->email,
                $exception,
            );

            return back()->with(
                'error',
                'E-mel pengesahan tidak dapat dihantar sekarang. Sila cuba semula sebentar lagi.',
            );
        }

        return back()->with(
            'success',
            'Jika akaun anda masih belum disahkan, e-mel pengesahan telah dihantar.',
        );
    }
}

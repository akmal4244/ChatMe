<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\AccountSessionService;
use App\Support\AccountNotificationFailureLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ProfileController extends Controller
{
    public function __construct(
        private readonly AccountSessionService $accountSessionService,
        private readonly AccountNotificationFailureLogger $notificationFailureLogger,
    ) {}

    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $originalEmail = Str::lower(trim((string) $user->email));
        $user->fill($request->validated());
        $emailChanged = $originalEmail !== Str::lower(trim((string) $user->email));

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if (! $emailChanged) {
            return redirect()->route('profile.edit')
                ->with('success', 'Profil anda berjaya dikemas kini.');
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            $this->notificationFailureLogger->report('profile_email_verification', $user->email, $exception);

            return redirect()->route('profile.edit')->with(
                'error',
                'Profil berjaya dikemas kini, tetapi e-mel pengesahan tidak dapat dihantar. Sila cuba hantar semula.',
            );
        }

        return redirect()->route('profile.edit')
            ->with('success', 'Profil anda berjaya dikemas kini. Sila sahkan e-mel baharu anda.');
    }

    public function updatePassword(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $currentSessionId = $request->session()->getId();

        $user->forceFill([
            'password' => Hash::make((string) $request->validated('password')),
            'remember_token' => Str::random(60),
        ])->save();

        $user->chatbots()->update([
            'developer_api_token_hash' => null,
            'developer_api_token_prefix' => null,
        ]);

        $this->accountSessionService->revokeOtherDatabaseSessions($user, $currentSessionId);
        $request->session()->regenerate(true);

        return redirect()->route('profile.edit')->with(
            'success',
            'Kata laluan anda berjaya dikemas kini. Sesi lain telah ditamatkan apabila disokong.',
        );
    }
}

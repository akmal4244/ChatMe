<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountSessionService;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function __construct(
        private readonly AccountSessionService $accountSessionService,
    ) {}

    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'email' => (string) $request->query('email', ''),
            'token' => $token,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->chatbots()->update([
                    'developer_api_token_hash' => null,
                    'developer_api_token_prefix' => null,
                ]);

                $this->accountSessionService->revokeAllDatabaseSessions($user);

                event(new PasswordResetEvent($user));
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                ->with('success', 'Kata laluan anda berjaya ditetapkan semula. Sila log masuk.');
        }

        return back()
            ->withErrors(['email' => __('passwords.token')])
            ->withInput($request->only('email'));
    }
}

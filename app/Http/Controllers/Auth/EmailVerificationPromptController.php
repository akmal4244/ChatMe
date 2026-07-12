<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|View
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return view('auth.verify-email', [
            'maskedEmail' => $this->maskEmail((string) $request->user()->email),
        ]);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $domainParts = explode('.', $domain);
        $domainName = array_shift($domainParts) ?? '';

        $maskedLocal = Str::substr($local, 0, 1).str_repeat('*', max(0, Str::length($local) - 1));
        $maskedDomain = Str::substr($domainName, 0, 1).str_repeat('*', max(0, Str::length($domainName) - 1));
        $suffix = $domainParts === [] ? '' : '.'.implode('.', $domainParts);

        return $maskedLocal.'@'.$maskedDomain.$suffix;
    }
}

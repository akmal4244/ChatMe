<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

final class AccountEmailAvailability implements ValidationRule
{
    public function __construct(
        private readonly ?int $ignoreUserId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = Str::lower(trim((string) $value));

        if ($this->ignoreUserId !== null) {
            $currentUser = User::query()
                ->select(['id', 'email', 'system_role'])
                ->find($this->ignoreUserId);
            if ($currentUser !== null
                && Str::lower(trim((string) $currentUser->email)) === $email) {
                return;
            }

            if ($currentUser !== null && filled($currentUser->system_role)) {
                $fail('Alamat e-mel akaun sistem tidak boleh diubah.');

                return;
            }
        }

        $reservedEmails = collect([
            'homepage-bot@chatme.invalid',
            config('chatme.admin.email'),
        ])->filter(fn (mixed $reserved): bool => filled($reserved))
            ->map(fn (mixed $reserved): string => Str::lower(trim((string) $reserved)))
            ->all();

        if (in_array($email, $reservedEmails, true)) {
            $fail('Alamat e-mel ini tidak boleh digunakan.');

            return;
        }

        $query = User::query()->whereRaw('LOWER(email) = ?', [$email]);

        if ($this->ignoreUserId !== null) {
            $query->whereKeyNot($this->ignoreUserId);
        }

        if ($query->exists()) {
            $fail(__('validation.unique', [
                'attribute' => __('validation.attributes.email'),
            ]));
        }
    }
}

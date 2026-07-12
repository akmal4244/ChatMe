<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\AccountEmailAvailability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $currentEmail = Str::lower(trim((string) $this->user()->email));
        $emailChanged = $currentEmail !== (string) $this->input('email');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                new AccountEmailAvailability((int) $this->user()->getKey()),
                Rule::unique(User::class)->ignore($this->user()->getKey()),
            ],
            'company' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255', 'url:http,https'],
            'current_password' => $emailChanged
                ? ['required', 'current_password:web']
                : ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'current_password' => 'kata laluan semasa',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'company' => $this->nullableTrimmed('company'),
            'website' => $this->nullableTrimmed('website'),
        ]);
    }

    private function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}

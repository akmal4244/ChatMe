<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MalayLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_and_fallback_locale_are_malay(): void
    {
        $this->assertSame('ms', app()->getLocale());
        $this->assertSame('ms', config('app.fallback_locale'));
        $this->assertSame('ms_MY', config('app.faker_locale'));
    }

    public function test_business_time_uses_kuala_lumpur_while_storage_stays_utc(): void
    {
        $this->assertSame('Asia/Kuala_Lumpur', config('chatme.timezone'));
        $this->assertSame('UTC', config('app.timezone'));
    }

    public function test_validation_messages_and_attributes_are_malay(): void
    {
        $validator = validator(
            [
                'email' => 'bukan-emel',
                'password' => 'rahsia',
                'password_confirmation' => 'berbeza',
            ],
            [
                'name' => ['required'],
                'email' => ['required', 'email'],
                'password' => ['confirmed'],
            ]
        );

        $this->assertSame('Ruangan nama wajib diisi.', $validator->errors()->first('name'));
        $this->assertSame('Ruangan alamat e-mel mestilah alamat e-mel yang sah.', $validator->errors()->first('email'));
        $this->assertSame('Pengesahan kata laluan tidak sepadan.', $validator->errors()->first('password'));
    }

    public function test_failed_login_message_is_malay(): void
    {
        $this->post('/login', [
            'email' => 'tiada@example.test',
            'password' => 'salah',
        ])->assertSessionHasErrors([
            'email' => 'E-mel atau kata laluan yang dimasukkan tidak sepadan dengan rekod kami.',
        ]);
    }

    public function test_pagination_labels_are_malay(): void
    {
        $this->assertSame('Sebelumnya', __('pagination.previous'));
        $this->assertSame('Seterusnya', __('pagination.next'));
    }
}

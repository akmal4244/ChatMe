<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class AccountNotificationFailureLogger
{
    public function report(string $event, string $email, Throwable $exception): void
    {
        Log::error('Account notification delivery failed.', [
            'event' => $event,
            'email_hash' => hash('sha256', Str::lower(trim($email))),
            'exception_type' => $exception::class,
        ]);
    }
}

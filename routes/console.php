<?php

use App\Services\MessageQuotaService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('chatme:prune-message-quota-reservations', function (MessageQuotaService $quotas): void {
    $this->info($quotas->pruneExpired().' tempahan kuota luput dibersihkan.');
})->purpose('Buang tempahan kuota mesej yang telah luput');

Schedule::command('queue:prune-failed --hours=168')
    ->dailyAt('02:10')
    ->withoutOverlapping();

Schedule::command('queue:prune-batches --hours=168 --unfinished=168 --cancelled=168')
    ->dailyAt('02:20')
    ->withoutOverlapping();

Schedule::command('chatme:prune-message-quota-reservations')
    ->everyFiveMinutes()
    ->withoutOverlapping();

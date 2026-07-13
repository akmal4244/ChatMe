<?php

namespace App\Http\Controllers;

use App\Services\GoogleAuthConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

class HealthController extends Controller
{
    public function __construct(
        private readonly GoogleAuthConfiguration $googleAuth,
    ) {}

    public function __invoke(): JsonResponse
    {
        $checks = [
            'application' => 'ok',
            'database' => $this->databaseStatus(),
            'queue' => $this->queueStatus(),
            'storage' => $this->storageStatus(),
            'payments' => $this->paymentStatus(),
            'ai' => $this->aiStatus(),
            'google_auth' => $this->googleAuth->status(),
        ];

        $healthy = collect($checks)
            ->every(fn (string $status): bool => in_array($status, ['ok', 'disabled'], true));

        return response()->json([
            'status' => $healthy ? 'ok' : 'failed',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    private function databaseStatus(): string
    {
        try {
            DB::select('select 1');

            return 'ok';
        } catch (Throwable) {
            return 'failed';
        }
    }

    private function queueStatus(): string
    {
        try {
            Queue::connection()->size();

            return 'ok';
        } catch (Throwable) {
            return 'failed';
        }
    }

    private function storageStatus(): string
    {
        $path = (string) config('chatme.health.storage_path', storage_path());

        return is_dir($path) && is_readable($path) && is_writable($path)
            ? 'ok'
            : 'failed';
    }

    private function paymentStatus(): string
    {
        return filled(config('services.toyyibpay.secret_key'))
            && filled(config('services.toyyibpay.category_code'))
            ? 'ok'
            : 'failed';
    }

    private function aiStatus(): string
    {
        if (! config('services.cloudflare_ai.enabled')) {
            return 'disabled';
        }

        return filled(config('services.cloudflare_ai.account_id'))
            && filled(config('services.cloudflare_ai.token'))
            ? 'ok'
            : 'failed';
    }
}

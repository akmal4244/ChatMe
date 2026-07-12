<?php

namespace App\Http\Controllers;

use App\Models\PaymentOrder;
use App\Models\User;
use App\Services\Payments\PaymentActivationService;
use App\Services\ToyyibPay\ToyyibPayClient;
use App\Services\ToyyibPay\ToyyibPayException;
use App\Support\Ringgit;
use App\Support\ToyyibPayTimestamp;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class ToyyibPayCallbackController extends Controller
{
    public function __invoke(
        Request $request,
        ToyyibPayClient $client,
        PaymentActivationService $activationService,
    ): Response {
        $payload = $request->only([
            'refno',
            'status',
            'reason',
            'billcode',
            'order_id',
            'amount',
            'transaction_time',
            'hash',
        ]);

        try {
            $validHash = $client->verifyCallbackHash($payload);
        } catch (ToyyibPayException $exception) {
            Log::error('ToyyibPay callback verification is unavailable.', [
                'exception_class' => $exception::class,
            ]);

            return $this->plainResponse('UNAVAILABLE', 503);
        }

        if (! $validHash) {
            Log::warning('ToyyibPay callback signature was rejected.');

            return $this->plainResponse('INVALID', 400);
        }

        $validator = Validator::make($payload, [
            'refno' => ['required', 'string', 'max:255', 'regex:/^(?=.*\S)[^\x00-\x1F\x7F]+$/u'],
            'status' => ['required', 'string', Rule::in(['1', '2', '3'])],
            'reason' => ['nullable', 'string', 'max:500'],
            'billcode' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9]+$/'],
            'order_id' => ['required', 'string', 'uuid'],
            'amount' => ['required', 'string', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/'],
            'transaction_time' => ['nullable', 'string', 'max:64', 'regex:/^[^\x00-\x1F\x7F]+$/u'],
            'hash' => ['required', 'string', 'size:32', 'regex:/^[a-f0-9]{32}$/'],
        ]);

        if ($validator->fails()) {
            Log::warning('Verified ToyyibPay callback failed validation.', [
                'status' => is_string($payload['status'] ?? null) ? $payload['status'] : null,
                'invalid_fields' => array_keys($validator->failed()),
            ]);

            return $this->plainResponse('INVALID', 422);
        }

        /** @var array{refno:string,status:string,billcode:string,order_id:string,amount:string,transaction_time?:string} $validated */
        $validated = $validator->validated();
        $providerPaidAt = ToyyibPayTimestamp::parse($validated['transaction_time'] ?? null);

        if (isset($validated['transaction_time']) && ! $providerPaidAt) {
            Log::warning('Verified ToyyibPay callback contained an invalid transaction time.', [
                'status' => $validated['status'],
            ]);
        }

        try {
            $amountCents = Ringgit::decimalToCents($validated['amount']);
            $ownerId = (int) PaymentOrder::query()
                ->where('external_reference', $validated['order_id'])
                ->value('user_id');
            $matched = $ownerId > 0 && DB::transaction(
                function () use (
                    $validated,
                    $amountCents,
                    $activationService,
                    $providerPaidAt,
                    $ownerId,
                ): bool {
                    $owner = User::query()->lockForUpdate()->find($ownerId);
                    $order = PaymentOrder::query()
                        ->where('external_reference', $validated['order_id'])
                        ->lockForUpdate()
                        ->first();

                    if (! $owner
                        || ! $order
                        || $order->user_id !== $owner->id
                        || $order->provider !== 'toyyibpay'
                        || $order->bill_code !== $validated['billcode']
                        || $order->amount_cents !== $amountCents) {
                        return false;
                    }

                    if ($validated['status'] === '1') {
                        $activationService->activate(
                            $order,
                            $validated['refno'],
                            $providerPaidAt,
                        );

                        return true;
                    }

                    if ($order->status !== PaymentOrder::STATUS_PAID) {
                        $order->forceFill([
                            'status' => $validated['status'] === '2'
                                ? PaymentOrder::STATUS_PENDING
                                : PaymentOrder::STATUS_FAILED,
                            'failure_reason' => $validated['status'] === '2'
                                ? null
                                : 'provider_failed',
                        ])->save();
                    }

                    return true;
                },
                3,
            );

            if (! $matched) {
                Log::warning('Verified ToyyibPay callback did not match its payment order.', [
                    'external_reference' => $validated['order_id'],
                    'status' => $validated['status'],
                ]);

                return $this->plainResponse('INVALID', 422);
            }
        } catch (UniqueConstraintViolationException $exception) {
            Log::warning('ToyyibPay callback transaction reference conflicts with another order.', [
                'external_reference' => $validated['order_id'],
                'status' => $validated['status'],
                'exception_class' => $exception::class,
            ]);

            return $this->plainResponse('CONFLICT', 409);
        } catch (Throwable $exception) {
            Log::error('ToyyibPay callback processing failed.', [
                'external_reference' => $validated['order_id'],
                'status' => $validated['status'],
                'exception_class' => $exception::class,
            ]);

            return $this->plainResponse('ERROR', 500);
        }

        return $this->plainResponse('OK');
    }

    private function plainResponse(string $content, int $status = 200): Response
    {
        return response($content, $status, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}

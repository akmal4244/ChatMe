<?php

namespace Tests\Unit;

use App\Models\PaymentOrder;
use App\Models\Plan;
use App\Models\User;
use App\Services\ToyyibPay\ToyyibPayClient;
use App\Services\ToyyibPay\ToyyibPayException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ToyyibPayClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.toyyibpay', [
            'base_url' => 'https://dev.toyyibpay.test/',
            'secret_key' => 'test-secret-key',
            'category_code' => 'CAT123',
            'dnqr_enabled' => true,
            'timeout' => 12,
        ]);
    }

    public function test_create_bill_sends_the_exact_fixed_price_fpx_and_dnqr_payload_once(): void
    {
        [$order, $user] = $this->order('Pelan Pró! Sangat Panjang Melebihi Had', '49.99');
        Http::fake([
            'https://dev.toyyibpay.test/index.php/api/createBill' => Http::response([
                ['BillCode' => 'BILLabc123'],
            ]),
        ]);

        $billCode = $this->client()->createBill(
            $order,
            $user,
            '60123456789',
            'https://chatme.test/subscription/orders/'.$order->external_reference.'/return',
            'https://chatme.test/payments/toyyibpay/callback',
        );

        $this->assertSame('BILLabc123', $billCode);
        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($order, $user): bool {
            $data = $request->data();

            $this->assertTrue($request->isForm());
            $this->assertSame('test-secret-key', $data['userSecretKey']);
            $this->assertSame('CAT123', $data['categoryCode']);
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9 _]+$/', $data['billName']);
            $this->assertLessThanOrEqual(30, strlen($data['billName']));
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9 _]+$/', $data['billDescription']);
            $this->assertLessThanOrEqual(100, strlen($data['billDescription']));
            $this->assertSame(1, $data['billPriceSetting']);
            $this->assertSame(1, $data['billPayorInfo']);
            $this->assertSame(4999, $data['billAmount']);
            $this->assertSame($order->external_reference, $data['billExternalReferenceNo']);
            $this->assertSame($user->name, $data['billTo']);
            $this->assertSame($user->email, $data['billEmail']);
            $this->assertSame('60123456789', $data['billPhone']);
            $this->assertSame(0, $data['billPaymentChannel']);
            $this->assertSame(3, $data['billExpiryDays']);
            $this->assertSame(1, $data['enableDuitNowQR']);
            $this->assertSame(0, $data['chargeDuitNowQR']);
            $this->assertArrayNotHasKey('billChargeToCustomer', $data);
            $this->assertStringStartsWith('https://chatme.test/', $data['billReturnUrl']);
            $this->assertSame('https://chatme.test/payments/toyyibpay/callback', $data['billCallbackUrl']);

            return $request->url() === 'https://dev.toyyibpay.test/index.php/api/createBill';
        });
    }

    public function test_create_bill_disables_dnqr_fields_safely_when_configuration_is_off(): void
    {
        config()->set('services.toyyibpay.dnqr_enabled', false);
        [$order, $user] = $this->order();
        Http::fake([
            '*' => Http::response([['BillCode' => 'FPXONLY1']]),
        ]);

        $this->client()->createBill(
            $order,
            $user,
            '60123456789',
            'https://chatme.test/return',
            'https://chatme.test/callback',
        );

        Http::assertSent(function ($request): bool {
            $this->assertSame(0, $request['enableDuitNowQR']);
            $this->assertArrayNotHasKey('chargeDuitNowQR', $request->data());

            return true;
        });
    }

    public function test_missing_configuration_fails_before_any_http_request(): void
    {
        config()->set('services.toyyibpay.secret_key', null);
        [$order, $user] = $this->order();
        Http::fake();

        try {
            $this->client()->createBill(
                $order,
                $user,
                '60123456789',
                'https://chatme.test/return',
                'https://chatme.test/callback',
            );
            $this->fail('Expected a configuration exception.');
        } catch (ToyyibPayException $exception) {
            $this->assertSame('configuration_error', $exception->reason);
            $this->assertStringNotContainsString('test-secret-key', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_transport_failure_is_sanitized_and_bill_creation_is_not_retried(): void
    {
        [$order, $user] = $this->order();
        $attempts = 0;
        Http::fake(function () use (&$attempts): never {
            $attempts++;
            throw new ConnectionException('raw transport detail test-secret-key payer@example.test');
        });

        try {
            $this->client()->createBill(
                $order,
                $user,
                '60123456789',
                'https://chatme.test/return',
                'https://chatme.test/callback',
            );
            $this->fail('Expected a transport exception.');
        } catch (ToyyibPayException $exception) {
            $this->assertSame('transport_error', $exception->reason);
            $this->assertStringNotContainsString('test-secret-key', $exception->getMessage());
            $this->assertStringNotContainsString('payer@example.test', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }

        $this->assertSame(1, $attempts);
    }

    public function test_http_and_malformed_responses_fail_without_exposing_the_raw_body(): void
    {
        [$order, $user] = $this->order();
        $rawSecretBody = 'provider raw body test-secret-key payer@example.test';
        Http::fakeSequence()
            ->push($rawSecretBody, 500)
            ->push('not-json', 200, ['Content-Type' => 'application/json'])
            ->push([['unexpected' => 'shape']], 200);

        foreach (['http_error', 'invalid_response', 'invalid_response'] as $expectedReason) {
            try {
                $this->client()->createBill(
                    $order,
                    $user,
                    '60123456789',
                    'https://chatme.test/return',
                    'https://chatme.test/callback',
                );
                $this->fail('Expected a provider response exception.');
            } catch (ToyyibPayException $exception) {
                $this->assertSame($expectedReason, $exception->reason);
                $this->assertStringNotContainsString($rawSecretBody, $exception->getMessage());
            }
        }

        Http::assertSentCount(3);
    }

    public function test_payment_url_and_transaction_query_use_only_the_configured_host_and_valid_bill_code(): void
    {
        Http::fake([
            'https://dev.toyyibpay.test/index.php/api/getBillTransactions' => Http::response([
                ['billpaymentStatus' => '1', 'billExternalReferenceNo' => 'ORDER-1'],
            ]),
        ]);

        $this->assertSame(
            'https://dev.toyyibpay.test/BILL123',
            $this->client()->paymentUrl('BILL123'),
        );
        $transactions = $this->client()->getBillTransactions('BILL123');

        $this->assertSame('1', $transactions[0]['billpaymentStatus']);
        Http::assertSent(function ($request): bool {
            $this->assertSame(['billCode' => 'BILL123'], $request->data());

            return $request->url() === 'https://dev.toyyibpay.test/index.php/api/getBillTransactions';
        });
    }

    public function test_invalid_bill_codes_and_transaction_shapes_fail_closed(): void
    {
        $this->expectException(ToyyibPayException::class);
        $this->client()->paymentUrl('../attacker');
    }

    public function test_malformed_transaction_list_is_rejected_without_raw_data(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'raw provider test-secret-key']),
        ]);

        try {
            $this->client()->getBillTransactions('BILL123');
            $this->fail('Expected an invalid response exception.');
        } catch (ToyyibPayException $exception) {
            $this->assertSame('invalid_response', $exception->reason);
            $this->assertStringNotContainsString('test-secret-key', $exception->getMessage());
        }
    }

    public function test_provider_bill_code_must_be_alphanumeric(): void
    {
        [$order, $user] = $this->order();
        Http::fake([
            '*' => Http::response([['BillCode' => '../redirect']]),
        ]);

        try {
            $this->client()->createBill(
                $order,
                $user,
                '60123456789',
                'https://chatme.test/return',
                'https://chatme.test/callback',
            );
            $this->fail('Expected an invalid Bill code response.');
        } catch (ToyyibPayException $exception) {
            $this->assertSame('invalid_response', $exception->reason);
        }
    }

    public function test_client_rejects_invalid_phone_and_credentialed_base_url_before_http(): void
    {
        [$order, $user] = $this->order();
        Http::fake();

        try {
            $this->client()->createBill(
                $order,
                $user,
                '0312345678',
                'https://chatme.test/return',
                'https://chatme.test/callback',
            );
            $this->fail('Expected an invalid phone exception.');
        } catch (\InvalidArgumentException) {
            Http::assertNothingSent();
        }

        config()->set('services.toyyibpay.base_url', 'https://user@toyyibpay.test');

        $this->expectException(ToyyibPayException::class);
        $this->client()->paymentUrl('BILL123');
    }

    public function test_callback_hash_verification_uses_the_documented_formula_and_accepts_hex_case(): void
    {
        $payload = [
            'status' => '1',
            'order_id' => (string) Str::uuid(),
            'refno' => 'TP123456',
        ];
        $payload['hash'] = strtoupper(md5('test-secret-key'.$payload['status'].$payload['order_id'].$payload['refno'].'ok'));

        $this->assertTrue($this->client()->verifyCallbackHash($payload));

        $payload['hash'] = str_repeat('0', 32);
        $this->assertFalse($this->client()->verifyCallbackHash($payload));
        $this->assertFalse($this->client()->verifyCallbackHash(['status' => '1']));
    }

    public function test_client_rejects_mismatched_user_or_insecure_local_urls_before_http(): void
    {
        [$order] = $this->order();
        $otherUser = User::factory()->create();
        Http::fake();

        $this->expectException(ToyyibPayException::class);

        $this->client()->createBill(
            $order,
            $otherUser,
            '60123456789',
            'http://chatme.test/return',
            'https://chatme.test/callback',
        );
    }

    private function client(): ToyyibPayClient
    {
        return app(ToyyibPayClient::class);
    }

    /** @return array{PaymentOrder, User} */
    private function order(string $name = 'Pro', string $price = '49.00'): array
    {
        $user = User::factory()->create([
            'name' => 'Nur Aisyah',
            'email' => 'aisyah@example.test',
        ]);
        $plan = Plan::create([
            'name' => $name,
            'slug' => 'plan-'.Str::lower(Str::random(8)),
            'price' => $price,
        ]);
        $order = PaymentOrder::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'amount_cents' => $plan->priceInCents(),
            'status' => PaymentOrder::STATUS_CREATING,
        ]);

        return [$order, $user];
    }
}

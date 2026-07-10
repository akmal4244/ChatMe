<?php

namespace App\Services\ToyyibPay;

use App\Models\PaymentOrder;
use App\Models\User;
use App\Support\MalaysianPhone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class ToyyibPayClient
{
    public function createBill(
        PaymentOrder $order,
        User $user,
        string $phone,
        string $returnUrl,
        string $callbackUrl,
    ): string {
        $secret = $this->secretKey();
        $categoryCode = $this->categoryCode();
        $this->assertOrderUser($order, $user);
        $this->assertApplicationHttpsUrl($returnUrl);
        $this->assertApplicationHttpsUrl($callbackUrl);

        $plan = $order->plan()->firstOrFail();
        $payload = [
            'userSecretKey' => $secret,
            'categoryCode' => $categoryCode,
            'billName' => $this->providerText('ChatMe '.$plan->name, 30, 'ChatMe Plan'),
            'billDescription' => $this->providerText(
                'Langganan ChatMe '.$plan->name.' satu bulan',
                100,
                'Langganan ChatMe satu bulan',
            ),
            'billPriceSetting' => 1,
            'billPayorInfo' => 1,
            'billAmount' => $order->amount_cents,
            'billReturnUrl' => $returnUrl,
            'billCallbackUrl' => $callbackUrl,
            'billExternalReferenceNo' => $order->external_reference,
            'billTo' => $this->payerName($user->name),
            'billEmail' => $user->email,
            'billPhone' => MalaysianPhone::normalize($phone),
            'billSplitPayment' => 0,
            'billPaymentChannel' => 0,
            'billExpiryDays' => 3,
            'enableDuitNowQR' => $this->duitNowQrEnabled() ? 1 : 0,
        ];

        if ($this->duitNowQrEnabled()) {
            $payload['chargeDuitNowQR'] = 0;
        }

        $response = $this->postForm('/index.php/api/createBill', $payload);
        $decoded = $this->decodedList($response);
        $billCode = $decoded[0]['BillCode'] ?? null;

        if (! is_string($billCode) || ! $this->isValidBillCode($billCode)) {
            throw new ToyyibPayException('invalid_response');
        }

        return $billCode;
    }

    /** @return array<int, array<string, mixed>> */
    public function getBillTransactions(string $billCode): array
    {
        $this->assertBillCode($billCode);
        $response = $this->postForm('/index.php/api/getBillTransactions', [
            'billCode' => $billCode,
        ]);

        return $this->decodedList($response);
    }

    /** @param array<string, mixed> $payload */
    public function verifyCallbackHash(array $payload): bool
    {
        foreach (['status', 'order_id', 'refno', 'hash'] as $key) {
            if (! array_key_exists($key, $payload) || ! is_string($payload[$key])) {
                return false;
            }
        }

        $status = (string) $payload['status'];
        $orderId = (string) $payload['order_id'];
        $reference = (string) $payload['refno'];
        $received = (string) $payload['hash'];

        if (! in_array($status, ['1', '2', '3'], true)
            || $orderId === ''
            || strlen($orderId) > 100
            || $reference === ''
            || strlen($reference) > 255
            || ! preg_match('/^[a-f0-9]{32}$/', $received)) {
            return false;
        }

        $expected = md5($this->secretKey().$status.$orderId.$reference.'ok');

        return hash_equals($expected, $received);
    }

    public function duitNowQrEnabled(): bool
    {
        return (bool) config('services.toyyibpay.dnqr_enabled', false);
    }

    public function paymentUrl(string $billCode): string
    {
        $this->assertBillCode($billCode);

        return $this->baseUrl().'/'.$billCode;
    }

    /** @param array<string, scalar|null> $payload */
    private function postForm(string $path, array $payload): Response
    {
        try {
            $response = Http::asForm()
                ->acceptJson()
                ->withoutRedirecting()
                ->timeout($this->timeout())
                ->post($this->baseUrl().$path, $payload);
        } catch (ConnectionException) {
            throw new ToyyibPayException('transport_error');
        }

        if (! $response->successful()) {
            throw new ToyyibPayException('http_error');
        }

        return $response;
    }

    /** @return array<int, array<string, mixed>> */
    private function decodedList(Response $response): array
    {
        $decoded = $response->json();

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new ToyyibPayException('invalid_response');
        }

        foreach ($decoded as $item) {
            if (! is_array($item)) {
                throw new ToyyibPayException('invalid_response');
            }
        }

        return $decoded;
    }

    private function baseUrl(): string
    {
        $baseUrl = rtrim(trim((string) config('services.toyyibpay.base_url')), '/');
        $parts = parse_url($baseUrl);

        if (! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || empty($parts['host'])
            || strtolower($parts['host']) !== $this->expectedHost()
            || ($parts['port'] ?? 443) !== 443
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['path']) && $parts['path'] !== '')) {
            throw new ToyyibPayException('configuration_error');
        }

        return $baseUrl;
    }

    private function expectedHost(): string
    {
        return (bool) config('services.toyyibpay.sandbox', false)
            ? 'dev.toyyibpay.com'
            : 'toyyibpay.com';
    }

    private function secretKey(): string
    {
        $secret = trim((string) config('services.toyyibpay.secret_key'));

        if ($secret === '' || strlen($secret) > 255) {
            throw new ToyyibPayException('configuration_error');
        }

        return $secret;
    }

    private function categoryCode(): string
    {
        $categoryCode = trim((string) config('services.toyyibpay.category_code'));

        if (! preg_match('/^[A-Za-z0-9]{1,100}$/', $categoryCode)) {
            throw new ToyyibPayException('configuration_error');
        }

        return $categoryCode;
    }

    private function timeout(): int
    {
        $timeout = (int) config('services.toyyibpay.timeout', 15);

        if ($timeout < 1 || $timeout > 60) {
            throw new ToyyibPayException('configuration_error');
        }

        return $timeout;
    }

    private function assertOrderUser(PaymentOrder $order, User $user): void
    {
        if ($order->user_id !== $user->id || $order->amount_cents <= 0) {
            throw new ToyyibPayException('invalid_request');
        }
    }

    private function assertApplicationHttpsUrl(string $url): void
    {
        $parts = parse_url($url);

        if (! filter_var($url, FILTER_VALIDATE_URL)
            || ! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            throw new ToyyibPayException('invalid_request');
        }

        $applicationUrl = rtrim(trim((string) config('app.url')), '/');
        $applicationParts = parse_url($applicationUrl);

        if (! is_array($applicationParts)
            || ($applicationParts['scheme'] ?? null) !== 'https'
            || empty($applicationParts['host'])
            || isset($applicationParts['user'])
            || isset($applicationParts['pass'])
            || isset($applicationParts['query'])
            || isset($applicationParts['fragment'])) {
            throw new ToyyibPayException('configuration_error');
        }

        $targetPort = $parts['port'] ?? 443;
        $applicationPort = $applicationParts['port'] ?? 443;

        if (strtolower($parts['host']) !== strtolower($applicationParts['host'])
            || $targetPort !== $applicationPort) {
            throw new ToyyibPayException('invalid_request');
        }
    }

    private function assertBillCode(string $billCode): void
    {
        if (! $this->isValidBillCode($billCode)) {
            throw new ToyyibPayException('invalid_request');
        }
    }

    private function isValidBillCode(string $billCode): bool
    {
        return preg_match('/^[A-Za-z0-9]{1,100}$/', $billCode) === 1;
    }

    private function providerText(string $value, int $limit, string $fallback): string
    {
        $value = Str::ascii($value);
        $value = preg_replace('/[^A-Za-z0-9 _]+/', ' ', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';
        $value = substr($value, 0, $limit);

        return $value !== '' ? rtrim($value) : $fallback;
    }

    private function payerName(string $name): string
    {
        $name = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '');

        return $name !== '' ? mb_substr($name, 0, 255) : 'Pelanggan ChatMe';
    }
}

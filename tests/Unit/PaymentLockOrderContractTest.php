<?php

namespace Tests\Unit;

use App\Http\Controllers\ToyyibPayCallbackController;
use App\Services\Payments\PaymentActivationService;
use App\Services\Payments\ToyyibPayReconciliationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PaymentLockOrderContractTest extends TestCase
{
    /** @param class-string $class */
    #[Test]
    #[DataProvider('paymentMutators')]
    public function payment_mutations_lock_the_owner_before_the_order(string $class, string $method): void
    {
        $source = $this->methodSource($class, $method);
        $transaction = strpos($source, 'DB::transaction(');
        $this->assertNotFalse($transaction, "{$class}::{$method} must use a transaction.");
        $transactionSource = substr($source, $transaction);
        $ownerLock = strpos($transactionSource, 'User::query()');
        $orderLock = strpos($transactionSource, 'PaymentOrder::query()');

        $this->assertNotFalse($ownerLock, "{$class}::{$method} must lock the payment owner.");
        $this->assertNotFalse($orderLock, "{$class}::{$method} must lock the payment order.");
        $this->assertLessThan(
            $orderLock,
            $ownerLock,
            "{$class}::{$method} must use the global User -> PaymentOrder lock order.",
        );
        $this->assertStringContainsString('lockForUpdate()', $transactionSource);
    }

    /** @return iterable<string, array{class-string, string}> */
    public static function paymentMutators(): iterable
    {
        yield 'activation' => [PaymentActivationService::class, 'activate'];
        yield 'callback' => [ToyyibPayCallbackController::class, '__invoke'];
        yield 'reconciliation' => [ToyyibPayReconciliationService::class, 'reconcile'];
    }

    /** @param class-string $class */
    private function methodSource(string $class, string $method): string
    {
        $reflection = new ReflectionMethod($class, $method);
        $file = $reflection->getFileName();
        $this->assertNotFalse($file);
        $lines = file($file);
        $this->assertIsArray($lines);

        return implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1,
        ));
    }
}

<?php

namespace Tests\Unit;

use App\Services\OwnerMessagingLimiter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class OwnerMessagingLimiterContractTest extends TestCase
{
    #[Test]
    public function owner_limit_check_and_both_counters_are_serialized_by_a_fail_closed_lock(): void
    {
        $method = new ReflectionMethod(OwnerMessagingLimiter::class, 'denied');
        $file = $method->getFileName();
        $this->assertNotFalse($file);
        $lines = file($file);
        $this->assertIsArray($lines);
        $source = implode('', array_slice(
            $lines,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1,
        ));

        $this->assertStringContainsString('Cache::lock(', $source);
        $this->assertStringContainsString('->block(', $source);
        $this->assertStringContainsString('catch (LockTimeoutException)', $source);

        $check = strpos($source, 'RateLimiter::tooManyAttempts(');
        $hit = strpos($source, 'RateLimiter::hit(');
        $this->assertNotFalse($check);
        $this->assertNotFalse($hit);
        $this->assertLessThan($hit, $check, 'The serialized critical section must check before incrementing.');
    }
}

<?php

namespace Tests\Unit;

use App\Models\Chatbot;
use App\Models\ChatLog;
use App\Models\KnowledgeItem;
use App\Models\MessageQuotaReservation;
use App\Models\MessageUsage;
use App\Models\PaymentOrder;
use App\Models\Subscription;
use App\Models\TesterAiUsage;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DatabaseForeignKeyCastTest extends TestCase
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $foreignKeys
     */
    #[Test]
    #[DataProvider('foreignKeyModels')]
    public function mysql_numeric_string_foreign_keys_are_exposed_as_integers(
        string $modelClass,
        array $foreignKeys,
    ): void {
        $attributes = array_fill_keys($foreignKeys, '42');
        $model = (new $modelClass)->newFromBuilder($attributes);

        foreach ($foreignKeys as $foreignKey) {
            $this->assertSame(
                42,
                $model->getAttribute($foreignKey),
                "{$modelClass}::{$foreignKey} must be safe for strict integer comparisons after MySQL hydration.",
            );
        }
    }

    /** @return iterable<string, array{class-string<Model>, list<string>}> */
    public static function foreignKeyModels(): iterable
    {
        yield 'chatbot owner' => [Chatbot::class, ['user_id']];
        yield 'knowledge chatbot' => [KnowledgeItem::class, ['chatbot_id']];
        yield 'chat log chatbot' => [ChatLog::class, ['chatbot_id']];
        yield 'payment order relations' => [PaymentOrder::class, ['user_id', 'plan_id', 'subscription_id']];
        yield 'subscription relations' => [Subscription::class, ['user_id', 'plan_id']];
        yield 'quota reservation relations' => [MessageQuotaReservation::class, ['user_id', 'chatbot_id']];
        yield 'durable message usage owner' => [MessageUsage::class, ['user_id']];
        yield 'tester usage owner' => [TesterAiUsage::class, ['user_id']];
    }
}

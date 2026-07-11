<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_critical_tenant_queries_have_composite_indexes(): void
    {
        $this->assertTableHasIndexColumns('chat_logs', ['chatbot_id', 'role', 'created_at']);
        $this->assertTableHasIndexColumns('knowledge_items', ['chatbot_id', 'is_active']);
        $this->assertTableHasIndexColumns('subscriptions', ['user_id', 'status', 'starts_at', 'id']);
    }

    /** @param array<int, string> $expected */
    private function assertTableHasIndexColumns(string $table, array $expected): void
    {
        $indexes = collect(Schema::getIndexes($table))
            ->pluck('columns')
            ->map(fn (array $columns): string => implode(',', $columns));

        $this->assertContains(
            implode(',', $expected),
            $indexes->all(),
            "Table {$table} is missing its critical composite index."
        );
    }
}

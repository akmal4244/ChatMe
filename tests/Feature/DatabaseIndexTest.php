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

    public function test_system_identity_and_knowledge_source_markers_are_uniquely_constrained(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'system_role'));
        $this->assertTrue(Schema::hasColumn('chatbots', 'system_role'));
        $this->assertTrue(Schema::hasColumn('knowledge_items', 'source_key'));

        $this->assertTableHasUniqueIndexColumns('users', ['system_role']);
        $this->assertTableHasUniqueIndexColumns('chatbots', ['system_role']);
        $this->assertTableHasUniqueIndexColumns('knowledge_items', ['chatbot_id', 'source_key']);
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

    /** @param array<int, string> $expected */
    private function assertTableHasUniqueIndexColumns(string $table, array $expected): void
    {
        $indexes = collect(Schema::getIndexes($table))
            ->filter(fn (array $index): bool => (bool) ($index['unique'] ?? false))
            ->pluck('columns')
            ->map(fn (array $columns): string => implode(',', $columns));

        $this->assertContains(
            implode(',', $expected),
            $indexes->all(),
            "Table {$table} is missing its required unique index."
        );
    }
}

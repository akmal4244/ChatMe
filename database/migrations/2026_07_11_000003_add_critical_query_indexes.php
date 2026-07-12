<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_logs', function (Blueprint $table): void {
            $table->index(
                ['chatbot_id', 'role', 'created_at'],
                'chat_logs_tenant_quota_index'
            );
        });

        Schema::table('knowledge_items', function (Blueprint $table): void {
            $table->index(
                ['chatbot_id', 'is_active'],
                'knowledge_items_match_index'
            );
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->index(
                ['user_id', 'status', 'starts_at', 'id'],
                'subscriptions_active_term_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropIndex('subscriptions_active_term_index');
        });

        Schema::table('knowledge_items', function (Blueprint $table): void {
            $table->dropIndex('knowledge_items_match_index');
        });

        Schema::table('chat_logs', function (Blueprint $table): void {
            $table->dropIndex('chat_logs_tenant_quota_index');
        });
    }
};

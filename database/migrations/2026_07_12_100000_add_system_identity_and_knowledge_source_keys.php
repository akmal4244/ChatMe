<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('system_role', 64)->nullable()->after('is_admin');
            $table->unique('system_role', 'users_system_role_unique');
        });

        Schema::table('chatbots', function (Blueprint $table): void {
            $table->string('system_role', 64)->nullable()->after('user_id');
            $table->unique('system_role', 'chatbots_system_role_unique');
        });

        Schema::table('knowledge_items', function (Blueprint $table): void {
            $table->string('source_key', 100)->nullable()->after('chatbot_id');
            $table->unique(
                ['chatbot_id', 'source_key'],
                'knowledge_items_chatbot_source_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_items', function (Blueprint $table): void {
            $table->dropUnique('knowledge_items_chatbot_source_unique');
            $table->dropColumn('source_key');
        });

        Schema::table('chatbots', function (Blueprint $table): void {
            $table->dropUnique('chatbots_system_role_unique');
            $table->dropColumn('system_role');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_system_role_unique');
            $table->dropColumn('system_role');
        });
    }
};

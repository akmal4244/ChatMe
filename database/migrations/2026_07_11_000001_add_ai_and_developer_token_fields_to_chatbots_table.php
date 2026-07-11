<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbots', function (Blueprint $table): void {
            $table->text('fallback_message')->nullable()->after('system_prompt');
            $table->char('developer_api_token_hash', 64)->nullable()->unique()->after('api_key');
            $table->string('developer_api_token_prefix', 20)->nullable()->after('developer_api_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('chatbots', function (Blueprint $table): void {
            $table->dropUnique(['developer_api_token_hash']);
            $table->dropColumn([
                'fallback_message',
                'developer_api_token_hash',
                'developer_api_token_prefix',
            ]);
        });
    }
};

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('chatbots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('avatar_url')->nullable();
            $table->string('primary_color')->default('#4F46E5');
            $table->string('secondary_color')->default('#ffffff');
            $table->string('position')->default('bottom-right');
            $table->text('welcome_message')->default('Hello! How can I help you today?');
            $table->text('placeholder_text')->default('Type your message...');
            $table->string('bot_name')->default('ChatMe Bot');
            $table->text('system_prompt')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('api_key')->unique()->nullable();
            $table->string('domain_whitelist')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('chatbots'); }
};

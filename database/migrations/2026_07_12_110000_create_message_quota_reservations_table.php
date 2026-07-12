<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_quota_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chatbot_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique('message_quota_reservations_token_unique');
            $table->string('channel', 32);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(
                ['user_id', 'created_at', 'expires_at'],
                'message_quota_reservations_period_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_quota_reservations');
    }
};

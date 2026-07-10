<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('chatbot_limit')->default(1);
            $table->integer('knowledge_limit')->default(50);
            $table->integer('monthly_messages')->default(500);
            $table->boolean('custom_domain')->default(false);
            $table->boolean('remove_branding')->default(false);
            $table->boolean('api_access')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};

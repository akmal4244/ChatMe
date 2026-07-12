<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tester_ai_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('usage_date');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamps();

            $table->unique(
                ['user_id', 'usage_date'],
                'tester_ai_usages_user_date_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tester_ai_usages');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_orders', function (Blueprint $table): void {
            $table->uuid('checkout_key')->nullable()->after('external_reference');
            $table->unique(['user_id', 'checkout_key']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_orders', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'checkout_key']);
            $table->dropColumn('checkout_key');
        });
    }
};

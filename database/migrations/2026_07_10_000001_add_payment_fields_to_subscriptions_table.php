<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('provider')->nullable()->after('plan_id');
            $table->string('provider_reference')->nullable()->unique()->after('provider');
            $table->string('status')->nullable()->index()->after('provider_reference');
            $table->timestamp('starts_at')->nullable()->after('trial_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropUnique(['provider_reference']);
            $table->dropIndex(['status']);
            $table->dropColumn(['provider', 'provider_reference', 'status', 'starts_at']);
        });
    }
};

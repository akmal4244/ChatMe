<?php

use App\Support\Ringgit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->unsignedBigInteger('unit_price_cents')->nullable()->after('plan_id');
        });

        DB::table('subscriptions')
            ->where(function ($query): void {
                $query->whereNull('provider')->orWhere('provider', '!=', 'system');
            })
            ->orderBy('id')
            ->get(['id', 'plan_id'])
            ->each(function (object $subscription): void {
                $paidAmount = DB::table('payment_orders')
                    ->where('subscription_id', $subscription->id)
                    ->where('status', 'paid')
                    ->orderByDesc('paid_at')
                    ->orderByDesc('id')
                    ->value('amount_cents');
                $unitPrice = $paidAmount !== null
                    ? (int) $paidAmount
                    : Ringgit::decimalToCents((string) DB::table('plans')
                        ->where('id', $subscription->plan_id)
                        ->value('price'));

                if ($unitPrice > 0) {
                    DB::table('subscriptions')
                        ->where('id', $subscription->id)
                        ->update(['unit_price_cents' => $unitPrice]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn('unit_price_cents');
        });
    }
};

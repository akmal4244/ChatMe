<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('provider')->nullable()->after('plan_id');
            $table->string('provider_reference')->nullable()->unique()->after('provider');
            $table->string('status')->nullable()->default('inactive')->index()->after('provider_reference');
            $table->timestamp('starts_at')->nullable()->after('trial_ends_at');
        });

        $migratedAt = Carbon::now()->utc()->toImmutable();
        $date = static function (mixed $value) {
            if (! is_string($value) || trim($value) === '') {
                return null;
            }

            try {
                return Carbon::parse($value, 'UTC')->utc()->toImmutable();
            } catch (Throwable) {
                return null;
            }
        };
        $planSlugs = DB::table('plans')->pluck('slug', 'id');

        DB::table('subscriptions')->orderBy('id')->chunkById(100, function ($rows) use ($date, $migratedAt, $planSlugs): void {
            foreach ($rows as $row) {
                $legacyStatus = strtolower(trim((string) $row->stripe_status));
                $trialEndsAt = $date($row->trial_ends_at);
                $endsAt = $date($row->ends_at);
                $status = 'inactive';
                $provider = filled($row->stripe_id) || filled($row->stripe_status)
                    ? 'stripe'
                    : 'legacy';
                $isGrandfatheredLifetime = $planSlugs->get($row->plan_id) === 'lifetime'
                    && $legacyStatus === 'lifetime';

                if ($isGrandfatheredLifetime) {
                    $provider = 'legacy_lifetime';
                    $status = 'active';
                    $endsAt = null;
                } elseif ($legacyStatus === 'active') {
                    if (! $endsAt || $endsAt->gt($migratedAt)) {
                        $status = 'active';
                        $endsAt ??= $migratedAt->addMonthNoOverflow();
                    } else {
                        $status = 'expired';
                    }
                } elseif ($legacyStatus === 'trialing' && $trialEndsAt?->gt($migratedAt)) {
                    $status = 'active';
                    $endsAt = $trialEndsAt;
                } elseif ($endsAt?->lte($migratedAt)) {
                    $status = 'expired';
                }

                DB::table('subscriptions')->where('id', $row->id)->update([
                    'provider' => $provider,
                    'status' => $status,
                    'starts_at' => $date($row->created_at) ?? $migratedAt,
                    'ends_at' => $endsAt,
                ]);
            }
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

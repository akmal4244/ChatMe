<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacySubscriptionMigrationTest extends TestCase
{
    public function test_legacy_rows_are_backfilled_from_provable_stripe_state_and_unknown_rows_fail_closed(): void
    {
        $originalConnection = DB::getDefaultConnection();
        config()->set('database.connections.legacy_subscription_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::setDefaultConnection('legacy_subscription_test');
        Carbon::setTestNow('2026-07-10 12:00:00 UTC');

        try {
            Schema::create('plans', function (Blueprint $table): void {
                $table->id();
                $table->string('slug')->unique();
            });
            Schema::create('subscriptions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('plan_id');
                $table->string('stripe_id')->nullable();
                $table->string('stripe_status')->nullable();
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();
            });
            DB::table('plans')->insert([
                ['id' => 1, 'slug' => 'free'],
                ['id' => 2, 'slug' => 'lifetime'],
            ]);

            $this->insertLegacy('stripe-active', 'active');
            $this->insertLegacy('stripe-cancelled', 'canceled', '2026-08-10 12:00:00');
            $this->insertLegacy(null, null, '2026-08-10 12:00:00');
            $this->insertLegacy('stripe-trial', 'trialing', null, '2026-07-20 12:00:00');
            $this->insertLegacy('stripe-expired', 'active', '2026-07-01 12:00:00');
            $this->insertLegacy(null, 'lifetime', null, null, 2);

            $migration = require database_path('migrations/2026_07_10_000001_add_payment_fields_to_subscriptions_table.php');
            $migration->up();

            $rows = DB::table('subscriptions')->orderBy('id')->get();

            $this->assertSame('stripe', $rows[0]->provider);
            $this->assertSame('active', $rows[0]->status);
            $this->assertSame('2026-08-10 12:00:00', $rows[0]->ends_at);
            $this->assertSame('inactive', $rows[1]->status);
            $this->assertSame('legacy', $rows[2]->provider);
            $this->assertSame('inactive', $rows[2]->status);
            $this->assertSame('active', $rows[3]->status);
            $this->assertSame('2026-07-20 12:00:00', $rows[3]->ends_at);
            $this->assertSame('expired', $rows[4]->status);
            $this->assertNotNull($rows[0]->starts_at);
            $this->assertSame('legacy_lifetime', $rows[5]->provider);
            $this->assertSame('active', $rows[5]->status);
            $this->assertNull($rows[5]->ends_at);
        } finally {
            Carbon::setTestNow();
            DB::setDefaultConnection($originalConnection);
            DB::purge('legacy_subscription_test');
            config()->offsetUnset('database.connections.legacy_subscription_test');
        }
    }

    private function insertLegacy(
        ?string $stripeId,
        ?string $stripeStatus,
        ?string $endsAt = null,
        ?string $trialEndsAt = null,
        int $planId = 1,
    ): void {
        DB::table('subscriptions')->insert([
            'user_id' => 1,
            'plan_id' => $planId,
            'stripe_id' => $stripeId,
            'stripe_status' => $stripeStatus,
            'trial_ends_at' => $trialEndsAt,
            'ends_at' => $endsAt,
            'created_at' => '2026-06-01 00:00:00',
            'updated_at' => '2026-06-01 00:00:00',
        ]);
    }
}

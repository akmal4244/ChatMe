<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('usage_month');
            $table->unsignedBigInteger('message_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'usage_month'], 'message_usages_user_month_unique');
        });

        $businessNow = CarbonImmutable::now((string) config('chatme.timezone', 'Asia/Kuala_Lumpur'));
        $periodStart = $businessNow->startOfMonth()->utc();
        $periodEnd = $businessNow->endOfMonth()->utc();
        $usageMonth = $businessNow->startOfMonth()->toDateString();
        $now = now();

        DB::table('chat_logs')
            ->join('chatbots', 'chatbots.id', '=', 'chat_logs.chatbot_id')
            ->where('chat_logs.role', 'user')
            ->whereBetween('chat_logs.created_at', [$periodStart, $periodEnd])
            ->groupBy('chatbots.user_id')
            ->selectRaw('chatbots.user_id as user_id, COUNT(*) as message_count')
            ->orderBy('chatbots.user_id')
            ->get()
            ->each(function (object $usage) use ($now, $usageMonth): void {
                DB::table('message_usages')->insert([
                    'user_id' => (int) $usage->user_id,
                    'usage_month' => $usageMonth,
                    'message_count' => (int) $usage->message_count,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_usages');
    }
};

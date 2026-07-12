<?php

namespace App\Services;

use App\Models\TesterAiUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TesterAiUsageService
{
    public function reserve(User $user): bool
    {
        $limit = max(0, min(1000, (int) config('chatme.tester.daily_ai_limit', 20)));
        if ($limit === 0) {
            return false;
        }

        $usageDate = now((string) config('chatme.timezone'))->toDateString();

        return DB::transaction(function () use ($limit, $usageDate, $user): bool {
            User::query()->lockForUpdate()->findOrFail($user->id);
            $usage = TesterAiUsage::query()
                ->where('user_id', $user->id)
                ->where('usage_date', $usageDate)
                ->lockForUpdate()
                ->first();

            if ($usage && $usage->attempts >= $limit) {
                return false;
            }

            $usage ??= new TesterAiUsage;
            $usage->forceFill([
                'user_id' => $user->id,
                'usage_date' => $usageDate,
                'attempts' => ($usage->attempts ?? 0) + 1,
            ])->save();

            return true;
        });
    }
}

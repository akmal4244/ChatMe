<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class AdminSeeder extends Seeder
{
    private const ADMIN_ROLE = 'primary_admin';

    public function run(): void
    {
        $name = config('chatme.admin.name');
        $email = config('chatme.admin.email');
        $password = config('chatme.admin.password');

        if (blank($name) || blank($email) || blank($password)) {
            return;
        }

        $name = trim((string) $name);
        $email = Str::lower(trim((string) $email));

        DB::transaction(function () use ($email, $name, $password): void {
            $adminByRole = User::query()
                ->where('system_role', self::ADMIN_ROLE)
                ->lockForUpdate()
                ->first();
            $adminByEmail = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->lockForUpdate()
                ->first();

            if ($adminByRole) {
                if (Str::lower(trim((string) $adminByRole->email)) !== $email
                    || ($adminByEmail && $adminByEmail->isNot($adminByRole))) {
                    throw new RuntimeException('The primary administrator identity conflicts with the configured email.');
                }

                $admin = $adminByRole;
                $admin->forceFill([
                    'name' => $name,
                    'is_admin' => true,
                    'email_verified_at' => $admin->email_verified_at ?? now(),
                ])->save();
            } else {
                if ($adminByEmail) {
                    throw new RuntimeException('The configured administrator email has been preclaimed by an unmarked account.');
                }

                $admin = new User;
                $admin->forceFill([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make((string) $password),
                    'is_admin' => true,
                    'system_role' => self::ADMIN_ROLE,
                    'email_verified_at' => now(),
                ])->save();
            }

            $freePlan = Plan::query()
                ->where('slug', 'free')
                ->lockForUpdate()
                ->first();

            if (! $freePlan) {
                return;
            }

            $subscription = Subscription::query()
                ->where('user_id', $admin->id)
                ->where('plan_id', $freePlan->id)
                ->lockForUpdate()
                ->first() ?? new Subscription;
            $subscription->forceFill([
                'user_id' => $admin->id,
                'plan_id' => $freePlan->id,
                'provider' => 'system',
                'status' => 'active',
                'starts_at' => $subscription->starts_at ?? now(),
                'ends_at' => null,
            ])->save();
        });
    }
}

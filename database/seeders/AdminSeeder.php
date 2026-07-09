<?php
namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $name = config('chatme.admin.name');
        $email = config('chatme.admin.email');
        $password = config('chatme.admin.password');

        if (blank($name) || blank($email) || blank($password)) {
            return;
        }

        $admin = User::updateOrCreate(['email' => $email], [
            'name' => $name,
            'password' => Hash::make($password),
            'is_admin' => true,
        ]);

        $freePlan = Plan::where('slug', 'free')->first();
        if ($freePlan) {
            Subscription::updateOrCreate([
                'user_id' => $admin->id,
                'plan_id' => $freePlan->id,
            ], [
                'stripe_status' => 'active',
            ]);
        }
    }
}

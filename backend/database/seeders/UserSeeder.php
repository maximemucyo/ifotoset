<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $proPlan = Plan::where('slug', 'pro')->first();
        $businessPlan = Plan::where('slug', 'business')->first();

        User::updateOrCreate([
            'email' => 'studio@ifotoset.com',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $proPlan ? $proPlan->id : 1,
            'name' => 'Sarah Photography Studio',
            'username' => 'sarahphoto',
            'password' => bcrypt('demo123'),
            'role' => 'photographer',
            'storage_used_bytes' => 0,
            'is_active' => true,
        ]);

        User::updateOrCreate([
            'email' => 'admin@ifotoset.com',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $businessPlan ? $businessPlan->id : 1,
            'name' => 'Admin Manager',
            'username' => 'admin',
            'password' => bcrypt('demo123'),
            'role' => 'admin',
            'storage_used_bytes' => 0,
            'is_active' => true,
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::firstOrCreate([
            'slug' => 'free',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Free Tier',
            'monthly_price' => 0.00,
            'annual_price' => 0.00,
            'currency' => 'USD',
            'storage_limit' => 5 * 1024 * 1024 * 1024, // 5 GB
            'video_limit' => 0,
            'gallery_limit' => 3,
            'team_limit' => 0,
        ]);

        Plan::firstOrCreate([
            'slug' => 'pro',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Professional Portfolio',
            'monthly_price' => 19.00,
            'annual_price' => 190.00,
            'currency' => 'USD',
            'storage_limit' => 100 * 1024 * 1024 * 1024, // 100 GB
            'video_limit' => 5 * 1024 * 1024 * 1024,   // 5 GB
            'gallery_limit' => 50,
            'team_limit' => 1,
        ]);

        Plan::firstOrCreate([
            'slug' => 'business',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Studio Business',
            'monthly_price' => 49.00,
            'annual_price' => 490.00,
            'currency' => 'USD',
            'storage_limit' => 1024 * 1024 * 1024 * 1024, // 1 TB
            'video_limit' => 50 * 1024 * 1024 * 1024,     // 50 GB
            'gallery_limit' => 9999,
            'team_limit' => 5,
        ]);
    }
}

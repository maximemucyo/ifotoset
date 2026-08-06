<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate([
            'slug' => 'free',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Free',
            'monthly_price' => 0.00,
            'annual_price' => 0.00,
            'currency' => 'RWF',
            'storage_limit' => 2 * 1024 * 1024 * 1024, // 2 GB
            'video_limit' => 0,
            'gallery_limit' => 3,
            'team_limit' => 0,
        ]);

        Plan::updateOrCreate([
            'slug' => 'basic',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Basic',
            'monthly_price' => 10999.00,
            'annual_price' => 107988.00, // 8,999 * 12
            'currency' => 'RWF',
            'storage_limit' => 50 * 1024 * 1024 * 1024, // 50 GB
            'video_limit' => 30 * 60, // 30 minutes (stored as seconds or custom limit)
            'gallery_limit' => 10,
            'team_limit' => 0,
        ]);

        Plan::updateOrCreate([
            'slug' => 'pro',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Professional',
            'monthly_price' => 29999.00,
            'annual_price' => 299988.00, // 24,999 * 12
            'currency' => 'RWF',
            'storage_limit' => 1024 * 1024 * 1024 * 1024, // 1 TB
            'video_limit' => 5 * 3600, // 5 hours
            'gallery_limit' => 100,
            'team_limit' => 1,
        ]);

        Plan::updateOrCreate([
            'slug' => 'business',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Business',
            'monthly_price' => 59999.00,
            'annual_price' => 599988.00, // 49,999 * 12
            'currency' => 'RWF',
            'storage_limit' => 3 * 1024 * 1024 * 1024 * 1024, // 3 TB
            'video_limit' => 15 * 3600, // 15 hours
            'gallery_limit' => 9999,
            'team_limit' => 5,
        ]);
    }
}

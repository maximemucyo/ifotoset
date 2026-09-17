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
            'gallery_limit' => null,
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
            'video_limit' => 30 * 60,
            'gallery_limit' => null,
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
            'storage_limit' => 1000 * 1024 * 1024 * 1024, // 1 TB (1,000 GB)
            'video_limit' => 5 * 3600,
            'gallery_limit' => null,
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
            'storage_limit' => 3000 * 1024 * 1024 * 1024, // 3 TB (3,000 GB)
            'video_limit' => 15 * 3600,
            'gallery_limit' => null,
            'team_limit' => 5,
        ]);
    }
}

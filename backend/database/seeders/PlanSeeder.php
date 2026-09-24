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
            'video_limit_seconds' => 0,
            'max_video_size_bytes' => 0,
            'max_single_video_duration_seconds' => 0,
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
            'video_limit_seconds' => 30 * 60,
            'max_video_size_bytes' => 524288000, // 500 MB
            'max_single_video_duration_seconds' => 900, // 15 mins
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
            'video_limit_seconds' => 5 * 3600,
            'max_video_size_bytes' => 2147483648, // 2 GB
            'max_single_video_duration_seconds' => 3600, // 1 hour
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
            'video_limit_seconds' => 15 * 3600,
            'max_video_size_bytes' => 4294967296, // 4 GB
            'max_single_video_duration_seconds' => 7200, // 2 hours
            'gallery_limit' => null,
            'team_limit' => 5,
        ]);
    }
}

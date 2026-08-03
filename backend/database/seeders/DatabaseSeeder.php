<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Clear existing data
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('plan_features')->truncate();
        DB::table('features')->truncate();
        DB::table('plans')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 2. Create default features
        $customDomain = Feature::create([
            'code' => 'custom_domain',
            'name' => 'Custom Domain Support',
        ]);

        $bookingManager = Feature::create([
            'code' => 'booking_manager',
            'name' => 'Booking & Calendar Manager',
        ]);

        $prioritySupport = Feature::create([
            'code' => 'priority_support',
            'name' => 'Priority Support Tier',
        ]);

        // 3. Create default plans
        $freePlan = Plan::create([
            'uuid' => Uuid::uuid7()->toString(),
            'slug' => 'free',
            'name' => 'Free Tier',
            'monthly_price' => 0.00,
            'annual_price' => 0.00,
            'currency' => 'USD',
            'storage_limit' => 5 * 1024 * 1024 * 1024, // 5 GB
            'video_limit' => 0,
            'gallery_limit' => 3,
            'team_limit' => 0,
        ]);

        $proPlan = Plan::create([
            'uuid' => Uuid::uuid7()->toString(),
            'slug' => 'pro',
            'name' => 'Professional Portfolio',
            'monthly_price' => 19.00,
            'annual_price' => 190.00,
            'currency' => 'USD',
            'storage_limit' => 100 * 1024 * 1024 * 1024, // 100 GB
            'video_limit' => 5 * 1024 * 1024 * 1024,   // 5 GB
            'gallery_limit' => 50,
            'team_limit' => 1,
        ]);

        $businessPlan = Plan::create([
            'uuid' => Uuid::uuid7()->toString(),
            'slug' => 'business',
            'name' => 'Studio Business',
            'monthly_price' => 49.00,
            'annual_price' => 490.00,
            'currency' => 'USD',
            'storage_limit' => 1024 * 1024 * 1024 * 1024, // 1 TB
            'video_limit' => 50 * 1024 * 1024 * 1024,     // 50 GB
            'gallery_limit' => 9999,
            'team_limit' => 5,
        ]);

        // 4. Attach features to plans
        $proPlanFeatures = [$customDomain];
        foreach ($proPlanFeatures as $feature) {
            DB::table('plan_features')->insert([
                'plan_id' => $proPlan->id,
                'feature_id' => $feature->id,
            ]);
        }

        $businessPlanFeatures = [$customDomain, $bookingManager, $prioritySupport];
        foreach ($businessPlanFeatures as $feature) {
            DB::table('plan_features')->insert([
                'plan_id' => $businessPlan->id,
                'feature_id' => $feature->id,
            ]);
        }
    }
}
?>

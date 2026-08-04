<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        $customDomain = Feature::firstOrCreate([
            'code' => 'custom_domain',
        ], [
            'name' => 'Custom Domain Support',
        ]);

        $bookingManager = Feature::firstOrCreate([
            'code' => 'booking_manager',
        ], [
            'name' => 'Booking & Calendar Manager',
        ]);

        $prioritySupport = Feature::firstOrCreate([
            'code' => 'priority_support',
        ], [
            'name' => 'Priority Support Tier',
        ]);

        $proPlan = Plan::where('slug', 'pro')->first();
        if ($proPlan) {
            DB::table('plan_features')->insertOrIgnore([
                'plan_id' => $proPlan->id,
                'feature_id' => $customDomain->id,
            ]);
        }

        $businessPlan = Plan::where('slug', 'business')->first();
        if ($businessPlan) {
            DB::table('plan_features')->insertOrIgnore([
                ['plan_id' => $businessPlan->id, 'feature_id' => $customDomain->id],
                ['plan_id' => $businessPlan->id, 'feature_id' => $bookingManager->id],
                ['plan_id' => $businessPlan->id, 'feature_id' => $prioritySupport->id],
            ]);
        }
    }
}

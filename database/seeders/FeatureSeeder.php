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

        // Feature links are only seeded the first time a feature is created.
        // After that admins own the plan/feature matrix, and this seeder runs
        // on every deploy, so it must not re-add features they removed.
        $link = fn (Feature $feature) => $feature->wasRecentlyCreated;

        $proPlan = Plan::where('slug', 'pro')->first();
        if ($proPlan && $link($customDomain)) {
            DB::table('plan_features')->insertOrIgnore([
                'plan_id' => $proPlan->id,
                'feature_id' => $customDomain->id,
            ]);
        }

        $businessPlan = Plan::where('slug', 'business')->first();
        if ($businessPlan) {
            $rows = collect([$customDomain, $bookingManager, $prioritySupport])
                ->filter($link)
                ->map(fn (Feature $feature) => ['plan_id' => $businessPlan->id, 'feature_id' => $feature->id])
                ->values()
                ->all();

            if ($rows) {
                DB::table('plan_features')->insertOrIgnore($rows);
            }
        }
    }
}

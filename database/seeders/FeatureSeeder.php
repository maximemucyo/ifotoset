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

        $bookingDeposits = Feature::firstOrCreate([
            'code' => 'booking_deposits',
        ], [
            'name' => 'Mobile Money Booking Deposits',
        ]);

        $premiumTemplates = Feature::firstOrCreate([
            'code' => 'premium_templates',
        ], [
            'name' => 'Premium Portfolio Templates',
        ]);

        $teams = Feature::firstOrCreate([
            'code' => 'teams',
        ], [
            'name' => 'Multi-user Team Accounts',
        ]);

        // Feature links are only seeded the first time a feature is created.
        // After that admins own the plan/feature matrix, and this seeder runs
        // on every deploy, so it must not re-add features they removed.
        $link = fn (Feature $feature) => $feature->wasRecentlyCreated;

        $matrix = [
            'basic' => [$premiumTemplates],
            'pro' => [$customDomain, $bookingDeposits, $premiumTemplates],
            'business' => [$customDomain, $bookingManager, $prioritySupport, $bookingDeposits, $premiumTemplates, $teams],
        ];

        foreach ($matrix as $slug => $features) {
            $plan = Plan::where('slug', $slug)->first();
            if (!$plan) {
                continue;
            }

            $rows = collect($features)
                ->filter($link)
                ->map(fn (Feature $feature) => ['plan_id' => $plan->id, 'feature_id' => $feature->id])
                ->values()
                ->all();

            if ($rows) {
                DB::table('plan_features')->insertOrIgnore($rows);
            }
        }
    }
}

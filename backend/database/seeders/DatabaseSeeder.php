<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Only a local machine or the test suite may wipe data. Anything else
        // (production, staging, a mistyped APP_ENV) gets the idempotent seeders only.
        if (!app()->environment('local', 'testing')) {
            $this->call([
                PlanSeeder::class,
                FeatureSeeder::class,
            ]);
            return;
        }

        // 1. Clear existing data in correct sequence to prevent FK errors
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('plan_features')->truncate();
        DB::table('features')->truncate();
        DB::table('plans')->truncate();
        DB::table('users')->truncate();
        DB::table('galleries')->truncate();
        DB::table('gallery_stats')->truncate();
        DB::table('reviews')->truncate();
        foreach (['photo_metadata', 'media_jobs', 'photos', 'bookings', 'clients', 'packages', 'availability_settings'] as $table) {
            if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 2. Orchestrate seeders
        $this->call([
            PlanSeeder::class,
            FeatureSeeder::class,
            UserSeeder::class,
            GallerySeeder::class,
            ReviewSeeder::class,
            DemoContentSeeder::class,
        ]);
    }
}

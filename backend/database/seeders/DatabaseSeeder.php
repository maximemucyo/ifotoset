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
        // 1. Clear existing data in correct sequence to prevent FK errors
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('plan_features')->truncate();
        DB::table('features')->truncate();
        DB::table('plans')->truncate();
        DB::table('users')->truncate();
        DB::table('galleries')->truncate();
        DB::table('gallery_stats')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 2. Orchestrate seeders
        $this->call([
            PlanSeeder::class,
            FeatureSeeder::class,
            UserSeeder::class,
            GallerySeeder::class,
        ]);
    }
}

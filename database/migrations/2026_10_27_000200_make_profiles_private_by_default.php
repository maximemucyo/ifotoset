<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Portfolios are opt-in: nobody is listed or reachable until they switch "Public portfolio" on
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('profile_public')->default(false)->change();
        });

        // Everyone got the old default of true without choosing it, so start them hidden too
        DB::table('users')->update(['profile_public' => false]);

        // Sitemaps are cached for hours; drop them so the hidden usernames stop being served now
        $pages = max(1, (int) ceil(max(DB::table('users')->count(), DB::table('galleries')->count()) / 1000));
        Cache::forget('sitemap_index_xml');
        foreach (['photographers', 'galleries'] as $section) {
            Cache::forget("sitemap_{$section}_xml");
            for ($page = 2; $page <= $pages; $page++) {
                Cache::forget("sitemap_{$section}-{$page}_xml");
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('profile_public')->default(true)->change();
        });
    }
};

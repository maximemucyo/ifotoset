<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('portfolio_settings')->nullable()->after('avatar_path');
            $table->string('logo_path')->nullable()->after('portfolio_settings');
            $table->string('cover_path')->nullable()->after('logo_path');
            $table->string('share_image_path')->nullable()->after('cover_path');
        });

        Schema::table('galleries', function (Blueprint $table) {
            $table->string('portfolio_category', 60)->nullable()->after('featured_order');
        });

        // /book, /about and /contact are portfolio pages now and would shadow galleries with those slugs
        // (the id suffix keeps the new slug unique per photographer)
        DB::table('galleries')->whereIn('slug', ['book', 'about', 'contact'])->orderBy('id')->get(['id', 'slug'])
            ->each(fn ($gallery) => DB::table('galleries')->where('id', $gallery->id)->update(['slug' => "{$gallery->slug}-{$gallery->id}"]));
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn('portfolio_category');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['portfolio_settings', 'logo_path', 'cover_path', 'share_image_path']);
        });
    }
};

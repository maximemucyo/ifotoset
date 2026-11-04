<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How the studio's watermark looks; null is the default (the studio name in the middle)
        Schema::table('users', function (Blueprint $table) {
            $table->json('watermark')->nullable();
        });

        // Off, so no gallery changes until the photographer turns it on
        Schema::table('galleries', function (Blueprint $table) {
            $table->boolean('watermark')->default(false);
        });

        // Which watermark design the photo's "_w{version}" previews were made with
        Schema::table('photos', function (Blueprint $table) {
            $table->string('watermark_version', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('photos', fn (Blueprint $table) => $table->dropColumn('watermark_version'));
        Schema::table('galleries', fn (Blueprint $table) => $table->dropColumn('watermark'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('watermark'));
    }
};

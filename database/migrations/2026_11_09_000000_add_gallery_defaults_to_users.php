<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a studio's new galleries start with; empty means today's defaults
        Schema::table('users', function (Blueprint $table) {
            $table->json('gallery_defaults')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('gallery_defaults');
        });
    }
};

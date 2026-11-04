<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The getting-started checklist, hidden for good from the Overview
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('setup_checklist_dismissed_at')->nullable();
        });

        // When the photographer last opened a client's picks: sent picks stay under "Needs you" until then
        Schema::table('gallery_favorite_lists', function (Blueprint $table) {
            $table->timestamp('seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('setup_checklist_dismissed_at');
        });

        Schema::table('gallery_favorite_lists', function (Blueprint $table) {
            $table->dropColumn('seen_at');
        });
    }
};

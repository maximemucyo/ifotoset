<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('media_jobs', function (Blueprint $table) {
            $table->index(['status', 'failed_at']);
            $table->index(['status', 'completed_at']);
            $table->index('created_at');
        });

        Schema::table('google_photo_syncs', function (Blueprint $table) {
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('media_jobs', function (Blueprint $table) {
            $table->dropIndex(['status', 'failed_at']);
            $table->dropIndex(['status', 'completed_at']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('google_photo_syncs', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
        });
    }
};

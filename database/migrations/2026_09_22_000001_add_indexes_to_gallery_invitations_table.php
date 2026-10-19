<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('gallery_invitations', function (Blueprint $table) {
            $table->index(['gallery_id', 'email'], 'idx_gallery_email');
            $table->index(['gallery_id', 'revoked_at'], 'idx_gallery_revoked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gallery_invitations', function (Blueprint $table) {
            $table->dropIndex('idx_gallery_email');
            $table->dropIndex('idx_gallery_revoked');
        });
    }
};

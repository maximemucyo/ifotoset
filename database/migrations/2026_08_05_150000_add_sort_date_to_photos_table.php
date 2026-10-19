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
        Schema::table('photos', function (Blueprint $table) {
            $table->timestamp('sort_date')
                ->storedAs('COALESCE(taken_at, created_at)')
                ->after('taken_at');
            
            $table->index(['gallery_id', 'sort_date', 'sort_order', 'id'], 'photos_gallery_sort_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropIndex('photos_gallery_sort_date_idx');
            $table->dropColumn('sort_date');
        });
    }
};

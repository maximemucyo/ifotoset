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
        Schema::table('galleries', function (Blueprint $table) {
            // taken | filename | uploaded (see Gallery::PHOTO_ORDERS)
            $table->string('photo_order', 16)->default('taken')->after('allow_google_photos');
        });

        Schema::table('photos', function (Blueprint $table) {
            // Camera file numbers break ties inside a burst shot in the same second.
            $table->string('sort_name', 255)
                ->storedAs("COALESCE(original_filename, filename, '')")
                ->after('sort_date');

            $table->index(['gallery_id', 'sort_date', 'sort_name', 'id'], 'photos_gallery_taken_order_idx');
            $table->index(['gallery_id', 'sort_name', 'id'], 'photos_gallery_name_order_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropIndex('photos_gallery_taken_order_idx');
            $table->dropIndex('photos_gallery_name_order_idx');
            $table->dropColumn('sort_name');
        });

        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn('photo_order');
        });
    }
};

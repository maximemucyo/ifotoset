<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insert missing gallery_stats rows for any existing galleries
        $galleries = DB::table('galleries')->select('id')->get();
        foreach ($galleries as $gallery) {
            DB::table('gallery_stats')->insertOrIgnore([
                'gallery_id' => $gallery->id,
                'photo_count' => 0,
                'video_count' => 0,
                'downloads_count' => 0,
                'favorites_count' => 0,
                'total_bytes' => 0,
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No schema changes to rollback, since it only initializes missing data
    }
};
?>

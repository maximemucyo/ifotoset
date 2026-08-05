<?php

namespace App\Services;

use App\Models\GalleryStats;
use Illuminate\Support\Facades\DB;

class GalleryStatisticsService
{
    /**
     * Recalculate and update the stats for a specific gallery.
     */
    public function recalculateGallery(int $galleryId): void
    {
        $photoStats = DB::table('photos')
            ->where('gallery_id', $galleryId)
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as photo_count, COALESCE(SUM(size), 0) as total_bytes')
            ->first();

        GalleryStats::updateOrCreate(
            ['gallery_id' => $galleryId],
            [
                'photo_count' => (int) ($photoStats->photo_count ?? 0),
                'total_bytes' => (int) ($photoStats->total_bytes ?? 0),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Recalculate and update the storage quota for a specific user.
     */
    public function recalculateUserStorage(int $userId): void
    {
        $totalBytes = DB::table('photos')
            ->join('galleries', 'photos.gallery_id', '=', 'galleries.id')
            ->where('galleries.user_id', $userId)
            ->whereNull('galleries.deleted_at')
            ->whereNull('photos.deleted_at')
            ->sum('photos.size');

        DB::table('users')
            ->where('id', $userId)
            ->update([
                'storage_used_bytes' => (int) $totalBytes,
                'updated_at' => now(),
            ]);
    }
}
?>

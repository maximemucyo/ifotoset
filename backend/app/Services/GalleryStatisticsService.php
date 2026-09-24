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
        $stats = DB::table('photos')
            ->where('gallery_id', $galleryId)
            ->whereNull('deleted_at')
            ->selectRaw("
                COUNT(CASE WHEN media_type = 'photo' OR media_type IS NULL OR media_type = '' THEN 1 END) as photo_count,
                COUNT(CASE WHEN media_type = 'video' AND status = 'ready' THEN 1 END) as video_count,
                COALESCE(SUM(size), 0) as total_bytes
            ")
            ->first();

        GalleryStats::updateOrCreate(
            ['gallery_id' => $galleryId],
            [
                'photo_count' => (int) ($stats->photo_count ?? 0),
                'video_count' => (int) ($stats->video_count ?? 0),
                'total_bytes' => (int) ($stats->total_bytes ?? 0),
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

        \App\Events\StorageRecalculatedEvent::dispatch($userId);
    }

    /**
     * Recalculate and update the video quota (seconds) for a specific user.
     * Used strictly for scheduled reconciliation or admin repair.
     */
    public function recalculateUserVideoSeconds(int $userId): void
    {
        $totalVideoSeconds = DB::table('photos')
            ->join('galleries', 'photos.gallery_id', '=', 'galleries.id')
            ->where('galleries.user_id', $userId)
            ->where('photos.media_type', 'video')
            ->where('photos.status', 'ready')
            ->whereNull('galleries.deleted_at')
            ->whereNull('photos.deleted_at')
            ->sum('photos.duration_seconds');

        DB::table('users')
            ->where('id', $userId)
            ->update([
                'video_seconds_used' => (int) $totalVideoSeconds,
                'updated_at' => now(),
            ]);
    }
}
?>

<?php

namespace App\Services;

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\PurgeGalleryJob;
use App\Jobs\PurgePhotoJob;

class TrashService
{
    public function __construct(
        protected StorageService $storageService,
        protected GalleryStatisticsService $statisticsService
    ) {}

    /**
     * Restore a soft-deleted gallery.
     */
    public function restoreGallery(Gallery $gallery): void
    {
        DB::transaction(function () use ($gallery) {
            $gallery->restore();
            
            // Recalculate statistics
            $this->statisticsService->recalculateUserStorage($gallery->user_id);
        });
    }

    /**
     * Restore a soft-deleted photo.
     */
    public function restorePhoto(Photo $photo): void
    {
        DB::transaction(function () use ($photo) {
            $photo->restore();
            
            // Recalculate statistics
            if ($photo->gallery_id) {
                $this->statisticsService->recalculateGallery($photo->gallery_id);
                
                $gallery = $photo->gallery()->withTrashed()->first();
                if ($gallery) {
                    $this->statisticsService->recalculateUserStorage($gallery->user_id);
                }
            }
        });
    }

    /**
     * Permanently delete a gallery (triggered by job).
     */
    public function purgeGallery(int $galleryId): void
    {
        $gallery = Gallery::onlyTrashed()->find($galleryId);
        if (!$gallery) {
            return; // Already deleted/purged
        }

        $userId = $gallery->user_id;
        $galleryUuid = $gallery->uuid;

        // Delete from B2 first. If this fails, it throws and aborts the DB transaction.
        $this->storageService->deleteDirectory("galleries/{$galleryUuid}");

        DB::transaction(function () use ($gallery, $userId, $galleryUuid) {
            // forceDelete() cascades in DB to delete stats, photos, and invitations.
            $gallery->forceDelete();

            // Recalculate user storage stats
            $this->statisticsService->recalculateUserStorage($userId);

            // Log audit
            Log::info('[Trash Audit] Gallery permanently deleted.', [
                'user_id' => $userId,
                'gallery_id' => $gallery->id,
                'gallery_uuid' => $galleryUuid,
                'actor' => auth()->id() ? 'manual' : 'scheduled_purge',
                'storage_prefix' => "galleries/{$galleryUuid}",
                'timestamp' => now()->toIso8601String(),
            ]);
        });
    }

    /**
     * Permanently delete a photo (triggered by job).
     */
    public function purgePhoto(int $photoId): void
    {
        $photo = Photo::onlyTrashed()->find($photoId);
        if (!$photo) {
            return; // Already deleted/purged
        }

        $galleryId = $photo->gallery_id;
        $photoPath = $photo->path;
        $photoUuid = $photo->uuid;

        // Delete from B2 first
        $this->storageService->deleteDirectory($photoPath);

        DB::transaction(function () use ($photo, $galleryId, $photoUuid) {
            $gallery = Gallery::withTrashed()->find($galleryId);
            $userId = $gallery?->user_id;

            $photo->forceDelete();

            // Recalculate statistics
            if ($galleryId) {
                $this->statisticsService->recalculateGallery($galleryId);
            }
            if ($userId) {
                $this->statisticsService->recalculateUserStorage($userId);
            }

            // Log audit
            Log::info('[Trash Audit] Photo permanently deleted.', [
                'user_id' => $userId,
                'photo_id' => $photo->id,
                'photo_uuid' => $photoUuid,
                'actor' => auth()->id() ? 'manual' : 'scheduled_purge',
                'storage_prefix' => $photoPath,
                'timestamp' => now()->toIso8601String(),
            ]);
        });
    }

    /**
     * Dispatch background jobs to empty all trash for a user.
     */
    public function emptyTrash(User $user): void
    {
        // 1. Dispatch jobs for soft-deleted galleries in chunks
        Gallery::onlyTrashed()
            ->where('user_id', $user->id)
            ->chunkById(50, function ($galleries) {
                foreach ($galleries as $gallery) {
                    PurgeGalleryJob::dispatch($gallery->id);
                }
            });

        // 2. Dispatch jobs for individually soft-deleted photos in chunks
        Photo::onlyTrashed()
            ->whereHas('gallery', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->whereNull('deleted_at');
            })
            ->chunkById(100, function ($photos) {
                foreach ($photos as $photo) {
                    PurgePhotoJob::dispatch($photo->id);
                }
            });
    }
}

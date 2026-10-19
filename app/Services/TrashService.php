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
        protected GalleryStatisticsService $statisticsService,
        protected StorageKeyResolver $storageKeyResolver
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
     * Permanently delete a gallery and all associated photos, variants, and archives.
     */
    public function purgeGallery(int $galleryId, bool $recalculateStats = true): void
    {
        $gallery = Gallery::onlyTrashed()
            ->with(['photos' => fn($q) => $q->withTrashed()])
            ->find($galleryId);

        if (!$gallery) {
            return; // Already deleted/purged
        }

        $userId = $gallery->user_id;
        $galleryUuid = (string) $gallery->uuid;

        // 1. Resolve all exact B2 object keys, variants, and folder prefixes
        $resolved = $this->storageKeyResolver->resolveGalleryKeys($gallery);

        // 2. Storage cleanup first. If this fails, it throws and aborts DB deletion.
        if (!empty($resolved['objects'])) {
            $this->storageService->deleteObjects($resolved['objects']);
        }

        foreach ($resolved['prefixes'] as $prefix) {
            $this->storageService->deleteDirectory($prefix);
        }

        // 3. Database deletion (only after storage deletion succeeds)
        DB::transaction(function () use ($gallery, $userId, $galleryUuid, $recalculateStats) {
            // Force delete photos explicitly to ensure no orphan records remain
            $gallery->photos()->withTrashed()->forceDelete();

            // Force delete gallery (cascades to invitations, stats, etc.)
            $gallery->forceDelete();

            if ($recalculateStats) {
                $this->statisticsService->recalculateUserStorage($userId);
            }

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
     * Permanently delete an individual photo and its variants.
     */
    public function purgePhoto(int $photoId, bool $recalculateStats = true): void
    {
        $photo = Photo::onlyTrashed()->find($photoId);
        if (!$photo) {
            return; // Already deleted/purged
        }

        $galleryId = $photo->gallery_id;
        $photoPath = (string) $photo->path;
        $photoUuid = (string) $photo->uuid;

        // 1. Resolve exact original key and all responsive variant keys
        $resolved = $this->storageKeyResolver->resolvePhotoKeys($photo);

        // 2. Storage cleanup first
        if (!empty($resolved['objects'])) {
            $this->storageService->deleteObjects($resolved['objects']);
        }

        if (!empty($resolved['prefix'])) {
            $this->storageService->deleteDirectory($resolved['prefix']);
        }

        // 3. Database deletion
        DB::transaction(function () use ($photo, $galleryId, $photoPath, $photoUuid, $recalculateStats) {
            $gallery = Gallery::withTrashed()->find($galleryId);
            $userId = $gallery?->user_id;

            $photo->forceDelete();

            if ($recalculateStats) {
                if ($galleryId) {
                    $this->statisticsService->recalculateGallery($galleryId);
                }
                if ($userId) {
                    $this->statisticsService->recalculateUserStorage($userId);
                }
            }

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
     * Bounded processing to empty all trash for a user with single-pass quota recalculation.
     */
    public function emptyTrash(User $user): void
    {
        // 1. Permanently purge soft-deleted galleries in chunks of 50
        Gallery::onlyTrashed()
            ->where('user_id', $user->id)
            ->chunkById(50, function ($galleries) {
                foreach ($galleries as $gallery) {
                    $this->purgeGallery($gallery->id, recalculateStats: false);
                }
            });

        // 2. Permanently purge remaining individually soft-deleted photos whose parent gallery is active
        Photo::onlyTrashed()
            ->whereHas('gallery', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->whereNull('deleted_at');
            })
            ->chunkById(100, function ($photos) {
                foreach ($photos as $photo) {
                    $this->purgePhoto($photo->id, recalculateStats: false);
                }
            });

        // 3. Recalculate user storage statistics once
        $this->statisticsService->recalculateUserStorage($user->id);
    }
}

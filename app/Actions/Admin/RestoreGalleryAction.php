<?php

namespace App\Actions\Admin;

use App\Models\AdminAuditLog;
use App\Models\Gallery;
use App\Models\User;
use App\Services\GalleryCachePurgeService;
use Illuminate\Support\Facades\DB;

class RestoreGalleryAction
{
    public function __construct(
        protected GalleryCachePurgeService $cachePurgeService
    ) {}

    /**
     * Restore a taken down gallery to normal moderation status, keeping photographer's original visibility untouched.
     */
    public function execute(User $admin, Gallery $gallery): Gallery
    {
        return DB::transaction(function () use ($admin, $gallery) {
            $gallery->update([
                'moderation_status' => 'normal',
                'restored_at' => now(),
                'restored_by' => $admin->id,
            ]);

            AdminAuditLog::record(
                $admin,
                'gallery.restored',
                'Gallery',
                $gallery->uuid,
                [
                    'gallery_id' => $gallery->id,
                    'title' => $gallery->title,
                    'photographer_id' => $gallery->user_id,
                    'owner_visibility' => $gallery->visibility,
                ]
            );

            $this->cachePurgeService->purge($gallery);

            return $gallery;
        });
    }
}

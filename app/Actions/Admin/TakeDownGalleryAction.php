<?php

namespace App\Actions\Admin;

use App\Models\AdminAuditLog;
use App\Models\Gallery;
use App\Models\User;
use App\Services\GalleryCachePurgeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TakeDownGalleryAction
{
    public function __construct(
        protected GalleryCachePurgeService $cachePurgeService
    ) {}

    /**
     * Take down an infringing/inappropriate gallery with reason, recording moderation status and audit log.
     */
    public function execute(User $admin, Gallery $gallery, string $reason): Gallery
    {
        $cleanReason = trim($reason);
        if (empty($cleanReason)) {
            throw ValidationException::withMessages([
                'reason' => ['A specific reason is required to take down a gallery for moderation.'],
            ]);
        }

        return DB::transaction(function () use ($admin, $gallery, $cleanReason) {
            $gallery->update([
                'moderation_status' => 'taken_down',
                'taken_down_at' => now(),
                'taken_down_by' => $admin->id,
                'takedown_reason' => $cleanReason,
                'restored_at' => null,
                'restored_by' => null,
            ]);

            AdminAuditLog::record(
                $admin,
                'gallery.taken_down',
                'Gallery',
                $gallery->uuid,
                [
                    'gallery_id' => $gallery->id,
                    'title' => $gallery->title,
                    'photographer_id' => $gallery->user_id,
                    'reason' => $cleanReason,
                    'owner_visibility' => $gallery->visibility,
                ]
            );

            $this->cachePurgeService->purge($gallery);

            return $gallery;
        });
    }
}

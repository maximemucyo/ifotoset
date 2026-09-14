<?php

namespace App\Actions\Admin;

use App\Models\AdminAuditLog;
use App\Models\Gallery;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ModerateGallery
{
    /**
     * Moderate a gallery (approve to public, or reject to private) with audit logging.
     */
    public function execute(User $admin, Gallery $gallery, string $decision, ?string $reason = null): Gallery
    {
        return DB::transaction(function () use ($admin, $gallery, $decision, $reason) {
            $newVisibility = ($decision === 'approve') ? 'public' : 'private';
            $oldVisibility = $gallery->visibility;

            $gallery->update(['visibility' => $newVisibility]);

            AdminAuditLog::record(
                $admin,
                $decision === 'approve' ? 'moderation.approved' : 'moderation.rejected',
                'Gallery',
                $gallery->uuid,
                [
                    'decision'       => $decision,
                    'reason'         => $reason,
                    'old_visibility' => $oldVisibility,
                    'new_visibility' => $newVisibility,
                    'title'          => $gallery->title,
                ]
            );

            return $gallery;
        });
    }
}

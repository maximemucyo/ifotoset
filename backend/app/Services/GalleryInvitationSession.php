<?php

namespace App\Services;

use App\Models\Gallery;
use App\Models\GalleryInvitation;
use Illuminate\Support\Facades\Session;

class GalleryInvitationSession
{
    public static function key(Gallery $gallery): string
    {
        return "gallery_invitation_{$gallery->uuid}";
    }

    public static function put(Gallery $gallery, GalleryInvitation $invitation): void
    {
        Session::put(self::key($gallery), [
            'invitation_id' => $invitation->id,
            'gallery_id' => $gallery->id,
            'granted_at' => now()->timestamp,
        ]);
    }

    public static function get(Gallery $gallery): ?array
    {
        $val = Session::get(self::key($gallery));
        return is_array($val) ? $val : null;
    }

    public static function forget(Gallery $gallery): void
    {
        Session::forget(self::key($gallery));
    }

    /**
     * Resolves the invitation from the session and re-verifies against the database.
     * If the invitation was revoked or expired, the session is cleared immediately.
     */
    public static function resolveValidInvitation(Gallery $gallery): ?GalleryInvitation
    {
        $sessionData = self::get($gallery);
        if (!$sessionData || empty($sessionData['invitation_id'])) {
            return null;
        }

        if ((int) ($sessionData['gallery_id'] ?? 0) !== (int) $gallery->id) {
            self::forget($gallery);
            return null;
        }

        $invitation = GalleryInvitation::where('id', $sessionData['invitation_id'])
            ->where('gallery_id', $gallery->id)
            ->first();

        if ($invitation && $invitation->isValid()) {
            return $invitation;
        }

        // Revoked, expired, or non-existent invitation
        self::forget($gallery);
        return null;
    }
}

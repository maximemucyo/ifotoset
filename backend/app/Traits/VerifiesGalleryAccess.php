<?php

namespace App\Traits;

use App\Models\Gallery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait VerifiesGalleryAccess
{
    /**
     * Verify if the request has access to the given public/private gallery.
     */
    protected function verifyGalleryAccess(Gallery $gallery, Request $request): ?JsonResponse
    {
        // Check if gallery is expired
        if ($gallery->expires_at && $gallery->expires_at->isPast()) {
            return response()->json([
                'code' => 'GALLERY_EXPIRED',
                'message' => 'This gallery has expired and is no longer accessible.',
            ], 403);
        }

        $visibility = $gallery->visibility;

        if ($visibility === 'public') {
            return null; // Access granted
        }

        // If private, check access method
        // 1. Password protection
        if (!empty($gallery->password_hash)) {
            // Check for stateless token in header or query
            $token = $request->header('X-Gallery-Token') ?: $request->query('token');
            $expectedToken = hash_hmac('sha256', $gallery->uuid, config('app.key'));

            if ($token && hash_equals($expectedToken, $token)) {
                return null; // Access granted
            }

            return response()->json([
                'code' => 'PASSWORD_REQUIRED',
                'message' => 'This gallery is password-protected.',
                'requires_password' => true,
                'password_hint' => $gallery->password_hint,
            ], 403);
        }

        // 2. Invitation list protection
        // Check for 'invite' token in query parameter
        $inviteToken = $request->query('invite');
        if ($inviteToken) {
            $hashedToken = hash('sha256', $inviteToken);
            $invitation = \App\Models\GalleryInvitation::where('gallery_id', $gallery->id)
                ->where('token', $hashedToken)
                ->first();

            if ($invitation) {
                if ($invitation->revoked_at) {
                    return response()->json([
                        'code' => 'INVITATION_REVOKED',
                        'message' => 'This invitation has been revoked.',
                    ], 403);
                }

                if ($invitation->expires_at && $invitation->expires_at->isPast()) {
                    return response()->json([
                        'code' => 'INVITATION_EXPIRED',
                        'message' => 'This invitation has expired.',
                    ], 403);
                }

                // Accept invitation if not already accepted
                if (!$invitation->accepted_at) {
                    $invitation->update(['accepted_at' => now()]);
                }

                return null; // Access granted
            }
        }

        return response()->json([
            'code' => 'INVITATION_REQUIRED',
            'message' => 'This gallery is private and requires a valid invitation link.',
            'requires_invitation' => true,
        ], 403);
    }
}

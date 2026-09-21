<?php

namespace App\Traits;

use App\Models\Gallery;
use App\Models\GalleryInvitation;
use App\Services\GalleryInvitationSession;
use App\ValueObjects\GalleryAccessDecision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait VerifiesGalleryAccess
{
    /**
     * Pure evaluation of gallery access rights for the given request.
     */
    protected function evaluateGalleryAccess(Gallery $gallery, Request $request): GalleryAccessDecision
    {
        // 1. Check if gallery is expired
        if ($gallery->expires_at && $gallery->expires_at->isPast()) {
            return GalleryAccessDecision::expired();
        }

        $visibility = $gallery->visibility;

        // 2. Public galleries: accessible to everyone
        if ($visibility === 'public') {
            return GalleryAccessDecision::granted();
        }

        // 3. PIN protected galleries
        if ($visibility === 'password' || !empty($gallery->password_hash)) {
            // Check session unlock with password hash checksum protection
            if ($request->hasSession()) {
                $sessionUnlock = $request->session()->get("gallery_unlocked_{$gallery->id}");
                if (is_array($sessionUnlock) && !empty($sessionUnlock['hash_checksum'])) {
                    if (hash_equals($sessionUnlock['hash_checksum'], md5($gallery->password_hash ?? ''))) {
                        return GalleryAccessDecision::granted();
                    }
                } elseif ($sessionUnlock === true) {
                    $request->session()->put("gallery_unlocked_{$gallery->id}", [
                        'unlocked' => true,
                        'hash_checksum' => md5($gallery->password_hash ?? ''),
                    ]);
                    return GalleryAccessDecision::granted();
                }
            }

            // Check for stateless token in header or query
            $token = $request->header('X-Gallery-Token') ?: $request->query('token');
            $expectedToken = hash_hmac('sha256', $gallery->uuid, config('app.key'));

            if ($token && hash_equals($expectedToken, $token)) {
                return GalleryAccessDecision::granted();
            }

            return GalleryAccessDecision::passwordRequired($gallery->password_hint);
        }

        // 4. Private galleries: requires valid invitation
        if ($visibility === 'private') {
            // Check active session with database re-verification
            if ($request->hasSession()) {
                $sessionData = GalleryInvitationSession::get($gallery);
                if ($sessionData && !empty($sessionData['invitation_id'])) {
                    $validSessionInvitation = GalleryInvitationSession::resolveValidInvitation($gallery);
                    if ($validSessionInvitation) {
                        return GalleryAccessDecision::granted();
                    }

                    // Session had an invitation, but it is now revoked, expired, or deleted
                    return GalleryAccessDecision::invitationInvalid();
                }
            }

            // Check if request query contains an invite token
            $inviteToken = $request->query('invite');
            if ($inviteToken) {
                $hashedToken = hash('sha256', $inviteToken);
                $invitation = GalleryInvitation::where('gallery_id', $gallery->id)
                    ->where('token', $hashedToken)
                    ->first();

                if ($invitation && $invitation->isValid()) {
                    return GalleryAccessDecision::granted();
                }

                // An invite token was provided but it is invalid, expired, or revoked
                return GalleryAccessDecision::invitationInvalid();
            }

            // No invite token and no active session
            return GalleryAccessDecision::invitationRequired();
        }

        return GalleryAccessDecision::invitationRequired();
    }

    /**
     * Explicit mutation: validates raw invitation token, records acceptance, and establishes session.
     * Returns true if valid and session was established, false otherwise.
     */
    protected function acceptInvitation(Gallery $gallery, string $rawToken, Request $request): bool
    {
        $hashedToken = hash('sha256', $rawToken);
        $invitation = GalleryInvitation::where('gallery_id', $gallery->id)
            ->where('token', $hashedToken)
            ->first();

        if (!$invitation || !$invitation->isValid()) {
            return false;
        }

        if (!$invitation->accepted_at) {
            $invitation->update(['accepted_at' => now()]);
        }

        if ($request->hasSession()) {
            GalleryInvitationSession::put($gallery, $invitation);
        }

        return true;
    }

    /**
     * Backward-compatible JSON access verification helper for API endpoints.
     */
    protected function verifyGalleryAccess(Gallery $gallery, Request $request): ?JsonResponse
    {
        $decision = $this->evaluateGalleryAccess($gallery, $request);

        if ($decision->isGranted()) {
            return null;
        }

        if ($decision->isExpired()) {
            return response()->json([
                'code' => 'GALLERY_EXPIRED',
                'message' => $decision->message,
            ], 403);
        }

        if ($decision->requiresPassword()) {
            return response()->json([
                'code' => 'PASSWORD_REQUIRED',
                'message' => $decision->message,
                'requires_password' => true,
                'password_hint' => $decision->passwordHint,
            ], 403);
        }

        if ($decision->isInvitationInvalid()) {
            return response()->json([
                'code' => 'INVITATION_INVALID',
                'message' => $decision->message,
                'invitation_invalid' => true,
            ], 403);
        }

        return response()->json([
            'code' => 'INVITATION_REQUIRED',
            'message' => $decision->message,
            'requires_invitation' => true,
        ], 403);
    }
}

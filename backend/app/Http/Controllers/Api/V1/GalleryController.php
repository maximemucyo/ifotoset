<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Visibility;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\GalleryResource;
use App\Models\Gallery;
use App\Models\GalleryStats;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    /**
     * List authenticated photographer's galleries.
     * GET /api/v1/galleries
     */
    public function index(Request $request): JsonResponse
    {
        $galleries = Gallery::where('user_id', $request->user()->id)
            ->with(['stats', 'coverPhoto'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return GalleryResource::collection($galleries)->response();
    }

    /**
     * Create a new gallery with per-user unique slug handling.
     * POST /api/v1/galleries
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'event_date' => ['nullable', 'date'],
            'visibility' => ['nullable', 'string', 'in:public,private'],
            'password' => ['nullable', 'string', 'min:6'],
            'password_hint' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'invite_emails' => ['nullable', 'array'],
            'invite_emails.*' => ['required', 'email:rfc,dns', 'distinct'],
            'access_method' => ['nullable', 'string', 'in:password,invite'],
        ]);

        $user = $request->user();

        // Ensure slug is unique for this specific photographer
        $slug = $validated['slug'];
        $count = Gallery::where('user_id', $user->id)->where('slug', $slug)->count();
        if ($count > 0) {
            $slug = "{$slug}-" . time();
        }

        $isPrivate = ($validated['visibility'] ?? null) === 'private';
        $accessMethod = $validated['access_method'] ?? null;

        $gallery = null;
        $invitationDataList = [];

        \Illuminate\Support\Facades\DB::transaction(function () use (&$gallery, &$invitationDataList, $user, $validated, $slug, $isPrivate, $accessMethod) {
            $gallery = Gallery::create([
                'user_id' => $user->id,
                'title' => $validated['title'],
                'slug' => $slug,
                'client_name' => $validated['client_name'] ?? null,
                'event_date' => $validated['event_date'] ?? null,
                'visibility' => $validated['visibility'] ?? Visibility::Public->value,
                'password_hash' => ($isPrivate && $accessMethod === 'password' && !empty($validated['password'])) ? bcrypt($validated['password']) : null,
                'password_hint' => ($isPrivate && $accessMethod === 'password') ? ($validated['password_hint'] ?? null) : null,
                'expires_at' => $validated['expires_at'] ?? null,
                'version' => 1,
            ]);

            // Create materialized stats row
            GalleryStats::create(['gallery_id' => $gallery->id]);

            // If private and access method is invite, create invitations
            if ($isPrivate && $accessMethod === 'invite' && !empty($validated['invite_emails'])) {
                foreach ($validated['invite_emails'] as $email) {
                    $rawToken = bin2hex(random_bytes(32));
                    $hashedToken = hash('sha256', $rawToken);

                    $invitation = \App\Models\GalleryInvitation::create([
                        'gallery_id' => $gallery->id,
                        'email' => strtolower(trim($email)),
                        'token' => $hashedToken,
                        'invited_by' => $user->id,
                        'expires_at' => $gallery->expires_at,
                    ]);

                    $invitationDataList[] = [
                        'raw_token' => $rawToken,
                        'email' => strtolower(trim($email)),
                    ];
                }
            }
        });

        // Dispatch queued emails after transaction commits
        if (!empty($invitationDataList) && $gallery) {
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
            foreach ($invitationDataList as $data) {
                try {
                    $inviteUrl = "{$frontendUrl}/g/{$gallery->slug}?invite={$data['raw_token']}";
                    \Illuminate\Support\Facades\Mail::to($data['email'])->queue(
                        new \App\Mail\GalleryInvitation($gallery, $inviteUrl, $user->name)
                    );
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning(
                        'Gallery invitation email failed to queue',
                        [
                            'gallery_id' => $gallery->id,
                            'recipient' => $data['email'],
                            'error' => $e->getMessage(),
                        ]
                    );
                }
            }
        }

        return (new GalleryResource($gallery->load('stats')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display gallery details.
     * GET /api/v1/galleries/{uuid}
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();

        $this->authorize('view', $gallery);

        $gallery->load(['stats', 'coverPhoto', 'photos', 'invitations']);

        return (new GalleryResource($gallery))->response();
    }

    /**
     * Display public gallery details (slug-based).
     * GET /api/v1/public/galleries/{slug}
     */
    public function showPublic(Request $request, string $slug): JsonResponse
    {
        $gallery = Gallery::where('slug', $slug)
            ->with(['stats', 'coverPhoto'])
            ->firstOrFail();

        $errorResponse = $this->verifyGalleryAccess($gallery, $request);
        if ($errorResponse) {
            return $errorResponse;
        }

        return (new GalleryResource($gallery))->response();
    }

    /**
     * Get paginated photos of a public gallery.
     * GET /api/v1/public/galleries/{slug}/photos
     */
    public function publicPhotos(Request $request, string $slug): JsonResponse
    {
        $gallery = Gallery::where('slug', $slug)->firstOrFail();

        $errorResponse = $this->verifyGalleryAccess($gallery, $request);
        if ($errorResponse) {
            return $errorResponse;
        }

        $perPage = $request->integer('per_page', 60);
        $perPage = max(1, min(100, $perPage));

        $photos = $gallery->photos()
            ->orderBy('sort_date', 'asc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->cursorPaginate($perPage);

        return response()->json([
            'data' => \App\Http\Resources\V1\PhotoResource::collection($photos->items()),
            'next_cursor' => $photos->nextCursor() ? $photos->nextCursor()->encode() : null,
            'has_more' => $photos->hasMorePages(),
        ]);
    }

    /**
     * Verify if the request has access to the given public/private gallery.
     */
    private function verifyGalleryAccess(Gallery $gallery, Request $request): ?JsonResponse
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

    /**
     * Unlock a password-protected public gallery.
     * POST /api/v1/public/galleries/{slug}/unlock
     */
    public function unlockPublic(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $gallery = Gallery::where('slug', $slug)->firstOrFail();

        if (empty($gallery->password_hash)) {
            return response()->json([
                'code' => 'NOT_PASSWORD_PROTECTED',
                'message' => 'This gallery does not require a password.',
            ], 400);
        }

        if ($gallery->expires_at && $gallery->expires_at->isPast()) {
            return response()->json([
                'code' => 'GALLERY_EXPIRED',
                'message' => 'This gallery has expired.',
            ], 403);
        }

        if (\Illuminate\Support\Facades\Hash::check($request->password, $gallery->password_hash)) {
            $token = hash_hmac('sha256', $gallery->uuid, config('app.key'));
            return response()->json([
                'token' => $token,
                'message' => 'Gallery unlocked successfully.',
            ]);
        }

        return response()->json([
            'code' => 'INVALID_PASSWORD',
            'message' => 'The password you entered is incorrect.',
        ], 401);
    }

    /**
     * Update gallery with optimistic locking version protection.
     * PATCH /api/v1/galleries/{uuid}
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();

        $this->authorize('update', $gallery);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'visibility' => ['sometimes', 'string', 'in:public,private'],
            'version' => ['required', 'integer'],
        ]);

        // Optimistic locking check
        if ((int) $validated['version'] !== $gallery->version) {
            return response()->json([
                'code' => 'CONCURRENCY_CONFLICT',
                'message' => 'The gallery was updated in another session. Please reload before saving changes.',
            ], 409);
        }

        $gallery->update([
            ...$validated,
            'version' => $gallery->version + 1,
        ]);

        return (new GalleryResource($gallery->load('stats')))->response();
    }

    /**
     * Soft-delete a gallery.
     * DELETE /api/v1/galleries/{uuid}
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();

        $this->authorize('delete', $gallery);

        $gallery->delete();

        return response()->json(null, 204);
    }

    /**
     * Record download analytics for a public gallery.
     * POST /api/v1/public/galleries/{slug}/download
     */
    public function recordDownload(Request $request, string $slug): JsonResponse
    {
        $gallery = Gallery::where('slug', $slug)->firstOrFail();

        $errorResponse = $this->verifyGalleryAccess($gallery, $request);
        if ($errorResponse) {
            return $errorResponse;
        }

        $validated = $request->validate([
            'photo_uuid' => ['nullable', 'string'],
            'email' => ['nullable', 'string', 'email'],
        ]);

        $gallery->stats()->increment('downloads_count');
        $gallery->stats()->update(['updated_at' => now()]);

        $email = isset($validated['email']) ? strtolower(trim($validated['email'])) : null;

        \Illuminate\Support\Facades\DB::table('activity_logs')->insert([
            'gallery_id' => $gallery->id,
            'event' => 'photo_downloaded',
            'properties' => json_encode([
                'photo_uuid' => $validated['photo_uuid'] ?? null,
                'email' => $email,
                'action' => 'download',
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'Download recorded successfully.',
            'downloads_count' => $gallery->stats->fresh()->downloads_count,
        ]);
    }

    /**
     * Increment or decrement favorites count for a public gallery.
     * POST /api/v1/public/galleries/{slug}/favorite
     */
    public function toggleFavorite(Request $request, string $slug): JsonResponse
    {
        $gallery = Gallery::where('slug', $slug)->firstOrFail();

        $errorResponse = $this->verifyGalleryAccess($gallery, $request);
        if ($errorResponse) {
            return $errorResponse;
        }

        $validated = $request->validate([
            'photo_uuid' => ['nullable', 'string'],
            'is_favorite' => ['required', 'boolean'],
            'email' => ['nullable', 'string', 'email'],
        ]);

        $isFavorite = $validated['is_favorite'];
        $email = isset($validated['email']) ? strtolower(trim($validated['email'])) : null;

        if ($isFavorite) {
            $gallery->stats()->increment('favorites_count');
        } else {
            // Ensure we don't decrement below 0
            if ($gallery->stats->favorites_count > 0) {
                $gallery->stats()->decrement('favorites_count');
            }
        }
        $gallery->stats()->update(['updated_at' => now()]);

        \Illuminate\Support\Facades\DB::table('activity_logs')->insert([
            'gallery_id' => $gallery->id,
            'event' => $isFavorite ? 'photo_favorited' : 'photo_unfavorited',
            'properties' => json_encode([
                'photo_uuid' => $validated['photo_uuid'] ?? null,
                'email' => $email,
                'action' => $isFavorite ? 'favorite' : 'unfavorite',
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => $isFavorite ? 'Favorite added.' : 'Favorite removed.',
            'favorites_count' => $gallery->stats->fresh()->favorites_count,
        ]);
    }
}
?>

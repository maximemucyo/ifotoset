<?php

namespace App\Http\Controllers\Web\Studio;

use App\Enums\Visibility;
use App\Http\Controllers\Controller;
use App\Mail\GalleryInvitation as GalleryInvitationMail;
use App\Models\Gallery;
use App\Models\GalleryInvitation;
use App\Models\Photo;
use App\Services\PublicUrlService;
use App\Services\RecipientEmailImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class GalleryController extends Controller
{
    /**
     * List all photographer's galleries.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = Gallery::where('user_id', $user->id)
            ->with(['coverPhoto', 'stats'])
            ->orderBy('created_at', 'desc');

        if ($request->has('visibility') && $request->visibility !== 'all') {
            $query->where('visibility', $request->visibility);
        }

        $galleries = $query->paginate(12);

        return view('studio.galleries.index', [
            'galleries' => $galleries,
        ]);
    }

    /**
     * Show form to create new gallery.
     */
    public function create(): View
    {
        return view('studio.galleries.create');
    }

    /**
     * Store a newly created gallery.
     */
    public function store(Request $request, RecipientEmailImportService $importService): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'event_date' => ['nullable', 'date'],
            'visibility' => ['required', 'string', 'in:public,private,password'],
            'password' => ['nullable', 'string', 'required_if:visibility,password'],
            'show_on_profile' => ['nullable', 'boolean'],
            'allow_photo_downloads' => ['nullable', 'boolean'],
            'allow_gallery_downloads' => ['nullable', 'boolean'],
            'invite_emails' => ['nullable', 'string'],
            'invite_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [
            'password.required_if' => 'A PIN is required when setting privacy to PIN Protected.',
        ]);

        $baseSlug = Str::slug($validated['title']);
        $slug = $baseSlug;
        $count = Gallery::where('user_id', $user->id)->where('slug', $slug)->count();
        if ($count > 0) {
            $slug = "{$baseSlug}-" . time();
        }

        $showOnProfile = ($validated['visibility'] === 'public') && $request->boolean('show_on_profile');

        $gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $user->id,
            'title' => $validated['title'],
            'slug' => $slug,
            'client_name' => $validated['client_name'] ?? null,
            'event_date' => $validated['event_date'] ?? null,
            'visibility' => $validated['visibility'],
            'show_on_profile' => $showOnProfile,
            'password_hash' => !empty($validated['password']) ? Hash::make($validated['password']) : null,
            'allow_photo_downloads' => $request->boolean('allow_photo_downloads', true),
            'allow_gallery_downloads' => $request->boolean('allow_gallery_downloads', true),
            'version' => 1,
        ]);

        // Process invitations if created as private
        if ($validated['visibility'] === 'private') {
            $emailsFromText = $importService->parseFromText($request->input('invite_emails'));
            $emailsFromFile = $request->hasFile('invite_file')
                ? $importService->parseFromFile($request->file('invite_file'))
                : [];
            $allEmails = array_values(array_unique(array_merge($emailsFromText, $emailsFromFile)));

            if (!empty($allEmails)) {
                $this->inviteRecipients($gallery, $allEmails);
            }
        }

        return redirect()->route('studio.galleries.show', $gallery->uuid)
            ->with('success', 'Gallery created successfully! You can now upload photos.');
    }

    /**
     * Manage photos inside the gallery.
     */
    public function show(Request $request, string $uuid): View
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $gallery);

        $paginated = app(\App\Queries\GalleryPhotoQuery::class)->studio($gallery, 50);
        $coverId = $gallery->cover_photo_id;

        $initialPhotosData = collect($paginated->items())->map(function (Photo $photo) use ($coverId) {
            return [
                'id' => $photo->id,
                'uuid' => $photo->uuid,
                'original_filename' => $photo->original_filename,
                'filename' => $photo->filename,
                'thumbnail_url' => $photo->getUrl('sm'),
                'medium_url' => $photo->getUrl('md'),
                'large_url' => $photo->getUrl('lg'),
                'full_url' => $photo->getUrl('xl'),
                'original_url' => $photo->getUrl(),
                'size' => $photo->size,
                'width' => $photo->width,
                'height' => $photo->height,
                'blurhash' => $photo->blurhash,
                'is_hidden' => (bool) $photo->is_hidden,
                'is_cover' => $coverId === $photo->id,
                'created_at' => $photo->created_at?->toIso8601String(),
            ];
        })->values();

        $storageService = app(\App\Services\StorageStatisticsService::class);
        $userStorage = $storageService->getStorageStats($request->user());
        $upgradePlans = \App\Models\Plan::where('slug', '!=', 'free')
            ->orderBy('monthly_price')
            ->get(['id', 'slug', 'name', 'monthly_price', 'storage_limit']);

        $billingReturnSuccess = ($request->query('billing_return') === 'success') && !$userStorage['is_free'];

        return view('studio.galleries.show', [
            'gallery' => $gallery,
            'photos' => $paginated,
            'totalPhotosCount' => $gallery->photos()->count(),
            'initialPhotosJson' => $initialPhotosData->toJson(),
            'initialNextCursor' => $paginated->nextCursor()?->encode(),
            'initialHasMore' => $paginated->hasMorePages(),
            'userStorage' => $userStorage,
            'upgradePlans' => $upgradePlans,
            'billingReturnSuccess' => $billingReturnSuccess,
        ]);
    }

    /**
     * Cursor-paginated photo endpoint for Studio gallery infinite scroll.
     */
    public function photos(Request $request, string $uuid): \Illuminate\Http\JsonResponse
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $gallery);

        $perPage = $request->integer('per_page', 50);
        $paginated = app(\App\Queries\GalleryPhotoQuery::class)->studio($gallery, $perPage);
        $coverId = $gallery->cover_photo_id;

        $data = collect($paginated->items())->map(function (Photo $photo) use ($coverId) {
            return [
                'id' => $photo->id,
                'uuid' => $photo->uuid,
                'original_filename' => $photo->original_filename,
                'filename' => $photo->filename,
                'thumbnail_url' => $photo->getUrl('sm'),
                'medium_url' => $photo->getUrl('md'),
                'large_url' => $photo->getUrl('lg'),
                'full_url' => $photo->getUrl('xl'),
                'original_url' => $photo->getUrl(),
                'size' => $photo->size,
                'width' => $photo->width,
                'height' => $photo->height,
                'blurhash' => $photo->blurhash,
                'is_hidden' => (bool) $photo->is_hidden,
                'is_cover' => $coverId === $photo->id,
                'created_at' => $photo->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $data,
            'next_cursor' => $paginated->nextCursor()?->encode(),
            'has_more' => $paginated->hasMorePages(),
        ]);
    }

    /**
     * Toggle or set the hidden status of a photo.
     */
    public function toggleHidePhoto(
        Request $request,
        string $uuid,
        string $photoUuid,
        \App\Actions\Studio\ToggleGalleryPhotoVisibilityAction $action
    ): \Illuminate\Http\JsonResponse {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $gallery);

        $photo = Photo::where('uuid', $photoUuid)->where('gallery_id', $gallery->id)->firstOrFail();
        $this->authorize('update', $photo);

        $hide = $request->has('hide') ? $request->boolean('hide') : null;
        $updatedPhoto = $action->execute($gallery, $photo, $hide);

        $gallery->refresh();

        return response()->json([
            'success' => true,
            'photo' => [
                'uuid' => $updatedPhoto->uuid,
                'is_hidden' => (bool) $updatedPhoto->is_hidden,
                'is_cover' => $gallery->cover_photo_id === $updatedPhoto->id,
            ],
            'gallery_cover_photo_id' => $gallery->cover_photo_id,
            'message' => $updatedPhoto->is_hidden ? 'Photo hidden from public gallery.' : 'Photo visible in public gallery.',
        ]);
    }

    /**
     * Soft-delete a photo from the gallery with optimistic UI support.
     */
    public function destroyPhoto(
        Request $request,
        string $uuid,
        string $photoUuid,
        \App\Actions\Studio\DeleteGalleryPhotoAction $action
    ): \Illuminate\Http\JsonResponse {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $gallery);

        $photo = Photo::where('uuid', $photoUuid)->where('gallery_id', $gallery->id)->firstOrFail();
        $this->authorize('delete', $photo);

        $action->execute($gallery, $photo);

        $gallery->refresh();

        return response()->json([
            'success' => true,
            'deleted_uuid' => $photoUuid,
            'gallery_cover_photo_id' => $gallery->cover_photo_id,
            'total_photos' => $gallery->photos()->count(),
            'message' => 'Photo deleted.',
        ]);
    }

    /**
     * Edit gallery details and privacy.
     */
    public function edit(string $uuid): View
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $gallery);

        $invitations = $gallery->invitations()->orderBy('created_at', 'desc')->get();

        return view('studio.galleries.edit', [
            'gallery' => $gallery,
            'invitations' => $invitations,
        ]);
    }

    /**
     * Update gallery metadata.
     */
    public function update(Request $request, string $uuid, RecipientEmailImportService $importService): RedirectResponse
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $gallery);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'event_date' => ['nullable', 'date'],
            'visibility' => ['required', 'string', 'in:public,private,password'],
            'password' => [
                'nullable',
                'string',
                Rule::requiredIf(fn() => $request->input('visibility') === 'password' && empty($gallery->password_hash)),
            ],
            'show_on_profile' => ['nullable', 'boolean'],
            'allow_photo_downloads' => ['nullable', 'boolean'],
            'allow_gallery_downloads' => ['nullable', 'boolean'],
            'invite_emails' => ['nullable', 'string'],
            'invite_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [
            'password.required' => 'A PIN is required when setting privacy to PIN Protected.',
        ]);

        $showOnProfile = ($validated['visibility'] === 'public') && $request->boolean('show_on_profile');

        $updateData = [
            'title' => $validated['title'],
            'client_name' => $validated['client_name'] ?? null,
            'event_date' => $validated['event_date'] ?? null,
            'visibility' => $validated['visibility'],
            'show_on_profile' => $showOnProfile,
            'allow_photo_downloads' => $request->boolean('allow_photo_downloads'),
            'allow_gallery_downloads' => $request->boolean('allow_gallery_downloads'),
        ];

        if (!empty($validated['password'])) {
            $updateData['password_hash'] = Hash::make($validated['password']);
        } elseif ($validated['visibility'] !== 'password') {
            $updateData['password_hash'] = null;
        }

        $gallery->update($updateData);

        // Process any new invitations if visibility is private
        if ($validated['visibility'] === 'private') {
            $emailsFromText = $importService->parseFromText($request->input('invite_emails'));
            $emailsFromFile = $request->hasFile('invite_file')
                ? $importService->parseFromFile($request->file('invite_file'))
                : [];
            $allEmails = array_values(array_unique(array_merge($emailsFromText, $emailsFromFile)));

            if (!empty($allEmails)) {
                $this->inviteRecipients($gallery, $allEmails);
            }
        }

        return redirect()->route('studio.galleries.show', $gallery->uuid)
            ->with('success', 'Gallery settings updated successfully.');
    }

    /**
     * Download standard CSV template for inviting gallery recipients.
     */
    public function downloadInvitationTemplate(RecipientEmailImportService $importService): Response
    {
        $csv = $importService->generateCsvTemplate();

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="gallery-invitation-template.csv"',
        ]);
    }

    /**
     * Add recipient email invitations to a private gallery.
     */
    public function addInvitations(Request $request, string $uuid, RecipientEmailImportService $importService): RedirectResponse
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $gallery);

        $request->validate([
            'invite_emails' => ['nullable', 'string'],
            'invite_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $emailsFromText = $importService->parseFromText($request->input('invite_emails'));
        $emailsFromFile = $request->hasFile('invite_file')
            ? $importService->parseFromFile($request->file('invite_file'))
            : [];
        $allEmails = array_values(array_unique(array_merge($emailsFromText, $emailsFromFile)));

        if (empty($allEmails)) {
            return back()->with('error', 'No valid email addresses were found to invite.');
        }

        $sentCount = $this->inviteRecipients($gallery, $allEmails);

        return back()->with('success', "Sent {$sentCount} " . Str::plural('invitation', $sentCount) . ' successfully.');
    }

    /**
     * Resend an invitation with token rotation.
     */
    public function resendInvitation(Request $request, string $uuid, int $id): RedirectResponse
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $gallery);

        $invitation = GalleryInvitation::where('id', $id)
            ->where('gallery_id', $gallery->id)
            ->firstOrFail();

        // Token rotation: always generate a fresh raw token and update hash
        $rawToken = bin2hex(random_bytes(32));
        $invitation->update([
            'token' => hash('sha256', $rawToken),
            'revoked_at' => null,
            'expires_at' => $gallery->expires_at,
        ]);

        $publicUrlService = app(PublicUrlService::class);
        $inviteUrl = $publicUrlService->galleryUrl($gallery->user->username, $gallery->slug) . "?invite={$rawToken}";

        Mail::to($invitation->email)->queue(
            new GalleryInvitationMail($gallery, $inviteUrl, $gallery->user->name)
        );

        return back()->with('success', "Invitation link refreshed and resent to {$invitation->email}.");
    }

    /**
     * Revoke an active invitation.
     */
    public function revokeInvitation(Request $request, string $uuid, int $id): RedirectResponse
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $gallery);

        $invitation = GalleryInvitation::where('id', $id)
            ->where('gallery_id', $gallery->id)
            ->firstOrFail();

        $invitation->update([
            'revoked_at' => now(),
        ]);

        return back()->with('success', "Access revoked for {$invitation->email}.");
    }

    /**
     * Internal helper to create or renew invitations idempotently with token rotation.
     *
     * @param Gallery $gallery
     * @param array<string> $emails
     * @return int Count of newly sent or renewed invitations
     */
    private function inviteRecipients(Gallery $gallery, array $emails): int
    {
        $publicUrlService = app(PublicUrlService::class);
        $invitationsToSend = [];

        foreach ($emails as $email) {
            $normalizedEmail = strtolower(trim($email));

            $existing = GalleryInvitation::where('gallery_id', $gallery->id)
                ->where('email', $normalizedEmail)
                ->first();

            if ($existing && $existing->isValid()) {
                // Active invitation already exists for this gallery, do not create duplicate
                continue;
            }

            $rawToken = bin2hex(random_bytes(32));
            $hashedToken = hash('sha256', $rawToken);

            if ($existing) {
                // Existing was revoked or expired: rotate token and renew
                $existing->update([
                    'token' => $hashedToken,
                    'revoked_at' => null,
                    'expires_at' => $gallery->expires_at,
                    'invited_by' => auth()->id(),
                ]);
                $invitationsToSend[] = ['invitation' => $existing, 'raw_token' => $rawToken];
            } else {
                $invitation = GalleryInvitation::create([
                    'gallery_id' => $gallery->id,
                    'email' => $normalizedEmail,
                    'token' => $hashedToken,
                    'invited_by' => auth()->id(),
                    'expires_at' => $gallery->expires_at,
                ]);
                $invitationsToSend[] = ['invitation' => $invitation, 'raw_token' => $rawToken];
            }
        }

        foreach ($invitationsToSend as $item) {
            $inviteUrl = $publicUrlService->galleryUrl($gallery->user->username, $gallery->slug) . "?invite={$item['raw_token']}";
            try {
                Mail::to($item['invitation']->email)->queue(
                    new GalleryInvitationMail($gallery, $inviteUrl, $gallery->user->name)
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to queue gallery invitation', [
                    'gallery_id' => $gallery->id,
                    'email' => $item['invitation']->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return count($invitationsToSend);
    }

    /**
     * Set a photo as the gallery cover.
     */
    public function setCover(
        Request $request,
        string $uuid,
        \App\Actions\Studio\SetGalleryCoverAction $action
    ) {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $gallery);

        $validated = $request->validate([
            'photo_id' => ['nullable', 'integer'],
            'photo_uuid' => ['nullable', 'string'],
        ]);

        $query = Photo::where('gallery_id', $gallery->id);
        if (!empty($validated['photo_uuid'])) {
            $query->where('uuid', $validated['photo_uuid']);
        } elseif (!empty($validated['photo_id'])) {
            $query->where('id', $validated['photo_id']);
        } else {
            abort(422, 'Photo identifier required.');
        }

        $photo = $query->firstOrFail();
        $this->authorize('setCover', $photo);

        $gallery = $action->execute($gallery, $photo);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'cover_photo_id' => $gallery->cover_photo_id,
                'cover_photo_uuid' => $photo->uuid,
                'message' => 'Cover photo updated.',
            ]);
        }

        return back()->with('success', 'Cover photo updated.');
    }

    /**
     * Soft-delete the gallery.
     */
    public function destroy(string $uuid): RedirectResponse
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('delete', $gallery);

        $gallery->delete();

        return redirect()->route('studio.galleries.index')
            ->with('success', 'Gallery moved to trash.');
    }
}

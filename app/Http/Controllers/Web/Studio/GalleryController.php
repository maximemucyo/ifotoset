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
use App\Support\GoogleAnalytics;
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
    /** Filters of the Galleries page, as `Gallery::scopeLifecycle()` reads them. */
    public const LIFECYCLE_FILTERS = ['drafts', 'shared', 'expiring', 'expired'];

    /**
     * List all photographer's galleries.
     */
    public function index(Request $request): View
    {
        $user = $request->account();

        $query = Gallery::where('user_id', $user->id)
            ->withLastViewedAt()
            ->with(['coverPhoto', 'stats']);

        $stage = $request->string('stage')->toString();
        if (in_array($stage, self::LIFECYCLE_FILTERS, true)) {
            $query->lifecycle($stage);
        }

        $search = trim($request->string('q')->toString());
        if ($search !== '') {
            $term = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
            $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('client_name', 'like', $term));
        }

        $sort = $request->string('sort')->toString();
        match ($sort) {
            'oldest'  => $query->orderBy('created_at', 'asc'),
            'updated' => $query->orderBy('updated_at', 'desc'),
            'title'   => $query->orderBy('title', 'asc'),
            'event'   => $query->orderByRaw('event_date IS NULL')->orderBy('event_date', 'desc'),
            default   => $query->orderBy('created_at', 'desc'),
        };

        $galleries = $query->paginate(12)->withQueryString();

        // All galleries belong to this user: avoid lazy-loading the owner for `public_url`.
        $galleries->getCollection()->each->setRelation('user', $user);

        $stageCounts = collect(self::LIFECYCLE_FILTERS)
            ->mapWithKeys(fn ($filter) => [$filter => Gallery::where('user_id', $user->id)->lifecycle($filter)->count()])
            ->all();

        return view('studio.galleries.index', [
            'galleries' => $galleries,
            'stageCounts' => $stageCounts,
            'totalCount' => Gallery::where('user_id', $user->id)->count(),
        ]);
    }

    /**
     * The galleries list with the New gallery dialog open (old links to the create page land here).
     */
    public function create(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfOverQuota($request->account())) {
            return $redirect;
        }

        return $this->index($request)->with('openNewGallery', true);
    }

    /**
     * Free-plan accounts over quota past the restrict day can't start new galleries.
     */
    private function redirectIfOverQuota(\App\Models\User $user): ?RedirectResponse
    {
        if (!$user->overQuotaStage()?->blocksNewGalleries()) {
            return null;
        }

        return redirect()->route('studio.galleries.index')
            ->with('error', 'Your account is over the Free plan storage limit. Upgrade or free up space to create new galleries.');
    }

    /**
     * Create a draft gallery from the New gallery dialog (title, date, client) and go straight to uploading.
     * Sharing settings may be sent too; clients can't open the gallery until it is shared.
     */
    public function store(Request $request, \App\Actions\Studio\CreateClient $createClient): RedirectResponse
    {
        $user = $request->account();

        if ($redirect = $this->redirectIfOverQuota($user)) {
            return $redirect;
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_uuid' => ['nullable', 'string', 'max:64'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'event_date' => ['nullable', 'date'],
            'visibility' => ['nullable', 'string', 'in:public,private,password'],
            'password' => ['nullable', 'string', 'min:6', 'max:64', 'required_if:visibility,password'],
            'show_on_profile' => ['nullable', 'boolean'],
            'download_pin_enabled' => ['nullable', 'boolean'],
            'download_pin' => ['exclude_unless:download_pin_enabled,1', 'required', 'digits:4'],
            'photo_order' => ['nullable', 'string', Rule::in(array_keys(Gallery::PHOTO_ORDERS))],
            'expires_on' => ['nullable', 'date_format:Y-m-d', $this->notBeforeToday($user)],
        ], [
            'password.required_if' => 'Choose a password for a password-protected gallery.',
            'password.min' => 'The password must be at least 6 characters.',
            'download_pin.required' => 'Enter a 4-digit download PIN, or turn the download PIN off.',
            'download_pin.digits' => 'The download PIN must be exactly 4 digits.',
        ]);

        $client = $this->resolveClient($user, $validated, $createClient);
        $visibility = $validated['visibility'] ?? 'public';

        $baseSlug = Str::slug($validated['title']);
        $slug = $baseSlug;
        $count = Gallery::where('user_id', $user->id)->where('slug', $slug)->count();
        if ($count > 0 || in_array($slug, Gallery::RESERVED_SLUGS, true)) {
            $slug = "{$baseSlug}-" . time();
        }

        $gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $user->id,
            'title' => $validated['title'],
            'slug' => $slug,
            'client_id' => $client?->id,
            'client_name' => $client?->name ?? ($validated['client_name'] ?? null),
            'event_date' => $validated['event_date'] ?? null,
            'visibility' => $visibility,
            'show_on_profile' => $visibility === 'public' && $request->boolean('show_on_profile'),
            'password_hash' => ($visibility === 'password' && !empty($validated['password'])) ? Hash::make($validated['password']) : null,
            ...Gallery::downloadAttributes($request->boolean('download_pin_enabled') ? $validated['download_pin'] : null),
            'photo_order' => $validated['photo_order'] ?? 'taken',
            'expires_at' => Gallery::expiryFromDate($validated['expires_on'] ?? null, $user),
            // A draft until the photographer presses Share
            'published_at' => null,
            'version' => 1,
        ]);

        GoogleAnalytics::track($request, $user, 'create_gallery', ['visibility' => $visibility]);

        return redirect()->route('studio.galleries.show', ['uuid' => $gallery->uuid, 'upload' => 1])
            ->with('success', 'Gallery created. Add your photos, then share it when it\'s ready.');
    }

    /**
     * A CRM client picked in the dialog, or a new one for a name that isn't in the list yet.
     */
    private function resolveClient(\App\Models\User $user, array $validated, \App\Actions\Studio\CreateClient $createClient): ?\App\Models\Client
    {
        if (!empty($validated['client_uuid'])) {
            return \App\Models\Client::where('user_id', $user->id)->where('uuid', $validated['client_uuid'])->first();
        }

        $name = trim((string) ($validated['client_name'] ?? ''));
        if ($name === '') {
            return null;
        }

        return \App\Models\Client::where('user_id', $user->id)->where('name', $name)->first()
            ?? $createClient->execute($user, ['name' => $name]);
    }

    /**
     * Share a draft: choose who can open it, then clients can reach the link.
     */
    public function publish(Request $request, string $uuid): RedirectResponse
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $gallery);

        $validated = $request->validate([
            'visibility' => ['required', 'string', 'in:public,private,password'],
            'password' => [
                'nullable', 'string', 'min:6', 'max:64',
                Rule::requiredIf(fn () => $request->input('visibility') === 'password' && empty($gallery->password_hash)),
            ],
            'expires_on' => ['nullable', 'date_format:Y-m-d', $this->notBeforeToday($gallery->user, $gallery)],
        ], [
            'password.required' => 'Choose a password for a password-protected gallery.',
            'password.min' => 'The password must be at least 6 characters.',
        ]);

        $update = [
            'visibility' => $validated['visibility'],
            'show_on_profile' => $validated['visibility'] === 'public' && $gallery->show_on_profile,
            'expires_at' => Gallery::expiryFromDate($validated['expires_on'] ?? null, $gallery->user),
            'published_at' => now(),
        ];
        if (!empty($validated['password'])) {
            $update['password_hash'] = Hash::make($validated['password']);
        } elseif ($validated['visibility'] !== 'password') {
            $update['password_hash'] = null;
        }

        $gallery->update($update);
        if ($gallery->wasChanged('expires_at')) {
            $gallery->invitations()->whereNull('revoked_at')->update(['expires_at' => $gallery->expires_at]);
        }

        return redirect()->route('studio.galleries.show', ['uuid' => $gallery->uuid, 'share' => 1])
            ->with('success', $validated['visibility'] === 'private'
                ? 'Gallery shared. Invite people from the Clients tab so they can open it.'
                : 'Gallery shared. Send the link to your client.');
    }

    /**
     * Back to draft: the link stops working until the gallery is shared again.
     */
    public function unpublish(string $uuid): RedirectResponse
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $gallery);

        $gallery->update(['published_at' => null]);

        return back()->with('success', 'Gallery is a draft again. The link stops working until you share it.');
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

        $setUuids = $gallery->sets()->pluck('uuid', 'id');
        $initialPhotosData = collect($paginated->items())->map(fn (Photo $photo) => $this->studioPhotoData($photo, $coverId, $setUuids))->values();

        $storageService = app(\App\Services\StorageStatisticsService::class);
        $userStorage = $storageService->getStorageStats($request->account());
        $upgradePlans = \App\Models\Plan::where('slug', '!=', 'free')
            ->orderBy('monthly_price')
            ->get(['id', 'slug', 'name', 'monthly_price', 'storage_limit', 'video_limit_seconds']);

        $billingReturnSuccess = ($request->query('billing_return') === 'success') && !$userStorage['is_free'];

        return view('studio.galleries.show', [
            'gallery' => $gallery,
            'photos' => $paginated,
            'totalPhotosCount' => $gallery->photos()->count(),
            'initialPhotosJson' => $initialPhotosData->toJson(JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'initialNextCursor' => $paginated->nextCursor()?->encode(),
            'initialHasMore' => $paginated->hasMorePages(),
            'userStorage' => $userStorage,
            'upgradePlans' => $upgradePlans,
            'billingReturnSuccess' => $billingReturnSuccess,
            'sets' => GallerySetController::summary($gallery),
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
        $set = $request->filled('set')
            ? \App\Models\GallerySet::where('gallery_id', $gallery->id)->where('uuid', (string) $request->query('set'))->firstOrFail()
            : null;
        $search = mb_substr((string) $request->query('q', ''), 0, 100);
        $paginated = app(\App\Queries\GalleryPhotoQuery::class)->studio($gallery, $perPage, $set, $search);
        $coverId = $gallery->cover_photo_id;
        $setUuids = $gallery->sets()->pluck('uuid', 'id');

        $data = collect($paginated->items())->map(fn (Photo $photo) => $this->studioPhotoData($photo, $coverId, $setUuids));

        return response()->json([
            'data' => $data,
            'next_cursor' => $paginated->nextCursor()?->encode(),
            'has_more' => $paginated->hasMorePages(),
        ]);
    }

    /**
     * One photo as the studio grid and lightbox read it. $setUuids maps set ids to uuids.
     */
    private function studioPhotoData(Photo $photo, ?int $coverId, \Illuminate\Support\Collection $setUuids): array
    {
        $isVideo = $photo->isVideo();

        return [
            'id' => $photo->id,
            'uuid' => $photo->uuid,
            'media_type' => $photo->media_type ?? 'photo',
            'is_video' => $isVideo,
            'duration' => $photo->duration_formatted,
            'status' => $photo->status,
            'original_filename' => $photo->original_filename,
            'filename' => $photo->filename,
            'thumbnail_url' => $photo->getThumbnailUrl('sm'),
            'medium_url' => $isVideo ? ($photo->getPosterUrl('md') ?? $photo->getUrl('md')) : $photo->getUrl('md'),
            'large_url' => $isVideo ? ($photo->getPosterUrl('lg') ?? $photo->getUrl('lg')) : $photo->getUrl('lg'),
            'full_url' => $isVideo ? ($photo->getPosterUrl('xl') ?? $photo->getUrl('xl')) : $photo->getUrl('xl'),
            'original_url' => $photo->getOriginalDownloadUrl(),
            'delivery_url' => $isVideo ? $photo->getStreamUrl() : null,
            'size' => $photo->size,
            'width' => $photo->width,
            'height' => $photo->height,
            'blurhash' => $photo->blurhash,
            'is_hidden' => (bool) $photo->is_hidden,
            'is_cover' => $coverId === $photo->id,
            'taken_label' => $photo->taken_at?->format('j M Y, H:i:s'),
            'created_at' => $photo->created_at?->toIso8601String(),
            'set' => $setUuids[$photo->set_id] ?? null,
        ];
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

        return view('studio.galleries.edit', ['gallery' => $gallery]);
    }

    /**
     * Design tab: the cover and focal point beside a live preview of the client gallery.
     */
    public function design(string $uuid): View
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $gallery);

        return view('studio.galleries.design', ['gallery' => $gallery]);
    }

    /**
     * Invite people by email and see who opened the gallery. Works for every visibility.
     */
    public function clients(string $uuid): View
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $gallery);

        return view('studio.galleries.clients', [
            'gallery' => $gallery,
            'invitations' => $gallery->invitations()->orderBy('created_at', 'desc')->get(),
        ]);
    }

    /**
     * Update gallery metadata.
     */
    /**
     * Save gallery settings. Each Settings section posts only its own fields, so anything left out stays as it is.
     */
    public function update(Request $request, string $uuid, \App\Actions\Studio\CreateClient $createClient): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $gallery);

        $visibility = $request->input('visibility', $gallery->visibility);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'client_uuid' => ['nullable', 'string', 'max:64'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'event_date' => ['nullable', 'date'],
            'visibility' => ['sometimes', 'required', 'string', 'in:public,private,password'],
            'password' => [
                'nullable',
                'string',
                'min:6',
                'max:64',
                Rule::requiredIf(fn() => $request->has('visibility') && $visibility === 'password' && empty($gallery->password_hash)),
            ],
            'password_hint' => ['nullable', 'string', 'max:100'],
            'show_on_profile' => ['nullable', 'boolean'],
            'download_pin_enabled' => ['nullable', 'boolean'],
            'download_pin' => ['exclude_unless:download_pin_enabled,1', 'required', 'digits:4'],
            'favorites_enabled' => ['nullable', 'boolean'],
            'favorites_limit' => ['nullable', 'integer', 'between:1,5000'],
            'photo_order' => ['nullable', 'string', Rule::in(array_keys(Gallery::PHOTO_ORDERS))],
            // An already-past date may be kept as is; a changed date must be today or later
            'expires_on' => ['nullable', 'date_format:Y-m-d', $this->notBeforeToday($gallery->user, $gallery)],
            'cover_photo_uuid' => ['nullable', 'string', 'max:64'],
            'cover_focal_x' => ['nullable', 'numeric', 'between:0,1'],
            'cover_focal_y' => ['nullable', 'numeric', 'between:0,1'],
            'template' => ['sometimes', 'required', 'string', Rule::in(array_keys(Gallery::TEMPLATES))],
            'grid_style' => ['sometimes', 'required', 'string', Rule::in(array_keys(Gallery::GRID_STYLES))],
            'color_mode' => ['sometimes', 'required', 'string', Rule::in(array_keys(Gallery::COLOR_MODES))],
        ], [
            'password.required' => 'Choose a password for a password-protected gallery.',
            'password.min' => 'The password must be at least 6 characters.',
            'download_pin.required' => 'Enter a 4-digit download PIN, or turn the download PIN off.',
            'download_pin.digits' => 'The download PIN must be exactly 4 digits.',
            'favorites_limit.integer' => 'Enter a number, or leave it empty for no limit.',
            'favorites_limit.between' => 'Enter a number from 1 to 5000, or leave it empty for no limit.',
        ]);

        $updateData = [];
        foreach (['template', 'grid_style', 'color_mode'] as $field) {
            if (isset($validated[$field])) {
                $updateData[$field] = $validated[$field];
            }
        }
        foreach (['title', 'event_date', 'photo_order'] as $field) {
            if ($request->has($field)) {
                $updateData[$field] = $validated[$field] ?? ($field === 'photo_order' ? $gallery->photo_order : null);
            }
        }

        if ($request->hasAny(['client_uuid', 'client_name'])) {
            // A free-text client from before clients were linked stays as it is until it's edited
            $keepsLegacyName = !$gallery->client_id && empty($validated['client_uuid'])
                && trim((string) ($validated['client_name'] ?? '')) === trim((string) $gallery->client_name);
            if (!$keepsLegacyName) {
                $client = $this->resolveClient($gallery->user, $validated, $createClient);
                $updateData['client_id'] = $client?->id;
                $updateData['client_name'] = $client?->name ?? ($validated['client_name'] ?? null);
            }
        }

        if ($request->hasAny(['visibility', 'show_on_profile'])) {
            $updateData['visibility'] = $visibility;
            $updateData['show_on_profile'] = $visibility === 'public' && $request->boolean('show_on_profile');
        }
        if (!empty($validated['password'])) {
            $updateData['password_hash'] = Hash::make($validated['password']);
        } elseif ($request->has('visibility') && $visibility !== 'password') {
            $updateData['password_hash'] = null;
        }
        if ($request->has('password_hint')) {
            $updateData['password_hint'] = $validated['password_hint'] ?? null;
        }

        if ($request->has('download_pin_enabled')) {
            $updateData += Gallery::downloadAttributes($request->boolean('download_pin_enabled') ? $validated['download_pin'] : null);
        }

        if ($request->has('favorites_enabled')) {
            $updateData['favorites_enabled'] = $request->boolean('favorites_enabled');
        }
        if ($request->has('favorites_limit')) {
            $updateData['favorites_limit'] = $validated['favorites_limit'] ?? null;
        }

        // Only a changed date is written, so re-saving keeps an expiry set elsewhere to the minute
        if ($request->has('expires_on')) {
            $expiresOn = $validated['expires_on'] ?? null;
            $currentExpiresOn = $gallery->expires_at?->copy()->setTimezone(Gallery::expiryTimezone($gallery->user))->format('Y-m-d');
            if ($expiresOn !== $currentExpiresOn) {
                $updateData['expires_at'] = Gallery::expiryFromDate($expiresOn, $gallery->user);
            }
        }

        // Cover chosen on the Design tab; the focal point below was placed on it
        if (!empty($validated['cover_photo_uuid'])) {
            $coverPhoto = Photo::where('gallery_id', $gallery->id)->where('uuid', $validated['cover_photo_uuid'])->firstOrFail();
            if ($coverPhoto->id !== $gallery->cover_photo_id) {
                $this->authorize('setCover', $coverPhoto);
                $gallery = app(\App\Actions\Studio\SetGalleryCoverAction::class)->execute($gallery, $coverPhoto);
            }
        }
        if ($request->has('cover_focal_x')) {
            $updateData['cover_focal_x'] = $validated['cover_focal_x'] ?? null;
            $updateData['cover_focal_y'] = $validated['cover_focal_y'] ?? null;
        }

        $gallery->update($updateData);

        // Invitations close with the gallery: keep active ones in step with a new expiry date
        if ($gallery->wasChanged('expires_at')) {
            $gallery->invitations()->whereNull('revoked_at')->update(['expires_at' => $gallery->expires_at]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Saved',
                'title' => $gallery->title,
                'summary' => $gallery->refresh()->settingsSummary(),
            ]);
        }

        return redirect()->route('studio.galleries.show', $gallery->uuid)
            ->with('success', 'Gallery settings updated successfully.');
    }

    private function notBeforeToday(?\App\Models\User $user, ?Gallery $gallery = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($user, $gallery) {
            $tz = Gallery::expiryTimezone($user);
            if ($gallery?->expires_at && $gallery->expires_at->copy()->setTimezone($tz)->format('Y-m-d') === $value) {
                return;
            }
            if (is_string($value) && $value < now($tz)->format('Y-m-d')) {
                $fail('Pick today or a later date, or choose Never.');
            }
        };
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
     * Invite people to a gallery by email, from the Clients tab.
     */
    public function addInvitations(Request $request, string $uuid, RecipientEmailImportService $importService): RedirectResponse
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $gallery);

        $request->validate([
            'invite_emails' => ['nullable', 'string'],
            'invite_file' => ['nullable', 'file', 'mimes:csv,tsv,txt', 'max:2048'],
        ]);

        $emailsFromText = $importService->parseFromText($request->input('invite_emails'));
        $emailsFromFile = $request->hasFile('invite_file')
            ? $importService->parseFromFile($request->file('invite_file'))
            : [];
        $allEmails = array_values(array_unique(array_merge($emailsFromText, $emailsFromFile)));

        if (empty($allEmails)) {
            return back()->withInput()->with('error', 'No valid email addresses found. Check the list and try again.');
        }

        $sentCount = $this->inviteRecipients($gallery, $allEmails);

        if ($sentCount === 0) {
            return back()->with('success', 'Everyone on that list already has an active invitation.');
        }

        return back()->with('success', "Sent {$sentCount} " . Str::plural('invitation', $sentCount) . '.');
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

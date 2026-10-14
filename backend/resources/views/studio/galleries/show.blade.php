@extends('layouts.app', ['title' => $gallery->title . ' · Studio · ifotoset'])

@section('breadcrumbs')
    <a href="{{ route('studio.galleries.index') }}" class="transition-colors hover:text-foreground">Galleries</a>
    <x-icon name="chevron-right" class="size-3.5 text-subtle" />
    <span class="truncate font-medium text-foreground">{{ $gallery->title }}</span>
@endsection

@php
    /*
     * Shared photo-card class names. The Blade cards (partials/photo-card) and the
     * cards built in JS (createPhotoCardElement) both read from this map, so the
     * state classes toggled by the manager (is-cover, is-hidden, is-selected,
     * .hidden on badges/buttons) style identically everywhere.
     */
    $gmCardClasses = [
        'card' => 'photo-card group relative flex flex-col overflow-hidden rounded-xl border border-border bg-card shadow-xs transition-[box-shadow,border-color,opacity,transform] duration-300 hover:shadow-md [&.is-cover]:border-brand/70 [&.is-cover]:ring-2 [&.is-cover]:ring-brand/25 [&.is-selected]:border-foreground/50 [&.is-selected]:ring-2 [&.is-selected]:ring-foreground/25',
        'img' => 'size-full object-cover transition duration-500 ease-out group-hover:scale-[1.03] group-[.is-hidden]:opacity-40 group-[.is-hidden]:grayscale',
        'scrim' => 'pointer-events-none absolute inset-0 bg-gradient-to-b from-black/50 via-transparent to-black/45 opacity-0 transition-opacity duration-200 group-hover:opacity-100 group-[.is-selected]:opacity-60',
        'select' => 'absolute left-2 top-2 z-20 flex size-6 items-center justify-center rounded-md border-[1.5px] border-white/85 bg-black/25 text-transparent opacity-0 shadow-sm backdrop-blur-sm transition hover:bg-black/45 focus-visible:opacity-100 group-hover:opacity-100 group-[.is-selected]:border-white group-[.is-selected]:bg-white group-[.is-selected]:text-black group-[.is-selected]:opacity-100 group-data-[selecting=true]/grid:opacity-100 [@media(hover:none)]:opacity-100',
        'actions' => 'absolute right-2 top-2 z-20 flex items-center gap-1 opacity-0 transition duration-150 focus-within:opacity-100 group-hover:opacity-100 group-data-[selecting=true]/grid:invisible [@media(hover:none)]:opacity-100',
        'actionBtn' => 'size-7 items-center justify-center rounded-md bg-black/45 text-white shadow-sm backdrop-blur-sm transition-colors hover:bg-black/70 focus-visible:outline-2 focus-visible:outline-white [&:not(.hidden)]:inline-flex',
        'badgeLight' => 'items-center gap-1 rounded-md bg-white/95 px-1.5 py-0.5 text-[10px] font-semibold text-black shadow-sm [&:not(.hidden)]:inline-flex',
        'badgeDark' => 'items-center gap-1 rounded-md bg-black/65 px-1.5 py-0.5 text-[10px] font-medium text-white backdrop-blur-sm [&:not(.hidden)]:inline-flex',
        'footer' => 'flex items-center justify-between gap-2 border-t border-border px-2.5 py-2 text-[11px] font-medium text-muted-foreground group-data-[density=compact]/grid:hidden',
    ];

    $gmIconHtml = fn (string $name, string $class, $stroke = 2) => trim(\Illuminate\Support\Facades\Blade::render(
        '<x-icon :name="$n" :class="$c" :stroke-width="$s" />', ['n' => $name, 'c' => $class, 's' => $stroke]
    ));
    $gmIcons = [
        'check' => $gmIconHtml('check', 'size-3.5', 3),
        'star' => $gmIconHtml('star', 'size-4'),
        'starSm' => $gmIconHtml('star', 'size-3 fill-current'),
        'eye' => $gmIconHtml('eye', 'size-4'),
        'eye-off' => $gmIconHtml('eye-off', 'size-4'),
        'eyeOffSm' => $gmIconHtml('eye-off', 'size-3'),
        'trash' => $gmIconHtml('trash-2', 'size-4'),
        'playSm' => $gmIconHtml('play', 'size-3 fill-current'),
        'spinner' => $gmIconHtml('loader-circle', 'size-3.5 animate-spin'),
        'alert' => $gmIconHtml('triangle-alert', 'size-4'),
    ];

    $visibilityLabel = match ($gallery->visibility) {
        'public' => 'Public',
        'password' => 'PIN protected',
        default => 'Unlisted',
    };
    $visibilityVariant = match ($gallery->visibility) {
        'public' => 'success',
        'password' => 'warning',
        default => 'neutral',
    };
    $isTakenDown = $gallery->moderation_status === 'taken_down';
    $isExpired = $gallery->expires_at && $gallery->expires_at->isPast();
    $coverThumbUrl = $gallery->cover_photo_id ? $gallery->coverPhoto?->getThumbnailUrl('sm') : null;
    $invitationsCount = $gallery->invitations()->count();
    $publicUrl = $gallery->public_url;
@endphp

@section('content')
<!-- Gallery Configuration JSON -->
<script id="gallery-config" type="application/json">
{!! json_encode([
    'galleryUuid' => $gallery->uuid,
    'csrfToken' => csrf_token(),
    'photosUrl' => route('studio.galleries.photos', $gallery->uuid),
    'coverUrl' => route('studio.galleries.cover', $gallery->uuid),
    'toggleHideUrlBase' => url('/studio/galleries/' . $gallery->uuid . '/photos'),
    'deletePhotoUrlBase' => url('/studio/galleries/' . $gallery->uuid . '/photos'),
    'initialTotalCount' => $totalPhotosCount,
    'initialNextCursor' => $initialNextCursor,
    'initialHasMore' => $initialHasMore,
    'uploadRequestUrl' => route('studio.uploads.request'),
    'uploadConfirmUrl' => route('studio.uploads.confirm'),
    'uploadAbortUrl' => route('studio.uploads.abort'),
    'videoStatusUrlBase' => url('/studio/galleries/' . $gallery->uuid . '/photos'),
    'storageStatsUrl' => route('studio.storage.stats'),
    'billingCheckoutUrl' => route('studio.billing.checkout', ['plan' => 'basic']) . '?return_to=' . urlencode(request()->getRequestUri()),
    'billingPlansUrl' => route('studio.billing.index') . '?return_to=' . urlencode(request()->getRequestUri()),
    'userStorage' => $userStorage ?? [],
    'userVideo' => [
        'has_video' => auth()->user()->hasVideoSupport(),
        'used_seconds' => auth()->user()->video_seconds_used,
        'reserved_seconds' => auth()->user()->video_seconds_reserved,
        'limit_seconds' => auth()->user()->plan?->video_limit_seconds ?? 0,
        'available_seconds' => auth()->user()->getAvailableVideoSeconds(),
        'formatted' => auth()->user()->getVideoUsageFormatted(),
    ],
    'upgradePlans' => collect($upgradePlans ?? [])->map(fn ($p) => [
        'id' => $p->id,
        'slug' => $p->slug,
        'name' => $p->name,
        'price' => number_format($p->monthly_price, 0) . ' RWF',
        'storage' => $p->storage_limit >= 1099511627776
            ? round($p->storage_limit / 1099511627776, 1) . ' TB'
            : round($p->storage_limit / 1073741824, 0) . ' GB',
        'video_limit' => $p->video_limit_seconds > 0
            ? ($p->video_limit_seconds >= 3600
                ? round($p->video_limit_seconds / 3600, 1) . ' hrs video'
                : round($p->video_limit_seconds / 60) . ' mins video')
            : 'No video',
        'checkoutUrl' => route('studio.billing.checkout', $p->slug) . '?return_to=' . urlencode(request()->getRequestUri()),
    ])->values(),
    'billingReturnSuccess' => $billingReturnSuccess ?? false,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
</script>

<!-- Initial Photos JSON Hydration Data -->
<script id="gallery-initial-photos" type="application/json">
{!! $initialPhotosJson !!}
</script>

@include('studio.galleries.partials.manager-script')

<div id="studio-gallery-manager"
     class="relative"
     x-data="studioGalleryManager()"
     @keydown.escape.window="if (!lightboxOpen && (selectMode || selectedCount > 0)) clearSelection()"
     @dragenter.window="if ($event.dataTransfer && Array.from($event.dataTransfer.types || []).includes('Files')) isDragging = true">

    {{-- ============ Header ============ --}}
    @include('studio.galleries.partials.header')

    {{-- ============ Photos tab ============ --}}
    <section class="mt-6" aria-label="Photos and videos">

        <!-- Upload Dropzone Hero (when gallery is empty) -->
        <div x-show="totalPhotos === 0"
             x-cloak
             class="group/drop relative cursor-pointer overflow-hidden rounded-2xl border-2 border-dashed border-border-strong/70 bg-card/60 px-6 py-16 text-center transition-all hover:border-foreground/30 hover:bg-card sm:py-20"
             @dragover.prevent="isDragging = true"
             @dragleave.prevent="isDragging = false"
             @drop.prevent="isDragging = false; $dispatch('open-modal', 'upload-photos-modal'); addFiles($event.dataTransfer.files)"
             @click="$dispatch('open-modal', 'upload-photos-modal')"
             :class="isDragging ? '!border-brand bg-brand/5 ring-4 ring-brand/10' : ''"
             style="{{ $photos->isEmpty() ? '' : 'display: none;' }}">
            <div class="pointer-events-none absolute inset-0 bg-dots opacity-50 mask-radial"></div>
            <div class="relative mx-auto flex max-w-md flex-col items-center">
                <div class="relative mb-5">
                    <div class="absolute inset-0 -z-10 scale-150 rounded-full bg-brand/10 blur-2xl"></div>
                    <div class="flex size-16 items-center justify-center rounded-2xl border border-border bg-card text-brand shadow-sm transition-transform duration-300 group-hover/drop:-translate-y-1">
                        <x-icon name="cloud-upload" class="size-7" />
                    </div>
                </div>
                <h2 class="text-lg font-semibold tracking-tight text-foreground">Drop photos and videos here</h2>
                <p class="mt-1.5 text-[13px] leading-relaxed text-muted-foreground">
                    Upload full-resolution images and videos. Thumbnails are generated automatically and your clients see them the moment they are ready.
                </p>
                <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
                    <button type="button" class="btn btn-primary">
                        <x-icon name="upload" /> Browse files
                    </button>
                    <span class="text-xs text-muted-foreground">or drag and drop</span>
                </div>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-[11px] text-subtle">
                    <span class="inline-flex items-center gap-1.5"><x-icon name="image" class="size-3.5" /> JPG, PNG, WebP, AVIF up to 50 MB</span>
                    <span class="inline-flex items-center gap-1.5"><x-icon name="video" class="size-3.5" /> MP4, MOV, WebM</span>
                    <span class="inline-flex items-center gap-1.5"><x-icon name="shield-check" class="size-3.5" /> Checksummed and resumable</span>
                </div>
            </div>
        </div>

        <!-- Grid toolbar -->
        <div x-show="totalPhotos > 0"
             class="mb-4 flex flex-wrap items-center justify-between gap-3"
             style="{{ $photos->isEmpty() ? 'display: none;' : '' }}">
            <div class="flex min-w-0 items-center gap-2.5">
                <h2 class="text-[15px] font-semibold text-foreground">Media</h2>
                <span class="badge badge-neutral tabular-nums" x-text="totalPhotos">{{ $totalPhotosCount }}</span>
                <span class="hidden items-center gap-1.5 text-xs text-subtle md:inline-flex">
                    <x-icon name="mouse-pointer-click" class="size-3.5" /> Drop files anywhere to upload
                </span>
            </div>
            <div class="flex items-center gap-2">
                <div class="tabs" role="tablist" aria-label="Grid density">
                    <button type="button" class="tab" role="tab" :aria-selected="density === 'comfortable'" @click="density = 'comfortable'" title="Comfortable grid" aria-label="Comfortable grid">
                        <x-icon name="layout-grid" class="size-4" />
                    </button>
                    <button type="button" class="tab" role="tab" :aria-selected="density === 'compact'" @click="density = 'compact'" title="Compact grid" aria-label="Compact grid">
                        <x-icon name="grid" class="size-4" />
                    </button>
                </div>
                <button type="button" class="btn btn-outline btn-sm"
                        :class="(selectMode || selectedCount > 0) && 'bg-muted'"
                        @click="(selectMode || selectedCount > 0) ? clearSelection() : (selectMode = true)">
                    <x-icon name="check-check" />
                    <span x-text="(selectMode || selectedCount > 0) ? 'Done' : 'Select'">Select</span>
                </button>
                <button type="button" class="btn btn-primary btn-sm" @click="$dispatch('open-modal', 'upload-photos-modal')">
                    <x-icon name="upload" /> <span class="hidden sm:inline">Upload</span>
                </button>
            </div>
        </div>

        <!-- Photos Grid (Stable DOM container) -->
        <div x-show="totalPhotos > 0"
             class="group/grid grid gap-3 sm:gap-4"
             :class="density === 'compact' ? 'grid-cols-3 !gap-2 sm:grid-cols-4 md:grid-cols-6 xl:grid-cols-8' : 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5'"
             :data-density="density"
             :data-selecting="(selectMode || selectedCount > 0) ? 'true' : 'false'"
             id="studio-photos-grid"
             style="{{ $photos->isEmpty() ? 'display: none;' : '' }}">
            @foreach($photos as $photo)
                @include('studio.galleries.partials.photo-card', ['photo' => $photo])
            @endforeach
        </div>

        <!-- Infinite Scroll Sentinel -->
        <div id="infinite-scroll-sentinel"
             x-ref="sentinel"
             class="py-8 text-center"
             x-show="hasMore">
            <div x-show="loadingPhotos" class="inline-flex items-center justify-center gap-2 text-xs text-muted-foreground">
                <x-icon name="loader-circle" class="size-4 animate-spin" />
                <span>Loading more…</span>
            </div>
            <div x-show="!loadingPhotos && hasMore" class="text-xs text-subtle">
                Scroll to load more
            </div>
        </div>
    </section>

    {{-- ============ Full-page drop overlay ============ --}}
    <div x-show="isDragging"
         x-cloak
         x-transition.opacity.duration.150ms
         class="fixed inset-0 z-[70] flex items-center justify-center bg-background/70 p-6 backdrop-blur-sm"
         @dragover.prevent="isDragging = true"
         @dragleave.self="isDragging = false"
         @drop.prevent="isDragging = false; $dispatch('open-modal', 'upload-photos-modal'); addFiles($event.dataTransfer.files)">
        <div class="pointer-events-none flex w-full max-w-lg flex-col items-center rounded-3xl border-2 border-dashed border-brand bg-card/90 px-8 py-14 text-center shadow-xl">
            <div class="mb-4 flex size-14 items-center justify-center rounded-2xl bg-brand/10 text-brand">
                <x-icon name="cloud-upload" class="size-7" />
            </div>
            <p class="text-base font-semibold text-foreground">Drop to upload to “{{ $gallery->title }}”</p>
            <p class="mt-1 text-[13px] text-muted-foreground">Photos and videos are uploaded directly and added to the grid as they finish.</p>
        </div>
    </div>

    @include('studio.galleries.partials.bulk-bar')
    @include('studio.galleries.partials.lightbox')
    @include('studio.galleries.partials.upload-dock')
    @include('studio.galleries.partials.upload-modal')

    {{-- Delete confirmation --}}
    <x-ui.modal name="confirm-delete-photos" maxWidth="md">
        <div class="flex items-start gap-4">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                <x-icon name="trash-2" class="size-5" />
            </div>
            <div class="min-w-0">
                <h3 class="text-base font-semibold text-foreground"
                    x-text="pendingDelete.length > 1 ? 'Move ' + pendingDelete.length + ' items to trash?' : 'Move this item to trash?'">Move to trash?</h3>
                <p class="mt-1.5 text-[13px] leading-relaxed text-muted-foreground">
                    Removed items disappear from the client gallery right away. You can restore them from
                    <a href="{{ route('studio.trash.index') }}" class="link">Trash</a> until it is permanently purged.
                </p>
            </div>
        </div>
        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" class="btn btn-outline" @click="pendingDelete = []; $dispatch('close-modal', 'confirm-delete-photos')">Cancel</button>
            <button type="button" class="btn btn-danger" @click="confirmPendingDelete()">
                <x-icon name="trash-2" /> Move to trash
            </button>
        </div>
    </x-ui.modal>
</div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/artplayer/dist/artplayer.js"></script>
@endpush

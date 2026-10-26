@php
    $isGranted = $accessDecision->isGranted();
    $eventDate = $gallery->event_date ? \Carbon\Carbon::parse($gallery->event_date) : null;
    $photoTotal = $isGranted ? $gallery->public_photo_count : 0;
    $allowPhotoDownloads = $gallery->photoDownloadsAllowed();
    $downloadPinRequired = $gallery->requiresDownloadPin() && ! $downloadsUnlocked;
    $iconBtn = 'inline-flex h-9 min-w-9 items-center justify-center gap-2 rounded-full px-2.5 text-[13px] font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring';
    $lbBtn = 'inline-flex size-10 items-center justify-center rounded-full text-foreground/70 transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring';
    $panel = 'w-full max-w-sm animate-scale-in max-h-[calc(100dvh-2rem)] overflow-y-auto overscroll-contain rounded-2xl border border-border bg-popover p-6 text-popover-foreground shadow-xl';
    $backdrop = 'fixed inset-0 z-[60] flex hidden items-end justify-center bg-black/50 p-4 backdrop-blur-sm sm:items-center';
@endphp

@extends('layouts.public', [
    'seo' => $seo ?? null,
    'hideNav' => true,
    'hideFooter' => false,
    'defaultTheme' => 'light',
])

@push('styles')
<style>
    @keyframes gallery-hero-in { from { transform: scale(1.08); opacity: .4; } to { transform: scale(1); opacity: 1; } }
    @keyframes gallery-cue { 0% { transform: translateY(-100%); } 60%, 100% { transform: translateY(220%); } }
    .gallery-hero-img { animation: gallery-hero-in 2.4s cubic-bezier(.16,1,.3,1) both; }
    .gallery-cue-line { animation: gallery-cue 2.2s cubic-bezier(.65,0,.35,1) infinite; }
    #gallery-header[data-state="overlay"] { color: #fff; }
    #gallery-header[data-state="overlay"] .gallery-header-sub { color: rgb(255 255 255 / .75); }
    #gallery-header[data-state="overlay"] .gallery-header-btn:hover { background: rgb(255 255 255 / .14); color: #fff; }
    @media (prefers-reduced-motion: reduce) { .gallery-hero-img, .gallery-cue-line { animation: none; } }

    /* Locking scroll for the lightbox must not change the page width (it would re-flow the masonry under it) */
    html { scrollbar-gutter: stable; }
    /* The sticky toolbar is the scroll target: land flush with it, not 5rem short (layout default) */
    html { scroll-padding-top: 0; }

    /* Lightbox: each slide reserves room for the floating chrome; the frame is the photo's exact
       aspect-fit box, so blurhash, cached thumbnail and full-res layers stack pixel-perfectly. */
    .lightbox-slide-inner {
        container-type: size;
        padding: calc(3.75rem + env(safe-area-inset-top)) 0 calc(4rem + env(safe-area-inset-bottom));
    }
    @media (min-width: 640px) { .lightbox-slide-inner { padding-inline: 5.5rem; } }
    .lightbox-frame {
        --ar: 1.5;
        aspect-ratio: var(--ar);
        width: min(100cqw, 100cqh * var(--ar));
        transform-origin: 50% 50%;
        will-change: transform;
    }
    .lightbox-chrome { transition: opacity .25s ease; }
    /* Chrome bars let taps on their empty areas through to the stage; only their buttons are interactive */
    #lightbox-modal[data-chrome="hidden"] .lightbox-chrome { opacity: 0; }
    #lightbox-modal[data-chrome="hidden"] .lightbox-chrome,
    #lightbox-modal[data-chrome="hidden"] .lightbox-chrome * { pointer-events: none; }
</style>
@endpush

@section('content')

{{-- Icons for markup rendered from JS (see resources/js/gallery/modules/icons.js) --}}
<template id="gallery-icon-sprites">
    @foreach(['heart', 'share', 'download', 'play', 'check', 'copy'] as $spriteIcon)
        <span data-icon="{{ $spriteIcon }}"><x-icon :name="$spriteIcon" /></span>
    @endforeach
</template>

{{-- Top bar: transparent over the cover, slides away once the sticky toolbar takes over --}}
<header id="gallery-header"
        data-state="{{ ($isGranted && $coverUrl) ? 'overlay' : 'plain' }}"
        class="fixed inset-x-0 top-0 z-40 text-foreground transition-[transform,color] duration-500 ease-out"
        style="transform: translateY(0);">
    <div class="flex h-16 w-full items-center justify-between gap-4 px-4 sm:px-6 lg:px-10" style="padding-top: env(safe-area-inset-top);">
        <a href="{{ $photographer->public_url }}" class="group flex min-w-0 items-center gap-2.5">
            <x-ui.avatar :name="$photographer->name" :src="$photographer->avatar_url" size="sm" class="ring-1 ring-white/20" />
            <span class="min-w-0">
                <span class="block truncate text-[13px] font-semibold tracking-tight">{{ $photographer->name }}</span>
                <span class="gallery-header-sub block truncate text-[11px] text-muted-foreground">Photography</span>
            </span>
        </a>

        <button type="button"
                x-data
                @click="$store.theme.toggle()"
                class="gallery-header-btn inline-flex size-9 items-center justify-center rounded-full transition-colors hover:bg-muted"
                aria-label="Toggle theme" title="Toggle theme">
            <x-icon name="sun" class="hidden size-[18px] dark:block" />
            <x-icon name="moon" class="block size-[18px] dark:hidden" />
        </button>
    </div>
</header>

@if(! $isGranted)
    {{-- ===================== Access gates (no cover or photo exposure) ===================== --}}
    <section class="relative flex min-h-svh items-center justify-center overflow-hidden px-5 pb-16 pt-24">
        <div class="pointer-events-none absolute inset-0 bg-dots opacity-50 mask-radial"></div>
        <div class="pointer-events-none absolute left-1/2 top-1/3 size-[520px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand/10 blur-3xl"></div>

        <div class="relative w-full max-w-sm animate-fade-up text-center">
            @if($accessDecision->requiresPassword())
                <div class="mx-auto mb-7 flex size-14 items-center justify-center rounded-full border border-border bg-card text-foreground shadow-sm">
                    <x-icon name="lock" class="size-5" />
                </div>
                <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-muted-foreground">Private gallery</p>
                <h1 class="mt-3 font-serif text-4xl leading-[1.05] tracking-normal text-foreground sm:text-5xl">{{ $gallery->title }}</h1>
                <p class="mx-auto mt-4 max-w-xs text-sm leading-relaxed text-muted-foreground">
                    {{ $photographer->name }} has protected this collection. Enter the PIN you were given to continue.
                </p>

                <div class="mt-8 text-left" x-data="{ password: '', error: '', unlocking: false }">
                    <form @submit.prevent="
                        unlocking = true;
                        error = '';
                        fetch(window.location.pathname.replace(/\/+$/, '') + '/unlock', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ password: password })
                        })
                        .then(res => res.json())
                        .then(data => {
                            unlocking = false;
                            if (data.success) {
                                window.location.reload();
                            } else {
                                error = data.message || 'The PIN you entered is incorrect.';
                            }
                        })
                        .catch(() => {
                            unlocking = false;
                            error = 'An unexpected error occurred. Please try again.';
                        })
                    " class="space-y-3">
                        <label for="gallery-pin" class="sr-only">Gallery PIN</label>
                        <input type="password"
                               id="gallery-pin"
                               x-model="password"
                               required
                               autofocus
                               autocomplete="off"
                               placeholder="Enter PIN"
                               :class="error && 'input-error'"
                               class="input h-12 rounded-xl text-center font-mono text-lg tracking-[0.4em] placeholder:font-sans placeholder:text-sm placeholder:tracking-normal">
                        <p x-show="error" x-text="error" x-cloak class="flex items-center justify-center gap-1.5 text-center text-xs font-medium text-destructive"></p>

                        <button type="submit" :disabled="unlocking" class="btn btn-primary btn-lg w-full">
                            <x-icon name="loader-circle" class="animate-spin" x-show="unlocking" x-cloak />
                            <span x-text="unlocking ? 'Verifying…' : 'Unlock gallery'">Unlock gallery</span>
                            <x-icon name="arrow-right" x-show="!unlocking" />
                        </button>
                    </form>

                    @if($passwordHint)
                        <p class="mt-5 flex items-center justify-center gap-2 text-[13px] text-muted-foreground">
                            <x-icon name="key" class="size-3.5" />
                            <span>{{ $passwordHint }}</span>
                        </p>
                    @endif
                </div>
            @else
                @php
                    if ($accessDecision->requiresInvitation()) {
                        [$gateIcon, $gateEyebrow, $gateTitle, $gateText, $gateTone] = ['mail', 'Invitation only', 'This gallery is for invited guests', 'Please open the personal invitation link that was emailed to you to view this collection.', 'text-foreground'];
                    } elseif ($accessDecision->isInvitationInvalid()) {
                        [$gateIcon, $gateEyebrow, $gateTitle, $gateText, $gateTone] = ['link', 'Invitation unavailable', 'This link is no longer valid', 'The invitation may have expired or been revoked. Contact the photographer to request a new one.', 'text-warning'];
                    } else {
                        [$gateIcon, $gateEyebrow, $gateTitle, $gateText, $gateTone] = ['ban', 'Unavailable', 'This gallery is unavailable', $accessDecision->message ?? 'This gallery is currently unavailable due to content moderation.', 'text-destructive'];
                    }
                @endphp
                <div class="mx-auto mb-7 flex size-14 items-center justify-center rounded-full border border-border bg-card shadow-sm {{ $gateTone }}">
                    <x-icon :name="$gateIcon" class="size-5" />
                </div>
                <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-muted-foreground">{{ $gateEyebrow }}</p>
                <h1 class="mt-3 font-serif text-4xl leading-[1.05] tracking-normal text-foreground sm:text-5xl">{{ $gateTitle }}</h1>
                <p class="mx-auto mt-4 max-w-xs text-sm leading-relaxed text-muted-foreground">{{ $gateText }}</p>
                <a href="{{ $photographer->public_url }}" class="btn btn-outline mt-8">
                    <x-icon name="arrow-left" /> Visit {{ $photographer->name }}
                </a>
            @endif
        </div>
    </section>
@else
    {{-- ===================== Cover hero ===================== --}}
    @if($coverUrl)
        <section id="gallery-hero" data-variant="image" class="relative flex h-svh min-h-[560px] w-full select-none flex-col items-center justify-end overflow-hidden bg-black text-white">
            <img src="{{ $coverUrl }}"
                 @if($coverSrcset) srcset="{{ $coverSrcset }}" sizes="{{ $coverSizes }}" @endif
                 alt="{{ $gallery->title }}"
                 fetchpriority="high"
                 decoding="async"
                 class="gallery-hero-img absolute inset-0 size-full object-cover object-center">
            <div class="absolute inset-0 bg-gradient-to-b from-black/45 via-black/5 to-black/65"></div>

            <div class="relative z-10 mx-auto w-full max-w-5xl px-6 pb-[18vh] text-center sm:pb-[16vh]">
                <p class="animate-fade-up text-[11px] font-medium uppercase tracking-[0.32em] text-white/80" style="animation-delay: .25s">
                    {{ $photographer->name }}
                </p>
                <h1 class="mt-5 animate-fade-up font-serif text-5xl leading-[0.98] tracking-normal text-balance drop-shadow-[0_2px_24px_rgb(0_0_0/0.25)] sm:text-7xl lg:text-8xl" style="animation-delay: .4s">
                    {{ $gallery->title }}
                </h1>
                @if($eventDate)
                    <p class="mt-5 animate-fade-up text-xs uppercase tracking-[0.28em] text-white/80" style="animation-delay: .55s">
                        {{ $eventDate->format('F j, Y') }}
                    </p>
                @endif
                <div class="mt-9 animate-fade-up" style="animation-delay: .7s">
                    <button type="button"
                            id="btn-scroll-to-gallery"
                            class="inline-flex h-11 items-center gap-2 rounded-full border border-white/50 px-7 text-[13px] font-medium uppercase tracking-[0.18em] text-white backdrop-blur-[2px] transition-all duration-300 hover:border-white hover:bg-white hover:text-black focus-visible:outline-2 focus-visible:outline-white">
                        View gallery
                    </button>
                </div>
            </div>

            <button type="button"
                    onclick="document.getElementById('gallery-action-bar')?.scrollIntoView({ behavior: 'smooth' })"
                    class="absolute bottom-6 left-1/2 z-10 flex -translate-x-1/2 flex-col items-center gap-2 text-white/70 transition-colors hover:text-white"
                    aria-label="Scroll to photos">
                <span class="text-[10px] uppercase tracking-[0.3em]">Scroll</span>
                <span class="relative block h-10 w-px overflow-hidden bg-white/25">
                    <span class="gallery-cue-line absolute inset-x-0 top-0 block h-1/2 bg-white"></span>
                </span>
            </button>
        </section>
    @else
        <section id="gallery-hero" data-variant="plain" class="relative overflow-hidden px-6 pb-16 pt-36 text-center sm:pb-24 sm:pt-44">
            <div class="pointer-events-none absolute inset-0 bg-dots opacity-40 mask-radial"></div>
            <div class="relative mx-auto max-w-4xl">
                <p class="animate-fade-up text-[11px] font-medium uppercase tracking-[0.32em] text-muted-foreground">{{ $photographer->name }}</p>
                <h1 class="mt-5 animate-fade-up font-serif text-5xl leading-[0.98] tracking-normal text-balance text-foreground sm:text-7xl lg:text-8xl" style="animation-delay: .1s">
                    {{ $gallery->title }}
                </h1>
                @if($eventDate)
                    <p class="mt-5 animate-fade-up text-xs uppercase tracking-[0.28em] text-muted-foreground" style="animation-delay: .2s">
                        {{ $eventDate->format('F j, Y') }}
                    </p>
                @endif
            </div>
        </section>
    @endif

    {{-- ===================== Sticky toolbar ===================== --}}
    <div id="gallery-action-bar" class="sticky top-0 z-30 select-none border-b border-border/70 bg-background/85 backdrop-blur-xl backdrop-saturate-150">
        <div class="flex h-16 w-full items-center justify-between gap-3 px-4 sm:px-6 lg:px-10">
            <div class="min-w-0">
                <h2 class="truncate font-serif text-xl leading-tight tracking-normal text-foreground sm:text-2xl">{{ $gallery->title }}</h2>
                <p class="truncate text-[11px] uppercase tracking-[0.16em] text-muted-foreground">
                    <span class="tabular-nums">{{ number_format($photoTotal) }}</span> {{ Str::plural('photo', $photoTotal) }}
                    @if($eventDate)<span class="hidden sm:inline"> · {{ $eventDate->format('M j, Y') }}</span>@endif
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-0.5 sm:gap-1">
                <button type="button"
                        id="btn-filter-favorites"
                        aria-label="Filter by favorites"
                        aria-pressed="false"
                        title="Show favorites"
                        class="{{ $iconBtn }} aria-pressed:bg-brand/10 aria-pressed:text-brand">
                    <x-icon name="heart" class="size-[18px]" />
                    <span class="hidden lg:inline">Favorites</span>
                    <span id="favorites-counter" class="min-w-4 text-[11px] font-semibold tabular-nums">0</span>
                </button>

                @if($gallery->galleryDownloadsAllowed() || $gallery->googlePhotosExportEnabled())
                    <button type="button"
                            id="btn-download-options"
                            aria-label="Download gallery"
                            title="Download all"
                            class="{{ $iconBtn }}">
                        <x-icon name="download" class="size-[18px]" />
                        <span class="hidden lg:inline">Download</span>
                    </button>
                @endif

                <button type="button"
                        id="btn-share-gallery"
                        aria-label="Share gallery"
                        title="Share gallery"
                        class="{{ $iconBtn }}">
                    <x-icon name="share" class="size-[18px]" />
                    <span class="hidden lg:inline">Share</span>
                </button>

                @if($photoTotal > 0)
                <button type="button"
                        id="btn-play-slideshow"
                        aria-label="Play slideshow"
                        title="Slideshow"
                        class="{{ $iconBtn }}">
                    <x-icon name="play" class="size-[18px]" />
                    <span class="hidden lg:inline">Slideshow</span>
                </button>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== Photo grid ===================== --}}
    <div class="w-full px-1 py-1 sm:px-4 sm:py-4 lg:px-10 lg:py-8"
         id="gallery-container"
         data-slug="{{ $gallery->slug }}"
         data-username="{{ $photographer->username }}"
         data-gallery-url="{{ $gallery->public_url }}"
         data-photos-url="{{ route('subdomain.gallery.photos', ['username' => $photographer->username, 'slug' => $gallery->slug]) }}"
         data-export-url="{{ route('subdomain.gallery.export', ['username' => $photographer->username, 'slug' => $gallery->slug]) }}"
         data-total-photos="{{ $photoTotal }}"
         data-next-cursor="{{ $nextCursor }}"
         data-has-more="{{ $hasMore ? 'true' : 'false' }}"
         data-allow-photo-downloads="{{ $allowPhotoDownloads ? 'true' : 'false' }}"
         data-allow-gallery-downloads="{{ $gallery->galleryDownloadsAllowed() ? 'true' : 'false' }}"
         data-allow-google-photos="{{ $gallery->googlePhotosExportEnabled() ? 'true' : 'false' }}"
         data-download-pin-required="{{ $downloadPinRequired ? 'true' : 'false' }}"
         data-download-unlock-url="{{ route('subdomain.gallery.download-unlock', ['username' => $photographer->username, 'slug' => $gallery->slug]) }}"
         @if($deepLinkedPhoto) data-deep-linked-photo="{{ json_encode($deepLinkedPhoto) }}" @endif>

        @php($photoAltFallback = $gallery->title . ' – photo by ' . $photographer->name)
        <div id="gallery-grid" class="relative w-full" data-alt-fallback="{{ $photoAltFallback }}">
            @foreach($photos as $photo)
                <x-gallery.photo-card :photo="$photo" :allow-downloads="$allowPhotoDownloads" :with-originals="$allowPhotoDownloads && ! $downloadPinRequired" :eager="! $coverUrl && $loop->index < 6" :alt-fallback="$photoAltFallback" />
            @endforeach
        </div>

        {{-- Instant pre-paint masonry (mirrors GalleryLayout.getColumnsAndGap) to avoid layout shift before the bundle loads --}}
        <script>
            (function () {
                var grid = document.getElementById('gallery-grid');
                if (!grid) return;
                var w = grid.clientWidth;
                if (!w) return;
                var cols = w < 640 ? 2 : (w < 1024 ? 3 : (w >= 1700 ? 5 : 4));
                var gap = w < 640 ? 4 : (w < 1024 ? 8 : 12);
                var colWidth = (w - (cols - 1) * gap) / cols;
                var colHeights = new Array(cols).fill(0);
                grid.querySelectorAll('.photo-card').forEach(function (card) {
                    var minCol = 0;
                    for (var i = 1; i < cols; i++) { if (colHeights[i] < colHeights[minCol]) minCol = i; }
                    var pw = parseInt(card.dataset.photoWidth || '1920', 10);
                    var ph = parseInt(card.dataset.photoHeight || '1080', 10);
                    var h = colWidth / ((pw && ph) ? (pw / ph) : 1.5);
                    var y = colHeights[minCol];
                    card.style.position = 'absolute';
                    card.style.left = Math.round(minCol * (colWidth + gap)) + 'px';
                    card.style.top = Math.round(y) + 'px';
                    card.style.width = Math.round(colWidth) + 'px';
                    card.style.height = Math.round(h) + 'px';
                    card.style.margin = '0';
                    colHeights[minCol] = y + h + gap;
                });
                var maxH = colHeights.length ? Math.max.apply(null, colHeights) : 0;
                grid.style.height = Math.max(0, maxH - gap) + 'px';
            })();
        </script>

        {{-- Favourites filter: nothing selected --}}
        <div id="gallery-empty-favorites" class="mx-auto my-16 hidden max-w-md px-4 text-center sm:my-24">
            <div class="mx-auto mb-6 flex size-14 items-center justify-center rounded-full border border-border bg-card text-muted-foreground">
                <x-icon name="heart" class="size-5" />
            </div>
            <h3 class="font-serif text-3xl tracking-normal text-foreground">No favorites yet</h3>
            <p class="mx-auto mt-3 max-w-xs text-sm leading-relaxed text-muted-foreground">
                Tap the heart on any photo to start your selection. Turn off the filter to browse the full gallery.
            </p>
        </div>

        @if(count($photos) === 0)
            <div id="gallery-empty" class="mx-auto my-16 max-w-md px-4 text-center sm:my-28">
                <div class="mx-auto mb-6 flex size-14 items-center justify-center rounded-full border border-border bg-card text-muted-foreground">
                    <x-icon name="images" class="size-5" />
                </div>
                <h3 class="font-serif text-3xl tracking-normal text-foreground">Photos are on their way</h3>
                <p class="mx-auto mt-3 max-w-xs text-sm leading-relaxed text-muted-foreground">
                    {{ $photographer->name }} is still preparing this collection. Check back soon.
                </p>
            </div>
        @endif

        {{-- Infinite scroll sentinel --}}
        <div id="gallery-sentinel" class="flex items-center justify-center py-14">
            <div id="gallery-spinner" class="flex hidden items-center gap-2.5 text-xs uppercase tracking-[0.2em] text-muted-foreground">
                <x-icon name="loader-circle" class="size-4 animate-spin" />
                <span>Loading</span>
            </div>
            <button type="button"
                    id="gallery-end"
                    onclick="(document.getElementById('gallery-action-bar') || document.getElementById('gallery-container'))?.scrollIntoView({ behavior: 'smooth' })"
                    class="group flex flex-col items-center gap-3 text-[11px] uppercase tracking-[0.24em] text-muted-foreground transition-colors hover:text-foreground {{ ($hasMore || count($photos) === 0) ? 'hidden' : '' }}"
                    aria-label="Back to top">
                <span class="flex size-10 items-center justify-center rounded-full border border-border transition-transform duration-300 group-hover:-translate-y-0.5 group-hover:border-border-strong">
                    <x-icon name="arrow-up" class="size-4" />
                </span>
                <span>Back to top</span>
            </button>
        </div>
    </div>

    {{-- ===================== Lightbox ===================== --}}
    {{-- Full-screen stage: chrome floats above the slides so a photo can fly in from (and back to) its grid card. --}}
    <div id="lightbox-modal"
         class="fixed inset-0 z-50 hidden touch-none select-none overscroll-none focus:outline-none"
         data-chrome="visible"
         role="dialog"
         aria-modal="true"
         aria-label="Photo viewer"
         tabindex="-1">

        <div id="lightbox-backdrop" class="absolute inset-0 bg-background"></div>

        <div class="lightbox-viewport absolute inset-0 overflow-hidden">
            <div id="lightbox-track" class="absolute inset-0 will-change-transform">
                @foreach([0, 1, 2] as $slotIndex)
                    <div class="lightbox-slide pointer-events-none absolute inset-0" data-slot="{{ $slotIndex }}">
                        <div class="lightbox-slide-inner absolute inset-0 flex items-center justify-center">
                            <div class="lightbox-frame relative">
                                <canvas class="lightbox-blurhash-canvas absolute inset-0 size-full"></canvas>
                                <img src="" alt="" decoding="async" class="lightbox-slide-thumb absolute inset-0 size-full object-cover">
                                <img src="" alt="" decoding="async" class="lightbox-slide-img absolute inset-0 size-full object-cover opacity-0 transition-opacity duration-500">
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <button type="button"
                id="lightbox-btn-prev"
                class="lightbox-chrome btn-lightbox-prev absolute left-3 top-1/2 z-20 hidden size-12 -translate-y-1/2 items-center justify-center rounded-full text-foreground/60 transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring sm:flex lg:left-5"
                aria-label="Previous photo">
            <x-icon name="chevron-left" class="size-7" stroke-width="1.5" />
        </button>
        <button type="button"
                id="lightbox-btn-next"
                class="lightbox-chrome btn-lightbox-next absolute right-3 top-1/2 z-20 hidden size-12 -translate-y-1/2 items-center justify-center rounded-full text-foreground/60 transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring sm:flex lg:right-5"
                aria-label="Next photo">
            <x-icon name="chevron-right" class="size-7" stroke-width="1.5" />
        </button>

        <header class="lightbox-chrome pointer-events-none absolute inset-x-0 top-0 z-30 flex [&_button]:pointer-events-auto items-center justify-between gap-2 px-2 py-2 sm:px-4"
                style="padding-top: calc(0.5rem + env(safe-area-inset-top)); padding-left: calc(0.5rem + env(safe-area-inset-left)); padding-right: calc(0.5rem + env(safe-area-inset-right));">
            <button type="button" class="btn-close-lightbox {{ $lbBtn }} w-auto gap-2 px-3 text-[13px] font-medium" aria-label="Back to gallery">
                <x-icon name="arrow-left" class="size-[18px]" />
                <span class="hidden sm:inline">Gallery</span>
            </button>

            <span id="lightbox-counter-top" class="pointer-events-none absolute left-1/2 hidden -translate-x-1/2 text-xs tabular-nums tracking-[0.16em] text-muted-foreground sm:block" aria-hidden="true"></span>

            <div class="flex items-center gap-0.5">
                <div id="lightbox-zoom-controls" class="mr-1 hidden items-center gap-0.5 border-r border-border pr-2 md:flex">
                    <button type="button" id="lightbox-btn-zoom-out" class="{{ $lbBtn }} size-9" aria-label="Zoom out" title="Zoom out">
                        <x-icon name="minus" class="size-4" />
                    </button>
                    <span id="lightbox-zoom-val" class="w-11 text-center text-xs tabular-nums text-muted-foreground">100%</span>
                    <button type="button" id="lightbox-btn-zoom-in" class="{{ $lbBtn }} size-9" aria-label="Zoom in" title="Zoom in">
                        <x-icon name="plus" class="size-4" />
                    </button>
                    <button type="button" id="lightbox-btn-zoom-reset" class="btn btn-ghost btn-xs hidden" aria-label="Reset zoom">Reset</button>
                </div>

                <button type="button" id="lightbox-btn-slideshow" class="{{ $lbBtn }}" aria-label="Play Slideshow" title="Slideshow (space)">
                    <x-icon name="play" class="icon-play size-[18px]" />
                    <x-icon name="pause" class="icon-pause hidden size-[18px]" />
                </button>
                <button type="button" id="lightbox-btn-favorite" class="{{ $lbBtn }} data-[favorited]:text-brand" aria-label="Favorite image" title="Favorite (F)">
                    <x-icon name="heart" class="size-[18px]" />
                </button>
                <button type="button" id="lightbox-btn-share" class="{{ $lbBtn }}" aria-label="Share photo" title="Share">
                    <x-icon name="share" class="size-[18px]" />
                </button>
                @if($allowPhotoDownloads)
                    <button type="button" id="lightbox-btn-download" class="{{ $lbBtn }}" aria-label="Download original photo" title="Download (D)">
                        <x-icon name="download" class="size-[18px]" />
                    </button>
                @endif
                <button type="button" class="btn-close-lightbox {{ $lbBtn }}" aria-label="Close Lightbox" title="Close (Esc)">
                    <x-icon name="x" class="size-5" />
                </button>
            </div>
        </header>

        {{-- Slideshow progress --}}
        <div class="lightbox-chrome pointer-events-none absolute inset-x-0 top-0 z-30 h-0.5" style="top: env(safe-area-inset-top);">
            <div id="lightbox-slideshow-progress" class="h-full w-full origin-left scale-x-0 bg-brand opacity-0 transition-opacity"></div>
        </div>

        <footer class="lightbox-chrome pointer-events-none absolute inset-x-0 bottom-0 z-30 px-3 py-3 [&_button]:pointer-events-auto" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
            <div class="relative mx-auto flex max-w-xl items-center justify-between sm:justify-center">
                <button type="button" id="lightbox-btn-prev-mobile" class="btn-lightbox-prev {{ $lbBtn }} sm:hidden" aria-label="Previous photo">
                    <x-icon name="chevron-left" class="size-5" />
                </button>
                <div class="flex min-w-0 flex-col items-center gap-0.5 px-2 text-center">
                    <span id="lightbox-filename" class="max-w-[55vw] truncate text-xs text-muted-foreground sm:max-w-[60vw]"></span>
                    <span id="lightbox-counter" class="text-[11px] tabular-nums tracking-[0.16em] text-subtle sm:hidden"></span>
                </div>
                <button type="button" id="lightbox-btn-next-mobile" class="btn-lightbox-next {{ $lbBtn }} sm:hidden" aria-label="Next photo">
                    <x-icon name="chevron-right" class="size-5" />
                </button>
            </div>
        </footer>
    </div>

    {{-- ===================== Dialogs (above lightbox) ===================== --}}

    {{-- 1. Save favourites: email capture --}}
    <div id="modal-save-favorites" class="{{ $backdrop }}" role="dialog" aria-modal="true" aria-labelledby="favorites-modal-title">
        <div class="{{ $panel }}">
            <div class="flex items-start justify-between gap-4">
                <div class="flex size-10 items-center justify-center rounded-full bg-brand/10 text-brand">
                    <x-icon name="heart" class="size-[18px]" />
                </div>
                <button type="button" id="btn-close-favorites-modal" class="btn btn-ghost btn-icon btn-sm -mr-2 -mt-1" aria-label="Close dialog">
                    <x-icon name="x" />
                </button>
            </div>
            <h3 id="favorites-modal-title" class="mt-4 font-serif text-2xl tracking-normal text-foreground">Save your favorites</h3>
            <p class="mt-1.5 text-[13px] leading-relaxed text-muted-foreground">
                Enter your email to keep your selection. {{ $photographer->name }} will be able to see the photos you choose.
            </p>
            <form id="form-save-favorites" class="mt-5 space-y-3">
                <label for="input-favorites-email" class="sr-only">Email address</label>
                <div class="input-group">
                    <x-icon name="mail" />
                    <input type="email" id="input-favorites-email" required placeholder="you@example.com" autocomplete="email" class="input">
                </div>
                <div class="flex gap-2 pt-1">
                    <button type="button" id="btn-cancel-favorites-modal" class="btn btn-outline flex-1">Cancel</button>
                    <button type="submit" class="btn btn-primary flex-1">Save &amp; favorite</button>
                </div>
            </form>
        </div>
    </div>

    {{-- 2. Share --}}
    <div id="modal-social-share" class="{{ $backdrop }}" role="dialog" aria-modal="true" aria-labelledby="share-modal-title">
        <div class="{{ $panel }}">
            <div class="flex items-center justify-between gap-4">
                <h3 id="share-modal-title" class="truncate text-base font-semibold text-foreground">Share Gallery</h3>
                <button type="button" id="btn-close-share-modal" class="btn btn-ghost btn-icon btn-sm -mr-2" aria-label="Close dialog">
                    <x-icon name="x" />
                </button>
            </div>

            <div class="mt-4 flex gap-2">
                <label for="share-url-input" class="sr-only">Share link</label>
                <input type="text" id="share-url-input" readonly class="input input-sm h-9 min-w-0 flex-1 bg-muted/60 font-mono text-xs text-muted-foreground">
                <button type="button" id="btn-copy-share-link" class="btn btn-primary btn-sm h-9 shrink-0">Copy link</button>
            </div>

            <div class="mt-5 grid grid-cols-3 gap-2">
                @foreach([['share-whatsapp', 'message-circle', 'WhatsApp', true], ['share-facebook', 'facebook', 'Facebook', true], ['share-email', 'mail', 'Email', false]] as [$shareId, $shareIcon, $shareLabel, $external])
                    <a id="{{ $shareId }}" href="#" @if($external) target="_blank" rel="noopener noreferrer" @endif
                       class="group flex flex-col items-center gap-2 rounded-xl border border-border px-2 py-3.5 text-muted-foreground transition-colors hover:border-border-strong hover:bg-muted hover:text-foreground">
                        <x-icon :name="$shareIcon" class="size-5" />
                        <span class="text-xs font-medium">{{ $shareLabel }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- 3. Download options --}}
    <div id="modal-download-options" class="{{ $backdrop }}" role="dialog" aria-modal="true" aria-labelledby="download-modal-title">
        <div class="{{ $panel }}">
            <div class="flex items-center justify-between gap-4">
                <h3 id="download-modal-title" class="text-base font-semibold text-foreground">Download gallery</h3>
                <button type="button" id="btn-close-download-options" class="btn btn-ghost btn-icon btn-sm -mr-2" aria-label="Close dialog">
                    <x-icon name="x" />
                </button>
            </div>
            <p class="mt-1 text-[13px] text-muted-foreground">Choose how you'd like to save this collection.</p>

            <div class="mt-5 space-y-2">
                @if($gallery->galleryDownloadsAllowed())
                    <button type="button" id="btn-choose-zip-download"
                            class="group flex w-full items-center gap-3.5 rounded-xl border border-border p-3.5 text-left transition-colors hover:border-border-strong hover:bg-muted/60">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-foreground">
                            <x-icon name="download" class="size-[18px]" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium text-foreground">Download as ZIP</span>
                            <span class="block text-xs text-muted-foreground">Full-resolution files in a single archive.</span>
                        </span>
                        <x-icon name="chevron-right" class="text-subtle transition-transform group-hover:translate-x-0.5" />
                    </button>
                @endif

                @if($gallery->googlePhotosExportEnabled())
                    <button type="button" id="btn-choose-google-sync"
                            class="group flex w-full items-center gap-3.5 rounded-xl border border-border p-3.5 text-left transition-colors hover:border-border-strong hover:bg-muted/60">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-foreground">
                            <x-icon name="images" class="size-[18px]" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium text-foreground">Save to Google Photos</span>
                            <span class="block text-xs text-muted-foreground">Import straight into your Google account.</span>
                        </span>
                        <x-icon name="chevron-right" class="text-subtle transition-transform group-hover:translate-x-0.5" />
                    </button>
                @endif
            </div>
        </div>
    </div>

    @if($gallery->googlePhotosExportEnabled())
    {{-- 4. Google Photos target --}}
    <div id="modal-google-photos-sync" class="{{ $backdrop }}" role="dialog" aria-modal="true" aria-labelledby="google-modal-title">
        <div class="{{ $panel }}">
            <div class="flex items-center justify-between gap-4">
                <h3 id="google-modal-title" class="text-base font-semibold text-foreground">Save to Google Photos</h3>
                <button type="button" id="btn-close-google-sync" class="btn btn-ghost btn-icon btn-sm -mr-2" aria-label="Close dialog">
                    <x-icon name="x" />
                </button>
            </div>
            <p class="mt-1 text-[13px] text-muted-foreground">Which photos would you like to sync?</p>

            <form id="form-google-photos-sync" class="mt-5 space-y-4">
                <div class="space-y-2">
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-border p-3.5 transition-colors hover:bg-muted/60 has-[:checked]:border-foreground/40 has-[:checked]:bg-muted/60">
                        <input type="radio" name="syncTarget" value="all" checked class="size-4">
                        <span>
                            <span class="block text-sm font-medium text-foreground">Entire gallery</span>
                            <span class="block text-xs text-muted-foreground">Every photo in this collection.</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-border p-3.5 transition-colors hover:bg-muted/60 has-[:checked]:border-foreground/40 has-[:checked]:bg-muted/60">
                        <input type="radio" name="syncTarget" value="favorites" class="size-4">
                        <span>
                            <span class="block text-sm font-medium text-foreground">My favorites only</span>
                            <span class="block text-xs text-muted-foreground">Just the photos you've hearted.</span>
                        </span>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary w-full">Continue <x-icon name="arrow-right" /></button>
            </form>
        </div>
    </div>
    @endif

    @if($downloadPinRequired)
    {{-- Download PIN --}}
    <div id="modal-download-pin" class="{{ $backdrop }}" role="dialog" aria-modal="true" aria-labelledby="download-pin-modal-title">
        <div class="{{ $panel }}">
            <div class="flex items-start justify-between gap-4">
                <div class="flex size-10 items-center justify-center rounded-full bg-muted text-foreground">
                    <x-icon name="key" class="size-[18px]" />
                </div>
                <button type="button" id="btn-close-download-pin" class="btn btn-ghost btn-icon btn-sm -mr-2 -mt-1" aria-label="Close dialog">
                    <x-icon name="x" />
                </button>
            </div>
            <h3 id="download-pin-modal-title" class="mt-4 text-base font-semibold text-foreground">Enter download PIN</h3>
            <p class="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                {{ $photographer->name }} needs a PIN before photos can be downloaded. Ask them for it if you don't have it.
            </p>
            <form id="form-download-pin" class="mt-5 space-y-3">
                <label for="download-pin-input" class="sr-only">Download PIN</label>
                <input type="password"
                       id="download-pin-input"
                       inputmode="numeric"
                       maxlength="4"
                       required
                       autocomplete="off"
                       placeholder="4-digit PIN"
                       class="input h-12 rounded-xl text-center font-mono text-lg tracking-[0.4em] placeholder:font-sans placeholder:text-sm placeholder:tracking-normal">
                <p id="download-pin-error" class="hidden text-center text-xs font-medium text-destructive"></p>
                <button type="submit" id="btn-submit-download-pin" class="btn btn-primary w-full">Unlock downloads</button>
            </form>
        </div>
    </div>
    @endif

    {{-- 5. iOS save notice --}}
    <div id="modal-ios-download-notice" class="{{ $backdrop }}" role="dialog" aria-modal="true" aria-labelledby="ios-download-modal-title">
        <div class="{{ $panel }}">
            <div class="flex items-start justify-between gap-4">
                <div class="flex size-10 items-center justify-center rounded-full bg-muted text-foreground">
                    <x-icon name="download" class="size-[18px]" />
                </div>
                <button type="button" id="btn-close-ios-download-notice" class="btn btn-ghost btn-icon btn-sm -mr-2 -mt-1" aria-label="Close dialog">
                    <x-icon name="x" />
                </button>
            </div>
            <h3 id="ios-download-modal-title" class="mt-4 text-base font-semibold text-foreground">Save to Photos</h3>
            <p class="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                Your photo will open in a new tab. <span class="font-medium text-foreground">Press and hold</span> it, then choose <span class="font-medium text-foreground">Save to Photos</span>.
            </p>
            <div class="mt-6 flex flex-col gap-2">
                <button type="button" id="btn-confirm-ios-download" class="btn btn-primary w-full">Open photo</button>
                <button type="button" id="btn-cancel-ios-download" class="btn btn-ghost w-full">Cancel</button>
            </div>
        </div>
    </div>
@endif

@endsection

@section('footer')
<footer class="mt-auto select-none border-t border-border/70">
    <div class="flex w-full flex-col items-center justify-center gap-3 px-4 py-10 text-xs text-muted-foreground sm:flex-row sm:justify-between sm:px-6 lg:px-10">
        <a href="{{ $photographer->public_url }}" class="font-serif text-lg tracking-normal text-foreground transition-opacity hover:opacity-70">{{ $photographer->name }}</a>
        @if($photographer->isFree())
            <a href="{{ app(\App\Services\PublicUrlService::class)->homeUrl(['utm_source' => 'gallery', 'utm_medium' => 'footer_logo', 'utm_campaign' => 'powered_by']) }}"
               class="inline-flex items-center gap-2 transition-opacity hover:opacity-80">
                <span>Delivered with</span>
                <x-brand.logo size="sm" />
            </a>
        @else
            <p>&copy; {{ date('Y') }} {{ $photographer->name }}. All rights reserved.</p>
        @endif
    </div>
</footer>
@endsection

@push('scripts')
    @if($isGranted)
        @vite(['resources/js/pages/gallery.js'])
    @endif
@endpush

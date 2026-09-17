@extends('layouts.public', [
    'title' => $gallery->title . ' - ' . $photographer->name . ' | ifotoset',
    'description' => "View photo collection '{$gallery->title}' by {$photographer->name}.",
    'ogImage' => $coverUrl,
    'hideNav' => true,
    'hideFooter' => false,
    'defaultTheme' => 'light',
])

@section('content')

<!-- Dedicated Dynamic Sticky Header -->
<header id="gallery-header"
        class="fixed top-0 inset-x-0 z-40 transition-all duration-300 transform bg-black/20 text-white backdrop-blur-[2px] border-b border-white/10"
        style="transform: translateY(0);">
    <div class="w-full px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
        <!-- Logo & Title -->
        <div class="flex items-center gap-3 min-w-0">
            <a href="{{ app(\App\Services\PublicUrlService::class)->homeUrl(['utm_source' => 'gallery', 'utm_medium' => 'header_logo', 'utm_campaign' => 'powered_by']) }}" class="flex items-center gap-2 shrink-0">
                <img src="{{ asset('logo.png') }}" alt="ifotoset" class="w-7 h-7 object-contain">
                <span class="text-lg font-bold tracking-tight text-primary">ifoto<span class="text-foreground">set</span></span>
            </a>
            <div class="h-4 w-px bg-current opacity-20 shrink-0"></div>
            <h1 class="font-bold text-base sm:text-lg truncate max-w-[160px] sm:max-w-md">
                {{ $gallery->title }}
            </h1>
        </div>

        <!-- Photographer info & Theme Toggle -->
        <div class="flex items-center gap-3 text-xs shrink-0">
            <a href="{{ $photographer->public_url }}"
               class="hidden sm:flex items-center gap-1.5 font-medium hover:underline opacity-90 hover:opacity-100">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span>By {{ $photographer->name }}</span>
            </a>

            @if($gallery->event_date)
                <span class="hidden md:flex items-center gap-1.5 font-medium opacity-80">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span>{{ \Carbon\Carbon::parse($gallery->event_date)->format('M d, Y') }}</span>
                </span>
            @endif

            <div class="h-4 w-px bg-current opacity-20 hidden sm:block"></div>

            <!-- Theme Toggle -->
            <button type="button"
                    onclick="toggleTheme()"
                    class="p-2 rounded-xl hover:bg-white/10 transition-colors focus:outline-none focus:ring-2 focus:ring-primary"
                    aria-label="Toggle theme">
                <svg class="w-4 h-4 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <svg class="w-4 h-4 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                </svg>
            </button>
        </div>
    </div>
</header>

<!-- Hero Section -->
@if($coverUrl)
    <section id="gallery-hero" class="relative w-full h-svh min-h-[90vh] overflow-hidden select-none flex flex-col justify-between">
        <img src="{{ $coverUrl }}"
             alt="{{ $gallery->title }}"
             fetchpriority="high"
             class="absolute inset-0 w-full h-full object-cover object-center">
        <div class="absolute inset-0 bg-black/40"></div>

        <!-- Spacer -->
        <div></div>

        <!-- Center Details -->
        <div class="relative z-10 max-w-4xl mx-auto px-6 text-center text-white space-y-3 drop-shadow-lg">
            <h2 class="text-4xl sm:text-6xl md:text-7xl font-extrabold tracking-tight leading-tight">
                {{ $gallery->title }}
            </h2>
            @if($gallery->event_date)
                <p class="text-sm sm:text-base text-white/90 font-medium">
                    {{ \Carbon\Carbon::parse($gallery->event_date)->format('F j, Y') }}
                </p>
            @endif
        </div>

        <!-- Bottom CTA -->
        <div class="relative z-10 flex flex-col items-center gap-3 pb-12">
            @if($requiresPassword)
                <button type="button"
                        onclick="document.getElementById('password-unlock-section')?.scrollIntoView({ behavior: 'smooth' })"
                        class="px-6 py-3 rounded-full bg-white text-zinc-900 font-bold text-sm shadow-xl hover:bg-white/90 hover:scale-105 active:scale-95 transition-all cursor-pointer flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <span>Enter PIN / Unlock</span>
                </button>
                <button type="button"
                        onclick="document.getElementById('password-unlock-section')?.scrollIntoView({ behavior: 'smooth' })"
                        class="p-2 rounded-full text-white hover:bg-white/10 transition-all animate-bounce"
                        aria-label="Scroll to unlock">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7-7-7" />
                    </svg>
                </button>
            @elseif($requiresInvitation)
                <button type="button"
                        onclick="document.getElementById('invitation-required-section')?.scrollIntoView({ behavior: 'smooth' })"
                        class="px-6 py-3 rounded-full bg-white text-zinc-900 font-bold text-sm shadow-xl hover:bg-white/90 hover:scale-105 active:scale-95 transition-all cursor-pointer">
                    View Details
                </button>
            @else
                <button type="button"
                        id="btn-scroll-to-gallery"
                        class="px-6 py-3 rounded-full bg-white text-zinc-900 font-bold text-sm shadow-xl hover:bg-white/90 hover:scale-105 active:scale-95 transition-all cursor-pointer">
                    View Gallery
                </button>
                <button type="button"
                        onclick="document.getElementById('gallery-action-bar')?.scrollIntoView({ behavior: 'smooth' })"
                        class="p-2 rounded-full text-white hover:bg-white/10 transition-all animate-bounce"
                        aria-label="Scroll to photos">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7-7-7" />
                    </svg>
                </button>
            @endif
        </div>
    </section>
@else
    <section id="gallery-hero" class="bg-card border-b border-border py-16 px-4 text-center">
        <div class="max-w-3xl mx-auto space-y-3">
            <h2 class="text-3xl sm:text-5xl font-extrabold text-foreground tracking-tight">
                {{ $gallery->title }}
            </h2>
            <p class="text-sm text-muted-foreground">
                By {{ $photographer->name }} @if($gallery->event_date) &bull; {{ \Carbon\Carbon::parse($gallery->event_date)->format('F j, Y') }} @endif
            </p>
        </div>
    </section>
@endif

<!-- Password Protection Screen -->
@if($requiresPassword)
<div id="password-unlock-section" class="max-w-md mx-auto my-16 px-4" x-data="{ password: '', error: '', unlocking: false }">
    <div class="bg-card border border-border rounded-2xl p-8 text-center shadow-xl">
        <div class="w-14 h-14 rounded-2xl bg-primary/10 text-primary mx-auto flex items-center justify-center font-bold text-2xl mb-4">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
        </div>
        <h2 class="text-2xl font-bold text-foreground">PIN / Password Protected Gallery</h2>
        <p class="text-xs text-muted-foreground mt-2 mb-4 leading-relaxed">
            This collection is protected. Please enter the PIN or password provided by the photographer to unlock.
        </p>

        @if($passwordHint)
            <div class="p-3 mb-5 rounded-xl bg-secondary/50 border border-border text-xs text-primary font-medium">
                Hint: {{ $passwordHint }}
            </div>
        @endif

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
                    error = data.message || 'Incorrect PIN or password.';
                }
            })
            .catch(() => {
                unlocking = false;
                error = 'An unexpected error occurred. Please try again.';
            })
        " class="space-y-4">
            <div>
                <input type="password"
                       x-model="password"
                       required
                       autofocus
                       placeholder="Enter PIN / password..."
                       class="block w-full rounded-xl border border-border bg-input px-4 py-3 text-foreground text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary text-center">
                <p x-show="error" x-text="error" class="text-xs text-destructive mt-2" style="display: none;"></p>
            </div>

            <button type="submit"
                    :disabled="unlocking"
                    class="w-full py-3 px-4 rounded-xl bg-primary hover:bg-primary/90 text-primary-foreground font-semibold text-sm shadow transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-60">
                <span x-show="unlocking" class="inline-block w-4 h-4 border-2 border-primary-foreground border-t-transparent rounded-full animate-spin"></span>
                <span x-text="unlocking ? 'Verifying...' : 'Unlock Collection'">Unlock Collection</span>
            </button>
        </form>
    </div>
</div>
@elseif($requiresInvitation)
<div id="invitation-required-section" class="max-w-md mx-auto my-16 px-4 text-center space-y-4">
    <div class="w-14 h-14 rounded-none bg-amber-500/10 text-amber-500 mx-auto flex items-center justify-center font-bold text-2xl">
        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
    </div>
    <h2 class="text-2xl font-bold text-foreground">Invitation Required</h2>
    <p class="text-xs text-muted-foreground leading-relaxed">
        This gallery is private and restricted to invited guests. Please use the unique link sent to your email.
    </p>
</div>
@else

<!-- Gallery Action Bar (Fixed below banner/big image, non-floating) -->
<div id="gallery-action-bar" class="border-b border-border bg-card select-none">
    <div class="w-full px-4 sm:px-6 lg:px-8 py-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- Title & Metadata -->
        <div class="space-y-1">
            <h2 class="text-xl sm:text-2xl font-extrabold tracking-tight text-foreground uppercase">
                {{ $gallery->title }}
            </h2>
            <div class="flex flex-wrap items-center gap-x-2 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                <span>By {{ $photographer->name }}</span>
                @if($gallery->event_date)
                    <span>&bull;</span>
                    <span>{{ \Carbon\Carbon::parse($gallery->event_date)->format('M d, Y') }}</span>
                @endif
                <span>&bull;</span>
                <span>{{ $gallery->public_photo_count }} {{ Str::plural('Photo', $gallery->public_photo_count) }}</span>
            </div>
        </div>

        <!-- Action Buttons (Sharp edges, matching Next.js) -->
        <div class="flex items-center gap-2">
            <!-- Favorites Filter Toggle -->
            <button type="button"
                    id="btn-filter-favorites"
                    aria-label="Filter by favorites"
                    aria-pressed="false"
                    title="Filter by favorites"
                    class="p-3 rounded-none border border-border bg-secondary/40 hover:bg-secondary text-foreground/80 hover:text-foreground font-semibold text-xs transition-all flex items-center justify-center gap-1.5 cursor-pointer min-w-[44px] min-h-[44px]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                </svg>
                <span class="hidden sm:inline">Favorites</span>
                <span id="favorites-counter" class="text-[11px] font-bold opacity-70">0</span>
            </button>

            <!-- Download Gallery Options -->
            @if($gallery->allow_gallery_downloads || $gallery->allow_google_photos)
                <button type="button"
                        id="btn-download-options"
                        aria-label="Download gallery"
                        title="Download options"
                        class="p-3 rounded-none border border-border bg-secondary/40 hover:bg-secondary text-foreground/80 hover:text-foreground font-semibold text-xs transition-all flex items-center justify-center gap-1.5 cursor-pointer min-w-[44px] min-h-[44px]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    <span class="hidden sm:inline">Download</span>
                </button>
            @endif

            <!-- Share Gallery -->
            <button type="button"
                    id="btn-share-gallery"
                    aria-label="Share gallery"
                    title="Share this gallery"
                    class="p-3 rounded-none border border-border bg-secondary/40 hover:bg-secondary text-foreground/80 hover:text-foreground font-semibold text-xs transition-all flex items-center justify-center gap-1.5 cursor-pointer min-w-[44px] min-h-[44px]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                </svg>
                <span class="hidden sm:inline">Share</span>
            </button>

            <!-- Play Slideshow -->
            <button type="button"
                    id="btn-play-slideshow"
                    aria-label="Play slideshow"
                    title="Start slideshow"
                    class="p-3 rounded-none border border-border bg-secondary/40 hover:bg-secondary text-foreground/80 hover:text-foreground font-semibold text-xs transition-all flex items-center justify-center gap-1.5 cursor-pointer min-w-[44px] min-h-[44px]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="hidden sm:inline">Slideshow</span>
            </button>
        </div>
    </div>
</div>

<!-- Photo Gallery Grid -->
<div class="w-full px-1.5 sm:px-3 md:px-4 lg:px-6 py-6 sm:py-8"
     id="gallery-container"
     data-slug="{{ $gallery->slug }}"
     data-username="{{ $photographer->username }}"
     data-gallery-url="{{ $gallery->public_url }}"
     data-photos-url="{{ route('subdomain.gallery.photos', ['username' => $photographer->username, 'slug' => $gallery->slug]) }}"
     data-export-url="{{ route('subdomain.gallery.export', ['username' => $photographer->username, 'slug' => $gallery->slug]) }}"
     data-total-photos="{{ $gallery->public_photo_count }}"
     data-next-cursor="{{ $nextCursor }}"
     data-has-more="{{ $hasMore ? 'true' : 'false' }}"
     data-allow-photo-downloads="{{ $gallery->allow_photo_downloads ? 'true' : 'false' }}"
     data-allow-gallery-downloads="{{ $gallery->allow_gallery_downloads ? 'true' : 'false' }}"
     data-allow-google-photos="{{ $gallery->allow_google_photos ? 'true' : 'false' }}"
     @if($deepLinkedPhoto) data-deep-linked-photo="{{ json_encode($deepLinkedPhoto) }}" @endif>

    <!-- Photo Cards Masonry Grid -->
    <div id="gallery-grid" class="relative w-full">
        @foreach($photos as $photo)
            <x-gallery.photo-card :photo="$photo" />
        @endforeach
    </div>

    <!-- Instant Pre-paint Masonry Layout (zero layout shift before JS bundle loads) -->
    <script>
        (function() {
            var grid = document.getElementById('gallery-grid');
            if (!grid) return;
            var w = grid.clientWidth;
            if (!w) return;
            var cols = w < 640 ? 2 : (w < 768 ? 3 : (w < 1024 ? 4 : (w >= 1800 ? 6 : 5)));
            var gap = w < 768 ? 12 : 6;
            var colWidth = (w - (cols - 1) * gap) / cols;
            var colHeights = new Array(cols).fill(0);
            var cards = grid.querySelectorAll('.photo-card');
            cards.forEach(function(card) {
                var minCol = 0;
                for (var i = 1; i < cols; i++) {
                    if (colHeights[i] < colHeights[minCol]) minCol = i;
                }
                var pw = parseInt(card.dataset.photoWidth || '1920', 10);
                var ph = parseInt(card.dataset.photoHeight || '1080', 10);
                var aspect = (pw && ph) ? (pw / ph) : 1.5;
                var h = colWidth / aspect;
                var x = minCol * (colWidth + gap);
                var y = colHeights[minCol];
                card.style.position = 'absolute';
                card.style.left = Math.round(x) + 'px';
                card.style.top = Math.round(y) + 'px';
                card.style.width = Math.round(colWidth) + 'px';
                card.style.height = Math.round(h) + 'px';
                card.style.margin = '0';
                colHeights[minCol] = y + h + gap;
            });
            var maxH = colHeights.length > 0 ? Math.max.apply(null, colHeights) : 0;
            grid.style.height = Math.max(0, maxH - gap) + 'px';
        })();
    </script>

    <!-- Empty Favorites State -->
    <div id="gallery-empty-favorites" class="hidden my-16 p-12 text-center bg-card border border-border rounded-2xl max-w-md mx-auto space-y-3">
        <div class="w-14 h-14 rounded-2xl bg-rose-500/10 text-rose-500 mx-auto flex items-center justify-center">
            <svg class="w-7 h-7 fill-current" viewBox="0 0 24 24">
                <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
            </svg>
        </div>
        <h3 class="text-lg font-bold text-foreground">No favorites selected</h3>
        <p class="text-xs text-muted-foreground leading-relaxed">
            You haven't marked any photos as favorites yet. Turn off the filter and click the heart icon on any photo to build your personal selection.
        </p>
    </div>

    <!-- Empty Gallery State -->
    @if(count($photos) === 0)
    <div id="gallery-empty" class="my-16 p-12 text-center bg-card border border-border rounded-2xl max-w-md mx-auto space-y-3">
        <div class="w-14 h-14 rounded-2xl bg-secondary text-muted-foreground mx-auto flex items-center justify-center">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
        </div>
        <h3 class="text-lg font-bold text-foreground">No photos yet</h3>
        <p class="text-xs text-muted-foreground leading-relaxed">
            This collection is currently empty. Check back soon!
        </p>
    </div>
    @endif

    <!-- Infinite Scroll Sentinel -->
    <div id="gallery-sentinel" class="py-12 flex justify-center items-center">
        <div id="gallery-spinner" class="text-xs text-muted-foreground flex items-center gap-2" style="{{ $hasMore ? '' : 'display: none;' }}">
            <span class="inline-block w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>
            <span>Loading more photos...</span>
        </div>
        <button type="button"
                id="gallery-end"
                onclick="(document.getElementById('gallery-action-bar') || document.getElementById('gallery-container'))?.scrollIntoView({ behavior: 'smooth' })"
                class="inline-flex items-center gap-2 px-6 py-3 rounded-none border border-border bg-secondary/40 hover:bg-secondary text-foreground/80 hover:text-foreground text-xs font-semibold uppercase tracking-wider transition-all cursor-pointer hover:-translate-y-0.5 shadow-sm active:translate-y-0 focus:outline-none focus:ring-2 focus:ring-primary {{ ($hasMore || count($photos) === 0) ? 'hidden' : '' }}"
                aria-label="Back to top">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
            </svg>
            <span>Back to top</span>
        </button>
    </div>
</div>


<!-- ========================================================================= -->
<!-- FULLSCREEN LIGHTBOX -->
<!-- ========================================================================= -->
<div id="lightbox-modal"
     class="fixed inset-0 z-50 bg-background/98 flex flex-col justify-between select-none touch-none hidden focus:outline-none"
     role="dialog"
     aria-modal="true"
     tabindex="-1">

    <!-- Top Header Bar -->
    <header class="w-full px-4 py-3 sm:px-6 flex items-center justify-between z-10 shrink-0 bg-background/80 backdrop-blur-sm border-b border-border/40"
            style="padding-top: calc(0.75rem + env(safe-area-inset-top)); padding-left: calc(1rem + env(safe-area-inset-left)); padding-right: calc(1rem + env(safe-area-inset-right));">
        <button type="button"
                class="btn-close-lightbox flex items-center gap-2 text-foreground/80 hover:text-foreground hover:bg-secondary/40 min-h-[44px] px-3.5 py-2 rounded-none font-medium text-xs sm:text-sm transition-colors cursor-pointer"
                aria-label="Back to Gallery">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            <span class="hidden sm:inline">Gallery</span>
        </button>

        <div class="flex items-center gap-1 sm:gap-1.5">
            <!-- Slideshow Button -->
            <button type="button"
                    id="lightbox-btn-slideshow"
                    class="min-w-[44px] min-h-[44px] flex items-center justify-center rounded-none text-foreground/80 hover:text-foreground hover:bg-secondary/40 active:bg-secondary/60 transition-colors"
                    aria-label="Play Slideshow">
                <svg class="icon-play w-5 h-5 fill-current" viewBox="0 0 24 24">
                    <path d="M8 5v14l11-7z" />
                </svg>
                <svg class="icon-pause w-5 h-5 hidden fill-current" viewBox="0 0 24 24">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.75 5.25a.75.75 0 01.75.75v12a.75.75 0 01-1.5 0v-12a.75.75 0 01.75-.75zm10.5 0a.75.75 0 01.75.75v12a.75.75 0 01-1.5 0v-12a.75.75 0 01.75-.75z" />
                </svg>
            </button>

            <!-- Favorite Button -->
            <button type="button"
                    id="lightbox-btn-favorite"
                    class="min-w-[44px] min-h-[44px] flex items-center justify-center rounded-none text-foreground/80 hover:text-foreground hover:bg-secondary/40 active:bg-secondary/60 transition-colors"
                    aria-label="Favorite image">
                <svg class="w-5 h-5 fill-none stroke-current stroke-2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                </svg>
            </button>

            <!-- Share Button -->
            <button type="button"
                    id="lightbox-btn-share"
                    class="min-w-[44px] min-h-[44px] flex items-center justify-center rounded-none text-foreground/80 hover:text-foreground hover:bg-secondary/40 active:bg-secondary/60 transition-colors"
                    aria-label="Share photo">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                </svg>
            </button>

            <!-- Download Button -->
            <button type="button"
                    id="lightbox-btn-download"
                    class="min-w-[44px] min-h-[44px] flex items-center justify-center rounded-none text-foreground/80 hover:text-foreground hover:bg-secondary/40 active:bg-secondary/60 transition-colors"
                    aria-label="Download original photo">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
            </button>

            <!-- Zoom Controls -->
            <div class="hidden md:flex items-center gap-1 border-l border-border/60 pl-2 ml-1">
                <button type="button" id="lightbox-btn-zoom-out" class="min-w-[36px] min-h-[36px] flex items-center justify-center rounded-none text-muted-foreground hover:text-foreground hover:bg-secondary/40" aria-label="Zoom out">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                    </svg>
                </button>
                <span id="lightbox-zoom-val" class="text-xs font-semibold text-muted-foreground w-10 text-center font-mono">100%</span>
                <button type="button" id="lightbox-btn-zoom-in" class="min-w-[36px] min-h-[36px] flex items-center justify-center rounded-none text-muted-foreground hover:text-foreground hover:bg-secondary/40" aria-label="Zoom in">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </button>
                <button type="button" id="lightbox-btn-zoom-reset" class="px-2 py-1 rounded-none bg-secondary text-[11px] font-bold text-foreground hover:bg-muted hidden" aria-label="Reset zoom">
                    Reset
                </button>
            </div>

            <!-- Close Button -->
            <button type="button"
                    class="btn-close-lightbox min-w-[44px] min-h-[44px] flex items-center justify-center rounded-none text-muted-foreground hover:text-foreground hover:bg-secondary/40 transition-colors ml-1"
                    aria-label="Close Lightbox">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </header>

    <!-- Main Viewport -->
    <div class="lightbox-viewport relative flex-1 min-h-0 w-full flex items-center justify-center overflow-hidden px-0">
        <!-- Prev Chevron (Desktop only) -->
        <button type="button"
                id="lightbox-btn-prev"
                class="btn-lightbox-prev hidden sm:flex absolute left-3 sm:left-6 p-3 rounded-none bg-black/20 hover:bg-black/40 text-white backdrop-blur-sm transition-all z-20 focus:outline-none focus:ring-2 focus:ring-primary cursor-pointer items-center justify-center"
                aria-label="Previous photo">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
            </svg>
        </button>

        <!-- Sliding Track containing 3 Slide Slots (prev, current, next) -->
        <div id="lightbox-track" class="w-full h-full relative flex items-center justify-center overflow-hidden select-none will-change-transform">
            <!-- Slot 0 -->
            <div class="lightbox-slide absolute inset-0 w-full h-full flex items-center justify-center p-0 pointer-events-none" data-slot="0">
                <div class="lightbox-slide-inner relative w-full h-full flex items-center justify-center min-h-0 min-w-0">
                    <canvas class="lightbox-blurhash-canvas absolute w-full sm:w-auto max-w-full max-h-full object-contain pointer-events-none transition-opacity duration-300"></canvas>
                    <img src="" alt="" decoding="async" class="lightbox-slide-img w-full sm:w-auto max-w-full max-h-full object-contain rounded-none shadow-2xl select-none pointer-events-none opacity-0 transition-opacity duration-200">
                </div>
            </div>
            <!-- Slot 1 -->
            <div class="lightbox-slide absolute inset-0 w-full h-full flex items-center justify-center p-0 pointer-events-none" data-slot="1">
                <div class="lightbox-slide-inner relative w-full h-full flex items-center justify-center min-h-0 min-w-0">
                    <canvas class="lightbox-blurhash-canvas absolute w-full sm:w-auto max-w-full max-h-full object-contain pointer-events-none transition-opacity duration-300"></canvas>
                    <img src="" alt="" decoding="async" class="lightbox-slide-img w-full sm:w-auto max-w-full max-h-full object-contain rounded-none shadow-2xl select-none pointer-events-none opacity-0 transition-opacity duration-200">
                </div>
            </div>
            <!-- Slot 2 -->
            <div class="lightbox-slide absolute inset-0 w-full h-full flex items-center justify-center p-0 pointer-events-none" data-slot="2">
                <div class="lightbox-slide-inner relative w-full h-full flex items-center justify-center min-h-0 min-w-0">
                    <canvas class="lightbox-blurhash-canvas absolute w-full sm:w-auto max-w-full max-h-full object-contain pointer-events-none transition-opacity duration-300"></canvas>
                    <img src="" alt="" decoding="async" class="lightbox-slide-img w-full sm:w-auto max-w-full max-h-full object-contain rounded-none shadow-2xl select-none pointer-events-none opacity-0 transition-opacity duration-200">
                </div>
            </div>
        </div>

        <!-- Next Chevron (Desktop only) -->
        <button type="button"
                id="lightbox-btn-next"
                class="btn-lightbox-next hidden sm:flex absolute right-3 sm:right-6 p-3 rounded-none bg-black/20 hover:bg-black/40 text-white backdrop-blur-sm transition-all z-20 focus:outline-none focus:ring-2 focus:ring-primary cursor-pointer items-center justify-center"
                aria-label="Next photo">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
            </svg>
        </button>
    </div>

    <!-- Bottom Footer Bar -->
    <footer class="w-full py-2.5 sm:py-3 px-4 z-10 shrink-0 bg-background/80 backdrop-blur-sm border-t border-border/40">
        <div class="flex items-center justify-between sm:justify-center relative max-w-xl mx-auto">
            <!-- Mobile Prev Button (Thumb-accessible, does NOT obstruct image) -->
            <button type="button"
                    id="lightbox-btn-prev-mobile"
                    class="btn-lightbox-prev sm:hidden p-2 rounded-none text-foreground/80 hover:text-foreground hover:bg-secondary/40 active:scale-90 transition-all cursor-pointer flex items-center justify-center"
                    aria-label="Previous photo">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            <!-- File Details & Counter -->
            <div class="flex flex-col items-center gap-0.5 px-2 min-w-0 text-center">
                <span id="lightbox-filename" class="text-xs font-semibold text-foreground/90 truncate max-w-[55vw] sm:max-w-[85vw]"></span>
                <span id="lightbox-counter" class="text-[11px] font-mono text-muted-foreground"></span>
            </div>

            <!-- Mobile Next Button (Thumb-accessible, does NOT obstruct image) -->
            <button type="button"
                    id="lightbox-btn-next-mobile"
                    class="btn-lightbox-next sm:hidden p-2 rounded-none text-foreground/80 hover:text-foreground hover:bg-secondary/40 active:scale-90 transition-all cursor-pointer flex items-center justify-center"
                    aria-label="Next photo">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>
    </footer>
</div>

<!-- ========================================================================= -->
<!-- MODALS (Rendered above lightbox at z-[60]) -->
<!-- ========================================================================= -->

<!-- 1. Save Your Favorites Email Modal -->
<div id="modal-save-favorites" class="fixed inset-0 z-[60] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 hidden" role="dialog" aria-modal="true">
    <div class="bg-card border border-border rounded-2xl p-6 max-w-sm w-full shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-200">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-bold text-foreground flex items-center gap-2">
                <svg class="w-5 h-5 text-rose-500 fill-current" viewBox="0 0 24 24">
                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                </svg>
                <span>Save Your Favorites</span>
            </h3>
            <button type="button" id="btn-close-favorites-modal" class="p-1 rounded-lg text-muted-foreground hover:text-foreground hover:bg-secondary transition-colors" aria-label="Close dialog">&times;</button>
        </div>
        <p class="text-xs text-muted-foreground leading-relaxed">
            Please enter your email to save your personal selection. This notifies the photographer of the photos you've chosen.
        </p>
        <form id="form-save-favorites" class="space-y-3">
            <input type="email"
                   id="input-favorites-email"
                   required
                   placeholder="your.email@example.com"
                   class="w-full bg-input border border-border rounded-xl px-4 py-2.5 text-sm text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
            <div class="flex gap-2">
                <button type="button" id="btn-cancel-favorites-modal" class="flex-1 py-2.5 rounded-xl bg-secondary text-foreground text-xs font-semibold hover:bg-secondary/80 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="flex-1 py-2.5 rounded-xl bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/90 transition-colors shadow">
                    Save & Favorite
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 2. Social Share Modal -->
<div id="modal-social-share" class="fixed inset-0 z-[60] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 hidden" role="dialog" aria-modal="true">
    <div class="bg-card border border-border rounded-2xl p-6 max-w-sm w-full shadow-2xl space-y-5 animate-in fade-in zoom-in-95 duration-200">
        <div class="flex items-center justify-between">
            <h3 id="share-modal-title" class="text-lg font-bold text-foreground">Share Gallery</h3>
            <button type="button" id="btn-close-share-modal" class="p-1 rounded-lg text-muted-foreground hover:text-foreground hover:bg-secondary transition-colors" aria-label="Close dialog">&times;</button>
        </div>

        <div class="flex gap-2">
            <input type="text"
                   id="share-url-input"
                   readonly
                   class="flex-1 min-w-0 px-3.5 py-2 bg-secondary/40 border border-border rounded-xl text-xs text-muted-foreground focus:outline-none select-all font-mono">
            <button type="button"
                    id="btn-copy-share-link"
                    class="px-4 py-2 bg-primary text-primary-foreground font-semibold rounded-xl text-xs hover:bg-primary/90 transition-all shrink-0">
                Copy Link
            </button>
        </div>

        <div class="grid grid-cols-4 gap-2 pt-2 text-center">
            <!-- WhatsApp -->
            <a id="share-whatsapp" href="#" target="_blank" rel="noopener noreferrer"
               class="flex flex-col items-center gap-1.5 p-2 rounded-xl hover:bg-secondary transition-colors text-muted-foreground hover:text-foreground">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
                    <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                        <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01c6.612.001 11.94 5.34 11.94 11.95 0 6.611-5.328 11.95-11.94 11.95a11.94 11.94 0 01-11.94-11.95c0-2.096.547-4.14 1.588-5.946L.057 24zm6.59-4.846c1.6.95 3.197 1.451 4.887 1.453 5.485 0 9.948-4.463 9.952-9.953.002-2.66-1.025-5.161-2.894-7.03C16.626 1.8 14.127.777 11.468.777c-5.482 0-9.94 4.466-9.944 9.954-.002 1.79.49 3.54 1.428 5.09L1.92 22.18l6.727-1.761c1.586.865 3.323 1.32 5.074 1.322z" />
                    </svg>
                </div>
                <span class="text-[10px] font-medium">WhatsApp</span>
            </a>

            <!-- Facebook -->
            <a id="share-facebook" href="#" target="_blank" rel="noopener noreferrer"
               class="flex flex-col items-center gap-1.5 p-2 rounded-xl hover:bg-secondary transition-colors text-muted-foreground hover:text-foreground">
                <div class="w-10 h-10 rounded-xl bg-blue-600/10 text-blue-600 flex items-center justify-center">
                    <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                    </svg>
                </div>
                <span class="text-[10px] font-medium">Facebook</span>
            </a>

            <!-- Email -->
            <a id="share-email" href="#"
               class="flex flex-col items-center gap-1.5 p-2 rounded-xl hover:bg-secondary transition-colors text-muted-foreground hover:text-foreground">
                <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-500 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <span class="text-[10px] font-medium">Email</span>
            </a>
        </div>
    </div>
</div>

<!-- 3. Download Options Modal -->
<div id="modal-download-options" class="fixed inset-0 z-[60] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 hidden" role="dialog" aria-modal="true">
    <div class="bg-card border border-border rounded-2xl p-6 max-w-sm w-full shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-200">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-bold text-foreground flex items-center gap-2">
                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                <span>Download Options</span>
            </h3>
            <button type="button" id="btn-close-download-options" class="p-1 rounded-lg text-muted-foreground hover:text-foreground hover:bg-secondary transition-colors" aria-label="Close dialog">&times;</button>
        </div>
        <p class="text-xs text-muted-foreground leading-relaxed">
            Choose how you would like to export or save this collection:
        </p>

        <div class="space-y-2.5">
            @if($gallery->allow_gallery_downloads)
            <button type="button" id="btn-choose-zip-download"
                    class="w-full text-left p-3.5 rounded-xl border border-border hover:border-primary/50 bg-secondary/20 hover:bg-secondary/40 transition-all flex items-center gap-3 cursor-pointer">
                <div class="p-2.5 rounded-xl bg-primary/10 text-primary shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-foreground">Download ZIP Archive</h4>
                    <p class="text-[11px] text-muted-foreground">Pack photos into a single downloadable file.</p>
                </div>
            </button>
            @endif

            @if($gallery->allow_google_photos)
            <button type="button" id="btn-choose-google-sync"
                    class="w-full text-left p-3.5 rounded-xl border border-border hover:border-primary/50 bg-secondary/20 hover:bg-secondary/40 transition-all flex items-center gap-3 cursor-pointer">
                <div class="p-2.5 rounded-xl bg-primary/10 text-primary shrink-0">
                    <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2m-1 12H6v-2h12v2m0-4H6V9h12v2z"/>
                    </svg>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-foreground">Sync to Google Photos</h4>
                    <p class="text-[11px] text-muted-foreground">Import photos directly into your Google Photos account.</p>
                </div>
            </button>
            @endif
        </div>
    </div>
</div>

<!-- 4. Google Photos Sync Modal -->
<div id="modal-google-photos-sync" class="fixed inset-0 z-[60] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 hidden" role="dialog" aria-modal="true">
    <div class="bg-card border border-border rounded-2xl p-6 max-w-sm w-full shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-200">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-bold text-foreground flex items-center gap-2">
                <svg class="w-5 h-5 text-primary fill-current" viewBox="0 0 24 24">
                    <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2m-1 12H6v-2h12v2m0-4H6V9h12v2z"/>
                </svg>
                <span>Save to Google Photos</span>
            </h3>
            <button type="button" id="btn-close-google-sync" class="p-1 rounded-lg text-muted-foreground hover:text-foreground hover:bg-secondary transition-colors" aria-label="Close dialog">&times;</button>
        </div>
        <p class="text-xs text-muted-foreground leading-relaxed">
            Select which photos from this collection to sync:
        </p>

        <form id="form-google-photos-sync" class="space-y-4">
            <div class="space-y-2">
                <label class="flex items-center gap-3 p-3 rounded-xl border border-border bg-secondary/20 cursor-pointer hover:border-primary/50 transition-colors">
                    <input type="radio" name="syncTarget" value="all" checked class="text-primary focus:ring-primary">
                    <div>
                        <span class="text-sm font-semibold text-foreground block">Entire Gallery</span>
                        <span class="text-xs text-muted-foreground">All photos in this collection.</span>
                    </div>
                </label>
                <label class="flex items-center gap-3 p-3 rounded-xl border border-border bg-secondary/20 cursor-pointer hover:border-primary/50 transition-colors">
                    <input type="radio" name="syncTarget" value="favorites" class="text-primary focus:ring-primary">
                    <div>
                        <span class="text-sm font-semibold text-foreground block">My Favorites Only</span>
                        <span class="text-xs text-muted-foreground">Only photos you've favorited.</span>
                    </div>
                </label>
            </div>

            <button type="submit" class="w-full py-2.5 rounded-xl bg-primary text-primary-foreground font-semibold text-sm hover:bg-primary/90 transition-all shadow">
                Continue to Export
            </button>
        </form>
    </div>
</div>

<!-- 5. iOS Photo Download Notice Modal -->
<div id="modal-ios-download-notice" class="fixed inset-0 z-[60] bg-black/70 backdrop-blur-sm flex items-center justify-center p-4 hidden" role="dialog" aria-modal="true" aria-labelledby="ios-download-modal-title">
    <div class="bg-card text-card-foreground border border-border/40 rounded-none p-6 max-w-sm w-full shadow-2xl relative">
        <div class="flex items-center justify-between mb-4">
            <h3 id="ios-download-modal-title" class="text-base font-bold text-foreground flex items-center gap-2">
                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                <span>Save to Photos</span>
            </h3>
            <button type="button" id="btn-close-ios-download-notice" class="min-w-[44px] min-h-[44px] flex items-center justify-center rounded-none text-muted-foreground hover:text-foreground hover:bg-secondary/40 transition-colors" aria-label="Close dialog">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <p class="text-sm text-foreground/85 leading-relaxed mb-6">
            A new window with your photo will open. <strong>Press and hold</strong> the photo, then choose <strong>Save to Photos</strong>.
        </p>

        <div class="flex flex-col gap-2">
            <button type="button"
                    id="btn-confirm-ios-download"
                    class="w-full py-3 px-4 rounded-none bg-primary text-primary-foreground font-semibold text-sm hover:bg-primary/90 transition-all shadow text-center cursor-pointer">
                Open Photo
            </button>
            <button type="button"
                    id="btn-cancel-ios-download"
                    class="w-full py-2.5 px-4 rounded-none text-muted-foreground hover:text-foreground hover:bg-secondary/40 font-medium text-xs transition-colors text-center cursor-pointer">
                Cancel
            </button>
        </div>
    </div>
</div>
@endif

<script>
function toggleTheme() {
    const isDark = document.documentElement.classList.toggle('dark');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
}
</script>

@endsection

@section('footer')
<footer class="border-t border-border bg-card/60 backdrop-blur-sm py-8 sm:py-10 mt-auto select-none">
    <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-center items-center text-xs text-muted-foreground">
        @if($photographer->isFree())
            <div class="flex items-center gap-2">
                <span class="text-xs text-muted-foreground font-medium">Powered by</span>
                <a href="{{ app(\App\Services\PublicUrlService::class)->homeUrl(['utm_source' => 'gallery', 'utm_medium' => 'footer_logo', 'utm_campaign' => 'powered_by']) }}" class="flex items-center gap-2 shrink-0 hover:opacity-80 transition-opacity">
                    <img src="{{ asset('logo.png') }}" alt="ifotoset" class="w-7 h-7 object-contain">
                    <span class="text-lg font-bold tracking-tight text-primary">ifoto<span class="text-foreground">set</span></span>
                </a>
            </div>
        @else
            <p>&copy; {{ date('Y') }} {{ $photographer->name }}. All rights reserved.</p>
        @endif
    </div>
</footer>
@endsection

@push('scripts')
    @vite(['resources/js/pages/gallery.js'])
@endpush

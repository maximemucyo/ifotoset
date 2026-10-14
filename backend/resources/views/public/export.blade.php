@extends('layouts.public', [
    'title' => 'Export ' . $gallery->title . ' - ' . $photographer->name . ' | ifotoset',
    'description' => "Export photo archive for '{$gallery->title}'.",
    'hideNav' => true,
    'hideFooter' => true,
])

@section('content')
<div class="relative flex min-h-svh flex-col"
     x-data="galleryExportManager({
         slug: '{{ $gallery->slug }}',
         galleryUuid: '{{ $gallery->uuid }}',
         username: '{{ $photographer->username }}',
         initialType: '{{ $type }}',
         initialTarget: '{{ $target }}',
         csrfToken: '{{ csrf_token() }}'
     })">
    <div class="pointer-events-none absolute inset-0 bg-dots opacity-40 mask-radial"></div>

    {{-- Top bar --}}
    <header class="relative z-10 flex h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-10">
        <a href="{{ route('public.gallery', ['username' => $photographer->username, 'slug' => $gallery->slug]) }}"
           class="btn btn-ghost btn-sm -ml-2" aria-label="Back to Gallery">
            <x-icon name="arrow-left" /> <span class="max-w-[50vw] truncate">{{ $gallery->title }}</span>
        </a>
        <x-ui.theme-toggle />
    </header>

    <div class="relative z-10 flex flex-1 items-center justify-center px-4 pb-16 pt-4 sm:px-6">
        <div class="w-full max-w-md animate-fade-up">
            <div class="mb-8 text-center">
                <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-muted-foreground">{{ $photographer->name }}</p>
                <h1 class="mt-3 font-serif text-4xl leading-[1.05] tracking-normal text-foreground sm:text-5xl">{{ $gallery->title }}</h1>
            </div>

            <div class="card overflow-hidden shadow-lg">
                {{-- Step: email + start --}}
                <div x-show="!exportId && !downloadUrl" class="p-6 sm:p-8">
                    <div class="flex items-start gap-3.5">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted text-foreground">
                            <template x-if="type === 'zip'"><x-icon name="download" class="size-[18px]" /></template>
                            <template x-if="type === 'google-photos'"><x-icon name="images" class="size-[18px]" /></template>
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-[15px] font-semibold text-foreground" x-text="type === 'zip' ? 'Download gallery (ZIP)' : 'Export to Google Photos'"></h2>
                            <p class="mt-1 text-[13px] leading-relaxed text-muted-foreground" x-text="type === 'zip'
                                ? 'All high-resolution photos bundled into one downloadable archive.'
                                : 'Sync photos into a private album in your Google Photos account.'"></p>
                            @if($target === 'favorites')
                                <span class="badge badge-brand mt-2.5"><x-icon name="heart" /> Favorites only</span>
                            @endif
                        </div>
                    </div>

                    <form @submit.prevent="startExport" class="mt-7 space-y-4">
                        <div>
                            <label for="export_email" class="label">Email address</label>
                            <div class="input-group">
                                <x-icon name="mail" />
                                <input type="email" id="export_email" x-model="email" required autocomplete="email" placeholder="you@example.com" class="input h-11">
                            </div>
                            <p class="help flex items-start gap-1.5">
                                <x-icon name="info" class="mt-px size-3.5 shrink-0" />
                                <span>We'll email you when it's ready. You can wait here or close this page.</span>
                            </p>
                        </div>

                        <div x-show="errorMessage" x-cloak class="alert alert-danger"><x-icon name="circle-alert" /> <span x-text="errorMessage"></span></div>

                        <button type="submit" :disabled="submitting" class="btn btn-primary btn-lg w-full">
                            <x-icon name="loader-circle" class="animate-spin" x-show="submitting" x-cloak />
                            <span x-text="submitting ? 'Starting…' : (type === 'zip' ? 'Prepare download' : 'Connect Google Photos')"></span>
                        </button>
                    </form>
                </div>

                {{-- Step: progress / result --}}
                <div x-show="exportId || downloadUrl" x-cloak class="p-6 sm:p-8" aria-live="polite">
                    <div class="text-center">
                        <div x-show="['pending', 'processing', 'uploading', 'authenticating'].includes(status)" class="relative mx-auto flex size-16 items-center justify-center">
                            <svg class="absolute inset-0 size-16 -rotate-90" viewBox="0 0 64 64" aria-hidden="true">
                                <circle cx="32" cy="32" r="28" fill="none" stroke="var(--border)" stroke-width="3" />
                                <circle cx="32" cy="32" r="28" fill="none" stroke="var(--brand)" stroke-width="3" stroke-linecap="round"
                                        stroke-dasharray="175.93" :stroke-dashoffset="175.93 - (175.93 * Math.max(4, percentage) / 100)"
                                        class="transition-[stroke-dashoffset] duration-500" />
                            </svg>
                            <span class="text-sm font-semibold tabular-nums text-foreground" x-text="percentage + '%'"></span>
                        </div>

                        <div x-show="status === 'completed' || status === 'ready'" class="mx-auto flex size-16 animate-scale-in items-center justify-center rounded-full bg-success/10 text-success">
                            <x-icon name="check" class="size-7" />
                        </div>

                        <div x-show="['failed', 'expired', 'cancelled'].includes(status)" class="mx-auto flex size-16 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                            <x-icon name="x" class="size-7" />
                        </div>

                        <h2 class="mt-5 text-lg font-semibold text-foreground" x-text="statusDisplayTitle"></h2>
                        <p class="mx-auto mt-1.5 max-w-xs text-[13px] leading-relaxed text-muted-foreground" x-text="statusDisplaySubtitle"></p>
                    </div>

                    <div x-show="status === 'processing' || status === 'uploading' || status === 'pending'" class="mt-7 space-y-2">
                        <div class="progress"><span :style="'width: ' + percentage + '%'"></span></div>
                        <div class="flex justify-between text-xs tabular-nums text-muted-foreground">
                            <span x-text="percentage + '% complete'"></span>
                            <span x-text="processedPhotos + ' / ' + totalPhotos + ' photos'"></span>
                        </div>
                    </div>

                    <div x-show="(status === 'completed' || status === 'ready') && downloadUrl" class="mt-7">
                        <a :href="downloadUrl" class="btn btn-primary btn-lg w-full">
                            <x-icon name="download" /> Download ZIP
                        </a>
                    </div>

                    <div x-show="['failed', 'expired', 'cancelled'].includes(status)" class="mt-7">
                        <button type="button" @click="resetExport" class="btn btn-outline btn-lg w-full">
                            <x-icon name="rotate-ccw" /> Try again
                        </button>
                    </div>
                </div>
            </div>

            <p class="mt-6 text-center text-xs text-muted-foreground">
                <a href="{{ route('public.gallery', ['username' => $photographer->username, 'slug' => $gallery->slug]) }}" class="transition-colors hover:text-foreground">Back to gallery</a>
            </p>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('galleryExportManager', (config) => {
        const scopedKey = 'visitor_identity_gallery_' + config.galleryUuid;
        const legacyKey = 'visitor_identity_' + config.slug;
        let storedEmail = localStorage.getItem(scopedKey) || '';
        if (!storedEmail) {
            try {
                const legacy = localStorage.getItem(legacyKey);
                if (legacy) storedEmail = JSON.parse(legacy).email || '';
            } catch(e) {}
        }

        return {
            slug: config.slug,
            galleryUuid: config.galleryUuid,
            username: config.username,
            type: config.initialType || 'zip',
            target: config.initialTarget || 'all',
            csrfToken: config.csrfToken,
            email: storedEmail,
            submitting: false,
            exportId: null,
            status: 'pending', // canonical: pending, authenticating, processing, uploading, completed, ready, failed, expired, cancelled
            percentage: 0,
            processedPhotos: 0,
            totalPhotos: 0,
            downloadUrl: '',
            errorMessage: '',
            pollTimer: null,

            get statusDisplayTitle() {
                switch (this.status) {
                    case 'pending': return 'Preparing your download';
                    case 'processing': return 'Packaging your photos';
                    case 'uploading': return 'Exporting to Google Photos';
                    case 'completed':
                    case 'ready': return 'Your download is ready';
                    case 'expired': return 'Download link expired';
                    case 'failed': return 'Something went wrong';
                    default: return 'Processing…';
                }
            },

            get statusDisplaySubtitle() {
                switch (this.status) {
                    case 'pending': return 'You can wait here, or leave the page and we will notify you by email.';
                    case 'processing': return 'You can wait here until your download is ready, or leave the page and we will notify you.';
                    case 'completed':
                    case 'ready': return 'Click below to download your files.';
                    case 'expired': return 'Download links expire after 24 hours. Please request a new one.';
                    case 'failed': return this.errorMessage || 'Something went wrong while preparing your files. Please try again.';
                    default: return 'Please wait while your files are being prepared.';
                }
            },

            async startExport() {
                if (!this.email || !this.email.includes('@')) {
                    this.errorMessage = 'Please provide a valid email address.';
                    return;
                }

                this.submitting = true;
                this.errorMessage = '';

                try {
                    if (this.type === 'zip') {
                        const response = await fetch(`/api/v1/public/galleries/${this.slug}/download-zip`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken
                            },
                            body: JSON.stringify({
                                email: this.email,
                                notify_when_ready: true,
                                notify: true
                            })
                        });

                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || 'Failed to prepare download.');
                        }

                        // Save to localStorage ONLY after server validation succeeds
                        try {
                            localStorage.setItem('visitor_identity_gallery_' + this.galleryUuid, this.email);
                            localStorage.setItem('visitor_identity_' + this.slug, JSON.stringify({
                                email: this.email,
                                created_at: new Date().toISOString()
                            }));
                        } catch(e) {
                            console.warn('Could not save identity to localStorage:', e);
                        }

                        this.exportId = data.download_id;

                        if (data.status === 'ready') {
                            this.status = 'ready';
                            this.percentage = 100;
                            this.downloadUrl = data.download_url;
                        } else {
                            this.status = data.status;
                            this.startPolling();
                        }
                    } else if (this.type === 'google-photos') {
                        const favoritesKey = 'photos_export_favorites_' + this.slug;
                        const favoriteUuids = this.target === 'favorites' ? JSON.parse(sessionStorage.getItem(favoritesKey) || '[]') : null;

                        const response = await fetch(`/api/v1/public/galleries/${this.slug}/google-photos/authorize`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken
                            },
                            body: JSON.stringify({
                                email: this.email,
                                notify_when_ready: true,
                                notify: true,
                                favorite_uuids: favoriteUuids
                            })
                        });

                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || 'Failed to initialize Google Photos.');
                        }

                        // Save to localStorage ONLY after server validation succeeds
                        try {
                            localStorage.setItem('visitor_identity_gallery_' + this.galleryUuid, this.email);
                        } catch(e) {}

                        if (data.url) {
                            window.location.href = data.url;
                        } else {
                            throw new Error('No authorization URL returned.');
                        }
                    }
                } catch (err) {
                    this.errorMessage = err.message || 'An unexpected error occurred.';
                } finally {
                    this.submitting = false;
                }
            },

            startPolling() {
                this.stopPolling();
                this.checkStatus();
                this.pollTimer = setInterval(() => {
                    if (document.visibilityState === 'visible') {
                        this.checkStatus();
                    }
                }, 2000);
            },

            stopPolling() {
                if (this.pollTimer) {
                    clearInterval(this.pollTimer);
                    this.pollTimer = null;
                }
            },

            async checkStatus() {
                if (!this.exportId) return;

                try {
                    const response = await fetch(`/api/v1/public/galleries/${this.slug}/download-zip/${this.exportId}`, {
                        headers: { 'Accept': 'application/json' }
                    });

                    if (!response.ok) {
                        if (response.status === 404) {
                            this.status = 'expired';
                        } else {
                            this.status = 'failed';
                        }
                        this.stopPolling();
                        return;
                    }

                    const data = await response.json();
                    const rawStatus = data.status;

                    if (rawStatus === 'ready' || rawStatus === 'ready_with_errors') {
                        this.status = 'ready';
                        this.percentage = 100;
                        this.downloadUrl = data.download_url || `/api/v1/public/galleries/${this.slug}/download-zip/${this.exportId}/download`;
                        this.stopPolling();
                    } else if (rawStatus === 'failed' || rawStatus === 'empty') {
                        this.status = 'failed';
                        this.stopPolling();
                    } else {
                        this.status = rawStatus;
                    }

                    this.percentage = data.percentage ?? this.percentage;
                    this.processedPhotos = (data.processed_photos || 0) + (data.failed_photos || 0);
                    this.totalPhotos = data.total_photos || this.totalPhotos;
                } catch (e) {
                    console.error('Polling error:', e);
                }
            },

            resetExport() {
                this.stopPolling();
                this.exportId = null;
                this.downloadUrl = '';
                this.status = 'pending';
                this.percentage = 0;
                this.errorMessage = '';
            },

            destroy() {
                this.stopPolling();
            }
        };
    });
});
</script>
@endsection

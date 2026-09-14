@extends('layouts.public', [
    'title' => 'Export ' . $gallery->title . ' - ' . $photographer->name . ' | ifotoset',
    'description' => "Export photo archive for '{$gallery->title}'.",
])

@section('content')
<div class="min-h-[80vh] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8"
     x-data="galleryExportManager({
         slug: '{{ $gallery->slug }}',
         username: '{{ $photographer->username }}',
         initialType: '{{ $type }}',
         initialTarget: '{{ $target }}',
         csrfToken: '{{ csrf_token() }}'
     })">
    <div class="bg-card border border-border rounded-2xl max-w-lg w-full shadow-2xl overflow-hidden relative">
        <!-- Accent Top Bar -->
        <div class="h-1.5 w-full bg-gradient-to-r from-primary via-blue-500 to-indigo-500"></div>

        <div class="p-6 sm:p-8 space-y-6">
            <!-- Header with Back Button -->
            <div class="flex items-center gap-3 pb-4 border-b border-border">
                <a href="{{ route('public.gallery', ['username' => $photographer->username, 'slug' => $gallery->slug]) }}"
                   class="p-2 rounded-xl bg-secondary/50 hover:bg-secondary text-muted-foreground hover:text-foreground border border-border transition-colors focus:outline-none focus:ring-2 focus:ring-primary"
                   aria-label="Back to Gallery">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold tracking-tight text-foreground truncate">Export Gallery</h1>
                    <p class="text-xs text-muted-foreground truncate">{{ $gallery->title }} &bull; by {{ $photographer->name }}</p>
                </div>
            </div>

            <!-- Pre-Export Form Step -->
            <div x-show="!exportId" class="space-y-6">
                <!-- Export Type Summary Banner -->
                <div class="p-4 rounded-xl border border-border/80 bg-secondary/20 flex items-start gap-3.5">
                    <div class="p-2.5 bg-primary/10 text-primary rounded-xl shrink-0 mt-0.5">
                        <template x-if="type === 'zip'">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                        </template>
                        <template x-if="type === 'google-photos'">
                            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                                <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2m-1 12H6v-2h12v2m0-4H6V9h12v2z"/>
                            </svg>
                        </template>
                    </div>
                    <div class="space-y-1 text-sm">
                        <h2 class="font-bold text-foreground" x-text="type === 'zip' ? 'Download Gallery Archive (ZIP)' : 'Export to Google Photos'"></h2>
                        <p class="text-xs text-muted-foreground leading-relaxed" x-text="type === 'zip'
                            ? 'All ready high-resolution photos will be bundled into a downloadable ZIP archive. Larger collections are queued in the background.'
                            : 'Sync photos directly into a private album inside your Google Photos account.'"></p>
                    </div>
                </div>

                <!-- Email Notification Input Panel -->
                <form @submit.prevent="startExport" class="space-y-4">
                    <div class="p-5 border border-border rounded-xl bg-card space-y-3">
                        <label for="export_email" class="block text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            Delivery Notification
                        </label>
                        <p class="text-xs text-foreground font-medium">
                            Enter your email to receive a secure download link when preparation is complete:
                        </p>
                        <input type="email"
                               id="export_email"
                               x-model="email"
                               required
                               placeholder="your.email@example.com"
                               class="w-full bg-input border border-border rounded-xl px-4 py-2.5 text-sm text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary transition-all">
                        <p class="text-[11px] text-muted-foreground flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-primary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            You may safely close or navigate away from this page at any time.
                        </p>
                    </div>

                    <div x-show="errorMessage" class="p-3 bg-destructive/10 border border-destructive/20 text-destructive text-xs rounded-xl font-medium" x-text="errorMessage"></div>

                    <button type="submit"
                            :disabled="submitting"
                            class="w-full py-3 px-4 rounded-xl bg-primary hover:bg-primary/90 text-primary-foreground font-semibold text-sm shadow-md transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-60">
                        <span x-show="submitting" class="inline-block w-4 h-4 border-2 border-primary-foreground border-t-transparent rounded-full animate-spin"></span>
                        <span x-text="submitting ? 'Starting Request...' : (type === 'zip' ? 'Start Archive Packaging' : 'Connect & Authorize Google Photos')"></span>
                    </button>
                </form>
            </div>

            <!-- Active Polling & Progress Step -->
            <div x-show="exportId" class="space-y-6" aria-live="polite">
                <!-- Status Header -->
                <div class="text-center space-y-3 py-4">
                    <!-- Pending / Processing Spinner -->
                    <div x-show="['pending', 'processing', 'uploading', 'authenticating'].includes(status)" class="w-16 h-16 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center mx-auto text-primary">
                        <span class="w-8 h-8 border-3 border-primary border-t-transparent rounded-full animate-spin"></span>
                    </div>

                    <!-- Completed Check -->
                    <div x-show="status === 'completed' || status === 'ready'" class="w-16 h-16 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center mx-auto text-emerald-500">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>

                    <!-- Failed / Expired -->
                    <div x-show="['failed', 'expired', 'cancelled'].includes(status)" class="w-16 h-16 rounded-full bg-destructive/10 border border-destructive/20 flex items-center justify-center mx-auto text-destructive">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>

                    <h2 class="text-xl font-bold text-foreground" x-text="statusDisplayTitle"></h2>
                    <p class="text-xs text-muted-foreground max-w-sm mx-auto" x-text="statusDisplaySubtitle"></p>
                </div>

                <!-- Progress Bar Container -->
                <div x-show="status === 'processing' || status === 'uploading' || status === 'pending'" class="space-y-2">
                    <div class="flex justify-between text-xs font-semibold text-muted-foreground">
                        <span x-text="percentage + '% Completed'"></span>
                        <span x-text="processedPhotos + ' / ' + totalPhotos + ' Photos'"></span>
                    </div>
                    <div class="w-full h-2.5 rounded-full bg-secondary overflow-hidden">
                        <div class="h-full bg-primary transition-all duration-300 rounded-full" :style="'width: ' + percentage + '%'"></div>
                    </div>
                </div>

                <!-- Action Button for Ready/Completed Archive -->
                <div x-show="status === 'completed' || status === 'ready'" class="pt-2">
                    <a :href="downloadUrl"
                       class="w-full py-3.5 px-4 rounded-xl bg-primary hover:bg-primary/90 text-primary-foreground font-bold text-sm shadow-md transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        <span>Download ZIP Archive</span>
                    </a>
                </div>

                <!-- Action Button for Error / Retry -->
                <div x-show="['failed', 'expired', 'cancelled'].includes(status)" class="pt-2">
                    <button @click="resetExport"
                            class="w-full py-3 px-4 rounded-xl bg-secondary hover:bg-secondary/80 text-foreground font-semibold text-sm transition-all">
                        Try Again
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('galleryExportManager', (config) => ({
        slug: config.slug,
        username: config.username,
        type: config.initialType || 'zip',
        target: config.initialTarget || 'all',
        csrfToken: config.csrfToken,
        email: localStorage.getItem('visitor_identity_' + config.slug) ? JSON.parse(localStorage.getItem('visitor_identity_' + config.slug)).email || '' : '',
        submitting: false,
        exportId: null,
        status: 'pending', // canonical: pending, authenticating, processing, uploading, completed, failed, expired, cancelled
        percentage: 0,
        processedPhotos: 0,
        totalPhotos: 0,
        downloadUrl: '',
        errorMessage: '',
        pollTimer: null,

        get statusDisplayTitle() {
            switch (this.status) {
                case 'pending': return 'Queueing Archive Request';
                case 'processing': return 'Packaging Photos into ZIP';
                case 'uploading': return 'Uploading to Google Photos';
                case 'completed':
                case 'ready': return 'Archive Ready to Download';
                case 'expired': return 'Download Link Expired';
                case 'failed': return 'Packaging Failed';
                default: return 'Processing...';
            }
        },

        get statusDisplaySubtitle() {
            switch (this.status) {
                case 'pending': return 'Waiting for a background worker to claim this task...';
                case 'processing': return 'Compiling high-resolution photos into a compressed archive.';
                case 'completed':
                case 'ready': return 'Your download is ready. Click the button below to save it.';
                case 'expired': return 'Archive files are kept for 24 hours. Please generate a new one.';
                case 'failed': return this.errorMessage || 'An error occurred during packaging. Please try again.';
                default: return 'Please wait while we process your request.';
            }
        },

        async startExport() {
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
                            notify: true
                        })
                    });

                    const data = await response.json();
                    if (!response.ok) {
                        throw new Error(data.message || 'Failed to start archive packaging.');
                    }

                    this.exportId = data.download_id;
                    this.status = data.status === 'ready' ? 'completed' : data.status;
                    this.downloadUrl = `/api/v1/public/galleries/${this.slug}/download-zip/${this.exportId}/download`;

                    if (this.status === 'completed') {
                        this.percentage = 100;
                    } else {
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
                            notify: true,
                            favorite_uuids: favoriteUuids
                        })
                    });

                    const data = await response.json();
                    if (!response.ok) {
                        throw new Error(data.message || 'Failed to initialize Google Photos.');
                    }

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

                // Map canonical status
                if (rawStatus === 'ready' || rawStatus === 'ready_with_errors') {
                    this.status = 'completed';
                    this.percentage = 100;
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
            this.status = 'pending';
            this.percentage = 0;
            this.errorMessage = '';
        }
    }));
});
</script>
@endsection

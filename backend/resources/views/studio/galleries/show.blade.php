@extends('layouts.app', ['title' => $gallery->title . ' - Studio Photo Manager'])

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

<script>
/**
 * Utility: Format bytes into human-readable string
 */
function formatBytes(bytes, decimals = 1) {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

/**
 * Utility: Sanitize error messages to avoid leaking technical backend specs
 */
function cleanErrorMessage(msg) {
    if (!msg || typeof msg !== 'string') return 'Upload interrupted. Click retry to resume.';
    const lower = msg.toLowerCase();
    if (lower.includes('413') || lower.includes('too large') || lower.includes('size')) {
        return 'Photo exceeds maximum allowed size (50MB).';
    }
    if (lower.includes('network') || lower.includes('failed to fetch') || lower.includes('connection')) {
        return 'Connection interrupted. Click retry to resume.';
    }
    if (lower.includes('quota') || lower.includes('storage limit')) {
        return 'Storage quota reached for your account.';
    }
    return 'Upload interrupted. Click retry to resume.';
}

/**
 * Utility: Reusable SHA-256 Web Worker with single-task queue (1 hash at a time off main thread)
 */
const sha256WorkerManager = (function() {
    let worker = null;
    const queue = [];
    let isProcessing = false;

    function initWorker() {
        if (worker) return worker;
        try {
            const workerCode = `
                self.onmessage = async function(e) {
                    const { id, file } = e.data;
                    try {
                        const buffer = await file.arrayBuffer();
                        const hashBuffer = await crypto.subtle.digest('SHA-256', buffer);
                        const hashArray = Array.from(new Uint8Array(hashBuffer));
                        const hex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
                        self.postMessage({ id, hash: hex });
                    } catch (err) {
                        self.postMessage({ id, error: err.message || 'Checksum calculation failed' });
                    }
                };
            `;
            const blob = new Blob([workerCode], { type: 'application/javascript' });
            const workerUrl = URL.createObjectURL(blob);
            worker = new Worker(workerUrl);
            URL.revokeObjectURL(workerUrl);

            worker.onmessage = function(e) {
                const current = queue.shift();
                isProcessing = false;
                if (current) {
                    if (e.data.hash) {
                        current.resolve(e.data.hash);
                    } else {
                        current.reject(new Error(e.data.error || 'Checksum calculation failed'));
                    }
                }
                processNext();
            };

            worker.onerror = function(err) {
                const current = queue.shift();
                isProcessing = false;
                if (current) {
                    current.reject(err);
                }
                processNext();
            };
        } catch (e) {
            console.warn('Web Worker initialization unavailable, will use main thread fallback:', e);
        }
        return worker;
    }

    function processNext() {
        if (isProcessing || queue.length === 0) return;
        const nextTask = queue[0];
        isProcessing = true;
        const w = initWorker();
        if (w) {
            w.postMessage({ id: nextTask.id, file: nextTask.file });
        } else {
            fallbackHash(nextTask.file)
                .then(hash => {
                    queue.shift();
                    isProcessing = false;
                    nextTask.resolve(hash);
                    processNext();
                })
                .catch(err => {
                    queue.shift();
                    isProcessing = false;
                    nextTask.reject(err);
                    processNext();
                });
        }
    }

    async function fallbackHash(file) {
        if (window.crypto && crypto.subtle) {
            const buffer = await file.arrayBuffer();
            const hashBuffer = await crypto.subtle.digest('SHA-256', buffer);
            const hashArray = Array.from(new Uint8Array(hashBuffer));
            return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
        }
        return '';
    }

    return {
        calculate(file) {
            return new Promise((resolve, reject) => {
                queue.push({
                    id: Math.random().toString(36).substring(2) + Date.now(),
                    file,
                    resolve,
                    reject
                });
                processNext();
            });
        }
    };
})();

async function calculateFileSha256(file) {
    try {
        return await sha256WorkerManager.calculate(file);
    } catch (e) {
        console.warn('SHA-256 computation warning:', e);
        return '';
    }
}

function getVideoDuration(file) {
    return new Promise((resolve) => {
        try {
            const video = document.createElement('video');
            video.preload = 'metadata';
            video.onloadedmetadata = () => {
                URL.revokeObjectURL(video.src);
                resolve(Math.round(video.duration) || 0);
            };
            video.onerror = () => {
                resolve(0);
            };
            video.src = URL.createObjectURL(file);
        } catch (_) {
            resolve(0);
        }
    });
}

/**
 * Upload configuration presets
 */
const UPLOAD_CONFIG = {
    concurrency: 5,
    progressThrottleMs: 100,
    progressMinDeltaPercent: 2,
    maxRetries: 3
};

/**
 * Alpine Master Component: Studio Gallery View Manager
 */
window.studioGalleryManager = function(inlineConfig) {
    let config = inlineConfig || {};
    if (!config.galleryUuid) {
        try {
            const cfgEl = document.getElementById('gallery-config');
            if (cfgEl && cfgEl.textContent) {
                config = JSON.parse(cfgEl.textContent);
            }
        } catch (e) {
            console.error('Failed to parse gallery-config:', e);
        }
    }

    let initialPhotosList = [];
    try {
        const dataEl = document.getElementById('gallery-initial-photos');
        if (dataEl && dataEl.textContent) {
            initialPhotosList = JSON.parse(dataEl.textContent);
        }
    } catch (e) {
        console.error('Failed to parse gallery-initial-photos:', e);
    }

    // Internal non-reactive data structures:
    // photoUuids Set for O(1) deduplication without Alpine proxy overhead
    const photoUuids = new Set(initialPhotosList.map(p => p.uuid));
    // uploadTasks Map for heavy objects (XHR, sessions, bytes loaded) to prevent Alpine reactive bloat
    const uploadTasks = new Map();

    return {
        galleryUuid: config.galleryUuid || '',
        csrfToken: config.csrfToken || '',
        photosUrl: config.photosUrl || '',
        coverUrl: config.coverUrl || '',
        toggleHideUrlBase: config.toggleHideUrlBase || '',
        deletePhotoUrlBase: config.deletePhotoUrlBase || '',
        uploadRequestUrl: config.uploadRequestUrl || '',
        uploadConfirmUrl: config.uploadConfirmUrl || '',
        uploadAbortUrl: config.uploadAbortUrl || '',
        videoStatusUrlBase: config.videoStatusUrlBase || '',
        storageStatsUrl: config.storageStatsUrl || '',
        billingCheckoutUrl: config.billingCheckoutUrl || '',
        billingPlansUrl: config.billingPlansUrl || '',
        userStorage: config.userStorage || null,
        userVideo: config.userVideo || null,
        upgradePlans: config.upgradePlans || [],
        billingReturnSuccess: Boolean(config.billingReturnSuccess),

        // Quota & Upgrade UI State
        quotaErrorState: null,
        batchQuotaWarning: null,
        upgradedCelebration: null,
        isCheckingStatus: false,
        checkStatusFeedback: null,

        // Gallery Photos State
        photos: initialPhotosList,
        totalPhotos: Number(config.initialTotalCount) || 0,
        nextCursor: config.initialNextCursor || null,
        hasMore: Boolean(config.initialHasMore && config.initialNextCursor),
        loadingPhotos: false,
        observer: null,

        // Lightbox State
        lightboxOpen: false,
        lightboxIndex: 0,

        get currentPhoto() {
            if (this.lightboxIndex >= 0 && this.lightboxIndex < this.photos.length) {
                return this.photos[this.lightboxIndex];
            }
            return null;
        },

        // Uploads State
        uploads: [],
        uploadStats: {
            totalBytes: 0,
            uploadedBytes: 0
        },
        isDragging: false,
        statusSummary: null,

        get isUploading() {
            return this.uploads.some(u => u.status === 'queued' || u.status === 'preparing' || u.status === 'uploading' || u.status === 'retrying' || u.status === 'processing');
        },

        get activeCount() {
            return this.uploads.filter(u => u.status === 'preparing' || u.status === 'uploading' || u.status === 'processing' || u.status === 'retrying').length;
        },

        get completedCount() {
            return this.uploads.filter(u => u.status === 'done').length;
        },

        get failedCount() {
            return this.uploads.filter(u => u.status === 'error').length;
        },

        get totalCount() {
            return this.uploads.length;
        },

        // O(1) Aggregate Progress Calculation
        get overallProgress() {
            if (!this.uploadStats.totalBytes) return 0;
            return Math.min(100, Math.floor((this.uploadStats.uploadedBytes / this.uploadStats.totalBytes) * 100));
        },

        init() {
            this.setupInfiniteScroll();
            this.setupNetworkResilience();
            this.setupUnloadGuard();
            this.setupCrossTabBillingListener();
            if (this.billingReturnSuccess) {
                this.checkAndApplyUpgradedStorage(true);
            }
            // Resume status polling for any videos currently being processed
            this.photos.filter(p => p.status === 'processing').forEach(p => {
                this.pollProcessingStatus(p.uuid);
            });
        },

        setupInfiniteScroll() {
            this.$nextTick(() => {
                const sentinelEl = this.$refs.sentinel;
                if (!sentinelEl) return;

                this.observer = new IntersectionObserver((entries) => {
                    if (entries[0] && entries[0].isIntersecting) {
                        if (this.hasMore && !this.loadingPhotos) {
                            this.fetchNextBatch();
                        }
                    }
                }, { rootMargin: '400px' });

                if (this.hasMore) {
                    this.observer.observe(sentinelEl);
                }
            });
        },

        async fetchNextBatch() {
            if (this.loadingPhotos || !this.hasMore || !this.nextCursor) return;

            this.loadingPhotos = true;

            try {
                const url = new URL(this.photosUrl, window.location.origin);
                url.searchParams.set('cursor', this.nextCursor);
                url.searchParams.set('per_page', '50');

                const res = await fetch(url.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!res.ok) throw new Error('Failed to load photos');

                const data = await res.json();
                const incomingPhotos = data.data || [];

                // Deduplicate by UUID via internal Set
                const uniqueNewPhotos = incomingPhotos.filter(p => !photoUuids.has(p.uuid));

                // Append each new photo card to the grid
                const grid = document.getElementById('studio-photos-grid');
                if (grid) {
                    uniqueNewPhotos.forEach((photo) => {
                        this.photos.push(photo);
                        photoUuids.add(photo.uuid);
                        const cardEl = this.createPhotoCardElement(photo);
                        grid.appendChild(cardEl);
                    });
                } else {
                    this.photos.push(...uniqueNewPhotos);
                    uniqueNewPhotos.forEach(p => photoUuids.add(p.uuid));
                }

                this.nextCursor = data.next_cursor || null;
                this.hasMore = Boolean(data.has_more && this.nextCursor);

                if (!this.hasMore && this.observer && this.$refs.sentinel) {
                    this.observer.unobserve(this.$refs.sentinel);
                }
            } catch (err) {
                console.error('Failed to load more photos:', err);
            } finally {
                this.loadingPhotos = false;
            }
        },

        createPhotoCardElement(photo) {
            const card = document.createElement('div');
            card.id = `photo-card-${photo.uuid}`;
            card.dataset.uuid = photo.uuid;
            card.dataset.id = photo.id;
            card.className = `photo-card group relative rounded-xl overflow-hidden bg-card border ${photo.is_cover ? 'border-primary ring-2 ring-primary/40 is-cover' : 'border-border'} ${photo.is_hidden ? 'opacity-65 grayscale-[30%] is-hidden' : ''} shadow-sm flex flex-col justify-between transition-all duration-300`;

            const thumb = photo.thumbnail_url || photo.medium_url || photo.large_url || photo.original_url || photo.cdn_url;
            const filename = photo.original_filename || photo.filename || 'Photo';
            const sizeMb = photo.size ? `${(photo.size / 1048576).toFixed(1)}MB` : '';
            const isVideo = photo.is_video || photo.media_type === 'video';
            const duration = photo.duration || '';
            const isProcessing = photo.status === 'processing';

            card.innerHTML = `
                <div class="aspect-square bg-muted relative overflow-hidden cursor-pointer" onclick="window._openLightboxByUuid('${photo.uuid}')">
                    <img src="${thumb}" alt="${filename}" loading="lazy" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" />
                    <div class="cover-badge absolute top-2 left-2 z-10 ${photo.is_cover ? '' : 'hidden'}">
                        <span class="px-2 py-0.5 rounded bg-primary text-primary-foreground text-[10px] font-bold uppercase tracking-wider shadow">Cover</span>
                    </div>
                    <div class="hidden-badge absolute top-2 right-2 z-10 ${photo.is_hidden ? '' : 'hidden'}">
                        <span class="px-2 py-0.5 rounded bg-amber-500/90 text-white text-[10px] font-bold tracking-wider shadow flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" /></svg>
                            Hidden
                        </span>
                    </div>
                    ${isVideo ? `
                        <div class="video-duration-badge absolute bottom-2 left-2 z-10">
                            <span class="px-2 py-0.5 rounded-full bg-black/75 backdrop-blur-md text-white text-[10px] font-semibold flex items-center gap-1 shadow">
                                <svg class="w-2.5 h-2.5 fill-current text-primary" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                <span>${duration || 'Video'}</span>
                            </span>
                        </div>
                    ` : ''}
                    ${isProcessing ? `
                        <div class="processing-overlay absolute inset-0 bg-black/40 backdrop-blur-[2px] z-10 flex flex-col items-center justify-center text-white text-xs gap-1.5 pointer-events-none">
                            <svg class="w-5 h-5 animate-spin text-primary" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span class="font-medium">Processing...</span>
                        </div>
                    ` : ''}
                    <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-between p-2.5 z-20" onclick="event.stopPropagation()">
                        <div class="flex items-center justify-between">
                            <button type="button" onclick="window._openLightboxByUuid('${photo.uuid}')" class="p-1.5 rounded-lg bg-black/50 hover:bg-black/80 text-white transition-colors" title="Preview photo">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                            <div class="flex items-center gap-1.5">
                                <button type="button" onclick="window._toggleHide('${photo.uuid}')" class="btn-toggle-hide p-1.5 rounded-lg transition-colors text-white ${photo.is_hidden ? 'bg-amber-500 hover:bg-amber-600' : 'bg-black/50 hover:bg-black/80'}" title="${photo.is_hidden ? 'Show in client gallery' : 'Hide from client gallery'}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        ${photo.is_hidden ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>' : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />'}
                                    </svg>
                                </button>
                                <button type="button" onclick="window._deletePhoto('${photo.uuid}')" class="p-1.5 rounded-lg bg-destructive/80 hover:bg-destructive text-white transition-colors" title="Delete photo">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                        </div>
                        <div class="cover-action-container">
                            <button type="button" onclick="window._setCover('${photo.uuid}')" class="btn-set-cover w-full py-1.5 px-2 text-[11px] font-semibold bg-white/90 hover:bg-white text-black rounded-lg transition-colors text-center shadow-xs flex items-center justify-center gap-1.5 ${photo.is_cover || photo.is_hidden ? 'hidden' : ''}">
                                <svg class="w-3.5 h-3.5 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                Set as Cover
                            </button>
                            <div class="hidden-label text-[10px] text-white/80 text-center py-1 bg-black/40 rounded ${photo.is_hidden ? '' : 'hidden'}">
                                Hidden from client gallery
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-2 text-[11px] text-muted-foreground truncate border-t border-border flex items-center justify-between">
                    <span class="truncate">${filename}</span>
                    ${sizeMb ? `<span class="text-[10px] opacity-60 shrink-0 font-mono">${sizeMb}</span>` : ''}
                </div>
            `;
            return card;
        },

        // --- Lightbox Methods ---
        activeStudioArtplayer: null,

        initStudioArtplayer() {
            this.teardownStudioLightboxVideo();
            const container = document.getElementById('studio-artplayer-container');
            const photo = this.currentPhoto;
            if (!container || !photo) return;

            const videoUrl = photo.delivery_url || photo.original_url || photo.full_url;
            const posterUrl = photo.full_url || photo.large_url || photo.thumbnail_url || '';

            if (window.Artplayer) {
                try {
                    this.activeStudioArtplayer = new window.Artplayer({
                        container: container,
                        url: videoUrl,
                        poster: posterUrl,
                        volume: 0.7,
                        isLive: false,
                        muted: true,
                        autoplay: true,
                        pip: true,
                        autoSize: false,
                        autoMini: false,
                        screenshot: false,
                        setting: true,
                        loop: false,
                        playbackRate: true,
                        aspectRatio: true,
                        fullscreen: true,
                        fullscreenWeb: true,
                        playsInline: true,
                        airplay: true,
                        theme: '#e11d48',
                        customType: {
                            m3u8: function (video, url, artInstance) {
                                if (window.Hls && window.Hls.isSupported()) {
                                    if (artInstance.hls) artInstance.hls.destroy();
                                    const hls = new window.Hls();
                                    hls.loadSource(url);
                                    hls.attachMedia(video);
                                    artInstance.hls = hls;
                                    artInstance.on('destroy', () => hls.destroy());
                                } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                                    video.src = url;
                                } else {
                                    artInstance.notice.show = 'Unsupported video format';
                                }
                            },
                        },
                    });
                } catch (e) {
                    console.error('[Studio Lightbox] Artplayer init failed:', e);
                }
            }

            if (!this.activeStudioArtplayer) {
                const video = document.createElement('video');
                video.id = 'studio-lightbox-video';
                video.src = videoUrl;
                video.poster = posterUrl;
                video.controls = true;
                video.autoplay = true;
                video.playsInline = true;
                video.className = 'max-h-[82vh] max-w-[90vw] object-contain rounded-lg shadow-2xl';
                container.appendChild(video);
            }
        },

        openLightboxByUuid(photoUuid) {
            const idx = this.photos.findIndex(p => p.uuid === photoUuid);
            if (idx !== -1) {
                this.lightboxIndex = idx;
                this.lightboxOpen = true;
                document.body.style.overflow = 'hidden';
                this.$nextTick(() => {
                    if (this.currentPhoto && (this.currentPhoto.is_video || this.currentPhoto.media_type === 'video') && this.currentPhoto.status !== 'processing') {
                        this.initStudioArtplayer();
                    }
                });
            }
        },

        openLightboxByIndex(index) {
            if (index >= 0 && index < this.photos.length) {
                this.lightboxIndex = index;
                this.lightboxOpen = true;
                document.body.style.overflow = 'hidden';
                this.$nextTick(() => {
                    if (this.currentPhoto && (this.currentPhoto.is_video || this.currentPhoto.media_type === 'video') && this.currentPhoto.status !== 'processing') {
                        this.initStudioArtplayer();
                    }
                });
            }
        },

        teardownStudioLightboxVideo() {
            if (this.activeStudioArtplayer) {
                try {
                    this.activeStudioArtplayer.destroy(true);
                } catch (e) {}
                this.activeStudioArtplayer = null;
            }
            const container = document.getElementById('studio-artplayer-container');
            if (container) {
                container.innerHTML = '';
            }
            const video = document.getElementById('studio-lightbox-video');
            if (video) {
                try {
                    video.pause();
                    video.removeAttribute('src');
                    video.load();
                } catch (e) {}
                video.remove();
            }
        },

        closeLightbox() {
            this.teardownStudioLightboxVideo();
            this.lightboxOpen = false;
            document.body.style.overflow = '';
        },

        nextLightboxPhoto() {
            if (this.photos.length <= 1) return;
            this.teardownStudioLightboxVideo();
            if (this.lightboxIndex < this.photos.length - 1) {
                this.lightboxIndex++;
            } else {
                this.lightboxIndex = 0;
            }
            this.$nextTick(() => {
                if (this.currentPhoto && (this.currentPhoto.is_video || this.currentPhoto.media_type === 'video') && this.currentPhoto.status !== 'processing') {
                    this.initStudioArtplayer();
                }
            });
            if (this.lightboxIndex >= this.photos.length - 3 && this.hasMore && !this.loadingPhotos) {
                this.fetchNextBatch();
            }
        },

        prevLightboxPhoto() {
            if (this.photos.length <= 1) return;
            this.teardownStudioLightboxVideo();
            if (this.lightboxIndex > 0) {
                this.lightboxIndex--;
            } else {
                this.lightboxIndex = this.photos.length - 1;
            }
            this.$nextTick(() => {
                if (this.currentPhoto && (this.currentPhoto.is_video || this.currentPhoto.media_type === 'video') && this.currentPhoto.status !== 'processing') {
                    this.initStudioArtplayer();
                }
            });
        },

        pollProcessingStatus(photoUuid, uploadItem = null) {
            if (!this.videoStatusUrlBase) return;
            const url = `${this.videoStatusUrlBase}/${photoUuid}/status`;
            const interval = setInterval(async () => {
                try {
                    const res = await fetch(url, {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    if (data.status === 'ready') {
                        clearInterval(interval);
                        this.updateCardToReady(photoUuid, data.photo);
                        if (uploadItem) {
                            uploadItem.status = 'done';
                            uploadItem.progress = 100;
                            uploadItem.errorMessage = '';
                        }
                    } else if (data.status === 'failed') {
                        clearInterval(interval);
                        this.updateCardToFailed(photoUuid, data.error);
                        if (uploadItem) {
                            uploadItem.status = 'error';
                            uploadItem.errorMessage = data.error || 'Video processing failed.';
                        }
                    }
                } catch (e) {
                    console.warn('Status poll exception:', e);
                }
            }, 3000);
        },

        updateCardToReady(uuid, photoData) {
            const idx = this.photos.findIndex(p => p.uuid === uuid);
            if (idx !== -1 && photoData) {
                Object.assign(this.photos[idx], photoData);
                this.photos[idx].status = 'ready';
            }
            const card = document.getElementById(`photo-card-${uuid}`);
            if (card) {
                const procOverlay = card.querySelector('.processing-overlay');
                if (procOverlay) procOverlay.remove();

                const img = card.querySelector('img');
                if (img && photoData && (photoData.thumbnail_url || photoData.poster_url)) {
                    img.src = photoData.thumbnail_url || photoData.poster_url;
                }

                if (photoData && photoData.duration) {
                    let badge = card.querySelector('.video-duration-badge');
                    if (!badge) {
                        badge = document.createElement('div');
                        badge.className = 'video-duration-badge absolute bottom-2 left-2 z-10';
                        badge.innerHTML = `<span class="px-2 py-0.5 rounded-full bg-black/75 backdrop-blur-md text-white text-[10px] font-semibold flex items-center gap-1 shadow">
                            <svg class="w-2.5 h-2.5 fill-current text-primary" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            <span>${photoData.duration}</span>
                        </span>`;
                        const container = card.querySelector('.aspect-square');
                        if (container) container.appendChild(badge);
                    }
                }
            }
        },

        updateCardToFailed(uuid, error) {
            const card = document.getElementById(`photo-card-${uuid}`);
            if (card) {
                const procOverlay = card.querySelector('.processing-overlay');
                if (procOverlay) {
                    procOverlay.classList.remove('bg-black/40');
                    procOverlay.classList.add('bg-destructive/80');
                    procOverlay.innerHTML = `<span class="text-[11px] font-semibold text-white px-2 text-center">Processing failed</span>`;
                }
            }
        },

        // --- Photo Operations ---

        /**
         * Toggle photo visibility
         */
        async toggleHide(photoUuid) {
            const photo = this.photos.find(p => p.uuid === photoUuid);
            const card = document.getElementById(`photo-card-${photoUuid}`);
            const originalHidden = photo ? photo.is_hidden : (card ? card.classList.contains('is-hidden') : false);
            const newHidden = !originalHidden;

            // Optimistic DOM update
            if (photo) photo.is_hidden = newHidden;
            if (card) {
                card.classList.toggle('opacity-65', newHidden);
                card.classList.toggle('grayscale-[30%]', newHidden);
                card.classList.toggle('is-hidden', newHidden);
                const badge = card.querySelector('.hidden-badge');
                if (badge) badge.classList.toggle('hidden', !newHidden);
                const hiddenLabel = card.querySelector('.hidden-label');
                if (hiddenLabel) hiddenLabel.classList.toggle('hidden', !newHidden);
                const setCoverBtn = card.querySelector('.btn-set-cover');
                if (setCoverBtn) {
                    const isCover = photo ? photo.is_cover : card.classList.contains('is-cover');
                    setCoverBtn.classList.toggle('hidden', newHidden || isCover);
                }
                const toggleBtn = card.querySelector('.btn-toggle-hide');
                if (toggleBtn) {
                    toggleBtn.classList.toggle('bg-amber-500', newHidden);
                    toggleBtn.classList.toggle('hover:bg-amber-600', newHidden);
                    toggleBtn.classList.toggle('bg-black/50', !newHidden);
                    toggleBtn.classList.toggle('hover:bg-black/80', !newHidden);
                }
            }

            try {
                const res = await fetch(`${this.toggleHideUrlBase}/${photoUuid}/hide`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!res.ok) throw new Error('Failed to update photo visibility');
                const data = await res.json();

                if (photo) {
                    photo.is_hidden = data.is_hidden;
                    if (data.reassigned_cover_id) {
                        this.updateCoverSelection(data.reassigned_cover_id);
                    }
                }
            } catch (err) {
                // Rollback on failure
                if (photo) photo.is_hidden = originalHidden;
                if (card) {
                    card.classList.toggle('opacity-65', originalHidden);
                    card.classList.toggle('grayscale-[30%]', originalHidden);
                    card.classList.toggle('is-hidden', originalHidden);
                    const badge = card.querySelector('.hidden-badge');
                    if (badge) badge.classList.toggle('hidden', !originalHidden);
                }
                alert('Could not update photo visibility. Please try again.');
            }
        },

        /**
         * Set cover photo
         */
        async setCover(photoUuid) {
            const photo = this.photos.find(p => p.uuid === photoUuid);
            if (photo && photo.is_hidden) {
                alert('A hidden photo cannot be set as the collection cover.');
                return;
            }

            const prevCoverPhoto = this.photos.find(p => p.is_cover);
            if (photo) {
                this.photos.forEach(p => { p.is_cover = false; });
                photo.is_cover = true;
            }
            this.updateCoverSelection(photo ? photo.id : null, photoUuid);

            try {
                const res = await fetch(this.coverUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ photo_uuid: photoUuid })
                });

                if (!res.ok) {
                    const errData = await res.json().catch(() => ({}));
                    throw new Error(errData.message || 'Failed to update cover photo');
                }
            } catch (err) {
                if (prevCoverPhoto) {
                    this.photos.forEach(p => { p.is_cover = false; });
                    prevCoverPhoto.is_cover = true;
                    this.updateCoverSelection(prevCoverPhoto.id, prevCoverPhoto.uuid);
                }
                alert(err.message || 'Could not set cover photo. Please try again.');
            }
        },

        updateCoverSelection(coverPhotoId, coverPhotoUuid = null) {
            document.querySelectorAll('.photo-card').forEach(c => {
                const isTarget = (coverPhotoUuid && c.dataset.uuid === coverPhotoUuid) ||
                                 (coverPhotoId && Number(c.dataset.id) === Number(coverPhotoId));
                c.classList.toggle('border-primary', isTarget);
                c.classList.toggle('ring-2', isTarget);
                c.classList.toggle('ring-primary/40', isTarget);
                c.classList.toggle('is-cover', isTarget);
                c.classList.toggle('border-border', !isTarget);
                const badge = c.querySelector('.cover-badge');
                if (badge) badge.classList.toggle('hidden', !isTarget);
                const btn = c.querySelector('.btn-set-cover');
                const isHidden = c.classList.contains('is-hidden');
                if (btn) btn.classList.toggle('hidden', isTarget || isHidden);
            });
        },

        /**
         * Delete photo
         */
        async deletePhoto(photoUuid) {
            if (!confirm('Are you sure you want to delete this photo? It can be restored from Trash.')) {
                return;
            }

            const card = document.getElementById(`photo-card-${photoUuid}`);
            if (card) {
                card.style.opacity = '0.2';
                card.style.pointerEvents = 'none';
            }

            this.totalPhotos = Math.max(0, this.totalPhotos - 1);

            try {
                const res = await fetch(`${this.deletePhotoUrlBase}/${photoUuid}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!res.ok) throw new Error('Deletion failed on server');

                const data = await res.json();
                if (card) card.remove();

                const deletedIdx = this.photos.findIndex(p => p.uuid === photoUuid);
                if (deletedIdx !== -1) {
                    this.photos.splice(deletedIdx, 1);
                }
                photoUuids.delete(photoUuid);

                if (data.gallery_cover_photo_id) {
                    this.updateCoverSelection(data.gallery_cover_photo_id);
                }
            } catch (err) {
                if (card) {
                    card.style.opacity = '';
                    card.style.pointerEvents = '';
                }
                this.totalPhotos++;
                alert('Failed to delete photo. It has been restored.');
            }
        },

        async deleteInLightbox(photoUuid) {
            const currentIdx = this.lightboxIndex;
            await this.deletePhoto(photoUuid);

            if (this.photos.length === 0) {
                this.closeLightbox();
            } else if (this.lightboxIndex >= this.photos.length) {
                this.lightboxIndex = Math.max(0, this.photos.length - 1);
            }
        },

        // --- Upload Resilience & Queue Management ---
        setupNetworkResilience() {
            window.addEventListener('online', () => {
                let resumed = 0;
                this.uploads.forEach(u => {
                    if (u.status === 'error' || u.status === 'retrying') {
                        const task = uploadTasks.get(u.id);
                        if (task) {
                            task.loadedBytes = 0;
                            task.userCancelled = false;
                        }
                        u.status = 'queued';
                        u.retryCount = 0;
                        u.errorMessage = '';
                        resumed++;
                    }
                });
                if (resumed > 0) {
                    this.runQueue();
                }
            });
        },

        setupUnloadGuard() {
            window.addEventListener('beforeunload', (e) => {
                if (this.isUploading) {
                    const msg = 'Uploads are still in progress. Completed photos are already saved, but unfinished uploads may be interrupted if you leave this page.';
                    e.preventDefault();
                    e.returnValue = msg;
                    return msg;
                }
            });
        },

        setupCrossTabBillingListener() {
            // 1. BroadcastChannel API for multi-tab/window synchronization
            if (typeof window.BroadcastChannel !== 'undefined') {
                try {
                    const bc = new BroadcastChannel('ifotoset_billing');
                    bc.onmessage = (ev) => {
                        if (ev.data && ev.data.type === 'BILLING_COMPLETED') {
                            this.checkAndApplyUpgradedStorage(true);
                        }
                    };
                } catch (_) {}
            }

            // 2. Storage event fallback (cross-window storage listener)
            window.addEventListener('storage', (e) => {
                if (e.key === 'ifotoset_billing_completed') {
                    this.checkAndApplyUpgradedStorage(true);
                }
            });

            // 3. Window focus fallback (when photographer returns to gallery tab)
            window.addEventListener('focus', () => {
                if (this.quotaErrorState || this.batchQuotaWarning) {
                    this.checkAndApplyUpgradedStorage(false);
                }
            });
        },

        async checkAndApplyUpgradedStorage(isDirectReturn = false, isUserInitiated = false) {
            if (!this.storageStatsUrl) return;

            if (isUserInitiated) {
                this.isCheckingStatus = true;
                this.checkStatusFeedback = null;
            }

            try {
                const res = await fetch(this.storageStatsUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!res.ok) {
                    if (isUserInitiated) {
                        this.checkStatusFeedback = {
                            type: 'error',
                            message: 'Could not connect to verify storage. Please try again.'
                        };
                    }
                    return;
                }

                const stats = await res.json();
                const prevLimit = this.userStorage ? (this.userStorage.limit_bytes || 0) : 0;
                const newLimit = stats.limit_bytes || 0;
                const prevPlan = this.userStorage ? this.userStorage.plan_id : null;
                const planUpgraded = (stats.plan_id !== prevPlan) || (newLimit > prevLimit);

                // Check if space was freed (e.g. from trash) or is sufficient for failed items
                const requiredBytes = this.quotaErrorState ? (this.quotaErrorState.requiredBytes || 1) : 1;
                const hasEnoughSpace = (stats.available_bytes >= requiredBytes) || (stats.available_bytes > 0 && this.uploads.some(u => u.status === 'error' && u.size <= stats.available_bytes));

                this.userStorage = stats;

                if (planUpgraded || hasEnoughSpace || isDirectReturn) {
                    this.quotaErrorState = null;
                    this.batchQuotaWarning = null;

                    if (planUpgraded || isDirectReturn) {
                        this.upgradedCelebration = {
                            planName: stats.plan_name || 'Upgraded Plan',
                            storageLimitFormatted: stats.limit_formatted || formatBytes(stats.limit_bytes)
                        };
                    }

                    if (isUserInitiated) {
                        this.checkStatusFeedback = {
                            type: 'success',
                            message: planUpgraded
                                ? `Success! Upgraded to ${stats.plan_name || 'new plan'}. Resuming uploads...`
                                : `Storage capacity available (${stats.available_formatted || formatBytes(stats.available_bytes)} free). Resuming uploads...`
                        };
                    }

                    // Auto-resume uploads paused by quota error
                    this.retryAllFailed();

                    if (isUserInitiated) {
                        setTimeout(() => { this.checkStatusFeedback = null; }, 5000);
                    }
                } else {
                    // Still insufficient storage
                    if (this.quotaErrorState) {
                        this.quotaErrorState.availableBytes = stats.available_bytes;
                        this.quotaErrorState.formattedAvailable = stats.available_formatted || formatBytes(stats.available_bytes);
                        this.quotaErrorState.usedBytes = stats.used_bytes;
                        this.quotaErrorState.limitBytes = stats.limit_bytes;
                        this.quotaErrorState.formattedLimit = stats.limit_formatted || formatBytes(stats.limit_bytes);
                    }

                    if (isUserInitiated) {
                        const avail = stats.available_formatted || formatBytes(stats.available_bytes);
                        this.checkStatusFeedback = {
                            type: 'warning',
                            message: `Storage still full (${avail} available). Complete your upgrade or empty trash.`
                        };
                    }
                }
            } catch (err) {
                console.error('Failed to query updated storage stats:', err);
                if (isUserInitiated) {
                    this.checkStatusFeedback = {
                        type: 'error',
                        message: 'Network error while checking storage status.'
                    };
                }
            } finally {
                if (isUserInitiated) {
                    this.isCheckingStatus = false;
                }
            }
        },

        openUpgradeWindow(targetUrl = null) {
            const url = targetUrl || this.billingPlansUrl || this.billingCheckoutUrl;
            if (url) {
                window.open(url, '_blank');
            }
        },

        handleFileInput(e) {
            if (e.target && e.target.files) {
                this.addFiles(e.target.files);
                e.target.value = '';
            }
        },

        addFiles(fileList) {
            if (!fileList || fileList.length === 0) return;

            const filesArray = Array.from(fileList).filter(f => f.type.startsWith('image/') || f.type.startsWith('video/') || /\.(mp4|mov|webm)$/i.test(f.name));
            if (filesArray.length === 0) return;

            // Preflight batch size check against remaining quota
            const totalBatchBytes = filesArray.reduce((acc, f) => acc + f.size, 0);
            if (this.userStorage && typeof this.userStorage.available_bytes === 'number') {
                const available = Number(this.userStorage.available_bytes);
                if (totalBatchBytes > available) {
                    this.batchQuotaWarning = {
                        batchBytes: totalBatchBytes,
                        formattedBatchSize: formatBytes(totalBatchBytes),
                        availableBytes: available,
                        formattedAvailable: formatBytes(available),
                        deficitBytes: totalBatchBytes - available,
                        formattedDeficit: formatBytes(totalBatchBytes - available)
                    };
                } else {
                    this.batchQuotaWarning = null;
                }
            }

            const newItems = filesArray.map(file => {
                const id = 'up-' + Math.random().toString(36).substring(2, 9) + '-' + Date.now();
                const isVideo = file.type.startsWith('video/') || /\.(mp4|mov|webm)$/i.test(file.name);

                // Non-reactive upload task storage
                uploadTasks.set(id, {
                    xhr: null,
                    sessionId: null,
                    loadedBytes: 0,
                    userCancelled: false,
                    lastProgressUpdate: 0
                });

                this.uploadStats.totalBytes += file.size;

                // Lightweight item in Alpine queue - ZERO previewUrl for queued items!
                return {
                    id: id,
                    file: file,
                    name: file.name,
                    formattedSize: formatBytes(file.size),
                    size: file.size,
                    isVideo: isVideo,
                    previewUrl: null,
                    status: 'queued',
                    progress: 0,
                    retryCount: 0,
                    errorMessage: '',
                    isQuotaError: false
                };
            });

            this.statusSummary = null;
            this.uploads.push(...newItems);
            this.runQueue();
        },

        runQueue() {
            const active = this.uploads.filter(u => u.status === 'preparing' || u.status === 'uploading' || u.status === 'processing');
            const availableSlots = UPLOAD_CONFIG.concurrency - active.length;

            if (availableSlots <= 0) return;

            const queuedItems = this.uploads.filter(u => u.status === 'queued').slice(0, availableSlots);
            for (const item of queuedItems) {
                this.startUpload(item);
            }
        },

        cleanupItemPreview(item) {
            if (item && item.previewUrl) {
                try {
                    URL.revokeObjectURL(item.previewUrl);
                } catch (_) {}
                item.previewUrl = null;
            }
        },

        async startUpload(item) {
            const task = uploadTasks.get(item.id);
            if (!task || task.userCancelled) return;

            item.status = 'preparing';
            item.progress = 0;
            item.errorMessage = '';

            // Generate active preview only for the active upload slot (max 5 active)
            try {
                item.previewUrl = URL.createObjectURL(item.file);
            } catch (_) {}

            let sessionId = null;

            try {
                // 1. Calculate integrity hash via dedicated Web Worker (1 at a time, off main thread)
                const sha256 = await calculateFileSha256(item.file);
                if (item.status === 'cancelled' || task.userCancelled) {
                    this.cleanupItemPreview(item);
                    return;
                }

                // If video, probe declared duration for quota reservation preflight
                let declaredDuration = null;
                if (item.isVideo) {
                    try {
                        declaredDuration = await getVideoDuration(item.file);
                    } catch (_) {}
                }

                // 2. Request upload slot
                const reqRes = await fetch(this.uploadRequestUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken
                    },
                    body: JSON.stringify({
                        gallery_id: this.galleryUuid,
                        gallery_uuid: this.galleryUuid,
                        filename: item.file.name,
                        file_size: item.file.size,
                        size_bytes: item.file.size,
                        mime_type: item.file.type || (item.isVideo ? 'video/mp4' : 'image/jpeg'),
                        sha256: sha256,
                        duration_seconds: declaredDuration
                    })
                });

                if (!reqRes.ok) {
                    const errData = await reqRes.json().catch(() => ({}));
                    if (reqRes.status === 409 || errData.code === 'STORAGE_QUOTA_EXCEEDED' || errData.is_quota_error) {
                        const required = errData.required_bytes || item.file.size;
                        const available = errData.available_bytes !== undefined ? errData.available_bytes : (this.userStorage ? this.userStorage.available_bytes : 0);
                        const limit = errData.limit_bytes || (this.userStorage ? this.userStorage.limit_bytes : 0);
                        const used = errData.used_bytes || (this.userStorage ? this.userStorage.used_bytes : 0);
                        const reserved = errData.reserved_bytes || 0;

                        this.quotaErrorState = {
                            requiredBytes: required,
                            formattedRequired: formatBytes(required),
                            availableBytes: available,
                            formattedAvailable: formatBytes(available),
                            limitBytes: limit,
                            formattedLimit: formatBytes(limit),
                            usedBytes: used,
                            reservedBytes: reserved,
                            upgradeUrl: errData.upgrade_url || this.billingCheckoutUrl
                        };

                        item.isQuotaError = true;
                        item.status = 'error';
                        item.errorMessage = 'Storage quota reached. Upgrade your plan to continue.';
                        this.cleanupItemPreview(item);
                        return;
                    }
                    if (errData.code === 'VIDEO_NOT_SUPPORTED' || errData.code === 'VIDEO_QUOTA_EXCEEDED' || errData.code === 'VIDEO_FILE_SIZE_EXCEEDED') {
                        item.status = 'error';
                        item.errorMessage = errData.message || 'Video plan limit exceeded.';
                        this.cleanupItemPreview(item);
                        return;
                    }
                    throw new Error(errData.message || 'Unable to prepare upload');
                }

                const reqData = await reqRes.json();
                const presignedUrl = reqData.presigned_url || reqData.upload_url;
                sessionId = reqData.upload_session_id || reqData.session_id;
                task.sessionId = sessionId;
                const extraHeaders = reqData.headers || {};

                if (!presignedUrl || !sessionId) {
                    throw new Error('Invalid upload session');
                }

                if (item.status === 'cancelled' || task.userCancelled) {
                    this.abortSession(sessionId);
                    this.cleanupItemPreview(item);
                    return;
                }

                // 3. Direct binary upload to signed storage URL
                item.status = 'uploading';

                await new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    task.xhr = xhr;
                    xhr.open('PUT', presignedUrl, true);
                    xhr.setRequestHeader('Content-Type', item.file.type || 'image/jpeg');

                    if (extraHeaders) {
                        for (const [k, v] of Object.entries(extraHeaders)) {
                            try {
                                xhr.setRequestHeader(k, v);
                            } catch (_) {}
                        }
                    }

                    xhr.upload.onprogress = (e) => {
                        if (!e.lengthComputable) return;

                        // Byte delta accounting for accurate O(1) overall progress
                        const currentLoaded = Math.min(e.loaded, item.size);
                        const delta = currentLoaded - task.loadedBytes;
                        if (delta > 0) {
                            task.loadedBytes = currentLoaded;
                            this.uploadStats.uploadedBytes += delta;
                        }

                        // Throttled Alpine reactive state updates (~100ms / 2% delta)
                        const now = performance.now();
                        const progress = Math.floor((e.loaded / e.total) * 100);

                        if (progress === 100 || progress - item.progress >= UPLOAD_CONFIG.progressMinDeltaPercent || (now - task.lastProgressUpdate) >= UPLOAD_CONFIG.progressThrottleMs) {
                            task.lastProgressUpdate = now;
                            item.progress = progress;
                        }
                    };

                    xhr.onload = () => {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            resolve();
                        } else {
                            reject(new Error(xhr.status === 413 ? 'Photo exceeds maximum allowed size (50MB)' : 'Upload could not be completed'));
                        }
                    };

                    xhr.onerror = () => reject(new Error('Network error during upload'));
                    xhr.ontimeout = () => reject(new Error('Network timeout during upload'));
                    xhr.onabort = () => reject(new Error('Cancelled'));
                    xhr.send(item.file);
                });

                if (item.status === 'cancelled' || task.userCancelled) {
                    this.abortSession(sessionId);
                    this.cleanupItemPreview(item);
                    return;
                }

                // 4. Confirm photo upload and commit database record
                item.status = 'processing';

                const confirmRes = await fetch(this.uploadConfirmUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken
                    },
                    body: JSON.stringify({
                        upload_session_id: sessionId,
                        session_id: sessionId
                    })
                });

                if (!confirmRes.ok) {
                    const errData = await confirmRes.json().catch(() => ({}));
                    throw new Error(errData.message || 'Photo confirmation could not be completed');
                }

                const confirmData = await confirmRes.json();

                // Accurate byte accounting: ensure full size credited
                const remainingDelta = item.size - task.loadedBytes;
                if (remainingDelta > 0) {
                    this.uploadStats.uploadedBytes += remainingDelta;
                    task.loadedBytes = item.size;
                }

                // Revoke preview immediately upon completion to free RAM
                this.cleanupItemPreview(item);

                // Live Background Gallery Insertion!
                const photoData = (confirmData && confirmData.data) ? confirmData.data : confirmData;
                this.handlePhotoUploaded(photoData);

                if (item.isVideo && photoData && photoData.status === 'processing') {
                    item.status = 'processing';
                    this.pollProcessingStatus(photoData.uuid, item);
                } else {
                    item.status = 'done';
                    item.progress = 100;
                    item.errorMessage = '';
                }

            } catch (err) {
                if (item.status === 'cancelled' || task.userCancelled) {
                    this.cleanupItemPreview(item);
                    return;
                }

                // Roll back byte counter for failed attempt to prevent double-counting on retry
                if (task.loadedBytes > 0) {
                    this.uploadStats.uploadedBytes = Math.max(0, this.uploadStats.uploadedBytes - task.loadedBytes);
                    task.loadedBytes = 0;
                }

                // Auto-retry with backoff on network issues
                if (item.retryCount < UPLOAD_CONFIG.maxRetries) {
                    item.retryCount++;
                    item.status = 'retrying';
                    item.errorMessage = `Network interrupted. Retrying... (attempt ${item.retryCount}/${UPLOAD_CONFIG.maxRetries})`;
                    const delay = Math.min(1000 * Math.pow(2, item.retryCount), 8000);
                    setTimeout(() => {
                        if (item.status === 'retrying') {
                            item.status = 'queued';
                            this.runQueue();
                        }
                    }, delay);
                    return;
                }

                this.cleanupItemPreview(item);
                item.status = 'error';
                item.errorMessage = cleanErrorMessage(err.message);
            } finally {
                this.runQueue();
                this.checkAllCompleted();
            }
        },

        handlePhotoUploaded(photo) {
            if (!photo || !photo.uuid) return;

            // O(1) deduplication check using internal Set
            if (photoUuids.has(photo.uuid)) {
                return;
            }
            photoUuids.add(photo.uuid);

            // 1. Update canonical photo data source
            this.photos.unshift(photo);
            this.totalPhotos++;

            // 2. Insert card into DOM (rendering optimization)
            this.insertPhotoCard(photo);
        },

        insertPhotoCard(photo) {
            const grid = document.getElementById('studio-photos-grid');
            if (!grid) return;

            const cardEl = this.createPhotoCardElement(photo);
            cardEl.classList.add('transition-all', 'duration-500', 'opacity-0', 'scale-95');
            grid.prepend(cardEl);

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    cardEl.classList.remove('opacity-0', 'scale-95');
                    cardEl.classList.add('opacity-100', 'scale-100');
                });
            });
        },

        abortSession(sessionId) {
            if (!sessionId) return;
            fetch(this.uploadAbortUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                body: JSON.stringify({ upload_session_id: sessionId, session_id: sessionId })
            }).catch(() => {});
        },

        cancelUpload(id) {
            const item = this.uploads.find(u => u.id === id);
            if (!item) return;

            const task = uploadTasks.get(id);
            if (task) {
                task.userCancelled = true;
                if (task.loadedBytes > 0) {
                    this.uploadStats.uploadedBytes = Math.max(0, this.uploadStats.uploadedBytes - task.loadedBytes);
                    task.loadedBytes = 0;
                }
                if (task.xhr) {
                    try { task.xhr.abort(); } catch (_) {}
                }
                if (task.sessionId) {
                    this.abortSession(task.sessionId);
                }
            }

            this.cleanupItemPreview(item);
            item.status = 'cancelled';
            item.isQuotaError = false;
            this.runQueue();
            this.checkAllCompleted();
        },

        retryUpload(id) {
            const item = this.uploads.find(u => u.id === id);
            if (!item) return;

            const task = uploadTasks.get(id);
            if (task) {
                task.loadedBytes = 0;
                task.userCancelled = false;
                task.lastProgressUpdate = 0;
            }

            item.status = 'queued';
            item.isQuotaError = false;
            item.progress = 0;
            item.retryCount = 0;
            item.errorMessage = '';
            this.statusSummary = null;
            this.runQueue();
        },

        retryAllFailed() {
            this.uploads.forEach(item => {
                if (item.status === 'error' || item.status === 'cancelled') {
                    const task = uploadTasks.get(item.id);
                    if (task) {
                        task.loadedBytes = 0;
                        task.userCancelled = false;
                        task.lastProgressUpdate = 0;
                    }
                    item.status = 'queued';
                    item.isQuotaError = false;
                    item.progress = 0;
                    item.retryCount = 0;
                    item.errorMessage = '';
                }
            });
            this.quotaErrorState = null;
            this.batchQuotaWarning = null;
            this.statusSummary = null;
            this.runQueue();
        },

        checkAllCompleted() {
            const stillActive = this.uploads.some(u => u.status === 'queued' || u.status === 'preparing' || u.status === 'uploading' || u.status === 'retrying' || u.status === 'processing');
            if (stillActive) return;

            const completed = this.uploads.filter(u => u.status === 'done').length;
            const failed = this.uploads.filter(u => u.status === 'error').length;
            const quotaFailed = this.uploads.filter(u => u.status === 'error' && u.isQuotaError).length;

            if (completed > 0 && failed === 0) {
                this.statusSummary = {
                    type: 'success',
                    message: `All ${completed} photos uploaded and saved to your gallery!`
                };
            } else if (quotaFailed > 0) {
                // Quota banner displays detailed recovery instructions; suppress duplicate generic red error
                this.statusSummary = null;
            } else if (completed > 0 && failed > 0) {
                this.statusSummary = {
                    type: 'warning',
                    message: `${completed} photo${completed > 1 ? 's' : ''} saved to gallery. ${failed} interrupted. You can click retry.`
                };
            } else if (failed > 0) {
                this.statusSummary = {
                    type: 'error',
                    message: `Upload could not be completed for ${failed} photo${failed > 1 ? 's' : ''}. Please retry.`
                };
            }
        },

        refreshGallery() {
            window.location.reload();
        }
    };
};

// Global bridge helpers so dynamically inserted cards can trigger Alpine methods
window._openLightboxByUuid = function(uuid) {
    const el = document.querySelector('[x-data]');
    if (el && el._x_dataStack && el._x_dataStack[0]) {
        el._x_dataStack[0].openLightboxByUuid(uuid);
    }
};

window._openLightboxByIndex = function(idx) {
    const el = document.querySelector('[x-data]');
    if (el && el._x_dataStack && el._x_dataStack[0]) {
        el._x_dataStack[0].openLightboxByIndex(idx);
    }
};

window._toggleHide = function(uuid) {
    const el = document.querySelector('[x-data]');
    if (el && el._x_dataStack && el._x_dataStack[0]) {
        el._x_dataStack[0].toggleHide(uuid);
    }
};

window._setCover = function(uuid) {
    const el = document.querySelector('[x-data]');
    if (el && el._x_dataStack && el._x_dataStack[0]) {
        el._x_dataStack[0].setCover(uuid);
    }
};

window._deletePhoto = function(uuid) {
    const el = document.querySelector('[x-data]');
    if (el && el._x_dataStack && el._x_dataStack[0]) {
        el._x_dataStack[0].deletePhoto(uuid);
    }
};

if (window.Alpine) {
    Alpine.data('studioGalleryManager', window.studioGalleryManager);
}
document.addEventListener('alpine:init', () => {
    Alpine.data('studioGalleryManager', window.studioGalleryManager);
});
</script>

<div class="space-y-8 relative"
     x-data="studioGalleryManager()"
     x-init="init()">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-border">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('studio.galleries.index') }}" class="text-xs font-semibold text-muted-foreground hover:text-foreground">
                    &larr; Galleries
                </a>
                <span class="text-xs text-muted-foreground">&bull;</span>
                <x-ui.badge :variant="$gallery->visibility === 'public' ? 'default' : ($gallery->visibility === 'password' ? 'warning' : 'muted')">
                    {{ $gallery->visibility === 'private' ? 'Unlisted' : ($gallery->visibility === 'password' ? 'PIN Protected' : 'Public') }}
                </x-ui.badge>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">{{ $gallery->title }}</h1>
            <p class="text-xs text-muted-foreground mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                <span>
                    <span x-text="totalPhotos">{{ $totalPhotosCount }}</span> <span x-text="totalPhotos === 1 ? 'item' : 'items'">{{ Str::plural('photo', $totalPhotosCount) }}</span>
                </span>
                <span>&bull;</span>
                <span>Slug: <span class="font-mono text-primary">{{ $gallery->slug }}</span></span>
                @if(auth()->user()->hasVideoSupport())
                    <span>&bull;</span>
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-secondary/80 border border-border text-[11px] font-medium text-foreground">
                        <svg class="w-3 h-3 text-primary fill-current" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                        <span>Video: {{ auth()->user()->getVideoUsageFormatted() }}</span>
                    </span>
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ $gallery->public_url }}" target="_blank"
               class="px-3.5 py-2 rounded-xl bg-card border border-border text-foreground hover:bg-secondary text-xs font-semibold shadow-sm transition-all flex items-center gap-1.5">
                View Public Gallery &nearr;
            </a>
            <a href="{{ route('studio.galleries.edit', $gallery->uuid) }}"
               class="px-3.5 py-2 rounded-xl bg-card border border-border text-foreground hover:bg-secondary text-xs font-semibold shadow-sm transition-all">
                Settings
            </a>
            <x-ui.button @click="$dispatch('open-modal', 'upload-photos-modal')" variant="primary" size="sm">
                + Upload Photos & Videos
            </x-ui.button>
        </div>
    </div>

    <!-- Upload Dropzone Hero (when gallery is empty) -->
    <div x-show="totalPhotos === 0"
         x-cloak
         class="rounded-2xl border-2 border-dashed border-border p-12 text-center bg-card flex flex-col items-center justify-center transition-all hover:border-primary/50 cursor-pointer"
         @dragover.prevent="isDragging = true"
         @dragleave.prevent="isDragging = false"
         @drop.prevent="isDragging = false; $dispatch('open-modal', 'upload-photos-modal'); addFiles($event.dataTransfer.files)"
         @click="$dispatch('open-modal', 'upload-photos-modal')"
         :class="isDragging ? 'border-primary bg-primary/5 ring-4 ring-primary/10' : ''"
         style="{{ $photos->isEmpty() ? '' : 'display: none;' }}">
        <div class="w-16 h-16 rounded-2xl bg-primary/10 text-primary flex items-center justify-center text-2xl font-bold mb-4">
            📸
        </div>
        <h2 class="text-lg font-bold text-foreground">This collection is currently empty</h2>
        <p class="text-xs text-muted-foreground max-w-sm mt-1 mb-6">
            Drag and drop your photos and videos to upload and share with your clients in full quality.
        </p>
        <x-ui.button type="button" variant="primary">
            Select Photos & Videos to Upload
        </x-ui.button>
    </div>

    <!-- Photos Grid (Stable DOM container) -->
    <div x-show="totalPhotos > 0"
         class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4"
         id="studio-photos-grid"
         style="{{ $photos->isEmpty() ? 'display: none;' : '' }}">
        @foreach($photos as $photo)
            @php
                $thumbUrl = $photo->getThumbnailUrl('sm') ?: $photo->getUrl('sm');
                $isCover = $gallery->cover_photo_id === $photo->id;
                $isHidden = (bool) $photo->is_hidden;
                $isVideo = $photo->isVideo();
                $isProcessing = $photo->status === 'processing';
            @endphp
            <div id="photo-card-{{ $photo->uuid }}"
                 data-uuid="{{ $photo->uuid }}"
                 data-id="{{ $photo->id }}"
                 class="photo-card group relative rounded-xl overflow-hidden bg-card border {{ $isCover ? 'border-primary ring-2 ring-primary/40 is-cover' : 'border-border' }} {{ $isHidden ? 'opacity-65 grayscale-[30%] is-hidden' : '' }} shadow-sm flex flex-col justify-between transition-all duration-300">

                <div class="aspect-square bg-muted relative overflow-hidden cursor-pointer"
                     @click="openLightboxByUuid('{{ $photo->uuid }}')">
                    <img src="{{ $thumbUrl }}"
                         alt="{{ $photo->original_filename }}"
                         loading="lazy"
                         class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" />

                    <!-- Cover Badge -->
                    <div class="cover-badge absolute top-2 left-2 z-10 {{ $isCover ? '' : 'hidden' }}">
                        <span class="px-2 py-0.5 rounded bg-primary text-primary-foreground text-[10px] font-bold uppercase tracking-wider shadow">Cover</span>
                    </div>

                    <!-- Hidden Badge -->
                    <div class="hidden-badge absolute top-2 right-2 z-10 {{ $isHidden ? '' : 'hidden' }}">
                        <span class="px-2 py-0.5 rounded bg-amber-500/90 text-white text-[10px] font-bold tracking-wider shadow flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" /></svg>
                            Hidden
                        </span>
                    </div>

                    @if($isVideo)
                        <div class="video-duration-badge absolute bottom-2 left-2 z-10 pointer-events-none">
                            <span class="px-2 py-0.5 rounded-full bg-black/75 backdrop-blur-md text-white text-[10px] font-semibold flex items-center gap-1 shadow">
                                <svg class="w-2.5 h-2.5 fill-current text-primary" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                <span>{{ $photo->duration_formatted ?: 'Video' }}</span>
                            </span>
                        </div>
                    @endif

                    @if($isProcessing)
                        <div class="processing-overlay absolute inset-0 bg-black/40 backdrop-blur-[2px] z-10 flex flex-col items-center justify-center text-white text-xs gap-1.5 pointer-events-none">
                            <svg class="w-5 h-5 animate-spin text-primary" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span class="font-medium">Processing...</span>
                        </div>
                    @endif

                    <!-- Hover Overlay Actions -->
                    <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-between p-2.5 z-20"
                         @click.stop>
                        <!-- Top Row: Preview, Hide/Show, Delete -->
                        <div class="flex items-center justify-between">
                            <button type="button"
                                    @click="openLightboxByUuid('{{ $photo->uuid }}')"
                                    class="p-1.5 rounded-lg bg-black/50 hover:bg-black/80 text-white transition-colors"
                                    title="Preview photo">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>

                            <div class="flex items-center gap-1.5">
                                <!-- Toggle Hide/Show Button -->
                                <button type="button"
                                        @click="toggleHide('{{ $photo->uuid }}')"
                                        class="btn-toggle-hide p-1.5 rounded-lg transition-colors text-white {{ $isHidden ? 'bg-amber-500 hover:bg-amber-600' : 'bg-black/50 hover:bg-black/80' }}"
                                        title="{{ $isHidden ? 'Show in client gallery' : 'Hide from client gallery' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        @if(!$isHidden)
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                        @else
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        @endif
                                    </svg>
                                </button>

                                <!-- Delete Button -->
                                <button type="button"
                                        @click="deletePhoto('{{ $photo->uuid }}')"
                                        class="p-1.5 rounded-lg bg-destructive/80 hover:bg-destructive text-white transition-colors"
                                        title="Delete photo">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Bottom Row: Set as Cover Button -->
                        <div class="cover-action-container">
                            <button type="button"
                                    @click="setCover('{{ $photo->uuid }}')"
                                    class="btn-set-cover w-full py-1.5 px-2 text-[11px] font-semibold bg-white/90 hover:bg-white text-black rounded-lg transition-colors text-center shadow-xs flex items-center justify-center gap-1.5 {{ ($isCover || $isHidden) ? 'hidden' : '' }}">
                                <svg class="w-3.5 h-3.5 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                Set as Cover
                            </button>
                            <div class="hidden-label text-[10px] text-white/80 text-center py-1 bg-black/40 rounded {{ $isHidden ? '' : 'hidden' }}">
                                Hidden from client gallery
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-2 text-[11px] text-muted-foreground truncate border-t border-border flex items-center justify-between">
                    <span class="truncate">{{ $photo->original_filename }}</span>
                    @if($photo->size)
                        <span class="text-[10px] opacity-60 shrink-0 font-mono">{{ round($photo->size / 1048576, 1) }}MB</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <!-- Infinite Scroll Sentinel -->
    <div id="infinite-scroll-sentinel"
         x-ref="sentinel"
         class="py-8 text-center transition-all"
         x-show="hasMore">
        <div x-show="loadingPhotos" class="flex items-center justify-center gap-2.5 text-xs text-muted-foreground">
            <svg class="w-4 h-4 animate-spin text-primary" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
            <span>Loading more photos...</span>
        </div>
        <div x-show="!loadingPhotos && hasMore" class="text-xs text-muted-foreground opacity-60">
            Scroll to load more photos
        </div>
    </div>

    <!-- Studio Lightbox Modal -->
    <div x-show="lightboxOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @keydown.escape.window="closeLightbox()"
         @keydown.left.window="prevLightboxPhoto()"
         @keydown.right.window="nextLightboxPhoto()"
         class="fixed inset-0 z-50 bg-black/95 backdrop-blur-md flex flex-col justify-between select-none"
         style="display: none;">

        <!-- Top Bar -->
        <div class="px-6 py-4 flex items-center justify-between text-white border-b border-white/10 z-10 bg-black/40">
            <div class="flex items-center gap-3">
                <span class="font-mono text-xs text-white/70" x-text="(lightboxIndex + 1) + ' / ' + photos.length"></span>
                <span class="text-white/30">&bull;</span>
                <span class="text-sm font-semibold truncate max-w-sm" x-text="currentPhoto ? (currentPhoto.original_filename || currentPhoto.filename) : ''"></span>
                <template x-if="currentPhoto && currentPhoto.is_cover">
                    <span class="px-2 py-0.5 rounded bg-primary text-primary-foreground text-[10px] font-bold uppercase tracking-wider">Cover</span>
                </template>
                <template x-if="currentPhoto && currentPhoto.is_hidden">
                    <span class="px-2 py-0.5 rounded bg-amber-500 text-white text-[10px] font-bold tracking-wider">Hidden</span>
                </template>
            </div>

            <div class="flex items-center gap-3">
                <!-- Toggle Hide in Lightbox -->
                <button type="button"
                        @click="if (currentPhoto) toggleHide(currentPhoto.uuid)"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5"
                        :class="currentPhoto && currentPhoto.is_hidden ? 'bg-amber-500 hover:bg-amber-600 text-white' : 'bg-white/10 hover:bg-white/20 text-white'">
                    <span x-text="currentPhoto && currentPhoto.is_hidden ? 'Make Visible' : 'Hide Photo'"></span>
                </button>

                <!-- Set Cover in Lightbox -->
                <template x-if="currentPhoto && !currentPhoto.is_cover && !currentPhoto.is_hidden">
                    <button type="button"
                            @click="setCover(currentPhoto.uuid)"
                            class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white text-xs font-semibold transition-colors">
                        Set as Cover
                    </button>
                </template>

                <!-- Delete in Lightbox -->
                <button type="button"
                        @click="if (currentPhoto) deleteInLightbox(currentPhoto.uuid)"
                        class="px-3 py-1.5 rounded-lg bg-destructive/80 hover:bg-destructive text-white text-xs font-semibold transition-colors flex items-center gap-1">
                    Delete
                </button>

                <button type="button"
                        @click="closeLightbox()"
                        class="p-2 rounded-xl text-white/70 hover:text-white hover:bg-white/10 transition-colors ml-2"
                        title="Close preview (Esc)">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <!-- Main Image Container with Navigation Arrows -->
        <div class="relative flex-1 flex items-center justify-center p-4 min-h-0 overflow-hidden"
             @click.self="closeLightbox()">

            <!-- Prev Arrow -->
            <button type="button"
                    @click.stop="prevLightboxPhoto()"
                    class="absolute left-6 z-20 p-3 rounded-full bg-black/50 hover:bg-black/80 text-white transition-all hover:scale-110 focus:outline-none"
                    title="Previous photo (Left arrow)">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>

            <!-- Image View -            <!-- Media View (Image or Video) -->
            <template x-if="currentPhoto">
                <div class="relative max-h-full max-w-full flex items-center justify-center">
                    <template x-if="currentPhoto.is_video || currentPhoto.media_type === 'video'">
                        <div class="relative flex flex-col items-center">
                            <template x-if="currentPhoto.status === 'processing'">
                                <div class="p-8 rounded-2xl bg-secondary/80 border border-border text-center flex flex-col items-center gap-3">
                                    <svg class="w-8 h-8 animate-spin text-primary" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                    <h4 class="text-sm font-bold text-foreground">Video is processing...</h4>
                                    <p class="text-xs text-muted-foreground max-w-xs">Optimizing video for fast streaming and generating thumbnails. It will appear here automatically once ready.</p>
                                </div>
                            </template>
                            <template x-if="currentPhoto.status !== 'processing'">
                                <div class="relative max-h-[82vh] max-w-[90vw] flex items-center justify-center">
                                    <div id="studio-artplayer-container"
                                         x-init="$nextTick(() => initStudioArtplayer())"
                                         class="w-[85vw] max-w-4xl h-[70vh] max-h-[82vh] rounded-lg shadow-2xl overflow-hidden bg-black flex items-center justify-center select-auto pointer-events-auto">
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                    <template x-if="!currentPhoto.is_video && currentPhoto.media_type !== 'video'">
                        <img :src="currentPhoto.full_url || currentPhoto.large_url || currentPhoto.original_url || currentPhoto.medium_url || currentPhoto.thumbnail_url"
                             :alt="currentPhoto.original_filename"
                             class="max-h-[82vh] max-w-[90vw] object-contain rounded-lg shadow-2xl transition-all duration-200 select-none" />
                    </template>
                </div>
            </template>

            <!-- Next Arrow -->
            <button type="button"
                    @click.stop="nextLightboxPhoto()"
                    class="absolute right-6 z-20 p-3 rounded-full bg-black/50 hover:bg-black/80 text-white transition-all hover:scale-110 focus:outline-none"
                    title="Next photo (Right arrow)">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>

        <!-- Bottom Bar / Photo Metadata -->
        <div class="px-6 py-3 border-t border-white/10 text-xs text-white/60 flex items-center justify-between bg-black/40">
            <div class="flex items-center gap-4">
                <template x-if="currentPhoto && currentPhoto.duration">
                    <span class="text-primary font-semibold" x-text="'Duration: ' + currentPhoto.duration"></span>
                </template>
                <template x-if="currentPhoto && currentPhoto.width && currentPhoto.height">
                    <span x-text="currentPhoto.width + ' × ' + currentPhoto.height + ' px'"></span>
                </template>
                <template x-if="currentPhoto && currentPhoto.size">
                    <span x-text="formatBytes(currentPhoto.size)"></span>
                </template>
            </div>
            <div class="flex items-center gap-2">
                <template x-if="currentPhoto && (currentPhoto.original_url || currentPhoto.full_url)">
                    <a :href="currentPhoto.original_url || currentPhoto.full_url"
                       target="_blank"
                       download
                       class="hover:text-white transition-colors underline">
                        Download Original &darr;
                    </a>
                </template>
            </div>
        </div>
    </div>

    <!-- Floating Background Upload Status Badge -->
    <div x-show="isUploading"
         x-transition
         class="fixed bottom-6 right-6 z-40 bg-card border border-border shadow-2xl rounded-2xl p-4 flex items-center gap-3.5 max-w-sm w-full"
         style="display: none;">
        <div class="relative flex items-center justify-center">
            <div class="w-7 h-7 rounded-full border-2 border-primary/20 border-t-primary animate-spin"></div>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold text-foreground flex items-center justify-between">
                <span>Uploading Photos</span>
                <span class="font-mono text-[11px] text-primary" x-text="completedCount + '/' + totalCount"></span>
            </p>
            <div class="h-1.5 bg-muted rounded-full overflow-hidden mt-1.5">
                <div class="h-full bg-primary rounded-full transition-all duration-300" :style="'width: ' + overallProgress + '%'"></div>
            </div>
        </div>
        <button type="button" @click="$dispatch('open-modal', 'upload-photos-modal')" class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-secondary hover:bg-secondary/80 text-foreground transition-colors shrink-0">
            View
        </button>
    </div>

    <!-- Upload Photos & Videos Modal -->
    <x-ui.modal name="upload-photos-modal" title="Upload Photos & Videos" maxWidth="2xl">
        <div class="space-y-5">
            <p class="text-xs text-muted-foreground">
                Select photos and videos to add to your collection. Upload high-resolution images and videos up to your plan limit.
            </p>

            <!-- Upgraded Plan Celebration Banner -->
            <template x-if="upgradedCelebration">
                <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-800 dark:text-emerald-300 flex items-start justify-between gap-3 shadow-xs">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-600 dark:text-emerald-300 flex items-center justify-center shrink-0 text-base">
                            ✨
                        </div>
                        <div>
                            <h4 class="font-bold text-xs uppercase tracking-wider text-emerald-800 dark:text-emerald-300">Plan Upgraded Successfully!</h4>
                            <p class="text-xs mt-0.5">
                                Your account is now on the <strong x-text="upgradedCelebration.planName"></strong> with <strong x-text="upgradedCelebration.storageLimitFormatted"></strong> of cloud storage. Unfinished uploads have been resumed.
                            </p>
                        </div>
                    </div>
                    <button type="button" @click="upgradedCelebration = null" class="text-emerald-600 hover:text-emerald-800 dark:hover:text-emerald-200 text-sm font-bold">&times;</button>
                </div>
            </template>

            <!-- Pre-flight Batch Quota Warning -->
            <template x-if="batchQuotaWarning && !quotaErrorState">
                <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-800 dark:text-amber-300 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs shadow-xs">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0 text-base">
                            ⚠️
                        </div>
                        <div>
                            <p class="font-bold">Selected files exceed remaining storage</p>
                            <p class="text-muted-foreground dark:text-amber-300/80 mt-0.5">
                                This batch is <strong x-text="batchQuotaWarning.formattedBatchSize"></strong>, but only <strong x-text="batchQuotaWarning.formattedAvailable"></strong> is left in your plan.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button" @click="openUpgradeWindow()" class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-semibold text-xs transition-colors shadow-xs">
                            Upgrade Plan &nearr;
                        </button>
                    </div>
                </div>
            </template>

            <!-- Active Quota Exhaustion Card -->
            <template x-if="quotaErrorState">
                <div class="p-4 sm:p-5 rounded-2xl bg-destructive/10 border-2 border-destructive/30 text-foreground flex flex-col gap-3.5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="w-9 h-9 rounded-xl bg-destructive/20 text-destructive flex items-center justify-center shrink-0 text-lg">
                                ⚡
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-destructive">Storage Quota Reached</h4>
                                <p class="text-xs text-muted-foreground mt-0.5">
                                    You have reached the <span class="font-semibold text-foreground" x-text="quotaErrorState.formattedLimit"></span> storage limit for your current plan. Your upload has been safely paused without losing your place.
                                </p>
                            </div>
                        </div>
                        <button type="button" @click="quotaErrorState = null" class="text-muted-foreground hover:text-foreground text-sm font-bold">&times;</button>
                    </div>

                    <!-- Storage Mini Bar Breakdown -->
                    <div class="p-3 bg-card rounded-xl border border-border/60 text-xs space-y-1.5">
                        <div class="flex justify-between items-center text-muted-foreground">
                            <span>Remaining capacity</span>
                            <span class="font-mono font-semibold text-destructive" x-text="quotaErrorState.formattedAvailable + ' available'"></span>
                        </div>
                        <div class="h-2 bg-secondary rounded-full overflow-hidden">
                            <div class="h-full bg-destructive rounded-full" style="width: 100%"></div>
                        </div>
                        <p class="text-[11px] text-muted-foreground">
                            Photo requires <span class="font-mono font-medium text-foreground" x-text="quotaErrorState.formattedRequired"></span> to upload.
                        </p>
                    </div>

                    <!-- Choose Plan Section -->
                    <template x-if="upgradePlans && upgradePlans.length > 0">
                        <div class="space-y-2 pt-2 border-t border-border/60">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-foreground">Choose a plan that fits your studio:</span>
                                <a :href="billingPlansUrl" target="_blank" class="text-primary hover:underline font-semibold flex items-center gap-1">
                                    <span>Compare all</span>
                                    <span>&nearr;</span>
                                </a>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <template x-for="plan in upgradePlans" :key="plan.slug">
                                    <button type="button"
                                            @click="openUpgradeWindow(plan.checkoutUrl)"
                                            class="p-2.5 rounded-xl border border-border/80 bg-card hover:border-primary hover:bg-primary/5 transition-all text-left flex items-center justify-between group shadow-xs">
                                        <div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-bold text-xs text-foreground group-hover:text-primary transition-colors" x-text="plan.name"></span>
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-primary/10 text-primary font-mono" x-text="plan.storage"></span>
                                            </div>
                                            <p class="text-[10px] text-muted-foreground mt-0.5" x-text="plan.price + ' / mo'"></p>
                                        </div>
                                        <span class="text-xs font-bold text-primary group-hover:translate-x-0.5 transition-transform">&nearr;</span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Check Status Feedback Notification (if any) -->
                    <template x-if="checkStatusFeedback">
                        <div class="p-2.5 rounded-xl text-xs font-medium flex items-center justify-between gap-2 transition-all"
                             :class="{
                                 'bg-amber-500/10 border border-amber-500/30 text-amber-700 dark:text-amber-300': checkStatusFeedback.type === 'warning',
                                 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300': checkStatusFeedback.type === 'success',
                                 'bg-destructive/10 border border-destructive/30 text-destructive': checkStatusFeedback.type === 'error'
                             }">
                            <div class="flex items-center gap-1.5">
                                <span x-text="checkStatusFeedback.type === 'success' ? '✓' : '⚠️'"></span>
                                <span x-text="checkStatusFeedback.message"></span>
                            </div>
                            <button type="button" @click="checkStatusFeedback = null" class="opacity-60 hover:opacity-100 text-sm">&times;</button>
                        </div>
                    </template>

                    <!-- Action Buttons: Upgrade Plan & Free Up Storage -->
                    <div class="flex flex-wrap items-center justify-between gap-2 pt-1 border-t border-border/40">
                        <a href="{{ route('studio.trash.index') }}" target="_blank" class="text-xs font-semibold text-muted-foreground hover:text-foreground hover:underline flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            Free up storage (empty trash) &nearr;
                        </a>

                        <div class="flex items-center gap-2">
                            <button type="button"
                                    @click="checkAndApplyUpgradedStorage(false, true)"
                                    :disabled="isCheckingStatus"
                                    class="px-3 py-1.5 rounded-xl border border-border bg-card hover:bg-secondary text-xs font-semibold transition-colors flex items-center gap-1.5 disabled:opacity-50">
                                <template x-if="isCheckingStatus">
                                    <svg class="w-3.5 h-3.5 animate-spin text-primary" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                </template>
                                <span x-text="isCheckingStatus ? 'Checking...' : 'Check Status'"></span>
                            </button>
                            <button type="button"
                                    @click="openUpgradeWindow(billingPlansUrl)"
                                    class="px-3.5 py-1.5 rounded-xl bg-primary hover:bg-primary/90 text-primary-foreground text-xs font-bold shadow transition-all flex items-center gap-1.5">
                                <span>Compare Plans</span>
                                <span>&nearr;</span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Dropzone Area -->
            <div class="border-2 border-dashed rounded-2xl p-6 sm:p-8 transition-all relative flex flex-col items-center justify-center text-center bg-secondary/10 hover:bg-secondary/20 hover:border-primary/50 cursor-pointer"
                 :class="isDragging ? 'border-primary bg-primary/5 ring-4 ring-primary/10' : 'border-border'"
                 @dragover.prevent="isDragging = true"
                 @dragleave.prevent="isDragging = false"
                 @drop.prevent="isDragging = false; addFiles($event.dataTransfer.files)">
                <input type="file"
                       id="photo-file-input"
                       multiple
                       accept="image/jpeg,image/png,image/webp,image/avif,video/mp4,video/quicktime,video/webm"
                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                       @change="handleFileInput($event)">
                <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center text-2xl mb-3 pointer-events-none">
                    📁
                </div>
                <p class="text-sm font-semibold text-foreground pointer-events-none">Click to browse or drag and drop photos & videos</p>
                <p class="text-xs text-muted-foreground mt-1 pointer-events-none">Photos up to 50MB, MP4 / MOV videos up to plan limit</p>
            </div>

            <!-- Global Status Alert Banner -->
            <template x-if="statusSummary">
                <div class="p-3.5 rounded-xl border text-xs flex items-center justify-between gap-2"
                     :class="{
                         'bg-green-500/10 border-green-500/30 text-green-700 dark:text-green-400': statusSummary.type === 'success',
                         'bg-amber-500/10 border-amber-500/30 text-amber-700 dark:text-amber-400': statusSummary.type === 'warning',
                         'bg-destructive/10 border-destructive/30 text-destructive': statusSummary.type === 'error'
                     }">
                    <span x-text="statusSummary.message" class="font-medium"></span>
                    <button type="button" @click="statusSummary = null" class="opacity-70 hover:opacity-100">&times;</button>
                </div>
            </template>

            <!-- Overall Queue Progress -->
            <template x-if="uploads.length > 0">
                <div class="space-y-2 p-4 bg-secondary/30 rounded-xl border border-border">
                    <div class="flex justify-between items-center text-xs">
                        <span class="font-medium text-foreground">
                            Upload Progress: <span class="font-bold text-primary" x-text="completedCount"></span> / <span x-text="totalCount"></span> photos saved
                        </span>
                        <div class="flex items-center gap-2">
                            <template x-if="failedCount > 0">
                                <button type="button" @click="retryAllFailed()" class="text-xs text-primary font-semibold hover:underline">
                                    Retry Failed (<span x-text="failedCount"></span>)
                                </button>
                            </template>
                            <span class="font-mono text-muted-foreground" x-text="overallProgress + '%'"></span>
                        </div>
                    </div>
                    <div class="h-2 bg-secondary rounded-full overflow-hidden">
                        <div class="h-full bg-primary transition-all duration-300 rounded-full"
                             :style="'width: ' + overallProgress + '%'"></div>
                    </div>
                </div>
            </template>

            <!-- Active Uploads List -->
            <template x-if="uploads.length > 0">
                <div class="max-h-60 overflow-y-auto space-y-2 pr-1 divide-y divide-border/40">
                    <template x-for="item in uploads" :key="item.id">
                        <div class="pt-2.5 first:pt-0 flex items-center justify-between gap-3 text-xs">
                            <!-- Preview Thumbnail -->
                            <div class="w-9 h-9 rounded-lg bg-secondary overflow-hidden shrink-0 border border-border flex items-center justify-center">
                                <template x-if="item.previewUrl">
                                    <img :src="item.previewUrl" class="w-full h-full object-cover">
                                </template>
                                <template x-if="!item.previewUrl && item.status !== 'done'">
                                    <svg class="w-4 h-4 text-muted-foreground/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </template>
                                <template x-if="item.status === 'done'">
                                    <div class="w-full h-full bg-green-500/10 text-green-600 dark:text-green-400 flex items-center justify-center">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                </template>
                            </div>

                            <!-- Name & Status Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2 mb-1">
                                    <p class="font-medium text-foreground truncate" x-text="item.name"></p>
                                    <span class="shrink-0 font-mono text-[10px] text-muted-foreground" x-text="item.formattedSize"></span>
                                </div>

                                <!-- Progress bar / State label -->
                                <div>
                                    <template x-if="item.status === 'uploading'">
                                        <div class="space-y-1">
                                            <div class="h-1 bg-secondary rounded-full overflow-hidden">
                                                <div class="h-full bg-primary transition-all duration-150" :style="'width: ' + item.progress + '%'"></div>
                                            </div>
                                            <p class="text-[10px] text-primary flex justify-between font-mono">
                                                <span>Uploading...</span>
                                                <span x-text="item.progress + '%'"></span>
                                            </p>
                                        </div>
                                    </template>

                                    <template x-if="item.status === 'preparing'">
                                        <p class="text-[10px] text-muted-foreground flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                                            Preparing secure upload...
                                        </p>
                                    </template>

                                    <template x-if="item.status === 'processing'">
                                        <p class="text-[10px] text-primary flex items-center gap-1.5 font-medium">
                                            <svg class="w-3 h-3 animate-spin shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                            <span x-text="item.isVideo ? 'Optimizing video stream...' : 'Saving to gallery...'"></span>
                                        </p>
                                    </template>

                                    <template x-if="item.status === 'retrying'">
                                        <p class="text-[10px] text-amber-500 font-medium flex items-center gap-1">
                                            <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                            <span x-text="item.errorMessage || 'Retrying upload...'"></span>
                                        </p>
                                    </template>

                                    <template x-if="item.status === 'queued'">
                                        <p class="text-[10px] text-muted-foreground">Waiting in queue...</p>
                                    </template>

                                    <template x-if="item.status === 'done'">
                                        <p class="text-[10px] text-green-600 dark:text-green-400 flex items-center gap-1 font-medium">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            Saved to gallery
                                        </p>
                                    </template>

                                    <template x-if="item.status === 'error'">
                                        <p class="text-[10px] text-destructive truncate font-medium" x-text="item.errorMessage || 'Upload interrupted'"></p>
                                    </template>

                                    <template x-if="item.status === 'cancelled'">
                                        <p class="text-[10px] text-muted-foreground italic">Cancelled</p>
                                    </template>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="shrink-0 flex items-center gap-1">
                                <template x-if="item.status === 'error'">
                                    <button type="button"
                                            @click="retryUpload(item.id)"
                                            class="px-2 py-1 bg-secondary hover:bg-secondary/80 text-primary font-semibold text-[11px] rounded transition-colors"
                                            title="Retry upload">
                                        Retry
                                    </button>
                                </template>

                                <template x-if="item.status === 'queued' || item.status === 'preparing' || item.status === 'uploading' || item.status === 'retrying'">
                                    <button type="button"
                                            @click="cancelUpload(item.id)"
                                            class="p-1 text-muted-foreground hover:text-foreground text-sm"
                                            title="Cancel">
                                        &times;
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <!-- Modal Actions Footer -->
            <div class="flex justify-end items-center gap-2 pt-2 border-t border-border">
                <template x-if="completedCount > 0">
                    <x-ui.button type="button" @click="refreshGallery()" variant="primary" size="sm">
                        Refresh Gallery
                    </x-ui.button>
                </template>
                <x-ui.button type="button" @click="$dispatch('close-modal', 'upload-photos-modal')" variant="outline" size="sm">
                    Close
                </x-ui.button>
            </div>
        </div>
    </x-ui.modal>
</div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/artplayer/dist/artplayer.js"></script>
@endpush

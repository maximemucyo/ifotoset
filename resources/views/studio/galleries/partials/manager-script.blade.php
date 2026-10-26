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
 * Utility: escape text before interpolating into card innerHTML
 */
function gmEscapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
}

/**
 * Utility: surface errors through the global toast (falls back to alert)
 */
function gmNotify(message, type = 'error') {
    if (typeof window.toast === 'function') {
        window.toast(message, type);
    } else {
        alert(message);
    }
}

/**
 * Photo card class names, shared with the Blade-rendered cards (partials/photo-card.blade.php).
 */
const GM_CARD_CLASS = @json($gmCardClasses['card']);
const GM_IMG_CLASS = @json($gmCardClasses['img']);
const GM_SCRIM_CLASS = @json($gmCardClasses['scrim']);
const GM_SELECT_CLASS = @json($gmCardClasses['select']);
const GM_ACTIONS_CLASS = @json($gmCardClasses['actions']);
const GM_ACTION_BTN = @json($gmCardClasses['actionBtn']);
const GM_BADGE_LIGHT = @json($gmCardClasses['badgeLight']);
const GM_BADGE_DARK = @json($gmCardClasses['badgeDark']);
const GM_FOOTER_CLASS = @json($gmCardClasses['footer']);
window.GM_ICONS = @json($gmIcons);

function gmProcessingOverlayHtml() {
    const I = window.GM_ICONS || {};
    return `<div class="processing-overlay pointer-events-none absolute inset-0 z-10 flex items-center justify-center bg-muted/70">
        <div class="skeleton absolute inset-0 !rounded-none opacity-70"></div>
        <span class="relative inline-flex items-center gap-1.5 rounded-full bg-card/95 px-2.5 py-1 text-[11px] font-medium text-foreground shadow-sm ring-1 ring-border">${I.spinner || ''}Processing</span>
    </div>`;
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
        queueExpanded: false,
        uploadSpeed: 0,

        // Grid / selection UI state
        density: 'comfortable',
        selected: [],
        selectMode: false,
        pendingDelete: [],
        bulkBusy: false,

        get selectedCount() {
            return this.selected.length;
        },

        get coverThumb() {
            const cover = this.photos.find(p => p.is_cover);
            return cover ? (cover.thumbnail_url || cover.medium_url || '') : '';
        },

        get formattedSpeed() {
            return this.uploadSpeed > 0 ? formatBytes(this.uploadSpeed) + '/s' : '';
        },

        get etaText() {
            if (!this.uploadSpeed) return '';
            const remaining = Math.max(0, this.uploadStats.totalBytes - this.uploadStats.uploadedBytes);
            const secs = Math.round(remaining / this.uploadSpeed);
            if (!secs) return '';
            if (secs < 60) return secs + 's left';
            const mins = Math.round(secs / 60);
            return mins < 60 ? mins + ' min left' : Math.round(mins / 60 * 10) / 10 + ' h left';
        },

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
            try {
                const savedDensity = localStorage.getItem('ifotoset.studio.gridDensity');
                if (savedDensity === 'compact' || savedDensity === 'comfortable') this.density = savedDensity;
            } catch (_) {}
            this.$watch('density', (v) => { try { localStorage.setItem('ifotoset.studio.gridDensity', v); } catch (_) {} });
            this.setupSpeedMeter();
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
            const I = window.GM_ICONS || {};
            const esc = gmEscapeHtml;
            const card = document.createElement('div');
            card.id = `photo-card-${photo.uuid}`;
            card.dataset.uuid = photo.uuid;
            card.dataset.id = photo.id;
            card.className = `${GM_CARD_CLASS} ${photo.is_cover ? 'is-cover' : ''} ${photo.is_hidden ? 'is-hidden' : ''} ${this.selected.includes(photo.uuid) ? 'is-selected' : ''}`;

            const thumb = photo.thumbnail_url || photo.medium_url || photo.large_url || photo.original_url || photo.cdn_url || '';
            const filename = esc(photo.original_filename || photo.filename || 'Photo');
            const sizeMb = photo.size ? formatBytes(photo.size) : '';
            const isVideo = photo.is_video || photo.media_type === 'video';
            const duration = esc(photo.duration || '');
            const isProcessing = isVideo && photo.status === 'processing';
            const uuid = esc(photo.uuid);

            card.innerHTML = `
                <div class="photo-media relative aspect-square cursor-pointer overflow-hidden bg-muted" onclick="window._cardClick('${uuid}')">
                    <img src="${esc(thumb)}" alt="${filename}" loading="lazy" decoding="async" onerror="this.style.visibility='hidden'" class="${GM_IMG_CLASS}" />
                    <div class="${GM_SCRIM_CLASS}"></div>
                    <button type="button" onclick="event.stopPropagation(); window._toggleSelect('${uuid}')" class="photo-select ${GM_SELECT_CLASS}" aria-label="Select ${filename}">${I.check || ''}</button>
                    <div class="${GM_ACTIONS_CLASS}" onclick="event.stopPropagation()">
                        <button type="button" onclick="window._setCover('${uuid}')" class="btn-set-cover ${GM_ACTION_BTN} ${photo.is_cover || photo.is_hidden ? 'hidden' : ''}" title="Set as cover" aria-label="Set as cover">${I.star || ''}</button>
                        <button type="button" onclick="window._toggleHide('${uuid}')" class="btn-toggle-hide ${GM_ACTION_BTN} group-[.is-hidden]:bg-warning group-[.is-hidden]:hover:bg-warning/90" title="${photo.is_hidden ? 'Show in client gallery' : 'Hide from client gallery'}" aria-label="Toggle visibility"><span class="contents group-[.is-hidden]:hidden">${I['eye-off'] || ''}</span><span class="hidden group-[.is-hidden]:contents">${I.eye || ''}</span></button>
                        <button type="button" onclick="window._deletePhoto('${uuid}')" class="${GM_ACTION_BTN} hover:!bg-destructive" title="Delete" aria-label="Delete">${I.trash || ''}</button>
                    </div>
                    <div class="card-badges pointer-events-none absolute bottom-2 left-2 z-10 flex flex-wrap items-center gap-1">
                        <span class="cover-badge ${GM_BADGE_LIGHT} ${photo.is_cover ? '' : 'hidden'}">${I.starSm || ''}Cover</span>
                        <span class="hidden-badge ${GM_BADGE_DARK} ${photo.is_hidden ? '' : 'hidden'}">${I.eyeOffSm || ''}Hidden</span>
                        ${isVideo ? `<span class="video-duration-badge ${GM_BADGE_DARK}">${I.playSm || ''}<span>${duration || 'Video'}</span></span>` : ''}
                    </div>
                    ${isProcessing ? gmProcessingOverlayHtml() : ''}
                </div>
                <div class="${GM_FOOTER_CLASS}">
                    <span class="truncate" title="${filename}">${filename}</span>
                    <span class="flex shrink-0 items-center gap-1.5">
                        <span class="hidden-label text-warning ${photo.is_hidden ? '' : 'hidden'}" title="Hidden from client gallery">${I.eyeOffSm || ''}</span>
                        ${sizeMb ? `<span class="font-mono text-[10px] text-subtle">${sizeMb}</span>` : ''}
                    </span>
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
                        theme: '#f0532a',
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
                        this.updateCardToReady(photoUuid, data.photo || data);
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
                    img.style.visibility = '';
                    img.src = photoData.thumbnail_url || photoData.poster_url;
                }

                if (photoData && photoData.duration) {
                    let badge = card.querySelector('.video-duration-badge');
                    if (!badge) {
                        const I = window.GM_ICONS || {};
                        badge = document.createElement('span');
                        badge.className = `video-duration-badge ${GM_BADGE_DARK}`;
                        badge.innerHTML = `${I.playSm || ''}<span>${gmEscapeHtml(photoData.duration)}</span>`;
                        const container = card.querySelector('.card-badges') || card.querySelector('.photo-media');
                        if (container) container.appendChild(badge);
                    }
                }
            }
        },

        updateCardToFailed(uuid, error) {
            const card = document.getElementById(`photo-card-${uuid}`);
            if (card) {
                let procOverlay = card.querySelector('.processing-overlay');
                if (!procOverlay) {
                    // Photos have no overlay while processing; add one so failures stay visible.
                    const media = card.querySelector('.photo-media');
                    if (media) {
                        media.insertAdjacentHTML('beforeend', gmProcessingOverlayHtml());
                        procOverlay = media.querySelector('.processing-overlay');
                    }
                }
                if (procOverlay) {
                    const I = window.GM_ICONS || {};
                    procOverlay.classList.remove('bg-black/40', 'bg-muted/70');
                    procOverlay.classList.add('bg-destructive/85');
                    procOverlay.innerHTML = `<span class="inline-flex items-center gap-1.5 px-2 text-center text-[11px] font-semibold text-white">${I.alert || ''}Processing failed</span>`;
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
                    toggleBtn.title = newHidden ? 'Show in client gallery' : 'Hide from client gallery';
                }
            }

            try {
                const res = await fetch(`${this.toggleHideUrlBase}/${photoUuid}/hide`, {
                    method: 'PATCH',
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
                    card.classList.toggle('is-hidden', originalHidden);
                    const badge = card.querySelector('.hidden-badge');
                    if (badge) badge.classList.toggle('hidden', !originalHidden);
                    const hiddenLabel = card.querySelector('.hidden-label');
                    if (hiddenLabel) hiddenLabel.classList.toggle('hidden', !originalHidden);
                    const setCoverBtn = card.querySelector('.btn-set-cover');
                    if (setCoverBtn) setCoverBtn.classList.toggle('hidden', originalHidden || card.classList.contains('is-cover'));
                }
                gmNotify('Could not update photo visibility. Please try again.');
            }
        },

        /**
         * Set cover photo
         */
        async setCover(photoUuid) {
            const photo = this.photos.find(p => p.uuid === photoUuid);
            if (photo && photo.is_hidden) {
                gmNotify('A hidden photo cannot be set as the collection cover.', 'warning');
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
                gmNotify(err.message || 'Could not set cover photo. Please try again.');
            }
        },

        updateCoverSelection(coverPhotoId, coverPhotoUuid = null) {
            document.querySelectorAll('.photo-card').forEach(c => {
                const isTarget = (coverPhotoUuid && c.dataset.uuid === coverPhotoUuid) ||
                                 (coverPhotoId && Number(c.dataset.id) === Number(coverPhotoId));
                c.classList.toggle('is-cover', isTarget);
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
        async deletePhoto(photoUuid, skipConfirm = false) {
            if (!skipConfirm) {
                // Ask through the styled confirmation modal; it calls back with skipConfirm = true.
                this.requestDelete([photoUuid]);
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
                this.selected = this.selected.filter(u => u !== photoUuid);

                if (data.gallery_cover_photo_id) {
                    this.updateCoverSelection(data.gallery_cover_photo_id);
                }
            } catch (err) {
                if (card) {
                    card.style.opacity = '';
                    card.style.pointerEvents = '';
                }
                this.totalPhotos++;
                gmNotify('Failed to delete photo. It has been restored.');
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
                    // The server signs Content-Type into the URL; send exactly its value
                    if (!extraHeaders || !extraHeaders['Content-Type']) {
                        xhr.setRequestHeader('Content-Type', item.file.type || 'image/jpeg');
                    }

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
                    // Images are saved immediately; thumbnails are generated in the background.
                    if (photoData && photoData.uuid && photoData.status === 'processing') {
                        this.pollProcessingStatus(photoData.uuid);
                    }
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
        },

        // --- Upload speed meter (sampled once per second, smoothed) ---
        setupSpeedMeter() {
            let lastBytes = this.uploadStats.uploadedBytes;
            setInterval(() => {
                const now = this.uploadStats.uploadedBytes;
                const delta = Math.max(0, now - lastBytes);
                lastBytes = now;
                if (!this.isUploading) {
                    if (this.uploadSpeed !== 0) this.uploadSpeed = 0;
                    return;
                }
                this.uploadSpeed = this.uploadSpeed ? Math.round(this.uploadSpeed * 0.6 + delta * 0.4) : delta;
            }, 1000);
        },

        dismissQueue() {
            if (this.isUploading) return;
            this.uploads.forEach(u => this.cleanupItemPreview(u));
            this.uploads = [];
            this.queueExpanded = false;
            if (this.uploads.length === 0) {
                this.uploadStats.totalBytes = 0;
                this.uploadStats.uploadedBytes = 0;
                this.statusSummary = null;
            }
        },

        // --- Selection & bulk actions (built on the per-photo endpoints) ---
        cardClick(photoUuid) {
            if (this.selectMode || this.selected.length > 0) {
                this.toggleSelect(photoUuid);
            } else {
                this.openLightboxByUuid(photoUuid);
            }
        },

        toggleSelect(photoUuid) {
            const isSelected = this.selected.includes(photoUuid);
            this.selected = isSelected ? this.selected.filter(u => u !== photoUuid) : [...this.selected, photoUuid];
            const card = document.getElementById(`photo-card-${photoUuid}`);
            if (card) card.classList.toggle('is-selected', !isSelected);
        },

        selectAllLoaded() {
            this.selected = this.photos.map(p => p.uuid);
            document.querySelectorAll('.photo-card').forEach(c => c.classList.add('is-selected'));
        },

        clearSelection() {
            this.selected = [];
            this.selectMode = false;
            document.querySelectorAll('.photo-card.is-selected').forEach(c => c.classList.remove('is-selected'));
        },

        async bulkSetHidden(hidden) {
            if (this.bulkBusy) return;
            this.bulkBusy = true;
            try {
                const targets = this.selected.filter(uuid => {
                    const p = this.photos.find(x => x.uuid === uuid);
                    return p && Boolean(p.is_hidden) !== hidden;
                });
                for (const uuid of targets) {
                    await this.toggleHide(uuid);
                }
                if (targets.length && window.toast) {
                    window.toast(`${targets.length} ${targets.length === 1 ? 'item' : 'items'} ${hidden ? 'hidden from' : 'visible in'} the client gallery`, 'success');
                }
            } finally {
                this.bulkBusy = false;
            }
        },

        requestDelete(uuids) {
            this.pendingDelete = [...uuids];
            this.$dispatch('open-modal', 'confirm-delete-photos');
        },

        async confirmPendingDelete() {
            const uuids = [...this.pendingDelete];
            this.$dispatch('close-modal', 'confirm-delete-photos');
            this.pendingDelete = [];
            if (!uuids.length) return;
            this.bulkBusy = true;
            try {
                for (const uuid of uuids) {
                    await this.deletePhoto(uuid, true);
                }
            } finally {
                this.bulkBusy = false;
            }
            if (this.lightboxOpen) {
                if (this.photos.length === 0) {
                    this.closeLightbox();
                } else if (this.lightboxIndex >= this.photos.length) {
                    this.lightboxIndex = Math.max(0, this.photos.length - 1);
                }
            }
        }
    };
};

// Global bridge helpers so dynamically inserted cards can trigger Alpine methods.
// Resolve the manager root explicitly (the app shell also uses x-data higher up the DOM).
function gmManager() {
    const el = document.getElementById('studio-gallery-manager') || document.querySelector('[x-data]');
    if (!el) return null;
    if (window.Alpine && typeof window.Alpine.$data === 'function') {
        try { return window.Alpine.$data(el); } catch (_) {}
    }
    return (el._x_dataStack && el._x_dataStack[0]) || null;
}

window._openLightboxByUuid = function(uuid) {
    const m = gmManager();
    if (m) m.openLightboxByUuid(uuid);
};

window._openLightboxByIndex = function(idx) {
    const m = gmManager();
    if (m) m.openLightboxByIndex(idx);
};

window._toggleHide = function(uuid) {
    const m = gmManager();
    if (m) m.toggleHide(uuid);
};

window._setCover = function(uuid) {
    const m = gmManager();
    if (m) m.setCover(uuid);
};

window._deletePhoto = function(uuid) {
    const m = gmManager();
    if (m) m.deletePhoto(uuid);
};

window._cardClick = function(uuid) {
    const m = gmManager();
    if (m) m.cardClick(uuid);
};

window._toggleSelect = function(uuid) {
    const m = gmManager();
    if (m) m.toggleSelect(uuid);
};

if (window.Alpine) {
    Alpine.data('studioGalleryManager', window.studioGalleryManager);
}
document.addEventListener('alpine:init', () => {
    Alpine.data('studioGalleryManager', window.studioGalleryManager);
});
</script>

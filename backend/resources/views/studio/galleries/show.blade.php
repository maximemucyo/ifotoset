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
 * Utility: Compute SHA-256 integrity checksum
 */
async function calculateFileSha256(file) {
    if (window.crypto && crypto.subtle) {
        try {
            const buffer = await file.arrayBuffer();
            const hashBuffer = await crypto.subtle.digest('SHA-256', buffer);
            const hashArray = Array.from(new Uint8Array(hashBuffer));
            return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
        } catch (e) {
            console.warn('Subtle digest unavailable, using fallback', e);
        }
    }
    return '';
}

/**
 * Alpine Master Component: Studio Gallery View Manager
 */
window.studioGalleryManager = function(inlineConfig) {
    const CONCURRENCY_LIMIT = 4;
    const MAX_RETRIES = 3;

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

        get overallProgress() {
            if (!this.uploads.length) return 0;
            const total = this.uploads.reduce((acc, u) => {
                if (u.status === 'done') return acc + 100;
                if (u.status === 'error' || u.status === 'cancelled') return acc + 0;
                return acc + (u.progress || 0);
            }, 0);
            return Math.round(total / this.uploads.length);
        },

        init() {
            this.setupInfiniteScroll();
            this.setupNetworkResilience();
            this.setupUnloadGuard();
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

                // Deduplicate by UUID
                const existingUuids = new Set(this.photos.map(p => p.uuid));
                const uniqueNewPhotos = incomingPhotos.filter(p => !existingUuids.has(p.uuid));

                // Append each new photo card to the grid
                const grid = document.getElementById('studio-photos-grid');
                if (grid) {
                    uniqueNewPhotos.forEach((photo) => {
                        const newIndex = this.photos.length;
                        this.photos.push(photo);
                        const cardEl = this.createPhotoCardElement(photo, newIndex);
                        grid.appendChild(cardEl);
                    });
                } else {
                    this.photos.push(...uniqueNewPhotos);
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

        createPhotoCardElement(photo, index) {
            const card = document.createElement('div');
            card.id = `photo-card-${photo.uuid}`;
            card.dataset.uuid = photo.uuid;
            card.dataset.id = photo.id;
            card.dataset.index = index;
            card.className = `photo-card group relative rounded-xl overflow-hidden bg-card border ${photo.is_cover ? 'border-primary ring-2 ring-primary/40 is-cover' : 'border-border'} ${photo.is_hidden ? 'opacity-65 grayscale-[30%] is-hidden' : ''} shadow-sm flex flex-col justify-between transition-all duration-300`;

            const thumb = photo.thumbnail_url || photo.medium_url || photo.large_url || photo.original_url;
            const filename = photo.original_filename || photo.filename || 'Photo';
            const sizeMb = photo.size ? `${(photo.size / 1048576).toFixed(1)}MB` : '';

            card.innerHTML = `
                <div class="aspect-square bg-muted relative overflow-hidden cursor-pointer" onclick="window._openLightboxByIndex(${index})">
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
                    <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-between p-2.5 z-20" onclick="event.stopPropagation()">
                        <div class="flex items-center justify-between">
                            <button type="button" onclick="window._openLightboxByIndex(${index})" class="p-1.5 rounded-lg bg-black/50 hover:bg-black/80 text-white transition-colors" title="Preview photo">
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
        openLightboxByIndex(index) {
            if (index >= 0 && index < this.photos.length) {
                this.lightboxIndex = index;
                this.lightboxOpen = true;
                document.body.style.overflow = 'hidden';
            }
        },

        closeLightbox() {
            this.lightboxOpen = false;
            document.body.style.overflow = '';
        },

        nextLightboxPhoto() {
            if (this.photos.length <= 1) return;
            if (this.lightboxIndex < this.photos.length - 1) {
                this.lightboxIndex++;
            } else {
                this.lightboxIndex = 0;
            }
            if (this.lightboxIndex >= this.photos.length - 3 && this.hasMore && !this.loadingPhotos) {
                this.fetchNextBatch();
            }
        },

        prevLightboxPhoto() {
            if (this.photos.length <= 1) return;
            if (this.lightboxIndex > 0) {
                this.lightboxIndex--;
            } else {
                this.lightboxIndex = this.photos.length - 1;
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

        handleFileInput(e) {
            if (e.target && e.target.files) {
                this.addFiles(e.target.files);
                e.target.value = '';
            }
        },

        addFiles(fileList) {
            if (!fileList || fileList.length === 0) return;

            const filesArray = Array.from(fileList).filter(f => f.type.startsWith('image/'));
            if (filesArray.length === 0) return;

            const newItems = filesArray.map(file => {
                let previewUrl = null;
                try {
                    previewUrl = URL.createObjectURL(file);
                } catch (_) {}

                return {
                    id: 'up-' + Math.random().toString(36).substring(2, 9) + '-' + Date.now(),
                    file: file,
                    name: file.name,
                    formattedSize: formatBytes(file.size),
                    previewUrl: previewUrl,
                    status: 'queued',
                    progress: 0,
                    retryCount: 0,
                    errorMessage: '',
                    xhr: null,
                    sessionId: null,
                    userCancelled: false
                };
            });

            this.statusSummary = null;
            this.uploads.push(...newItems);
            this.runQueue();
        },

        runQueue() {
            const active = this.uploads.filter(u => u.status === 'preparing' || u.status === 'uploading' || u.status === 'processing');
            const availableSlots = CONCURRENCY_LIMIT - active.length;

            if (availableSlots <= 0) return;

            const queuedItems = this.uploads.filter(u => u.status === 'queued').slice(0, availableSlots);
            for (const item of queuedItems) {
                this.startUpload(item);
            }
        },

        async startUpload(item) {
            item.status = 'preparing';
            item.progress = 0;
            item.errorMessage = '';

            let sessionId = null;

            try {
                // 1. Calculate integrity hash
                const sha256 = await calculateFileSha256(item.file);
                if (item.status === 'cancelled' || item.userCancelled) return;

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
                        mime_type: item.file.type || 'image/jpeg',
                        sha256: sha256
                    })
                });

                if (!reqRes.ok) {
                    const errData = await reqRes.json().catch(() => ({}));
                    throw new Error(errData.message || 'Unable to prepare photo upload');
                }

                const reqData = await reqRes.json();
                const presignedUrl = reqData.presigned_url || reqData.upload_url;
                sessionId = reqData.upload_session_id || reqData.session_id;
                item.sessionId = sessionId;
                const extraHeaders = reqData.headers || {};

                if (!presignedUrl || !sessionId) {
                    throw new Error('Invalid upload session');
                }

                if (item.status === 'cancelled' || item.userCancelled) {
                    this.abortSession(sessionId);
                    return;
                }

                // 3. Direct binary upload to signed storage URL
                item.status = 'uploading';

                await new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    item.xhr = xhr;
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
                        if (e.lengthComputable) {
                            item.progress = Math.round((e.loaded / e.total) * 100);
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

                if (item.status === 'cancelled' || item.userCancelled) {
                    this.abortSession(sessionId);
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

                // Photo confirmed and saved to DB
                item.status = 'done';
                item.progress = 100;
                item.errorMessage = '';
            } catch (err) {
                if (item.status === 'cancelled' || item.userCancelled) return;

                // Auto-retry with backoff on network issues
                if (item.retryCount < MAX_RETRIES) {
                    item.retryCount++;
                    item.status = 'retrying';
                    item.errorMessage = `Network interrupted. Retrying... (attempt ${item.retryCount}/${MAX_RETRIES})`;
                    const delay = Math.min(1000 * Math.pow(2, item.retryCount), 8000);
                    setTimeout(() => {
                        if (item.status === 'retrying') {
                            item.status = 'queued';
                            this.runQueue();
                        }
                    }, delay);
                    return;
                }

                item.status = 'error';
                item.errorMessage = cleanErrorMessage(err.message);
            } finally {
                this.runQueue();
                this.checkAllCompleted();
            }
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

            item.userCancelled = true;
            item.status = 'cancelled';
            if (item.xhr) {
                try { item.xhr.abort(); } catch (_) {}
            }
            if (item.sessionId) {
                this.abortSession(item.sessionId);
            }

            this.runQueue();
            this.checkAllCompleted();
        },

        retryUpload(id) {
            const item = this.uploads.find(u => u.id === id);
            if (!item) return;

            item.status = 'queued';
            item.progress = 0;
            item.retryCount = 0;
            item.errorMessage = '';
            item.userCancelled = false;
            this.statusSummary = null;
            this.runQueue();
        },

        retryAllFailed() {
            this.uploads.forEach(item => {
                if (item.status === 'error' || item.status === 'cancelled') {
                    item.status = 'queued';
                    item.progress = 0;
                    item.retryCount = 0;
                    item.errorMessage = '';
                    item.userCancelled = false;
                }
            });
            this.statusSummary = null;
            this.runQueue();
        },

        checkAllCompleted() {
            const stillActive = this.uploads.some(u => u.status === 'queued' || u.status === 'preparing' || u.status === 'uploading' || u.status === 'retrying' || u.status === 'processing');
            if (stillActive) return;

            const completed = this.uploads.filter(u => u.status === 'done').length;
            const failed = this.uploads.filter(u => u.status === 'error').length;

            if (completed > 0 && failed === 0) {
                this.statusSummary = {
                    type: 'success',
                    message: `All ${completed} photos uploaded and saved to your gallery! Updating gallery...`
                };
                setTimeout(() => {
                    window.location.reload();
                }, 1200);
            } else if (completed > 0 && failed > 0) {
                this.statusSummary = {
                    type: 'warning',
                    message: `${completed} photo${completed > 1 ? 's' : ''} saved to database. ${failed} interrupted. You can click retry or refresh now.`
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
                    {{ ucfirst($gallery->visibility) }}
                </x-ui.badge>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">{{ $gallery->title }}</h1>
            <p class="text-xs text-muted-foreground mt-1">
                <span x-text="totalPhotos">{{ $totalPhotosCount }}</span> <span x-text="totalPhotos === 1 ? 'photo' : 'photos'">{{ Str::plural('photo', $totalPhotosCount) }}</span> &bull; Slug: <span class="font-mono text-primary">{{ $gallery->slug }}</span>
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
                + Upload Photos
            </x-ui.button>
        </div>
    </div>

    <!-- Upload Dropzone Hero (when gallery is empty) -->
    @if($photos->isEmpty())
        <div class="rounded-2xl border-2 border-dashed border-border p-12 text-center bg-card flex flex-col items-center justify-center transition-all hover:border-primary/50 cursor-pointer"
             @dragover.prevent="isDragging = true"
             @dragleave.prevent="isDragging = false"
             @drop.prevent="isDragging = false; $dispatch('open-modal', 'upload-photos-modal'); addFiles($event.dataTransfer.files)"
             @click="$dispatch('open-modal', 'upload-photos-modal')"
             :class="isDragging ? 'border-primary bg-primary/5 ring-4 ring-primary/10' : ''">
            <div class="w-16 h-16 rounded-2xl bg-primary/10 text-primary flex items-center justify-center text-2xl font-bold mb-4">
                📸
            </div>
            <h2 class="text-lg font-bold text-foreground">This collection is currently empty</h2>
            <p class="text-xs text-muted-foreground max-w-sm mt-1 mb-6">
                Drag and drop your photos to upload and share with your clients in full quality.
            </p>
            <x-ui.button type="button" variant="primary">
                Select Photos to Upload
            </x-ui.button>
        </div>
    @else
        <!-- Photos Grid (Server Rendered for instant visibility) -->
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4" id="studio-photos-grid">
            @foreach($photos as $index => $photo)
                @php
                    $thumbUrl = $photo->getUrl('sm');
                    $isCover = $gallery->cover_photo_id === $photo->id;
                    $isHidden = (bool) $photo->is_hidden;
                @endphp
                <div id="photo-card-{{ $photo->uuid }}"
                     data-uuid="{{ $photo->uuid }}"
                     data-id="{{ $photo->id }}"
                     data-index="{{ $index }}"
                     class="photo-card group relative rounded-xl overflow-hidden bg-card border {{ $isCover ? 'border-primary ring-2 ring-primary/40 is-cover' : 'border-border' }} {{ $isHidden ? 'opacity-65 grayscale-[30%] is-hidden' : '' }} shadow-sm flex flex-col justify-between transition-all duration-300">

                    <div class="aspect-square bg-muted relative overflow-hidden cursor-pointer"
                         @click="openLightboxByIndex({{ $index }})">
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

                        <!-- Hover Overlay Actions -->
                        <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-between p-2.5 z-20"
                             @click.stop>
                            <!-- Top Row: Preview, Hide/Show, Delete -->
                            <div class="flex items-center justify-between">
                                <button type="button"
                                        @click="openLightboxByIndex({{ $index }})"
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
    @endif

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

            <!-- Image View -->
            <template x-if="currentPhoto">
                <div class="relative max-h-full max-w-full flex items-center justify-center">
                    <img :src="currentPhoto.full_url || currentPhoto.large_url || currentPhoto.original_url || currentPhoto.medium_url || currentPhoto.thumbnail_url"
                         :alt="currentPhoto.original_filename"
                         class="max-h-[82vh] max-w-[90vw] object-contain rounded-lg shadow-2xl transition-all duration-200 select-none" />
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

    <!-- Upload Photos Modal -->
    <x-ui.modal name="upload-photos-modal" title="Upload Photos" maxWidth="2xl">
        <div class="space-y-5">
            <p class="text-xs text-muted-foreground">
                Select photos to add to your collection. Upload high-resolution images in full quality.
            </p>

            <!-- Dropzone Area -->
            <div class="border-2 border-dashed rounded-2xl p-6 sm:p-8 transition-all relative flex flex-col items-center justify-center text-center bg-secondary/10 hover:bg-secondary/20 hover:border-primary/50 cursor-pointer"
                 :class="isDragging ? 'border-primary bg-primary/5 ring-4 ring-primary/10' : 'border-border'"
                 @dragover.prevent="isDragging = true"
                 @dragleave.prevent="isDragging = false"
                 @drop.prevent="isDragging = false; addFiles($event.dataTransfer.files)">
                <input type="file"
                       id="photo-file-input"
                       multiple
                       accept="image/jpeg,image/png,image/webp,image/avif"
                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                       @change="handleFileInput($event)">
                <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center text-2xl mb-3 pointer-events-none">
                    📁
                </div>
                <p class="text-sm font-semibold text-foreground pointer-events-none">Click to browse or drag and drop photos</p>
                <p class="text-xs text-muted-foreground mt-1 pointer-events-none">JPEG, PNG, WebP up to 50MB per file</p>
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
                                <template x-if="!item.previewUrl">
                                    <span class="text-[10px] text-muted-foreground font-mono">IMG</span>
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
                                        <p class="text-[10px] text-primary flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                                            Saving to gallery...
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

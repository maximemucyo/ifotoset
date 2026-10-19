/**
 * Gallery Pagination Module
 * Manages IntersectionObserver sentinel cursor loading
 * and feeds newly loaded photos to both DOM grid and in-memory photo store.
 */
import { renderBlurHashToCanvas } from './blurhash.js';
import { icon, escapeHtml } from './icons.js';

// Mirrors the masonry columns in layout.js (2 / 3 / 4 / 5); keep in sync with photo-card.blade.php
const CARD_SIZES = '(max-width: 639px) 50vw, (max-width: 1023px) 33vw, (max-width: 1699px) 25vw, 20vw';

export class GalleryPagination {
    constructor(config) {
        this.grid = document.getElementById('gallery-grid');
        this.sentinel = document.getElementById('gallery-sentinel');
        this.spinner = document.getElementById('gallery-spinner');
        this.endText = document.getElementById('gallery-end');
        this.emptyGallery = document.getElementById('gallery-empty');
        this.emptyFavorites = document.getElementById('gallery-empty-favorites');

        this.photosUrl = config.photosUrl;
        // "{gallery title} – photo by {photographer}", same as photo-card.blade.php
        this.altFallback = this.grid?.dataset.altFallback || '';
        this.allowDownloads = config.allowDownloads ?? true;
        this.nextCursor = config.initialNextCursor || null;
        this.hasMore = Boolean(config.initialHasMore && this.nextCursor);
        this.isLoading = false;
        this.filterFavorites = false;
        this.favoriteUuids = [];
        this.onPhotosLoadedCallbacks = [];

        this.init();
    }

    onPhotosLoaded(callback) {
        this.onPhotosLoadedCallbacks.push(callback);
    }

    init() {
        if (!this.sentinel) return;

        this.observer = new IntersectionObserver((entries) => {
            if (entries[0]?.isIntersecting && this.hasMore && !this.isLoading) {
                this.loadNextBatch();
            }
        }, {
            rootMargin: '400px'
        });

        if (this.hasMore) {
            this.observer.observe(this.sentinel);
        }
    }

    setFavoritesFilter(active, uuids = []) {
        this.filterFavorites = active;
        this.favoriteUuids = uuids;

        // Toggle card visibility in the DOM
        const cards = this.grid?.querySelectorAll('.photo-card') || [];
        let visibleCount = 0;

        cards.forEach(card => {
            const uuid = card.dataset.photoUuid;
            if (active) {
                if (uuids.includes(uuid)) {
                    card.classList.remove('hidden');
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                }
            } else {
                card.classList.remove('hidden');
                visibleCount++;
            }
        });

        // Toggle empty states
        if (this.emptyFavorites) {
            if (active && visibleCount === 0) {
                this.emptyFavorites.classList.remove('hidden');
            } else {
                this.emptyFavorites.classList.add('hidden');
            }
        }

        // Toggle back to top button visibility based on whether any cards are visible
        if (this.endText) {
            if (visibleCount > 0 && !this.hasMore) {
                this.endText.classList.remove('hidden');
            } else {
                this.endText.classList.add('hidden');
            }
        }
    }

    async loadNextBatch() {
        if (!this.hasMore || !this.nextCursor || this.isLoading) return;
        this.isLoading = true;
        let failed = false;

        if (this.spinner) this.spinner.classList.remove('hidden');

        try {
            const url = new URL(this.photosUrl, window.location.origin);
            url.searchParams.set('cursor', this.nextCursor);
            url.searchParams.set('per_page', '24');

            if (this.filterFavorites && this.favoriteUuids.length > 0) {
                url.searchParams.set('uuids', this.favoriteUuids.join(','));
            }

            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) throw new Error('Failed to load photos');

            const result = await response.json();
            const incomingPhotos = result.data || [];
            const newPhotos = [];

            incomingPhotos.forEach(photo => {
                if (this.grid && !this.grid.querySelector(`.photo-card[data-photo-uuid="${photo.uuid}"]`)) {
                    const card = this.createCardElement(photo);
                    if (this.filterFavorites && !this.favoriteUuids.includes(photo.uuid)) {
                        card.classList.add('hidden');
                    }
                    this.grid.appendChild(card);
                    newPhotos.push(photo);
                }
            });

            this.nextCursor = result.next_cursor || null;
            this.hasMore = Boolean(result.has_more && this.nextCursor);

            // Feed new photos to in-memory store
            if (newPhotos.length > 0) {
                this.onPhotosLoadedCallbacks.forEach(cb => cb(newPhotos));
            }

            if (!this.hasMore) {
                if (this.endText && !this.filterFavorites) {
                    const hasCards = (this.grid?.querySelectorAll('.photo-card:not(.hidden)')?.length || 0) > 0;
                    if (hasCards) {
                        this.endText.classList.remove('hidden');
                    }
                }
                if (this.observer && this.sentinel) this.observer.unobserve(this.sentinel);
            }
        } catch (err) {
            console.error('Gallery stream error:', err);
            failed = true;
        } finally {
            this.isLoading = false;
            if (this.spinner) this.spinner.classList.add('hidden');
        }

        // IntersectionObserver only fires on changes: if the sentinel is still in range
        // (short batch, or cards hidden by the favorites filter), re-observing re-checks it.
        if (this.hasMore && this.observer && this.sentinel) {
            this.observer.unobserve(this.sentinel);
            setTimeout(() => this.observer.observe(this.sentinel), failed ? 3000 : 0);
        }
    }

    createCardElement(photo) {
        // Mirrors resources/views/components/gallery/photo-card.blade.php. Keep both in sync.
        const w = photo.width || 1920;
        const h = photo.height || 1080;
        const filename = escapeHtml(photo.filename || '');
        const uuid = escapeHtml(photo.uuid);
        const isVideo = Boolean(photo.is_video);
        const downloadUrl = photo.delivery_download_url || photo.original || photo.full;
        const btnClass = 'grid size-9 place-items-center rounded-full text-white/90 transition hover:bg-white/15 hover:text-white focus-visible:outline-2 focus-visible:outline-white';

        const card = document.createElement('div');
        card.className = 'photo-card group relative cursor-pointer select-none overflow-hidden bg-muted';
        card.style.aspectRatio = `${w} / ${h}`;
        card.dataset.photoUuid = photo.uuid;
        card.dataset.photoFilename = photo.filename || '';
        card.dataset.photoLarge = photo.large || photo.full;
        card.dataset.photoFull = photo.full;
        card.dataset.photoOriginal = photo.original || photo.full;
        card.dataset.photoDelivery = photo.delivery_url || '';
        card.dataset.photoDeliveryDownload = photo.delivery_download_url || '';
        card.dataset.photoThumb = photo.thumbnail;
        card.dataset.photoSrcset = photo.srcset || '';
        card.dataset.photoBlurhash = photo.blurhash || '';
        card.dataset.photoWidth = w;
        card.dataset.photoHeight = h;
        card.dataset.isVideo = isVideo ? 'true' : 'false';
        card.dataset.videoDuration = photo.duration || '';

        card.innerHTML = `
            <button type="button"
                    class="photo-card-open absolute inset-0 z-[1] size-full cursor-pointer outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-white"
                    aria-label="View ${isVideo ? 'video' : 'photo'} ${filename}"></button>
            <canvas class="photo-blurhash-canvas pointer-events-none absolute inset-0 size-full transition-opacity duration-700"></canvas>
            <img src="${escapeHtml(photo.thumbnail)}"
                 ${photo.srcset ? `srcset="${escapeHtml(photo.srcset)}" sizes="${CARD_SIZES}"` : ''}
                 alt="${escapeHtml(photo.title || photo.caption || this.altFallback)}"
                 loading="lazy"
                 decoding="async"
                 width="${w}"
                 height="${h}"
                 class="pointer-events-none relative size-full object-cover opacity-0 transition-[opacity,transform] duration-700 ease-out group-hover:scale-[1.025]">
            ${isVideo ? `
            <div class="video-duration-badge pointer-events-none absolute bottom-2.5 left-2.5 z-[3] inline-flex items-center gap-1.5 rounded-full bg-black/55 px-2.5 py-1 text-[11px] font-medium text-white backdrop-blur-md">
                ${icon('play', 'size-3 shrink-0 fill-current')}
                <span class="tabular-nums">${escapeHtml(photo.duration || 'Video')}</span>
            </div>` : ''}
            <div class="favorite-badge pointer-events-none absolute right-2.5 top-2.5 z-[3] hidden text-white drop-shadow-[0_1px_4px_rgb(0_0_0/0.5)] transition-opacity duration-300 group-hover:opacity-0"
                 data-badge-uuid="${uuid}">
                ${icon('heart', 'size-[18px] shrink-0 fill-current')}
            </div>
            <div class="pointer-events-none absolute inset-0 z-[2] flex items-end justify-end bg-gradient-to-t from-black/50 via-black/0 to-black/0 p-2 opacity-0 transition-opacity duration-300 group-hover:opacity-100 group-focus-within:opacity-100">
                <div class="pointer-events-auto flex items-center gap-0.5">
                    <button type="button" class="btn-favorite ${btnClass}" data-uuid="${uuid}" aria-label="Favorite photo ${filename}" title="Favorite">
                        ${icon('heart', 'size-[18px] shrink-0')}
                    </button>
                    <button type="button" class="btn-share-photo ${btnClass}" data-uuid="${uuid}" data-filename="${filename}" aria-label="Share photo" title="Share">
                        ${icon('share', 'size-[18px] shrink-0')}
                    </button>
                    ${this.allowDownloads ? `
                    <button type="button" class="btn-download-photo ${btnClass}" data-uuid="${uuid}" data-filename="${filename}" data-url="${escapeHtml(downloadUrl)}" data-original-url="${escapeHtml(photo.original || photo.full)}" aria-label="Download ${isVideo ? 'video' : 'photo'}" title="Download">
                        ${icon('download', 'size-[18px] shrink-0')}
                    </button>` : ''}
                </div>
            </div>
        `;

        const canvas = card.querySelector('.photo-blurhash-canvas');
        const img = card.querySelector('img');
        if (canvas && photo.blurhash) {
            renderBlurHashToCanvas(photo.blurhash, canvas, photo.width || 1920, photo.height || 1080);
        }
        if (img) {
            if (img.complete && img.naturalWidth > 0) {
                img.classList.remove('opacity-0');
                if (canvas) canvas.classList.add('opacity-0');
            } else {
                img.addEventListener('load', () => {
                    img.classList.remove('opacity-0');
                    if (canvas) canvas.classList.add('opacity-0');
                }, { once: true });
            }
        }

        return card;
    }
}

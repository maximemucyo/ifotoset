/**
 * Gallery Pagination Module
 * Manages IntersectionObserver sentinel cursor loading
 * and feeds newly loaded photos to both DOM grid and in-memory photo store.
 */
import { renderBlurHashToCanvas } from './blurhash.js';

export class GalleryPagination {
    constructor(config) {
        this.grid = document.getElementById('gallery-grid');
        this.sentinel = document.getElementById('gallery-sentinel');
        this.spinner = document.getElementById('gallery-spinner');
        this.endText = document.getElementById('gallery-end');
        this.emptyGallery = document.getElementById('gallery-empty');
        this.emptyFavorites = document.getElementById('gallery-empty-favorites');

        this.photosUrl = config.photosUrl;
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
    }

    async loadNextBatch() {
        if (!this.hasMore || !this.nextCursor || this.isLoading) return;
        this.isLoading = true;

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
                if (this.spinner) this.spinner.classList.add('hidden');
                if (this.endText && !this.filterFavorites) this.endText.classList.remove('hidden');
                if (this.observer && this.sentinel) this.observer.unobserve(this.sentinel);
            }
        } catch (err) {
            console.error('Gallery stream error:', err);
        } finally {
            this.isLoading = false;
            if (this.spinner && !this.hasMore) this.spinner.classList.add('hidden');
        }
    }

    createCardElement(photo) {
        const aspectRatio = (photo.width && photo.height) ? (photo.width / photo.height) : 1.5;
        const card = document.createElement('div');
        card.className = 'photo-card group relative overflow-hidden rounded-none bg-card border border-border/15 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md hover:z-10 focus-within:ring-2 focus-within:ring-primary focus-within:z-10 cursor-pointer select-none';
        card.dataset.photoUuid = photo.uuid;
        card.dataset.photoFilename = photo.filename;
        card.dataset.photoLarge = photo.large || photo.full;
        card.dataset.photoFull = photo.full;
        card.dataset.photoOriginal = photo.original || photo.full;
        card.dataset.photoThumb = photo.thumbnail;
        card.dataset.photoBlurhash = photo.blurhash || '';
        card.dataset.photoWidth = photo.width || 1920;
        card.dataset.photoHeight = photo.height || 1080;
        card.setAttribute('tabindex', '0');
        card.setAttribute('role', 'button');
        card.setAttribute('aria-label', `View photo ${photo.filename}`);

        card.innerHTML = `
            <canvas class="photo-blurhash-canvas absolute inset-0 w-full h-full object-cover pointer-events-none transition-opacity duration-500"></canvas>

            <img src="${photo.thumbnail}"
                 alt="${photo.filename || 'Photo'}"
                 loading="lazy"
                 class="relative z-[1] w-full h-full object-cover transition-transform duration-500 group-hover:scale-105 pointer-events-none opacity-0 transition-opacity duration-500"
                 width="${photo.width}"
                 height="${photo.height}"
                 onload="this.classList.remove('opacity-0'); if (this.previousElementSibling) this.previousElementSibling.classList.add('opacity-0');">

            <div class="favorite-badge absolute top-3 right-3 p-1.5 rounded-none bg-black/65 backdrop-blur-sm text-rose-500 shadow-sm transition-opacity duration-300 pointer-events-none hidden"
                 data-badge-uuid="${photo.uuid}">
                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                </svg>
            </div>

            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 group-focus-visible:opacity-100 transition-opacity duration-300 flex flex-col justify-between p-3">
                <div class="flex justify-end gap-1.5" onclick="event.stopPropagation()">
                    <button type="button"
                            class="btn-favorite w-8 h-8 rounded-none bg-white/90 hover:bg-white text-zinc-900 hover:text-rose-500 flex items-center justify-center shadow-md transition-all hover:scale-105 focus:outline-none focus:ring-2 focus:ring-rose-500"
                            data-uuid="${photo.uuid}"
                            aria-label="Favorite photo">
                        <svg class="w-4 h-4 fill-none stroke-current stroke-2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                    </button>
                    <button type="button"
                            class="btn-share-photo w-8 h-8 rounded-none bg-white/90 hover:bg-white text-zinc-900 hover:text-primary flex items-center justify-center shadow-md transition-all hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary"
                            data-uuid="${photo.uuid}"
                            data-filename="${photo.filename}"
                            aria-label="Share photo">
                        <svg class="w-4 h-4 fill-none stroke-current stroke-2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                        </svg>
                    </button>
                    <button type="button"
                            class="btn-download-photo w-8 h-8 rounded-none bg-white/90 hover:bg-white text-zinc-900 hover:text-primary flex items-center justify-center shadow-md transition-all hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary"
                            data-uuid="${photo.uuid}"
                            data-filename="${photo.filename}"
                            data-url="${photo.original || photo.full}"
                            aria-label="Download photo">
                        <svg class="w-4 h-4 fill-none stroke-current stroke-2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                    </button>
                </div>

                <div class="flex items-center justify-between pointer-events-none">
                    <span class="bg-black/60 backdrop-blur-sm text-white text-[11px] px-2 py-1 rounded-none truncate max-w-[70%] font-medium">
                        ${photo.filename}
                    </span>
                    <div class="w-8 h-8 rounded-none bg-white/90 backdrop-blur-sm flex items-center justify-center text-zinc-900 shadow-sm shrink-0">
                        <svg class="w-4 h-4 fill-none stroke-current stroke-2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </div>
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

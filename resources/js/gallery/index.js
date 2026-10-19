/**
 * Gallery Main Module Entry Point
 * Orchestrates all modular components: Header, Favorites, Share, Downloads, Pagination, and Lightbox.
 */
import { initGalleryHeader } from './modules/header.js';
import { GalleryFavorites } from './modules/favorites.js';
import { GalleryShare } from './modules/share.js';
import { GalleryDownloads } from './modules/downloads.js';
import { GalleryPagination } from './modules/pagination.js';
import { GalleryLightbox } from './modules/lightbox/index.js';
import { GalleryLayout } from './modules/layout.js';
import { renderBlurHashToCanvas } from './modules/blurhash.js';
import { initDialogFocus } from './modules/dialogFocus.js';

export function bootstrapGallery() {
    const container = document.getElementById('gallery-container');
    if (!container) return;

    const slug = container.dataset.slug;
    const username = container.dataset.username;
    const photosUrl = container.dataset.photosUrl;
    const galleryUrl = container.dataset.galleryUrl || (window.location.origin + window.location.pathname);
    const exportUrl = container.dataset.exportUrl || null;
    const totalPhotos = parseInt(container.dataset.totalPhotos || '0', 10);
    const nextCursor = container.dataset.nextCursor || null;
    const hasMore = container.dataset.hasMore === 'true';
    const allowPhotoDownloads = container.dataset.allowPhotoDownloads === 'true';
    const allowGalleryDownloads = container.dataset.allowGalleryDownloads === 'true';
    const allowGooglePhotos = container.dataset.allowGooglePhotos === 'true';

    let initialDeepLinkedPhoto = null;
    try {
        const rawDeep = container.dataset.deepLinkedPhoto;
        if (rawDeep) initialDeepLinkedPhoto = JSON.parse(rawDeep);
    } catch {}

    // Extract initial photos and paint blurhash placeholders for all gallery cards
    const initialPhotos = [];
    document.querySelectorAll('.photo-card').forEach(card => {
        const pw = parseInt(card.dataset.photoWidth || '1920', 10);
        const ph = parseInt(card.dataset.photoHeight || '1080', 10);
        const blurhash = card.dataset.photoBlurhash || '';

        initialPhotos.push({
            uuid: card.dataset.photoUuid,
            filename: card.dataset.photoFilename,
            large: card.dataset.photoLarge || card.dataset.photoFull,
            full: card.dataset.photoFull,
            original: card.dataset.photoOriginal || card.dataset.photoFull,
            thumbnail: card.dataset.photoThumb,
            srcset: card.dataset.photoSrcset || null,
            is_video: card.dataset.isVideo === 'true',
            duration: card.dataset.videoDuration || '',
            delivery_url: card.dataset.photoDelivery || null,
            delivery_download_url: card.dataset.photoDeliveryDownload || null,
            blurhash: blurhash,
            width: pw,
            height: ph
        });

        // Paint blurhash placeholder onto card canvas
        const canvas = card.querySelector('.photo-blurhash-canvas');
        const img = card.querySelector('img');
        if (blurhash && canvas) {
            renderBlurHashToCanvas(blurhash, canvas, pw, ph);
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
    });

    // 1. Initialize Header
    initGalleryHeader();

    // 2. Initialize Layout (Masonry packing - zero whitespace gaps)
    const layout = new GalleryLayout({ gap: 12 });

    // 3. Initialize Favorites Manager
    const favorites = new GalleryFavorites({
        slug,
        username
    });

    // 4. Initialize Share Manager
    const share = new GalleryShare({
        slug,
        username,
        galleryUrl,
        galleryTitle: document.title
    });

    // 5. Initialize Downloads Manager
    const downloads = new GalleryDownloads({
        slug,
        username,
        galleryUrl,
        exportUrl,
        allowPhotoDownloads,
        allowGalleryDownloads,
        allowGooglePhotos
    });

    // 6. Initialize Pagination Manager
    const pagination = new GalleryPagination({
        photosUrl,
        initialNextCursor: nextCursor,
        initialHasMore: hasMore,
        allowDownloads: allowPhotoDownloads
    });

    // 7. Initialize Lightbox Controller
    const lightbox = new GalleryLightbox({
        slug,
        username,
        photosUrl,
        galleryUrl,
        initialPhotos,
        totalPhotos,
        favoritesManager: favorites,
        downloadsManager: downloads,
        shareManager: share,
        onNeedMorePhotos: () => {
            pagination.loadNextBatch();
        }
    });

    // Connect Pagination, Lightbox and Layout
    pagination.onPhotosLoaded((newPhotos) => {
        lightbox.addPhotos(newPhotos);
        favorites.applyFavoritesToDOM();
        layout.addPhotos(newPhotos);
    });

    // Connect Favorites Filter, Pagination and Layout
    favorites.onFilterChange((showOnly, favUuids) => {
        pagination.setFavoritesFilter(showOnly, favUuids);
        layout.relayout();
    });

    // Grid Card Event Delegation
    const grid = document.getElementById('gallery-grid');
    if (grid) {
        // Photos still processing have no stored size (cards fall back to 16:9 and crop portraits),
        // so reshape a card from its decoded image; naturalWidth/Height already honour EXIF orientation.
        let relayoutQueued = false;
        const syncCardAspect = (img) => {
            const card = img.closest('.photo-card');
            const nw = img.naturalWidth;
            const nh = img.naturalHeight;
            if (!card || !nw || !nh) return;
            const pw = parseInt(card.dataset.photoWidth || '0', 10);
            const ph = parseInt(card.dataset.photoHeight || '0', 10);
            if (pw && ph && Math.abs(nw / nh - pw / ph) < 0.01) return;

            card.dataset.photoWidth = nw;
            card.dataset.photoHeight = nh;
            card.style.aspectRatio = `${nw} / ${nh}`;
            const photo = lightbox.photos.find(p => p.uuid === card.dataset.photoUuid);
            if (photo) {
                photo.width = nw;
                photo.height = nh;
            }
            if (!relayoutQueued) {
                relayoutQueued = true;
                window.requestAnimationFrame(() => {
                    relayoutQueued = false;
                    layout.relayout();
                });
            }
        };
        grid.addEventListener('load', (e) => {
            if (e.target instanceof HTMLImageElement) syncCardAspect(e.target);
        }, true);
        grid.querySelectorAll('.photo-card img').forEach(img => {
            if (img.complete) syncCardAspect(img);
        });

        grid.addEventListener('click', (e) => {
            const favBtn = e.target.closest('.btn-favorite');
            if (favBtn) {
                e.stopPropagation();
                favorites.toggle(favBtn.dataset.uuid);
                return;
            }

            const shareBtn = e.target.closest('.btn-share-photo');
            if (shareBtn) {
                e.stopPropagation();
                const photo = lightbox.photos.find(p => p.uuid === shareBtn.dataset.uuid);
                if (photo) share.sharePhoto(photo);
                return;
            }

            const downloadBtn = e.target.closest('.btn-download-photo');
            if (downloadBtn) {
                e.stopPropagation();
                const photo = lightbox.photos.find(p => p.uuid === downloadBtn.dataset.uuid);
                if (photo) downloads.downloadPhoto(photo, downloadBtn);
                return;
            }

            const card = e.target.closest('.photo-card');
            if (card) {
                lightbox.openByUuid(card.dataset.photoUuid);
            }
        });

        // Keyboard activation comes from the native .photo-card-open button (Enter/Space fire click)
    }

    // Smooth Scroll CTA Button (scrolls to Action Bar directly below the banner)
    const scrollCta = document.getElementById('btn-scroll-to-gallery');
    if (scrollCta) {
        scrollCta.addEventListener('click', () => {
            const target = document.getElementById('gallery-action-bar') || document.getElementById('gallery-container');
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    }

    // Focus trap + focus return for the modals and the lightbox
    initDialogFocus();

    // Check URL for ?photo=UUID deep-linking
    lightbox.deepLink?.checkInitialUrl(initialDeepLinkedPhoto);

    // Global Escape key handler to cleanly close any active modal dialog before lightbox
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const activeModal = document.querySelector(
                '#modal-save-favorites:not(.hidden), #modal-social-share:not(.hidden), #modal-download-options:not(.hidden), #modal-google-photos-sync:not(.hidden), #modal-ios-download-notice:not(.hidden)'
            );
            if (activeModal) {
                e.preventDefault();
                e.stopImmediatePropagation();
                const closeBtn = activeModal.querySelector(
                    '#btn-close-favorites-modal, #btn-cancel-favorites-modal, #btn-close-share-modal, #btn-close-download-options, #btn-close-google-sync, #btn-close-ios-download-notice, #btn-cancel-ios-download'
                );
                if (closeBtn) {
                    closeBtn.click();
                } else {
                    activeModal.classList.add('hidden');
                }
            }
        }
    }, true);
}

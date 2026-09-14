/**
 * Gallery Lightbox Main Controller
 * Owns photo state, 3-slide carousel slot recycling, responsive image resolution,
 * blurhash rendering, and orchestrates zoom, navigation, slideshow, and deep-linking.
 */
import { LightboxZoom } from './zoom.js';
import { LightboxNavigation } from './navigation.js';
import { LightboxSlideshow } from './slideshow.js';
import { LightboxDeepLink } from './deepLink.js';
import { renderBlurHashToCanvas } from '../blurhash.js';

export class GalleryLightbox {
    constructor(config) {
        this.slug = config.slug;
        this.username = config.username;
        this.photosUrl = config.photosUrl;
        this.favoritesManager = config.favoritesManager;
        this.downloadsManager = config.downloadsManager;
        this.shareManager = config.shareManager;
        this.totalPhotos = Number.isFinite(config.totalPhotos) && config.totalPhotos > 0 ? config.totalPhotos : null;
        this.onNeedMorePhotos = config.onNeedMorePhotos || null;

        // In-memory decoupled photos state
        this.photos = [...(config.initialPhotos || [])];
        this.currentIndex = -1;
        this.isOpen = false;
        this.lastActiveElement = null;

        // Lifecycle state: 'IDLE' | 'DRAGGING' | 'ANIMATING'
        this.state = 'IDLE';
        this.currentIsFull = false;

        // DOM elements
        this.container = document.getElementById('lightbox-modal');
        this.track = document.getElementById('lightbox-track');
        this.slots = this.container ? Array.from(this.container.querySelectorAll('.lightbox-slide')) : [];

        // Dynamic 3-slot recycling map
        this.slotMap = { prev: 0, current: 1, next: 2 };

        this.filenameEl = document.getElementById('lightbox-filename');
        this.counterEl = document.getElementById('lightbox-counter');
        this.favoriteBtn = document.getElementById('lightbox-btn-favorite');
        this.shareBtn = document.getElementById('lightbox-btn-share');
        this.downloadBtn = document.getElementById('lightbox-btn-download');
        this.zoomInBtn = document.getElementById('lightbox-btn-zoom-in');
        this.zoomOutBtn = document.getElementById('lightbox-btn-zoom-out');
        this.zoomResetBtn = document.getElementById('lightbox-btn-zoom-reset');
        this.zoomValEl = document.getElementById('lightbox-zoom-val');

        this.initSubmodules();
        this.bindEvents();
    }

    initSubmodules() {
        if (!this.container || !this.track) return;

        // 1. Zoom module (with early 1.1x resolution upgrade callback)
        const initialImg = this.slots[this.slotMap.current]?.querySelector('.lightbox-slide-img');
        this.zoom = new LightboxZoom(
            initialImg,
            this.container.querySelector('.lightbox-viewport'),
            (scale) => {
                if (this.zoomValEl) this.zoomValEl.textContent = `${Math.round(scale * 100)}%`;
                if (this.zoomResetBtn) {
                    if (scale > 1) {
                        this.zoomResetBtn.classList.remove('hidden');
                    } else {
                        this.zoomResetBtn.classList.add('hidden');
                    }
                }
            },
            () => this.upgradeCurrentToFull()
        );

        // 2. Navigation module (pure interaction & physics layer)
        this.navigation = new LightboxNavigation(this);

        // 3. Slideshow module
        this.slideshow = new LightboxSlideshow(this);

        // 4. Deep-link module
        this.deepLink = new LightboxDeepLink(this);
    }

    bindEvents() {
        if (!this.container) return;

        // Close buttons
        const closeBtns = this.container.querySelectorAll('.btn-close-lightbox');
        closeBtns.forEach(btn => btn.addEventListener('click', () => this.close()));

        // Zoom controls
        if (this.zoomInBtn) this.zoomInBtn.addEventListener('click', () => this.zoom?.zoomIn());
        if (this.zoomOutBtn) this.zoomOutBtn.addEventListener('click', () => this.zoom?.zoomOut());
        if (this.zoomResetBtn) this.zoomResetBtn.addEventListener('click', () => this.zoom?.reset());

        // Actions: Favorite, Share, Download
        if (this.favoriteBtn) {
            this.favoriteBtn.addEventListener('click', () => {
                const currentPhoto = this.getCurrentPhoto();
                if (currentPhoto) {
                    this.favoritesManager?.toggle(currentPhoto.uuid);
                    this.updateFavoriteButton();
                }
            });

            // Reactively sync button whenever favorites change anywhere (e.g. after email submission)
            window.addEventListener('gallery:favorites-changed', () => {
                if (this.isOpen) this.updateFavoriteButton();
            });
        }

        if (this.shareBtn) {
            this.shareBtn.addEventListener('click', () => {
                const currentPhoto = this.getCurrentPhoto();
                if (currentPhoto) this.shareManager?.sharePhoto(currentPhoto);
            });
        }

        if (this.downloadBtn) {
            this.downloadBtn.addEventListener('click', () => {
                const currentPhoto = this.getCurrentPhoto();
                if (currentPhoto) this.downloadsManager?.downloadPhoto(currentPhoto, this.downloadBtn);
            });
        }

        // Global keyboard controls
        window.addEventListener('keydown', (e) => this.handleKeyDown(e));

        // Start slideshow from Action Bar
        const btnStartSlideshow = document.getElementById('btn-play-slideshow');
        if (btnStartSlideshow) {
            btnStartSlideshow.addEventListener('click', () => {
                if (this.photos.length > 0) {
                    this.openByIndex(0);
                    this.slideshow?.start();
                }
            });
        }
    }

    addPhotos(newPhotos) {
        newPhotos.forEach(p => this.addPhotoIfMissing(p));
        if (this.isOpen) {
            this.updateCounter();
        }
    }

    addPhotoIfMissing(photo) {
        if (!photo || !photo.uuid) return;
        const exists = this.photos.some(p => p.uuid === photo.uuid);
        if (!exists) {
            this.photos.push(photo);
        }
    }

    openByIndex(index) {
        if (index < 0 || index >= this.photos.length) return;
        this.lastActiveElement = document.activeElement;
        this.currentIndex = index;
        this.isOpen = true;
        this.state = 'IDLE';

        this.container?.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        // Reset track position and slot map
        this.slotMap = { prev: 0, current: 1, next: 2 };
        if (this.track) {
            this.track.style.transition = 'none';
            this.track.style.transform = 'translate3d(0%, 0, 0)';
        }

        this.positionSlots();
        this.renderAllSlots();

        this.container?.focus();
        this.navigation?.preloadAdjacent(this.currentIndex);
    }

    openByUuid(uuid) {
        const index = this.photos.findIndex(p => p.uuid === uuid);
        if (index !== -1) {
            this.openByIndex(index);
        }
    }

    close(clearUrl = true) {
        if (!this.isOpen) return;
        this.isOpen = false;
        this.state = 'IDLE';
        this.slideshow?.stop();
        this.zoom?.reset();

        this.container?.classList.add('hidden');
        document.body.style.overflow = '';

        if (clearUrl) {
            this.deepLink?.clearUrl();
        }

        if (this.lastActiveElement && typeof this.lastActiveElement.focus === 'function') {
            this.lastActiveElement.focus();
        }
    }

    getCurrentPhoto() {
        return (this.currentIndex >= 0 && this.currentIndex < this.photos.length)
            ? this.photos[this.currentIndex]
            : null;
    }

    getPrevPhoto() {
        if (this.photos.length <= 1) return null;
        const prevIdx = (this.currentIndex - 1 + this.photos.length) % this.photos.length;
        return this.photos[prevIdx];
    }

    getNextPhoto() {
        if (this.photos.length <= 1) return null;
        const nextIdx = (this.currentIndex + 1) % this.photos.length;
        return this.photos[nextIdx];
    }

    positionSlots() {
        if (this.slots.length < 3) return;
        this.slots[this.slotMap.prev].style.transform = 'translate3d(-100%, 0, 0)';
        this.slots[this.slotMap.current].style.transform = 'translate3d(0%, 0, 0)';
        this.slots[this.slotMap.next].style.transform = 'translate3d(100%, 0, 0)';
    }

    renderAllSlots() {
        if (this.slots.length < 3) return;
        this.renderSlide(this.slots[this.slotMap.current], this.getCurrentPhoto(), 'current');
        this.renderSlide(this.slots[this.slotMap.prev], this.getPrevPhoto(), 'prev');
        this.renderSlide(this.slots[this.slotMap.next], this.getNextPhoto(), 'next');
    }

    renderSlide(slotEl, photo, role) {
        if (!slotEl) return;
        const canvas = slotEl.querySelector('.lightbox-blurhash-canvas');
        const img = slotEl.querySelector('.lightbox-slide-img');
        if (!img) return;

        if (!photo) {
            img.style.display = 'none';
            if (canvas) canvas.style.display = 'none';
            return;
        }

        img.style.display = '';
        if (canvas) canvas.style.display = '';

        // Predictable bandwidth selection:
        // <= 768px viewports use 'lg' (~1600px WebP, saves 70% data and RAM on mobile)
        // > 768px viewports use 'xl' (~2560px WebP)
        const isMobile = window.innerWidth <= 768;
        const targetUrl = isMobile ? (photo.large || photo.full) : photo.full;

        const pw = photo.width || 1920;
        const ph = photo.height || 1080;
        const aspect = `${pw} / ${ph}`;

        // 1. Paint blurhash immediately to canvas without delaying image loading
        if (photo.blurhash && canvas) {
            renderBlurHashToCanvas(photo.blurhash, canvas, pw, ph);
            canvas.style.aspectRatio = aspect;
            canvas.style.opacity = '1';
        } else if (canvas) {
            canvas.style.opacity = '0';
        }

        img.style.aspectRatio = aspect;

        // 2. Set image source (check if URL changed to avoid reloading same image)
        if (img.dataset.loadedUrl !== targetUrl) {
            img.classList.add('opacity-0');
            img.onload = () => {
                img.classList.remove('opacity-0');
                if (canvas) canvas.style.opacity = '0';
                img.dataset.loadedUrl = targetUrl;
            };
            img.src = targetUrl;
            img.alt = photo.filename || 'Photo';
        } else if (img.complete && img.naturalWidth > 0) {
            img.classList.remove('opacity-0');
            if (canvas) canvas.style.opacity = '0';
        }

        // 3. Current active slide updates
        if (role === 'current') {
            this.currentIsFull = (targetUrl === photo.full);
            this.zoom?.setImage(img);

            if (this.filenameEl) this.filenameEl.textContent = photo.filename || '';
            this.updateCounter();
            this.updateFavoriteButton();
            this.deepLink?.syncUrl(photo.uuid);

            // Trigger safe pre-fetch when approaching end of loaded batch
            if (this.currentIndex >= this.photos.length - 4) {
                this.onNeedMorePhotos?.();
            }
        }
    }

    upgradeCurrentToFull() {
        if (this.currentIsFull) return;
        const currentPhoto = this.getCurrentPhoto();
        if (!currentPhoto || !currentPhoto.full) return;

        const currentSlot = this.slots[this.slotMap.current];
        const img = currentSlot?.querySelector('.lightbox-slide-img');
        if (img && img.src !== currentPhoto.full) {
            this.currentIsFull = true;
            img.dataset.loadedUrl = currentPhoto.full;
            img.src = currentPhoto.full;
        }
    }

    updateCounter() {
        if (this.counterEl) {
            const total = Number.isFinite(this.totalPhotos) && this.totalPhotos > 0
                ? this.totalPhotos
                : this.photos.length;
            this.counterEl.textContent = `${this.currentIndex + 1} / ${total}`;
        }
    }

    setDragOffset(deltaX) {
        if (!this.track) return;
        this.track.style.transition = 'none';
        this.track.style.transform = `translate3d(${deltaX}px, 0, 0)`;
    }

    snapTo(direction) {
        if (!this.track || this.state === 'ANIMATING') return;
        this.state = 'ANIMATING';

        const targetPercent = direction === 1 ? -100 : (direction === -1 ? 100 : 0);
        this.track.style.transition = 'transform 280ms cubic-bezier(0.25, 1, 0.5, 1)';
        this.track.style.transform = `translate3d(${targetPercent}%, 0, 0)`;

        let handled = false;
        const onEnd = () => {
            if (handled) return;
            handled = true;
            this.track.removeEventListener('transitionend', onEnd);

            if (direction === 1) {
                // Advance next: circular shift slot map
                this.currentIndex = (this.currentIndex + 1) % this.photos.length;
                const oldPrev = this.slotMap.prev;
                this.slotMap.prev = this.slotMap.current;
                this.slotMap.current = this.slotMap.next;
                this.slotMap.next = oldPrev;
            } else if (direction === -1) {
                // Advance prev: circular shift slot map
                this.currentIndex = (this.currentIndex - 1 + this.photos.length) % this.photos.length;
                const oldNext = this.slotMap.next;
                this.slotMap.next = this.slotMap.current;
                this.slotMap.current = this.slotMap.prev;
                this.slotMap.prev = oldNext;
            }

            // Reset track transform and re-position slots instantaneously
            this.track.style.transition = 'none';
            this.track.style.transform = 'translate3d(0%, 0, 0)';
            this.positionSlots();

            if (direction !== 0) {
                this.renderAllSlots();
                this.navigation?.preloadAdjacent(this.currentIndex);
            }

            this.state = 'IDLE';
        };

        this.track.addEventListener('transitionend', onEnd, { once: true });
        // Fallback timer to safeguard against skipped transitionend events
        setTimeout(onEnd, 350);
    }

    next() {
        if (this.state !== 'IDLE' || this.photos.length <= 1) return;
        this.zoom?.reset();
        this.snapTo(1);
    }

    prev() {
        if (this.state !== 'IDLE' || this.photos.length <= 1) return;
        this.zoom?.reset();
        this.snapTo(-1);
    }

    updateFavoriteButton() {
        if (!this.favoriteBtn) return;
        const currentPhoto = this.getCurrentPhoto();
        const svg = this.favoriteBtn.querySelector('svg');
        const isFav = currentPhoto && this.favoritesManager?.isFavorited(currentPhoto.uuid);

        if (isFav) {
            this.favoriteBtn.classList.add('text-rose-500', 'bg-rose-500/10');
            if (svg) svg.setAttribute('fill', 'currentColor');
        } else {
            this.favoriteBtn.classList.remove('text-rose-500', 'bg-rose-500/10');
            if (svg) svg.setAttribute('fill', 'none');
        }
    }

    handleKeyDown(e) {
        if (!this.isOpen) return;

        // Do not process lightbox shortcuts if a modal dialog is currently open
        const activeModal = document.querySelector(
            '#modal-save-favorites:not(.hidden), #modal-social-share:not(.hidden), #modal-download-options:not(.hidden), #modal-google-photos-sync:not(.hidden), #modal-ios-download-notice:not(.hidden)'
        );
        if (activeModal) return;

        const target = e.target;
        if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable) {
            return;
        }

        switch (e.key) {
            case 'Escape':
                e.preventDefault();
                this.close();
                break;
            case 'ArrowLeft':
                e.preventDefault();
                this.prev();
                break;
            case 'ArrowRight':
                e.preventDefault();
                this.next();
                break;
            case ' ':
                e.preventDefault();
                this.slideshow?.toggle();
                break;
            case 'f':
            case 'F':
                e.preventDefault();
                const photo = this.getCurrentPhoto();
                if (photo) {
                    this.favoritesManager?.toggle(photo.uuid);
                    this.updateFavoriteButton();
                }
                break;
            case 'd':
            case 'D':
                e.preventDefault();
                const curPhoto = this.getCurrentPhoto();
                if (curPhoto) this.downloadsManager?.downloadPhoto(curPhoto, this.downloadBtn);
                break;
        }
    }
}

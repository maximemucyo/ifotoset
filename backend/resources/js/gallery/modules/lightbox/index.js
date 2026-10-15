/**
 * Gallery Lightbox Main Controller
 * Owns photo state, 3-slide carousel slot recycling, responsive image resolution,
 * progressive layering (blurhash -> cached grid thumbnail -> full-res), open/close
 * transitions that fly the photo from/to its grid card, and orchestrates zoom,
 * navigation, slideshow, and deep-linking.
 */
import { LightboxZoom } from './zoom.js';
import { LightboxNavigation } from './navigation.js';
import { LightboxSlideshow } from './slideshow.js';
import { LightboxDeepLink } from './deepLink.js';
import { renderBlurHashToCanvas } from '../blurhash.js';

// hls.js + Artplayer are ~600 KB together; only fetch them the first time a video opens
let videoPlayerPromise = null;
function loadVideoPlayer() {
    videoPlayerPromise ??= Promise.all([import('artplayer'), import('hls.js')])
        .then(([artplayer, hls]) => ({ Artplayer: artplayer.default, Hls: hls.default }))
        .catch((e) => {
            videoPlayerPromise = null; // allow a retry on the next video
            throw e;
        });
    return videoPlayerPromise;
}

const EASE_OUT = 'cubic-bezier(0.22, 1, 0.36, 1)';
const SLIDE_GAP = 24; // px between neighbouring photos while swiping
const LG_WIDTH = 1600; // 'lg' rendition width; above this the 'xl' (2560) rendition is used
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

/** Transform (center origin) that maps `base` rect onto `target` rect. */
function flipTransform(target, base) {
    const tx = (target.left + target.width / 2) - (base.left + base.width / 2);
    const ty = (target.top + target.height / 2) - (base.top + base.height / 2);
    return `translate(${tx}px, ${ty}px) scale(${target.width / base.width}, ${target.height / base.height})`;
}

/** Run `done` once after a transform transition ends (with a timeout safety net). */
function afterTransition(el, ms, done) {
    let finished = false;
    const finish = () => {
        if (finished) return;
        finished = true;
        el.removeEventListener('transitionend', onEnd);
        done();
    };
    const onEnd = (e) => {
        if (e.target === el && e.propertyName === 'transform') finish();
    };
    el.addEventListener('transitionend', onEnd);
    setTimeout(finish, ms + 80);
}

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
        this.isClosing = false;
        this.lastActiveElement = null;

        // Lifecycle state: 'IDLE' | 'DRAGGING' | 'ANIMATING'
        this.state = 'IDLE';
        this.currentIsFull = false;
        this.dragOffset = 0;
        this.dismissBase = null;
        this.chromeVisible = true;
        this.hiddenCard = null;

        // DOM elements
        this.container = document.getElementById('lightbox-modal');
        this.backdrop = document.getElementById('lightbox-backdrop');
        this.track = document.getElementById('lightbox-track');
        this.viewport = this.container?.querySelector('.lightbox-viewport') || null;
        this.slots = this.container ? Array.from(this.container.querySelectorAll('.lightbox-slide')) : [];

        // Dynamic 3-slot recycling map
        this.slotMap = { prev: 0, current: 1, next: 2 };

        this.filenameEl = document.getElementById('lightbox-filename');
        this.counterEl = document.getElementById('lightbox-counter');
        this.counterTopEl = document.getElementById('lightbox-counter-top');
        this.favoriteBtn = document.getElementById('lightbox-btn-favorite');
        this.shareBtn = document.getElementById('lightbox-btn-share');
        this.downloadBtn = document.getElementById('lightbox-btn-download');
        this.zoomInBtn = document.getElementById('lightbox-btn-zoom-in');
        this.zoomOutBtn = document.getElementById('lightbox-btn-zoom-out');
        this.zoomResetBtn = document.getElementById('lightbox-btn-zoom-reset');
        this.zoomValEl = document.getElementById('lightbox-zoom-val');
        this.zoomControlsContainer = document.getElementById('lightbox-zoom-controls');
        this.activeVideo = null;
        this.activeArtplayer = null;

        this.initSubmodules();
        this.bindEvents();
    }

    initSubmodules() {
        if (!this.container || !this.track) return;

        // 1. Zoom module (with early 1.1x resolution upgrade callback)
        this.zoom = new LightboxZoom(
            this.frameOf(this.slots[this.slotMap.current]),
            this.viewport,
            (scale) => {
                if (this.zoomValEl) this.zoomValEl.textContent = `${Math.round(scale * 100)}%`;
                if (this.zoomResetBtn) this.zoomResetBtn.classList.toggle('hidden', scale <= 1);
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
            // Neighbours may have changed now that the loaded set grew past the current photo
            if (this.state === 'IDLE') {
                this.renderSlide(this.slots[this.slotMap.next], this.getNextPhoto(), 'next');
                this.renderSlide(this.slots[this.slotMap.prev], this.getPrevPhoto(), 'prev');
            }
        }
    }

    addPhotoIfMissing(photo) {
        if (!photo || !photo.uuid) return;
        const exists = this.photos.some(p => p.uuid === photo.uuid);
        if (!exists) {
            this.photos.push(photo);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Open / close                                                        */
    /* ------------------------------------------------------------------ */

    openByIndex(index) {
        if (index < 0 || index >= this.photos.length) return;

        // Already open (e.g. history navigation): jump without the entrance animation
        if (this.isOpen) {
            if (this.isClosing || this.state !== 'IDLE') return;
            this.zoom?.reset();
            this.currentIndex = index;
            this.renderAllSlots();
            this.navigation?.preloadAdjacent(this.currentIndex);
            return;
        }

        this.lastActiveElement = document.activeElement;
        this.currentIndex = index;
        this.isOpen = true;
        this.state = 'ANIMATING';
        this.dragOffset = 0;
        this.setChrome(true);

        this.container?.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        // Reset track position and slot map
        this.slotMap = { prev: 0, current: 1, next: 2 };
        if (this.track) {
            this.track.style.transition = 'none';
            this.track.style.transform = 'translate3d(0, 0, 0)';
        }

        this.positionSlots();
        this.renderAllSlots();
        this.container?.focus({ preventScroll: true });
        this.animateIn();
        this.navigation?.preloadAdjacent(this.currentIndex);
    }

    openByUuid(uuid) {
        const index = this.photos.findIndex(p => p.uuid === uuid);
        if (index !== -1) {
            this.openByIndex(index);
        }
    }

    animateIn() {
        const frame = this.currentFrame();
        const photo = this.getCurrentPhoto();
        const done = () => {
            if (frame) {
                frame.style.transition = '';
                frame.style.transform = '';
                frame.style.opacity = '';
            }
            this.restoreCard();
            if (this.isOpen && !this.isClosing) this.state = 'IDLE';
        };

        if (!frame || !this.backdrop || reducedMotion.matches) {
            if (this.backdrop) this.backdrop.style.opacity = '1';
            done();
            return;
        }

        const card = photo ? this.findCard(photo.uuid) : null;
        const cardRect = card?.getBoundingClientRect();
        const cardVisible = cardRect && cardRect.bottom > 0 && cardRect.top < window.innerHeight;
        const base = frame.getBoundingClientRect();

        frame.style.transition = 'none';
        if (cardVisible && base.width > 0) {
            frame.style.transform = flipTransform(cardRect, base);
            this.hideCard(card);
        } else {
            frame.style.transform = 'scale(0.94)';
            frame.style.opacity = '0';
        }
        this.backdrop.style.transition = 'none';
        this.backdrop.style.opacity = '0';
        this.container.dataset.chrome = 'hidden';

        void frame.offsetWidth; // commit the start state

        frame.style.transition = `transform 440ms ${EASE_OUT}, opacity 260ms ease`;
        frame.style.transform = '';
        frame.style.opacity = '';
        this.backdrop.style.transition = 'opacity 340ms ease';
        this.backdrop.style.opacity = '1';
        this.setChrome(this.chromeVisible);

        afterTransition(frame, 440, done);
    }

    close(clearUrl = true) {
        if (!this.isOpen || this.isClosing) return;
        this.isClosing = true;
        this.state = 'ANIMATING';
        this.slideshow?.stop();
        this.teardownActiveVideo();

        if (clearUrl) {
            this.deepLink?.clearUrl();
        }

        const frame = this.currentFrame();
        const photo = this.getCurrentPhoto();

        const finish = () => {
            this.container?.classList.add('hidden');
            if (frame) {
                frame.style.transition = '';
                frame.style.transform = '';
                frame.style.opacity = '';
            }
            if (this.backdrop) {
                this.backdrop.style.transition = '';
                this.backdrop.style.opacity = '';
            }
            this.restoreCard();
            this.zoom?.reset();
            this.dismissBase = null;
            this.isOpen = false;
            this.isClosing = false;
            this.state = 'IDLE';
            document.body.style.overflow = '';

            if (this.lastActiveElement && typeof this.lastActiveElement.focus === 'function') {
                this.lastActiveElement.focus({ preventScroll: true });
            }
        };

        if (!frame || !this.backdrop || reducedMotion.matches) {
            finish();
            return;
        }

        // Rect of the frame without any drag/zoom transform applied
        let base = this.dismissBase;
        if (!base) {
            this.zoom?.reset();
            frame.style.transition = 'none';
            frame.style.transform = '';
            base = frame.getBoundingClientRect();
        }

        const card = photo ? this.findCard(photo.uuid) : null;
        let cardRect = null;
        if (card) {
            cardRect = card.getBoundingClientRect();
            if (cardRect.bottom < 0 || cardRect.top > window.innerHeight) {
                // Bring the card into view behind the lightbox so the photo can land on it
                window.scrollTo({ top: window.scrollY + cardRect.top - (window.innerHeight - cardRect.height) / 2, behavior: 'instant' });
                cardRect = card.getBoundingClientRect();
            }
        }

        frame.style.transition = `transform 380ms ${EASE_OUT}, opacity 240ms ease`;
        if (cardRect && base.width > 0) {
            frame.style.transform = flipTransform(cardRect, base);
            this.hideCard(card);
        } else {
            frame.style.transform = `${frame.style.transform || ''} scale(0.92)`;
            frame.style.opacity = '0';
        }
        this.backdrop.style.transition = 'opacity 300ms ease';
        this.backdrop.style.opacity = '0';
        this.container.dataset.chrome = 'hidden';

        afterTransition(frame, 380, finish);
    }

    findCard(uuid) {
        const card = document.querySelector(`.photo-card[data-photo-uuid="${CSS.escape(uuid)}"]`);
        return card && !card.classList.contains('hidden') ? card : null;
    }

    // The flying frame stands in for the card during open/close
    hideCard(card) {
        this.restoreCard();
        card.style.visibility = 'hidden';
        this.hiddenCard = card;
    }

    restoreCard() {
        if (this.hiddenCard) this.hiddenCard.style.visibility = '';
        this.hiddenCard = null;
    }

    /* ------------------------------------------------------------------ */
    /* Chrome, taps and dismiss gesture                                    */
    /* ------------------------------------------------------------------ */

    setChrome(visible) {
        this.chromeVisible = visible;
        if (this.container) this.container.dataset.chrome = visible ? 'visible' : 'hidden';
    }

    toggleChrome() {
        this.setChrome(!this.chromeVisible);
    }

    /** Single tap toggles the controls; a quick second tap zooms (videos keep native controls). */
    handleTap(x, y) {
        const now = performance.now();
        const last = this.lastTap;
        if (last && now - last.t < 300 && Math.hypot(x - last.x, y - last.y) < 40) {
            clearTimeout(this.tapTimer);
            this.lastTap = null;
            if (!this.getCurrentPhoto()?.is_video) this.zoom?.toggleAt(x, y);
            return;
        }
        this.lastTap = { t: now, x, y };
        clearTimeout(this.tapTimer);
        this.tapTimer = setTimeout(() => {
            this.lastTap = null;
            this.toggleChrome();
        }, 300);
    }

    /** Desktop: clicking the empty stage around the photo closes the viewer. */
    handleBackdropClick(x, y) {
        if (this.state !== 'IDLE' || (this.zoom && this.zoom.scale > 1)) return;
        const rect = this.currentFrame()?.getBoundingClientRect();
        if (rect && x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom) return;
        this.close();
    }

    beginDismiss() {
        const frame = this.currentFrame();
        if (!frame) return;
        this.state = 'DRAGGING';
        this.slideshow?.stop();
        frame.style.transition = 'none';
        this.dismissBase = frame.getBoundingClientRect();
        if (this.backdrop) this.backdrop.style.transition = 'none';
        if (this.container) this.container.dataset.chrome = 'hidden';
    }

    updateDismiss(dx, dy) {
        const frame = this.currentFrame();
        if (!frame) return;
        const progress = Math.min(1, Math.abs(dy) / (window.innerHeight * 0.5));
        frame.style.transform = `translate(${dx * 0.5}px, ${dy}px) scale(${1 - progress * 0.25})`;
        if (this.backdrop) this.backdrop.style.opacity = String(1 - progress * 0.9);
    }

    endDismiss(shouldClose) {
        if (shouldClose) {
            this.close();
            return;
        }
        const frame = this.currentFrame();
        this.dismissBase = null;
        this.state = 'ANIMATING';
        if (this.backdrop) {
            this.backdrop.style.transition = 'opacity 280ms ease';
            this.backdrop.style.opacity = '1';
        }
        this.setChrome(this.chromeVisible);
        if (!frame) {
            this.state = 'IDLE';
            return;
        }
        frame.style.transition = `transform 320ms ${EASE_OUT}`;
        frame.style.transform = '';
        afterTransition(frame, 320, () => {
            frame.style.transition = '';
            if (!this.isClosing) this.state = 'IDLE';
        });
    }

    /* ------------------------------------------------------------------ */
    /* Slides                                                              */
    /* ------------------------------------------------------------------ */

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

    frameOf(slotEl) {
        return slotEl?.querySelector('.lightbox-frame') || null;
    }

    currentFrame() {
        return this.frameOf(this.slots[this.slotMap.current]);
    }

    positionSlots() {
        if (this.slots.length < 3) return;
        this.slots[this.slotMap.prev].style.transform = `translate3d(calc(-100% - ${SLIDE_GAP}px), 0, 0)`;
        this.slots[this.slotMap.current].style.transform = 'translate3d(0, 0, 0)';
        this.slots[this.slotMap.next].style.transform = `translate3d(calc(100% + ${SLIDE_GAP}px), 0, 0)`;
    }

    renderAllSlots() {
        if (this.slots.length < 3) return;
        this.renderSlide(this.slots[this.slotMap.current], this.getCurrentPhoto(), 'current');
        this.renderSlide(this.slots[this.slotMap.prev], this.getPrevPhoto(), 'prev');
        this.renderSlide(this.slots[this.slotMap.next], this.getNextPhoto(), 'next');
    }

    /**
     * Smallest rendition that stays sharp at the photo's on-screen size and pixel density:
     * a phone gets 'lg' (1600px), large/retina desktops get 'xl' (2560px).
     */
    pickUrl(photo) {
        if (photo.is_video) return photo.large || photo.full || photo.thumbnail;
        const ratio = (photo.width && photo.height) ? photo.width / photo.height : 1.5;
        const availW = window.innerWidth - (window.innerWidth >= 640 ? 176 : 0);
        const availH = window.innerHeight - 124;
        const displayW = Math.min(availW, availH * ratio);
        const needed = displayW * Math.min(window.devicePixelRatio || 1, 2);
        return (needed > LG_WIDTH * 1.15 && photo.full) ? photo.full : (photo.large || photo.full);
    }

    /** The exact rendition the grid card already decoded, so the lightbox paints instantly. */
    cachedThumbFor(photo) {
        const cardImg = this.findCard(photo.uuid)?.querySelector('img');
        return (cardImg && cardImg.complete && cardImg.currentSrc) || photo.thumbnail || '';
    }

    /**
     * Photos still processing have no stored size (the frame falls back to 16:9), so adopt the
     * decoded image's real shape; naturalWidth/Height already honour EXIF orientation.
     */
    syncAspect(frame, photo, el) {
        const nw = el.naturalWidth;
        const nh = el.naturalHeight;
        if (!nw || !nh) return;
        if (photo.width && photo.height && Math.abs(nw / nh - photo.width / photo.height) < 0.01) return;
        photo.width = nw;
        photo.height = nh;
        frame.style.setProperty('--ar', String(nw / nh));
    }

    renderSlide(slotEl, photo, role) {
        const frame = this.frameOf(slotEl);
        if (!frame) return;
        const canvas = frame.querySelector('.lightbox-blurhash-canvas');
        const thumb = frame.querySelector('.lightbox-slide-thumb');
        const img = frame.querySelector('.lightbox-slide-img');

        if (!photo) {
            frame.style.display = 'none';
            return;
        }
        frame.style.display = '';

        const pw = photo.width || 1920;
        const ph = photo.height || 1080;
        frame.style.setProperty('--ar', String(pw / ph));

        // Layer 1: blurhash
        if (canvas) {
            canvas.style.display = photo.blurhash ? '' : 'none';
            if (photo.blurhash) renderBlurHashToCanvas(photo.blurhash, canvas, pw, ph);
        }

        // Layer 2: grid thumbnail (normally already in the HTTP cache)
        const thumbSrc = this.cachedThumbFor(photo);
        if (thumb && thumb.getAttribute('src') !== thumbSrc) {
            thumb.src = thumbSrc;
        }
        if (thumb) {
            if (thumb.complete) {
                this.syncAspect(frame, photo, thumb);
            } else {
                thumb.addEventListener('load', () => {
                    if (thumb.getAttribute('src') === thumbSrc) this.syncAspect(frame, photo, thumb);
                }, { once: true });
            }
        }

        // Layer 3: display rendition, revealed only once decoded
        const targetUrl = this.pickUrl(photo);
        if (img && img.dataset.src !== targetUrl) {
            img.style.transition = 'none';
            img.classList.add('opacity-0');
            img.dataset.src = targetUrl;
            img.alt = photo.filename || 'Photo';
            img.src = targetUrl;
            img.decode().catch(() => {}).then(() => {
                if (img.dataset.src !== targetUrl || !img.naturalWidth) return;
                this.syncAspect(frame, photo, img);
                img.style.transition = '';
                img.classList.remove('opacity-0');
            });
        }

        if (role !== 'current') return;

        this.currentIsFull = (targetUrl === photo.full);
        this.teardownActiveVideo();

        if (photo.is_video) {
            if (this.zoomControlsContainer) this.zoomControlsContainer.classList.add('!hidden');
            this.slideshow?.stop();
            this.zoom?.setImage(null);
            this.mountVideo(frame, photo);
        } else {
            if (this.zoomControlsContainer) this.zoomControlsContainer.classList.remove('!hidden');
            this.zoom?.setImage(frame);
        }

        if (this.filenameEl) this.filenameEl.textContent = photo.filename || '';
        this.updateCounter();
        this.updateFavoriteButton();
        this.deepLink?.syncUrl(photo.uuid);

        // Trigger safe pre-fetch when approaching end of loaded batch
        if (this.currentIndex >= this.photos.length - 4) {
            this.onNeedMorePhotos?.();
        }
    }

    mountVideo(frame, photo) {
        const videoUrl = photo.delivery_url || photo.original;
        const poster = photo.large || photo.full || photo.thumbnail || '';
        const playerContainer = document.createElement('div');
        playerContainer.className = 'lightbox-artplayer-mount pointer-events-auto absolute inset-0 z-10 select-auto overflow-hidden bg-black';
        frame.appendChild(playerContainer);

        // Each mount gets a token so a player that finishes loading after the
        // visitor has moved on is never attached to a stale slide.
        const token = Symbol('video');
        this.videoMountToken = token;

        loadVideoPlayer()
            .then(({ Artplayer, Hls }) => {
                if (this.videoMountToken !== token || !playerContainer.isConnected) return;

                this.activeArtplayer = new Artplayer({
                    container: playerContainer,
                    url: videoUrl,
                    poster: poster,
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
                            if (Hls.isSupported()) {
                                if (artInstance.hls) artInstance.hls.destroy();
                                const hls = new Hls();
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
            })
            .catch((e) => {
                console.error('[Lightbox] Video player failed to load, using the native player:', e);
                if (this.videoMountToken !== token || !playerContainer.isConnected) return;

                const video = document.createElement('video');
                video.className = 'lightbox-slide-video size-full select-auto bg-black object-contain';
                video.controls = true;
                video.playsInline = true;
                video.preload = 'auto';
                if (poster) video.poster = poster;
                video.src = videoUrl;
                playerContainer.appendChild(video);
                this.activeVideo = video;

                video.muted = true;
                video.play().catch(() => {});
            });
    }

    teardownActiveVideo() {
        this.videoMountToken = null;
        if (this.activeArtplayer) {
            try {
                this.activeArtplayer.destroy(true);
            } catch (e) {}
            this.activeArtplayer = null;
        }

        if (this.activeVideo) {
            try {
                this.activeVideo.pause();
                this.activeVideo.removeAttribute('src');
                this.activeVideo.load();
            } catch (e) {}
            this.activeVideo.remove();
            this.activeVideo = null;
        }

        this.slots.forEach(slot => {
            slot.querySelectorAll('.lightbox-artplayer-mount').forEach(m => m.remove());
            const v = slot.querySelector('video');
            if (v) {
                try {
                    v.pause();
                    v.removeAttribute('src');
                    v.load();
                } catch (e) {}
                v.remove();
            }
        });
    }

    upgradeCurrentToFull() {
        if (this.currentIsFull) return;
        const currentPhoto = this.getCurrentPhoto();
        if (!currentPhoto || currentPhoto.is_video || !currentPhoto.full) return;

        const img = this.currentFrame()?.querySelector('.lightbox-slide-img');
        if (!img) return;
        this.currentIsFull = true;

        // Decode off-screen first so the swap never flashes
        const full = currentPhoto.full;
        const loader = new Image();
        loader.src = full;
        loader.decode().then(() => {
            if (this.getCurrentPhoto()?.uuid !== currentPhoto.uuid) return;
            img.dataset.src = full;
            img.src = full;
        }).catch(() => {});
    }

    updateCounter() {
        const total = Number.isFinite(this.totalPhotos) && this.totalPhotos > 0
            ? this.totalPhotos
            : this.photos.length;
        const text = `${this.currentIndex + 1} / ${total}`;
        if (this.counterEl) this.counterEl.textContent = text;
        if (this.counterTopEl) this.counterTopEl.textContent = text;
    }

    /* ------------------------------------------------------------------ */
    /* Navigation transitions                                              */
    /* ------------------------------------------------------------------ */

    setDragOffset(deltaX) {
        if (!this.track) return;
        this.dragOffset = deltaX;
        this.track.style.transition = 'none';
        this.track.style.transform = `translate3d(${deltaX}px, 0, 0)`;
    }

    /** Rotate the slot map after the track (or a crossfade) has landed on a neighbour. */
    shift(direction) {
        if (direction === 1) {
            this.currentIndex = (this.currentIndex + 1) % this.photos.length;
            const oldPrev = this.slotMap.prev;
            this.slotMap.prev = this.slotMap.current;
            this.slotMap.current = this.slotMap.next;
            this.slotMap.next = oldPrev;
        } else if (direction === -1) {
            this.currentIndex = (this.currentIndex - 1 + this.photos.length) % this.photos.length;
            const oldNext = this.slotMap.next;
            this.slotMap.next = this.slotMap.current;
            this.slotMap.current = this.slotMap.prev;
            this.slotMap.prev = oldNext;
        }
    }

    /**
     * Slide to the neighbour (1 / -1) or back to rest (0). Duration follows the remaining
     * distance and release velocity (px/ms) so a flick lands quickly and a button press glides.
     */
    snapTo(direction, velocity = 0) {
        if (!this.track || this.state === 'ANIMATING') return;
        this.state = 'ANIMATING';
        if (direction !== 0) this.teardownActiveVideo();

        const target = -direction * (this.track.clientWidth + SLIDE_GAP);
        const remaining = Math.abs(target - this.dragOffset);
        const duration = reducedMotion.matches
            ? 0
            : Math.round(Math.min(420, Math.max(direction === 0 ? 240 : 200, remaining / Math.max(Math.abs(velocity), 0.9))));

        const onEnd = () => {
            if (direction !== 0) this.shift(direction);

            // Reset track transform and re-position slots instantaneously
            this.dragOffset = 0;
            this.track.style.transition = 'none';
            this.track.style.transform = 'translate3d(0, 0, 0)';
            this.positionSlots();

            if (direction !== 0) {
                this.renderAllSlots();
                this.navigation?.preloadAdjacent(this.currentIndex);
            }

            if (!this.isClosing) this.state = 'IDLE';
        };

        if (duration === 0 || remaining < 1) {
            onEnd();
            return;
        }

        this.track.style.transition = `transform ${duration}ms ${EASE_OUT}`;
        this.track.style.transform = `translate3d(${target}px, 0, 0)`;
        afterTransition(this.track, duration, onEnd);
    }

    /** Slideshow advance: the next photo dissolves in over the current one. */
    fadeTo(direction = 1) {
        if (this.state !== 'IDLE' || this.photos.length <= 1) return;
        if (reducedMotion.matches) {
            this.snapTo(direction);
            return;
        }
        this.state = 'ANIMATING';
        this.zoom?.reset();
        this.teardownActiveVideo();

        const incoming = this.slots[direction === 1 ? this.slotMap.next : this.slotMap.prev];
        incoming.style.transition = 'none';
        incoming.style.transform = 'translate3d(0, 0, 0)';
        incoming.style.opacity = '0';
        incoming.style.zIndex = '2';
        void incoming.offsetWidth;
        incoming.style.transition = 'opacity 700ms ease';
        incoming.style.opacity = '1';

        setTimeout(() => {
            this.shift(direction);
            incoming.style.transition = '';
            incoming.style.opacity = '';
            incoming.style.zIndex = '';
            this.positionSlots();
            this.renderAllSlots();
            this.navigation?.preloadAdjacent(this.currentIndex);
            if (!this.isClosing) this.state = 'IDLE';
        }, 720);
    }

    next() {
        if (this.state !== 'IDLE' || this.photos.length <= 1) return;
        this.zoom?.reset();
        this.snapTo(1);
        this.slideshow?.restartTimer();
    }

    prev() {
        if (this.state !== 'IDLE' || this.photos.length <= 1) return;
        this.zoom?.reset();
        this.snapTo(-1);
        this.slideshow?.restartTimer();
    }

    updateFavoriteButton() {
        if (!this.favoriteBtn) return;
        const currentPhoto = this.getCurrentPhoto();
        const svg = this.favoriteBtn.querySelector('svg');
        const isFav = currentPhoto && this.favoritesManager?.isFavorited(currentPhoto.uuid);

        this.favoriteBtn.toggleAttribute('data-favorited', Boolean(isFav));
        this.favoriteBtn.setAttribute('aria-pressed', isFav ? 'true' : 'false');
        if (svg) svg.setAttribute('fill', isFav ? 'currentColor' : 'none');
    }

    handleKeyDown(e) {
        if (!this.isOpen || this.isClosing) return;

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
            case 'F': {
                e.preventDefault();
                const photo = this.getCurrentPhoto();
                if (photo) {
                    this.favoritesManager?.toggle(photo.uuid);
                    this.updateFavoriteButton();
                }
                break;
            }
            case 'd':
            case 'D': {
                e.preventDefault();
                const curPhoto = this.getCurrentPhoto();
                if (curPhoto) this.downloadsManager?.downloadPhoto(curPhoto, this.downloadBtn);
                break;
            }
        }
    }
}

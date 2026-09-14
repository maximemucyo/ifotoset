/**
 * Lightbox Navigation & Touch Physics Module
 * Pure interaction & physics layer:
 * - Real-time 1:1 touch dragging with threshold and velocity snapping.
 * - Strict gesture separation: horizontal drag only when zoom scale === 1.
 * - Protects against rapid-swipe race conditions by respecting lightbox lifecycle state.
 * - Keyboard shortcuts (Arrows, Escape, Space, F, D) and button bindings.
 * - Preloads only immediate adjacent photos (prev/next) for bandwidth optimization.
 */
export class LightboxNavigation {
    constructor(lightbox) {
        this.lightbox = lightbox;

        // Touch tracking state
        this.isTracking = false;
        this.isHorizontal = null;
        this.startX = 0;
        this.startY = 0;
        this.startTime = 0;

        this.init();
    }

    init() {
        const viewport = this.lightbox.container?.querySelector('.lightbox-viewport');
        if (!viewport) return;

        // Touch drag events for 1:1 mobile carousel tracking
        viewport.addEventListener('touchstart', (e) => this.handleTouchStart(e), { passive: true });
        viewport.addEventListener('touchmove', (e) => this.handleTouchMove(e), { passive: false });
        viewport.addEventListener('touchend', (e) => this.handleTouchEnd(e));
        viewport.addEventListener('touchcancel', (e) => this.handleTouchEnd(e));

        // Prev / Next button bindings
        const prevBtns = this.lightbox.container.querySelectorAll('.btn-lightbox-prev, #lightbox-btn-prev, #lightbox-btn-prev-mobile');
        const nextBtns = this.lightbox.container.querySelectorAll('.btn-lightbox-next, #lightbox-btn-next, #lightbox-btn-next-mobile');

        prevBtns.forEach(btn => btn.addEventListener('click', () => {
            if (this.lightbox.state !== 'IDLE') return;
            this.lightbox.prev();
        }));

        nextBtns.forEach(btn => btn.addEventListener('click', () => {
            if (this.lightbox.state !== 'IDLE') return;
            this.lightbox.next();
        }));
    }

    handleTouchStart(e) {
        // Only allow carousel swipe if:
        // 1. Lightbox is currently IDLE (prevents rapid-swipe corruption)
        // 2. Zoom scale is 1 (when zoomed in, touch gestures belong to image panning)
        // 3. Exactly 1 touch point
        if (this.lightbox.state !== 'IDLE' || (this.lightbox.zoom && this.lightbox.zoom.scale > 1)) {
            this.isTracking = false;
            return;
        }

        if (e.touches.length !== 1) {
            this.isTracking = false;
            return;
        }

        this.isTracking = true;
        this.isHorizontal = null;
        this.startX = e.touches[0].clientX;
        this.startY = e.touches[0].clientY;
        this.startTime = performance.now();
    }

    handleTouchMove(e) {
        if (!this.isTracking) return;

        if (this.lightbox.zoom && this.lightbox.zoom.scale > 1) {
            this.isTracking = false;
            return;
        }

        const currentX = e.touches[0].clientX;
        const currentY = e.touches[0].clientY;
        const deltaX = currentX - this.startX;
        const deltaY = currentY - this.startY;

        // Determine gesture angle early
        if (this.isHorizontal === null) {
            if (Math.abs(deltaX) > 6 || Math.abs(deltaY) > 6) {
                this.isHorizontal = Math.abs(deltaX) > Math.abs(deltaY);
                if (!this.isHorizontal) {
                    // Vertical gesture; cancel carousel tracking
                    this.isTracking = false;
                    return;
                }
            } else {
                return;
            }
        }

        if (this.isHorizontal) {
            // Prevent native page pull-down/scroll while swiping gallery photos
            if (e.cancelable) e.preventDefault();

            if (this.lightbox.state === 'IDLE') {
                this.lightbox.state = 'DRAGGING';
            }

            if (this.lightbox.state === 'DRAGGING') {
                // Apply slight boundary resistance if single photo
                const effectiveDeltaX = (this.lightbox.photos.length <= 1) ? deltaX * 0.25 : deltaX;
                this.lightbox.setDragOffset(effectiveDeltaX);
            }
        }
    }

    handleTouchEnd(e) {
        if (!this.isTracking) return;
        this.isTracking = false;

        if (this.lightbox.state !== 'DRAGGING') return;

        const endX = e.changedTouches[0]?.clientX ?? this.startX;
        const deltaX = endX - this.startX;
        const duration = Math.max(1, performance.now() - this.startTime);
        const velocity = deltaX / duration; // pixels per ms

        // Snap threshold: 50px offset or flick velocity > 0.3 px/ms
        if (this.lightbox.photos.length <= 1) {
            this.lightbox.snapTo(0);
        } else if (deltaX < -50 || velocity < -0.3) {
            this.lightbox.snapTo(1); // Next photo
        } else if (deltaX > 50 || velocity > 0.3) {
            this.lightbox.snapTo(-1); // Previous photo
        } else {
            this.lightbox.snapTo(0); // Snap back to current
        }
    }

    preloadAdjacent(currentIndex) {
        const photos = this.lightbox.photos;
        if (!photos || photos.length <= 1) return;

        const isMobile = window.innerWidth <= 768;
        const nextIdx = (currentIndex + 1) % photos.length;
        const prevIdx = (currentIndex - 1 + photos.length) % photos.length;

        // Preload only immediate next and prev (bandwidth-conserving)
        [photos[nextIdx], photos[prevIdx]].forEach(photo => {
            if (!photo) return;
            const targetUrl = isMobile ? (photo.large || photo.full) : photo.full;
            if (targetUrl) {
                const img = new Image();
                img.src = targetUrl;
            }
        });
    }
}

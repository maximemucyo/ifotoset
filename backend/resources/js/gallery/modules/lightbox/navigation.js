/**
 * Lightbox Navigation & Touch Physics Module
 * Pure interaction & physics layer:
 * - 1:1 horizontal drag between photos, snapping on distance or release velocity.
 * - Vertical drag to dismiss (photo follows the finger, backdrop fades).
 * - Tap toggles the controls, double-tap zooms (delegated to the lightbox).
 * - Gestures only start when zoom scale === 1; when zoomed, touches belong to panning.
 * - Protects against rapid-swipe race conditions by respecting lightbox lifecycle state.
 * - Preloads photos beyond the immediate neighbours (which are already rendered in their slots).
 */
const VELOCITY_WINDOW_MS = 100;

export class LightboxNavigation {
    constructor(lightbox) {
        this.lightbox = lightbox;

        // Active single-finger gesture: { x0, y0, t0, mode: null | 'swipe' | 'dismiss' | 'none', samples }
        this.gesture = null;
        this.lastTouchAt = 0;
        this.mouseDown = null;

        this.init();
    }

    init() {
        const viewport = this.lightbox.viewport;
        if (!viewport) return;

        viewport.addEventListener('touchstart', (e) => this.handleTouchStart(e), { passive: true });
        viewport.addEventListener('touchmove', (e) => this.handleTouchMove(e), { passive: false });
        viewport.addEventListener('touchend', (e) => this.handleTouchEnd(e));
        viewport.addEventListener('touchcancel', () => this.cancelGesture());

        // Desktop: click on the empty stage closes (ignoring clicks synthesised from touches and pan drags)
        viewport.addEventListener('mousedown', (e) => {
            this.mouseDown = { x: e.clientX, y: e.clientY };
        });
        viewport.addEventListener('click', (e) => {
            const down = this.mouseDown;
            this.mouseDown = null;
            if (performance.now() - this.lastTouchAt < 800) return;
            if (down && Math.hypot(e.clientX - down.x, e.clientY - down.y) > 6) return;
            this.lightbox.handleBackdropClick(e.clientX, e.clientY);
        });

        // Prev / Next button bindings
        const prevBtns = this.lightbox.container.querySelectorAll('.btn-lightbox-prev');
        const nextBtns = this.lightbox.container.querySelectorAll('.btn-lightbox-next');
        prevBtns.forEach(btn => btn.addEventListener('click', () => this.lightbox.prev()));
        nextBtns.forEach(btn => btn.addEventListener('click', () => this.lightbox.next()));
    }

    handleTouchStart(e) {
        this.lastTouchAt = performance.now();

        // A second finger means pinch-zoom: abandon any drag in progress
        if (e.touches.length !== 1) {
            this.cancelGesture();
            return;
        }

        const t = e.touches[0];
        const now = performance.now();
        this.gesture = {
            x0: t.clientX,
            y0: t.clientY,
            t0: now,
            mode: null,
            canDrag: this.lightbox.state === 'IDLE' && !(this.lightbox.zoom && this.lightbox.zoom.scale > 1),
            samples: [{ x: t.clientX, y: t.clientY, t: now }],
        };
    }

    handleTouchMove(e) {
        const g = this.gesture;
        if (!g || e.touches.length !== 1) return;

        const t = e.touches[0];
        const now = performance.now();
        const dx = t.clientX - g.x0;
        const dy = t.clientY - g.y0;

        g.samples.push({ x: t.clientX, y: t.clientY, t: now });
        while (g.samples.length > 2 && now - g.samples[0].t > VELOCITY_WINDOW_MS) g.samples.shift();

        // Lock the gesture axis once the finger has clearly moved
        if (!g.mode) {
            if (Math.hypot(dx, dy) < 8) return;
            if (!g.canDrag || this.lightbox.state !== 'IDLE') {
                g.mode = 'none';
            } else if (Math.abs(dx) > Math.abs(dy)) {
                g.mode = 'swipe';
                this.lightbox.state = 'DRAGGING';
            } else {
                g.mode = 'dismiss';
                this.lightbox.beginDismiss();
            }
        }

        if (g.mode === 'none') return;
        if (e.cancelable) e.preventDefault();

        if (g.mode === 'swipe') {
            // Resistance when there is nowhere to go
            this.lightbox.setDragOffset(this.lightbox.photos.length <= 1 ? dx * 0.25 : dx);
        } else {
            this.lightbox.updateDismiss(dx, dy);
        }
    }

    handleTouchEnd(e) {
        this.lastTouchAt = performance.now();
        const g = this.gesture;
        if (!g || e.touches.length > 0) return;
        this.gesture = null;

        const t = e.changedTouches[0];
        if (!t) return;
        const dx = t.clientX - g.x0;
        const dy = t.clientY - g.y0;

        if (!g.mode) {
            if (performance.now() - g.t0 < 350) this.lightbox.handleTap(t.clientX, t.clientY);
            return;
        }

        const { vx, vy } = this.releaseVelocity(g, t);

        if (g.mode === 'swipe') {
            if (this.lightbox.photos.length <= 1) {
                this.lightbox.snapTo(0);
            } else if (dx < -60 || (vx < -0.35 && dx < 0)) {
                this.lightbox.snapTo(1, vx);
            } else if (dx > 60 || (vx > 0.35 && dx > 0)) {
                this.lightbox.snapTo(-1, vx);
            } else {
                this.lightbox.snapTo(0, vx);
            }
            this.lightbox.slideshow?.restartTimer();
        } else if (g.mode === 'dismiss') {
            const flung = Math.abs(vy) > 0.5 && Math.sign(vy) === Math.sign(dy) && Math.abs(dy) > 30;
            this.lightbox.endDismiss(Math.abs(dy) > 120 || flung);
        }
    }

    /** Abort an in-flight drag and return to rest (e.g. a second finger lands). */
    cancelGesture() {
        const g = this.gesture;
        this.gesture = null;
        if (!g) return;
        if (g.mode === 'swipe') this.lightbox.snapTo(0);
        if (g.mode === 'dismiss') this.lightbox.endDismiss(false);
    }

    /** Velocity (px/ms) over the last ~100ms of movement, not the whole gesture. */
    releaseVelocity(g, t) {
        const now = performance.now();
        const first = g.samples.find(s => now - s.t <= VELOCITY_WINDOW_MS) || g.samples[g.samples.length - 1];
        const dt = Math.max(1, now - first.t);
        return { vx: (t.clientX - first.x) / dt, vy: (t.clientY - first.y) / dt };
    }

    /** Warm the cache two photos out in each direction; the ±1 neighbours load in their slots. */
    preloadAdjacent(currentIndex) {
        const photos = this.lightbox.photos;
        if (!photos || photos.length <= 3) return;

        [currentIndex + 2, currentIndex - 2].forEach(i => {
            const photo = photos[(i + photos.length) % photos.length];
            if (!photo || photo.is_video) return;
            const img = new Image();
            img.decoding = 'async';
            img.src = this.lightbox.pickUrl(photo);
        });
    }
}

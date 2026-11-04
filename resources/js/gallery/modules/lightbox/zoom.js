/**
 * Lightbox Zoom & Pan Controller
 * Handles mouse wheel and trackpad pinch zoom, mobile pinch-to-zoom, double-click zoom,
 * and mouse/touch panning with boundary clamping. Zooming keeps the point under the cursor
 * or fingers still, like pinching a page.
 * Strictly separates pan gestures (when scale > 1) from carousel swiping (scale === 1).
 */
export class LightboxZoom {
    constructor(imageEl, containerEl, onScaleChange, onUpgradeResolution = null) {
        this.image = imageEl;
        this.container = containerEl;
        this.onScaleChange = onScaleChange;
        this.onUpgradeResolution = onUpgradeResolution;

        this.scale = 1;
        this.position = { x: 0, y: 0 };
        this.isDragging = false;
        this.dragStart = { x: 0, y: 0 };

        // Touch pinch, or Safari's trackpad pinch: where it started, see startPinch()
        this.pinch = null;
        this.lastTouchAt = 0;

        this.init();
    }

    setImage(newImageEl) {
        if (this.image && this.image !== newImageEl) {
            this.image.style.transform = '';
            this.image.style.transition = '';
        }
        this.image = newImageEl;
        this.reset();
    }

    init() {
        if (!this.container) return;

        // Mouse wheel zoom
        this.container.addEventListener('wheel', (e) => this.handleWheel(e), { passive: false });

        // Double click toggle zoom
        this.container.addEventListener('dblclick', (e) => this.handleDoubleClick(e));

        // Drag & pan (mouse)
        this.container.addEventListener('mousedown', (e) => this.handleMouseDown(e));
        window.addEventListener('mousemove', (e) => this.handleMouseMove(e));
        window.addEventListener('mouseup', () => this.handleMouseUp());

        // Touch pinch & pan (mobile)
        this.container.addEventListener('touchstart', (e) => this.handleTouchStart(e), { passive: true });
        this.container.addEventListener('touchmove', (e) => this.handleTouchMove(e), { passive: false });
        this.container.addEventListener('touchend', (e) => this.handleTouchEnd(e));

        // Safari sends trackpad pinches as gesture events (Chrome and Firefox send ctrl + wheel)
        this.container.addEventListener('gesturestart', (e) => this.handleGestureStart(e));
        this.container.addEventListener('gesturechange', (e) => this.handleGestureChange(e));
        this.container.addEventListener('gestureend', (e) => this.handleGestureEnd(e));
    }

    clampPosition(currentScale, x, y) {
        if (currentScale <= 1 || !this.image) return { x: 0, y: 0 };

        const containerWidth = window.innerWidth;
        const containerHeight = window.innerHeight;
        const imageWidth = this.image.clientWidth || containerWidth;
        const imageHeight = this.image.clientHeight || containerHeight;

        const maxTranslateX = Math.max(0, (imageWidth * currentScale - containerWidth) / 2);
        const maxTranslateY = Math.max(0, (imageHeight * currentScale - containerHeight) / 2);

        return {
            x: Math.max(-maxTranslateX, Math.min(maxTranslateX, x)),
            y: Math.max(-maxTranslateY, Math.min(maxTranslateY, y))
        };
    }

    applyTransform(animate = true) {
        if (!this.image) return;
        this.image.style.transition = (this.isDragging || !animate) ? 'none' : 'transform 0.15s ease-out';
        this.image.style.transform = `translate(${this.position.x}px, ${this.position.y}px) scale(${this.scale})`;
        if (this.onScaleChange) this.onScaleChange(this.scale);
    }

    reset() {
        this.scale = 1;
        this.position = { x: 0, y: 0 };
        this.isDragging = false;
        this.applyTransform(false);
    }

    zoomIn() {
        const nextScale = Math.min(5, this.scale + 0.5);
        this.setScale(nextScale);
    }

    zoomOut() {
        const nextScale = Math.max(1, this.scale - 0.5);
        this.setScale(nextScale);
    }

    setScale(newScale) {
        this.scale = newScale;

        // Upgrade to full XL resolution early once user zooms past 1.1x
        if (this.scale > 1.1 && this.onUpgradeResolution) {
            this.onUpgradeResolution();
        }

        if (this.scale <= 1) {
            this.position = { x: 0, y: 0 };
        } else {
            this.position = this.clampPosition(this.scale, this.position.x, this.position.y);
        }
        this.applyTransform(true);
    }

    /** The image's centre on screen when it isn't panned (scaling happens around the centre). */
    restCentre() {
        // Offsets ignore the zoom transform, so this holds even while a zoom is still animating
        const parent = this.image.offsetParent?.getBoundingClientRect() ?? { left: 0, top: 0 };
        return {
            x: parent.left + this.image.offsetLeft + this.image.offsetWidth / 2,
            y: parent.top + this.image.offsetTop + this.image.offsetHeight / 2,
        };
    }

    /** Scale to nextScale while the image point under (clientX, clientY) stays under it. */
    zoomAt(clientX, clientY, nextScale, animate = false) {
        const centre = this.restCentre();
        const dx = clientX - centre.x;
        const dy = clientY - centre.y;
        nextScale = Math.max(1, Math.min(5, nextScale));

        if (nextScale <= 1) {
            this.reset();
            return;
        }
        const x = dx - (dx - this.position.x) * nextScale / this.scale;
        const y = dy - (dy - this.position.y) * nextScale / this.scale;
        this.scale = nextScale;
        if (this.scale > 1.1 && this.onUpgradeResolution) {
            this.onUpgradeResolution();
        }
        this.position = this.clampPosition(nextScale, x, y);
        this.applyTransform(animate);
    }

    handleWheel(e) {
        if (!this.image) return;
        e.preventDefault();

        // Line or page deltas come from a mouse wheel notch; make them pixels like a trackpad's
        const unit = e.deltaMode === 1 ? 16 : (e.deltaMode === 2 ? window.innerHeight : 1);
        const delta = e.deltaY * unit;

        // A trackpad pinch (ctrl + wheel) zooms as far as the fingers move; a wheel notch is about 15%
        const factor = e.ctrlKey
            ? Math.exp(-delta * 0.01)
            : Math.exp(-Math.max(-100, Math.min(100, delta)) * 0.0015);
        this.zoomAt(e.clientX, e.clientY, this.scale * factor);
    }

    handleDoubleClick(e) {
        // Touch double-taps are recognised by the navigation module; ignore the emulated dblclick
        if (performance.now() - this.lastTouchAt < 800) return;
        this.toggleAt(e.clientX, e.clientY);
    }

    /** Zoom to 2.5x around a point, or back to fit when already zoomed. */
    toggleAt(clientX, clientY) {
        if (!this.image) return;
        if (this.scale > 1) {
            this.reset();
        } else {
            this.zoomAt(clientX, clientY, 2.5, true);
        }
    }

    handleMouseDown(e) {
        if (this.scale <= 1) return;
        e.preventDefault();
        this.isDragging = true;
        this.dragStart = {
            x: e.clientX - this.position.x,
            y: e.clientY - this.position.y
        };
    }

    handleMouseMove(e) {
        if (!this.isDragging || this.scale <= 1) return;
        const newX = e.clientX - this.dragStart.x;
        const newY = e.clientY - this.dragStart.y;
        this.position = this.clampPosition(this.scale, newX, newY);
        this.applyTransform(false);
    }

    handleMouseUp() {
        this.isDragging = false;
    }

    /** Remember the scale, position and image point under (clientX, clientY) as a pinch starts. */
    startPinch(clientX, clientY, distance = 1) {
        const centre = this.restCentre();
        this.pinch = {
            centre,
            distance,
            scale: this.scale,
            // The image point under the fingers, in unscaled pixels from the centre
            point: {
                x: (clientX - centre.x - this.position.x) / this.scale,
                y: (clientY - centre.y - this.position.y) / this.scale,
            },
        };
    }

    /** Follow the pinch: the starting point stays under the fingers as they spread and move. */
    movePinch(clientX, clientY, ratio) {
        const p = this.pinch;
        // A little below fit while pinching in, which springs back when the fingers lift
        this.scale = Math.max(0.85, Math.min(5, p.scale * ratio));
        if (this.scale > 1.1 && this.onUpgradeResolution) {
            this.onUpgradeResolution();
        }
        const x = clientX - p.centre.x - p.point.x * this.scale;
        const y = clientY - p.centre.y - p.point.y * this.scale;
        this.position = this.scale > 1 ? this.clampPosition(this.scale, x, y) : { x, y };
        this.applyTransform(false);
    }

    endPinch() {
        this.pinch = null;
        if (this.scale <= 1) {
            this.scale = 1;
            this.position = { x: 0, y: 0 };
            this.applyTransform(true);
        }
    }

    handleTouchStart(e) {
        if (!this.image) return;
        if (e.touches.length === 2) {
            const [a, b] = e.touches;
            this.isDragging = false;
            this.startPinch((a.clientX + b.clientX) / 2, (a.clientY + b.clientY) / 2, Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY));
        } else if (e.touches.length === 1 && this.scale > 1) {
            this.isDragging = true;
            this.dragStart = {
                x: e.touches[0].clientX - this.position.x,
                y: e.touches[0].clientY - this.position.y
            };
        }
    }

    handleTouchMove(e) {
        if (e.touches.length === 2 && this.pinch) {
            e.preventDefault();
            const [a, b] = e.touches;
            const distance = Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
            this.movePinch((a.clientX + b.clientX) / 2, (a.clientY + b.clientY) / 2, distance / this.pinch.distance);
        } else if (e.touches.length === 1 && this.isDragging && this.scale > 1) {
            // When zoomed in, 1-finger drag is dedicated to image panning
            e.preventDefault();
            const newX = e.touches[0].clientX - this.dragStart.x;
            const newY = e.touches[0].clientY - this.dragStart.y;
            this.position = this.clampPosition(this.scale, newX, newY);
            this.applyTransform(false);
        }
    }

    handleTouchEnd(e) {
        this.lastTouchAt = performance.now();
        if (this.pinch) this.endPinch();
        this.isDragging = false;
        // Lifting one finger of a pinch carries on as a pan with the other
        if (e.touches.length === 1 && this.scale > 1) {
            this.isDragging = true;
            this.dragStart = {
                x: e.touches[0].clientX - this.position.x,
                y: e.touches[0].clientY - this.position.y
            };
        }
    }

    // iOS sends these alongside touches, which already handle the pinch
    handleGestureStart(e) {
        e.preventDefault();
        if (!this.image || this.pinch || performance.now() - this.lastTouchAt < 800) return;
        this.startPinch(e.clientX, e.clientY);
        this.pinch.gesture = true;
    }

    handleGestureChange(e) {
        e.preventDefault();
        if (this.pinch?.gesture) this.movePinch(e.clientX, e.clientY, e.scale);
    }

    handleGestureEnd(e) {
        e.preventDefault();
        if (this.pinch?.gesture) this.endPinch();
    }
}

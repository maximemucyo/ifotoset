/**
 * Lightbox Zoom & Pan Controller
 * Handles mouse wheel zoom, mobile pinch-to-zoom, double-click zoom,
 * and mouse/touch panning with boundary clamping.
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

        // Touch pinch
        this.pinchStartDist = null;
        this.pinchStartScale = 1;

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
        this.container.addEventListener('touchend', () => this.handleTouchEnd());
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

    handleWheel(e) {
        if (!this.image) return;
        e.preventDefault();
        const rect = this.image.getBoundingClientRect();
        const mouseX = e.clientX - rect.left - rect.width / 2;
        const mouseY = e.clientY - rect.top - rect.height / 2;

        const zoomFactor = 1.15;
        const direction = e.deltaY < 0 ? 1 : -1;
        let nextScale = this.scale * (direction > 0 ? zoomFactor : 1 / zoomFactor);
        nextScale = Math.max(1, Math.min(5, nextScale));

        if (nextScale <= 1) {
            this.reset();
        } else {
            const imageX = (mouseX - this.position.x) / this.scale;
            const imageY = (mouseY - this.position.y) / this.scale;
            const nextX = mouseX - imageX * nextScale;
            const nextY = mouseY - imageY * nextScale;

            this.scale = nextScale;
            if (this.scale > 1.1 && this.onUpgradeResolution) {
                this.onUpgradeResolution();
            }
            this.position = this.clampPosition(nextScale, nextX, nextY);
            this.applyTransform(false);
        }
    }

    handleDoubleClick(e) {
        if (!this.image) return;
        if (this.scale > 1) {
            this.reset();
        } else {
            const rect = this.image.getBoundingClientRect();
            const mouseX = e.clientX - rect.left - rect.width / 2;
            const mouseY = e.clientY - rect.top - rect.height / 2;
            const targetScale = 2.5;

            const imageX = (mouseX - this.position.x) / this.scale;
            const imageY = (mouseY - this.position.y) / this.scale;
            const nextX = mouseX - imageX * targetScale;
            const nextY = mouseY - imageY * targetScale;

            this.scale = targetScale;
            if (this.onUpgradeResolution) {
                this.onUpgradeResolution();
            }
            this.position = this.clampPosition(targetScale, nextX, nextY);
            this.applyTransform(true);
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

    handleTouchStart(e) {
        if (e.touches.length === 2) {
            this.pinchStartDist = Math.hypot(
                e.touches[0].clientX - e.touches[1].clientX,
                e.touches[0].clientY - e.touches[1].clientY
            );
            this.pinchStartScale = this.scale;
        } else if (e.touches.length === 1 && this.scale > 1) {
            this.isDragging = true;
            this.dragStart = {
                x: e.touches[0].clientX - this.position.x,
                y: e.touches[0].clientY - this.position.y
            };
        }
    }

    handleTouchMove(e) {
        if (e.touches.length === 2 && this.pinchStartDist) {
            e.preventDefault();
            const dist = Math.hypot(
                e.touches[0].clientX - e.touches[1].clientX,
                e.touches[0].clientY - e.touches[1].clientY
            );
            let nextScale = this.pinchStartScale * (dist / this.pinchStartDist);
            nextScale = Math.max(1, Math.min(5, nextScale));
            this.scale = nextScale;
            if (this.scale > 1.1 && this.onUpgradeResolution) {
                this.onUpgradeResolution();
            }
            this.position = this.clampPosition(nextScale, this.position.x, this.position.y);
            this.applyTransform(false);
        } else if (e.touches.length === 1 && this.isDragging && this.scale > 1) {
            // When zoomed in, 1-finger drag is dedicated to image panning
            e.preventDefault();
            const newX = e.touches[0].clientX - this.dragStart.x;
            const newY = e.touches[0].clientY - this.dragStart.y;
            this.position = this.clampPosition(this.scale, newX, newY);
            this.applyTransform(false);
        }
    }

    handleTouchEnd() {
        this.pinchStartDist = null;
        this.isDragging = false;
    }
}

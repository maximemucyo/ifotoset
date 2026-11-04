/**
 * Gallery grid layout: keeps the photo cards placed for the gallery's grid style (columns, rows or squares)
 * as the page resizes and more photos load. The placing itself is in ../grid-layout.js, which the page also
 * inlines so the first photos are placed before this bundle runs.
 */
import '../grid-layout.js';

export class GalleryLayout {
    constructor() {
        this.grid = document.getElementById('gallery-grid');
        if (!this.grid) return;

        this.init();
    }

    init() {
        this.relayout();

        if (window.ResizeObserver) {
            let lastWidth = this.grid.clientWidth;
            this.resizeObserver = new ResizeObserver((entries) => {
                for (const entry of entries) {
                    const width = entry.contentRect.width;
                    if (Math.abs(width - lastWidth) >= 6) {
                        lastWidth = width;
                        window.requestAnimationFrame(() => this.relayout());
                    }
                }
            });
            this.resizeObserver.observe(this.grid);
        } else {
            window.addEventListener('resize', () => {
                window.requestAnimationFrame(() => this.relayout());
            }, { passive: true });
        }
    }

    relayout() {
        if (this.grid) window.ifotosetGridLayout(this.grid);
    }

    // A justified row changes when photos join it, so new photos place the whole grid again
    addPhotos() {
        this.relayout();
    }
}

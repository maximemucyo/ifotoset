/**
 * Gallery Masonry Layout Module
 * Replicates the Next.js shortest-column packing algorithm
 * ensuring zero awkward whitespace gaps regardless of photo aspect ratio combinations.
 */
export class GalleryLayout {
    constructor(config = {}) {
        this.grid = document.getElementById('gallery-grid');
        if (!this.grid) return;

        this.gap = config.gap ?? 6;
        this.photos = [];
        this.colHeights = [];
        this.cols = 5;
        this.colWidth = 0;

        this.init();
    }

    init() {
        if (!this.grid) return;

        this.grid.style.position = 'relative';
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

    getColumnsAndGap(containerWidth) {
        let cols = 5;
        if (containerWidth < 640) {
            cols = 2;
        } else if (containerWidth < 768) {
            cols = 3;
        } else if (containerWidth < 1024) {
            cols = 4;
        } else if (containerWidth >= 1800) {
            cols = 6;
        }
        const gap = containerWidth < 768 ? 12 : this.gap;
        return { cols, gap };
    }

    relayout() {
        if (!this.grid) return;
        const containerWidth = this.grid.clientWidth;
        if (containerWidth <= 0) return;

        const { cols, gap } = this.getColumnsAndGap(containerWidth);
        this.cols = cols;
        this.colWidth = (containerWidth - (cols - 1) * gap) / cols;
        this.colHeights = new Array(cols).fill(0);

        const visibleCards = Array.from(this.grid.querySelectorAll('.photo-card:not(.hidden)'));
        
        visibleCards.forEach(card => {
            let minColIdx = 0;
            for (let i = 1; i < this.cols; i++) {
                if (this.colHeights[i] < this.colHeights[minColIdx]) {
                    minColIdx = i;
                }
            }

            const pw = parseInt(card.dataset.photoWidth || '1920', 10);
            const ph = parseInt(card.dataset.photoHeight || '1080', 10);
            const aspect = (pw && ph) ? (pw / ph) : 1.5;
            const height = this.colWidth / aspect;
            const x = minColIdx * (this.colWidth + gap);
            const y = this.colHeights[minColIdx];

            card.style.position = 'absolute';
            card.style.left = `${Math.round(x)}px`;
            card.style.top = `${Math.round(y)}px`;
            card.style.width = `${Math.round(this.colWidth)}px`;
            card.style.height = `${Math.round(height)}px`;
            card.style.margin = '0';

            this.colHeights[minColIdx] = y + height + gap;
        });

        const maxColHeight = this.colHeights.length > 0 ? Math.max(...this.colHeights) : 0;
        const totalHeight = Math.max(0, maxColHeight - gap);
        this.grid.style.height = `${Math.round(totalHeight)}px`;
    }

    addPhotos(newPhotos) {
        if (!this.grid) return;
        const containerWidth = this.grid.clientWidth;
        if (containerWidth <= 0) return;

        const { cols, gap } = this.getColumnsAndGap(containerWidth);
        if (this.cols !== cols || this.colHeights.length !== cols) {
            this.relayout();
            return;
        }

        newPhotos.forEach(photo => {
            const card = this.grid.querySelector(`.photo-card[data-photo-uuid="${photo.uuid}"]`);
            if (!card || card.classList.contains('hidden')) return;

            let minColIdx = 0;
            for (let i = 1; i < this.cols; i++) {
                if (this.colHeights[i] < this.colHeights[minColIdx]) {
                    minColIdx = i;
                }
            }

            const pw = photo.width || 1920;
            const ph = photo.height || 1080;
            const aspect = (pw && ph) ? (pw / ph) : 1.5;
            const height = this.colWidth / aspect;
            const x = minColIdx * (this.colWidth + gap);
            const y = this.colHeights[minColIdx];

            card.style.position = 'absolute';
            card.style.left = `${Math.round(x)}px`;
            card.style.top = `${Math.round(y)}px`;
            card.style.width = `${Math.round(this.colWidth)}px`;
            card.style.height = `${Math.round(height)}px`;
            card.style.margin = '0';

            this.colHeights[minColIdx] = y + height + gap;
        });

        const maxColHeight = this.colHeights.length > 0 ? Math.max(...this.colHeights) : 0;
        const totalHeight = Math.max(0, maxColHeight - gap);
        this.grid.style.height = `${Math.round(totalHeight)}px`;
    }
}

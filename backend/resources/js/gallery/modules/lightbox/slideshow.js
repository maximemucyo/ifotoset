/**
 * Lightbox Slideshow Module
 * Handles auto-advancing slideshow with 3-second interval and UI play/pause toggles.
 */
export class LightboxSlideshow {
    constructor(lightbox) {
        this.lightbox = lightbox;
        this.isPlaying = false;
        this.timer = null;
        this.intervalMs = 3000;
        this.toggleBtn = document.getElementById('lightbox-btn-slideshow');

        this.init();
    }

    init() {
        if (this.toggleBtn) {
            this.toggleBtn.addEventListener('click', () => this.toggle());
        }
    }

    start() {
        if (this.isPlaying) return;
        this.isPlaying = true;
        this.updateButton();
        this.scheduleNext();
    }

    stop() {
        if (!this.isPlaying) return;
        this.isPlaying = false;
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
        this.updateButton();
    }

    toggle() {
        if (this.isPlaying) {
            this.stop();
        } else {
            this.start();
        }
    }

    scheduleNext() {
        if (this.timer) clearInterval(this.timer);
        this.timer = setInterval(() => {
            if (this.isPlaying) {
                this.lightbox.next();
            }
        }, this.intervalMs);
    }

    updateButton() {
        if (!this.toggleBtn) return;
        const playIcon = this.toggleBtn.querySelector('.icon-play');
        const pauseIcon = this.toggleBtn.querySelector('.icon-pause');

        if (this.isPlaying) {
            this.toggleBtn.classList.add('text-primary', 'bg-primary/10');
            this.toggleBtn.setAttribute('aria-label', 'Pause Slideshow');
            if (playIcon) playIcon.classList.add('hidden');
            if (pauseIcon) pauseIcon.classList.remove('hidden');
        } else {
            this.toggleBtn.classList.remove('text-primary', 'bg-primary/10');
            this.toggleBtn.setAttribute('aria-label', 'Play Slideshow');
            if (playIcon) playIcon.classList.remove('hidden');
            if (pauseIcon) pauseIcon.classList.add('hidden');
        }
    }
}

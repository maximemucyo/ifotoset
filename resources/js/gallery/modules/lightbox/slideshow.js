/**
 * Lightbox Slideshow Module
 * Auto-advances with a crossfade every few seconds, with a thin progress bar
 * showing time to the next photo. Manual navigation restarts the countdown.
 */
export class LightboxSlideshow {
    constructor(lightbox) {
        this.lightbox = lightbox;
        this.isPlaying = false;
        this.timer = null;
        this.intervalMs = 4000;
        this.toggleBtn = document.getElementById('lightbox-btn-slideshow');
        this.progressEl = document.getElementById('lightbox-slideshow-progress');

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
        clearTimeout(this.timer);
        this.timer = null;
        this.resetProgress(false);
        this.updateButton();
    }

    toggle() {
        if (this.isPlaying) {
            this.stop();
        } else {
            this.start();
        }
    }

    restartTimer() {
        if (this.isPlaying) this.scheduleNext();
    }

    scheduleNext() {
        clearTimeout(this.timer);
        this.resetProgress(true);
        this.timer = setTimeout(() => {
            if (!this.isPlaying) return;
            this.lightbox.fadeTo(1);
            this.scheduleNext();
        }, this.intervalMs);
    }

    resetProgress(run) {
        const bar = this.progressEl;
        if (!bar) return;
        bar.style.transition = 'none';
        bar.style.transform = 'scaleX(0)';
        bar.style.opacity = run ? '1' : '0';
        if (!run) return;
        void bar.offsetWidth;
        bar.style.transition = `transform ${this.intervalMs}ms linear`;
        bar.style.transform = 'scaleX(1)';
    }

    updateButton() {
        if (!this.toggleBtn) return;
        const playIcon = this.toggleBtn.querySelector('.icon-play');
        const pauseIcon = this.toggleBtn.querySelector('.icon-pause');

        this.toggleBtn.classList.toggle('text-foreground', this.isPlaying);
        this.toggleBtn.classList.toggle('bg-muted', this.isPlaying);
        this.toggleBtn.setAttribute('aria-label', this.isPlaying ? 'Pause Slideshow' : 'Play Slideshow');
        if (playIcon) playIcon.classList.toggle('hidden', this.isPlaying);
        if (pauseIcon) pauseIcon.classList.toggle('hidden', !this.isPlaying);
    }
}

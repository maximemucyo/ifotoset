/**
 * Gallery Social Sharing Module
 * Supports Web Share API with fallback to social share modal
 * for both full gallery sharing and deep-linked photo sharing (?photo=UUID).
 */
export class GalleryShare {
    constructor(config) {
        this.slug = config.slug;
        this.username = config.username;
        this.galleryUrl = config.galleryUrl || null;
        this.galleryTitle = config.galleryTitle || document.title;
        this.modal = document.getElementById('modal-social-share');
        this.urlInput = document.getElementById('share-url-input');
        this.copyBtn = document.getElementById('btn-copy-share-link');
        this.modalTitle = document.getElementById('share-modal-title');
        this.activePhotoUuid = null;

        this.init();
    }

    init() {
        if (!this.modal) return;

        // Close button and backdrop
        const closeBtn = document.getElementById('btn-close-share-modal');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.closeModal());
        }

        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.closeModal();
            }
        });

        // Copy link action
        if (this.copyBtn && this.urlInput) {
            this.copyBtn.addEventListener('click', () => {
                this.urlInput.select();
                navigator.clipboard.writeText(this.urlInput.value).then(() => {
                    const originalText = this.copyBtn.textContent;
                    this.copyBtn.textContent = 'Copied!';
                    this.copyBtn.classList.add('bg-emerald-600', 'text-white');
                    setTimeout(() => {
                        this.copyBtn.textContent = originalText;
                        this.copyBtn.classList.remove('bg-emerald-600', 'text-white');
                    }, 2000);
                }).catch(() => {
                    // Fallback
                    document.execCommand('copy');
                });
            });
        }

        // Action Bar Share button (Full gallery)
        const galleryShareBtn = document.getElementById('btn-share-gallery');
        if (galleryShareBtn) {
            galleryShareBtn.addEventListener('click', () => this.shareGallery());
        }
    }

    getGalleryUrl(photoUuid = null) {
        const base = this.galleryUrl || (window.location.origin + window.location.pathname);
        const url = new URL(base, window.location.origin);
        if (photoUuid) {
            url.searchParams.set('photo', photoUuid);
        }
        return url.toString();
    }

    async shareGallery() {
        const url = this.getGalleryUrl();
        const shareData = {
            title: this.galleryTitle,
            text: `Check out the photo gallery "${this.galleryTitle}" by ${this.username}`,
            url: url
        };

        if (navigator.share) {
            try {
                await navigator.share(shareData);
                return;
            } catch (err) {
                if (err.name === 'AbortError') return; // User cancelled
            }
        }

        this.openModal(url, 'Share Gallery');
    }

    async sharePhoto(photo) {
        if (!photo) return;
        const url = this.getGalleryUrl(photo.uuid);
        const filename = photo.filename || 'Photo';
        const shareData = {
            title: filename,
            text: `Check out this photo from "${this.galleryTitle}"`,
            url: url
        };

        if (navigator.share) {
            try {
                await navigator.share(shareData);
                return;
            } catch (err) {
                if (err.name === 'AbortError') return;
            }
        }

        this.openModal(url, `Share Photo: ${filename}`);
    }

    openModal(url, title = 'Share Gallery') {
        if (!this.modal) return;

        if (this.modalTitle) {
            this.modalTitle.textContent = title;
        }

        if (this.urlInput) {
            this.urlInput.value = url;
        }

        // Update social link targets
        const encodedUrl = encodeURIComponent(url);
        const encodedText = encodeURIComponent(`${title}: ${url}`);

        const whatsappLink = document.getElementById('share-whatsapp');
        if (whatsappLink) whatsappLink.href = `https://api.whatsapp.com/send?text=${encodedText}`;

        const fbLink = document.getElementById('share-facebook');
        if (fbLink) fbLink.href = `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`;

        const emailLink = document.getElementById('share-email');
        if (emailLink) emailLink.href = `mailto:?subject=${encodeURIComponent(title)}&body=${encodedText}`;

        this.modal.classList.remove('hidden');
        setTimeout(() => this.urlInput?.select(), 50);
    }

    closeModal() {
        if (this.modal) {
            this.modal.classList.add('hidden');
        }
    }
}

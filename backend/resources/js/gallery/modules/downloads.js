/**
 * Gallery Downloads Module
 * Manages photo and gallery downloads across devices:
 * - Desktop & Android: Canonical direct download via backend endpoint with presigned original attachment
 * - iOS / iPhone: Modal instruction note with direct synchronous window.open for native "Save to Photos"
 * - Decoupled telemetry and export flows (ZIP & Google Photos Sync)
 */
export class GalleryDownloads {
    constructor(config) {
        this.slug = config.slug;
        this.username = config.username;
        this.galleryUrl = config.galleryUrl || null;
        this.exportUrl = config.exportUrl || null;
        this.allowPhotoDownloads = config.allowPhotoDownloads ?? true;
        this.allowGalleryDownloads = config.allowGalleryDownloads ?? false;
        this.allowGooglePhotos = config.allowGooglePhotos ?? false;
        this.csrfToken = config.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content;
        this.downloadingUuids = new Set();
        this.pendingIosPhoto = null;

        this.init();
    }

    isIos() {
        const ua = window.navigator.userAgent || '';
        return /iPad|iPhone|iPod/.test(ua) || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
    }

    init() {
        // Download options button on Action Bar
        const btnDownloadOptions = document.getElementById('btn-download-options');
        if (btnDownloadOptions) {
            btnDownloadOptions.addEventListener('click', () => this.handleGalleryDownloadTrigger());
        }

        this.initModals();
    }

    handleGalleryDownloadTrigger() {
        if (this.allowGalleryDownloads && this.allowGooglePhotos) {
            this.openDownloadOptionsModal();
        } else if (this.allowGalleryDownloads) {
            this.navigateToExport('zip');
        } else if (this.allowGooglePhotos) {
            this.openGooglePhotosModal();
        }
    }

    navigateToExport(type = 'zip', target = 'all') {
        const base = this.exportUrl || (this.galleryUrl ? `${this.galleryUrl.replace(/\/+$/, '')}/export` : (window.location.origin + `/p/${this.username}/${this.slug}/export`));
        const url = new URL(base, window.location.origin);
        url.searchParams.set('type', type);
        if (target) url.searchParams.set('target', target);
        window.location.href = url.toString();
    }

    initModals() {
        // 1. Download Options Modal
        const optionsModal = document.getElementById('modal-download-options');
        const closeOptionsBtn = document.getElementById('btn-close-download-options');
        const chooseZipBtn = document.getElementById('btn-choose-zip-download');
        const chooseGoogleBtn = document.getElementById('btn-choose-google-sync');

        if (optionsModal) {
            if (closeOptionsBtn) {
                closeOptionsBtn.addEventListener('click', () => optionsModal.classList.add('hidden'));
            }
            optionsModal.addEventListener('click', (e) => {
                if (e.target === optionsModal) optionsModal.classList.add('hidden');
            });

            if (chooseZipBtn) {
                chooseZipBtn.addEventListener('click', () => {
                    optionsModal.classList.add('hidden');
                    this.navigateToExport('zip');
                });
            }

            if (chooseGoogleBtn) {
                chooseGoogleBtn.addEventListener('click', () => {
                    optionsModal.classList.add('hidden');
                    this.openGooglePhotosModal();
                });
            }
        }

        // 2. Google Photos Sync Modal
        const googleModal = document.getElementById('modal-google-photos-sync');
        const closeGoogleBtn = document.getElementById('btn-close-google-sync');
        const formGoogleSync = document.getElementById('form-google-photos-sync');

        if (googleModal) {
            if (closeGoogleBtn) {
                closeGoogleBtn.addEventListener('click', () => googleModal.classList.add('hidden'));
            }
            googleModal.addEventListener('click', (e) => {
                if (e.target === googleModal) googleModal.classList.add('hidden');
            });

            if (formGoogleSync) {
                formGoogleSync.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const targetInput = formGoogleSync.querySelector('input[name="syncTarget"]:checked');
                    const target = targetInput ? targetInput.value : 'all';

                    if (target === 'favorites') {
                        const rawFavs = localStorage.getItem(`gallery_favorites_${this.slug}`) || '[]';
                        sessionStorage.setItem(`photos_export_favorites_${this.slug}`, rawFavs);
                    } else {
                        sessionStorage.removeItem(`photos_export_favorites_${this.slug}`);
                    }

                    googleModal.classList.add('hidden');
                    this.navigateToExport('google-photos', target);
                });
            }
        }

        // 3. iOS Download Notice Modal
        const iosModal = document.getElementById('modal-ios-download-notice');
        const closeIosBtn = document.getElementById('btn-close-ios-download-notice');
        const cancelIosBtn = document.getElementById('btn-cancel-ios-download');
        const confirmIosBtn = document.getElementById('btn-confirm-ios-download');

        if (iosModal) {
            const closeIos = () => {
                iosModal.classList.add('hidden');
                this.pendingIosPhoto = null;
            };

            closeIosBtn?.addEventListener('click', closeIos);
            cancelIosBtn?.addEventListener('click', closeIos);
            iosModal.addEventListener('click', (e) => {
                if (e.target === iosModal) closeIos();
            });

            confirmIosBtn?.addEventListener('click', () => {
                const photo = this.pendingIosPhoto;
                if (!photo) {
                    closeIos();
                    return;
                }

                const targetUrl = photo.original || photo.full;
                // Synchronous window.open in direct click context to avoid Safari popup blocking
                window.open(targetUrl, '_blank', 'noopener');
                closeIos();

                // Telemetry: record download initiated
                this.recordDownloadTelemetry(photo.uuid);
            });
        }
    }

    openDownloadOptionsModal() {
        const modal = document.getElementById('modal-download-options');
        if (modal) modal.classList.remove('hidden');
    }

    openGooglePhotosModal() {
        const modal = document.getElementById('modal-google-photos-sync');
        if (modal) modal.classList.remove('hidden');
    }

    /**
     * Download single photo directly and log telemetry.
     */
    async downloadPhoto(photo, triggerButton = null) {
        if (!photo || !photo.uuid) return;
        if (this.downloadingUuids.has(photo.uuid)) return;

        // iOS Flow: Display instruction note and open original in new tab for long-press save
        if (this.isIos()) {
            this.pendingIosPhoto = photo;
            const iosModal = document.getElementById('modal-ios-download-notice');
            if (iosModal) {
                iosModal.classList.remove('hidden');
                return;
            }

            // Fallback if modal is missing in DOM
            window.open(photo.original || photo.full, '_blank', 'noopener');
            this.recordDownloadTelemetry(photo.uuid);
            return;
        }

        // Canonical Desktop & Android Flow: Backend endpoint with presigned original attachment
        this.downloadingUuids.add(photo.uuid);

        if (triggerButton) {
            triggerButton.classList.add('opacity-70', 'cursor-wait');
        }

        const baseUrl = this.galleryUrl || (window.location.origin + `/p/${this.username}/${this.slug}`);
        const downloadUrl = `${baseUrl.replace(/\/+$/, '')}/photos/${photo.uuid}/download`;
        const link = document.createElement('a');
        link.href = downloadUrl;
        link.setAttribute('download', photo.filename || 'photo.jpg');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        setTimeout(() => {
            this.downloadingUuids.delete(photo.uuid);
            if (triggerButton) {
                triggerButton.classList.remove('opacity-70', 'cursor-wait');
            }
        }, 1200);
    }

    /**
     * Record initiated download telemetry asynchronously.
     */
    async recordDownloadTelemetry(photoUuid) {
        try {
            const rawIdentity = localStorage.getItem(`visitor_identity_${this.slug}`);
            const email = rawIdentity ? JSON.parse(rawIdentity).email : null;

            fetch(`/api/v1/public/galleries/${this.slug}/download`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                body: JSON.stringify({
                    photo_uuid: photoUuid,
                    email: email
                })
            }).catch(() => {});
        } catch {}
    }
}

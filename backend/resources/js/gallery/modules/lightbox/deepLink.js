/**
 * Lightbox Deep-Linking Module
 * Manages URL synchronization (?photo=UUID), browser history,
 * and asynchronous resolution of photos outside the initial batch.
 */
export class LightboxDeepLink {
    constructor(lightbox) {
        this.lightbox = lightbox;
        this.slug = lightbox.slug;
        this.username = lightbox.username;
        this.photosUrl = lightbox.photosUrl;

        this.init();
    }

    init() {
        // Popstate handler for browser back/forward
        window.addEventListener('popstate', () => {
            const params = new URLSearchParams(window.location.search);
            const photoUuid = params.get('photo');
            if (photoUuid) {
                this.resolveAndOpen(photoUuid);
            } else if (this.lightbox.isOpen) {
                this.lightbox.close(false); // don't push state again
            }
        });
    }

    checkInitialUrl(initialDeepLinkedPhoto = null) {
        const params = new URLSearchParams(window.location.search);
        const photoUuid = params.get('photo');
        if (!photoUuid) return;

        if (initialDeepLinkedPhoto && initialDeepLinkedPhoto.uuid === photoUuid) {
            this.lightbox.addPhotoIfMissing(initialDeepLinkedPhoto);
            this.lightbox.openByUuid(photoUuid);
            return;
        }

        this.resolveAndOpen(photoUuid);
    }

    async resolveAndOpen(uuid) {
        // 1. Check if already in in-memory photo collection
        const existing = this.lightbox.photos.find(p => p.uuid === uuid);
        if (existing) {
            this.lightbox.openByUuid(uuid);
            return;
        }

        // 2. Fetch photo metadata from server if outside loaded batches
        try {
            const url = new URL(this.photosUrl, window.location.origin);
            url.searchParams.set('uuid', uuid);

            const response = await fetch(url.toString(), {
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) return;

            const result = await response.json();
            if (result.data) {
                this.lightbox.addPhotoIfMissing(result.data);
                this.lightbox.openByUuid(uuid);
            }
        } catch (err) {
            console.error('Failed to resolve deep-linked photo:', err);
        }
    }

    syncUrl(photoUuid) {
        if (!photoUuid) return;
        const url = new URL(window.location.href);
        url.searchParams.set('photo', photoUuid);
        window.history.replaceState({ photoUuid }, '', url.toString());
    }

    clearUrl() {
        const url = new URL(window.location.href);
        if (url.searchParams.has('photo')) {
            url.searchParams.delete('photo');
            window.history.replaceState({}, '', url.toString());
        }
    }
}

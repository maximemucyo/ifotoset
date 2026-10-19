/**
 * Gallery Favorites Module
 * Optimistic client cache (localStorage) + Idempotent server synchronization
 * Handles visitor email collection modal and favorites-only filter mode.
 */
export class GalleryFavorites {
    constructor(config) {
        this.slug = config.slug;
        this.username = config.username;
        // Same-origin path: the session cookie is host-only on the photographer subdomain
        this.apiBase = `/api/v1/public/galleries/${encodeURIComponent(this.username)}/${encodeURIComponent(this.slug)}`;
        this.csrfToken = config.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content;
        this.storageKey = `gallery_favorites_${this.slug}`;
        this.identityKey = `visitor_identity_${this.slug}`;
        this.favorites = this.loadFavorites();
        this.visitorIdentity = this.loadIdentity();
        this.showFavoritesOnly = false;
        this.onFilterChangeCallbacks = [];
        this.pendingPhotoUuid = null;

        this.initDOM();
    }

    loadFavorites() {
        try {
            const raw = localStorage.getItem(this.storageKey);
            return raw ? JSON.parse(raw) : [];
        } catch {
            return [];
        }
    }

    loadIdentity() {
        try {
            const raw = localStorage.getItem(this.identityKey);
            return raw ? JSON.parse(raw) : null;
        } catch {
            return null;
        }
    }

    saveFavorites() {
        try {
            localStorage.setItem(this.storageKey, JSON.stringify(this.favorites));
        } catch (e) {
            console.error('Failed to save favorites to localStorage:', e);
        }
    }

    saveIdentity(email) {
        this.visitorIdentity = {
            email: email.trim().toLowerCase(),
            created_at: new Date().toISOString()
        };
        try {
            localStorage.setItem(this.identityKey, JSON.stringify(this.visitorIdentity));
        } catch (e) {
            console.error('Failed to save visitor identity to localStorage:', e);
        }
    }

    isFavorited(uuid) {
        return this.favorites.includes(uuid);
    }

    getCount() {
        return this.favorites.length;
    }

    onFilterChange(callback) {
        this.onFilterChangeCallbacks.push(callback);
    }

    initDOM() {
        this.applyFavoritesToDOM();
        this.initEmailModal();
        this.initFilterButton();
    }

    applyFavoritesToDOM() {
        // 1. Update card badges
        document.querySelectorAll('.favorite-badge').forEach(badge => {
            const uuid = badge.dataset.badgeUuid;
            if (this.isFavorited(uuid)) {
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        });

        // 2. Update card buttons
        document.querySelectorAll('.btn-favorite').forEach(btn => {
            const uuid = btn.dataset.uuid;
            const svg = btn.querySelector('svg');
            const fav = this.isFavorited(uuid);
            btn.toggleAttribute('data-favorited', fav);
            btn.setAttribute('aria-pressed', fav ? 'true' : 'false');
            if (svg) svg.setAttribute('fill', fav ? 'currentColor' : 'none');
        });

        // 3. Update header counter if present
        const counterEl = document.getElementById('favorites-counter');
        if (counterEl) {
            counterEl.textContent = this.favorites.length;
        }
    }

    async toggle(photoUuid) {
        if (!photoUuid) return;

        // If email hasn't been collected yet, prompt visitor
        if (!this.visitorIdentity?.email) {
            this.pendingPhotoUuid = photoUuid;
            this.openEmailModal();
            return;
        }

        const currentlyFavorited = this.isFavorited(photoUuid);
        const nextState = !currentlyFavorited;

        if (nextState) {
            if (!this.favorites.includes(photoUuid)) {
                this.favorites.push(photoUuid);
            }
        } else {
            this.favorites = this.favorites.filter(id => id !== photoUuid);
        }

        this.saveFavorites();
        this.applyFavoritesToDOM();

        // Notify filter callbacks if in favorites-only mode
        if (this.showFavoritesOnly) {
            this.notifyFilterChange();
        }

        window.dispatchEvent(new CustomEvent('gallery:favorites-changed', {
            detail: { photoUuid, isFavorite: nextState }
        }));

        // Idempotent server synchronization
        try {
            await fetch(`${this.apiBase}/favorite`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                body: JSON.stringify({
                    photo_uuid: photoUuid,
                    is_favorite: nextState,
                    email: this.visitorIdentity.email
                })
            });
        } catch (err) {
            console.error('Failed to sync favorite with server:', err);
        }
    }

    initEmailModal() {
        const modal = document.getElementById('modal-save-favorites');
        const form = document.getElementById('form-save-favorites');
        const emailInput = document.getElementById('input-favorites-email');
        const closeBtn = document.getElementById('btn-close-favorites-modal');
        const cancelBtn = document.getElementById('btn-cancel-favorites-modal');

        if (!modal || !form) return;

        const closeModal = () => {
            modal.classList.add('hidden');
            this.pendingPhotoUuid = null;
        };

        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const email = emailInput?.value || '';
            if (email && email.includes('@')) {
                this.saveIdentity(email);
                modal.classList.add('hidden');

                if (this.pendingPhotoUuid) {
                    const targetUuid = this.pendingPhotoUuid;
                    this.pendingPhotoUuid = null;
                    this.toggle(targetUuid);
                }
            }
        });
    }

    openEmailModal() {
        const modal = document.getElementById('modal-save-favorites');
        const emailInput = document.getElementById('input-favorites-email');
        if (modal) {
            modal.classList.remove('hidden');
            setTimeout(() => emailInput?.focus(), 50);
        }
    }

    initFilterButton() {
        const filterBtn = document.getElementById('btn-filter-favorites');
        if (!filterBtn) return;

        filterBtn.addEventListener('click', () => {
            this.showFavoritesOnly = !this.showFavoritesOnly;

            filterBtn.setAttribute('aria-pressed', this.showFavoritesOnly ? 'true' : 'false');
            const svg = filterBtn.querySelector('svg');
            if (svg) svg.setAttribute('fill', this.showFavoritesOnly ? 'currentColor' : 'none');

            this.notifyFilterChange();
        });
    }

    notifyFilterChange() {
        this.onFilterChangeCallbacks.forEach(cb => cb(this.showFavoritesOnly, this.favorites));
    }
}

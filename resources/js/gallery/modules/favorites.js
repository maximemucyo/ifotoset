/**
 * Gallery Favorites Module
 * The server keeps each visitor's picks by email; localStorage is the fast copy shown before it answers.
 * Handles the email modal, the favorites-only filter, the photographer's pick limit and sending the picks.
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
        this.enabled = config.enabled !== false;
        this.limit = config.limit || null;
        this.photographerName = config.photographerName || 'the photographer';
        this.submittedAt = null;
        this.changedSinceSubmit = false;
        this.barMessage = null;
        this.barTimer = null;

        if (!this.enabled) return;
        this.initDOM();
        this.syncFromServer();
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
        document.getElementById('btn-submit-favorites')?.addEventListener('click', () => this.submit());
    }

    async request(path, options = {}) {
        const res = await fetch(`${this.apiBase}${path}`, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': this.csrfToken,
                ...(options.headers || {})
            }
        });
        const json = await res.json().catch(() => ({}));
        return { ok: res.ok, status: res.status, json };
    }

    /**
     * The server's list wins. Picks this browser kept from before they were stored are sent up once, on the
     * first sync, so a photo removed on another phone doesn't come back later.
     */
    async syncFromServer() {
        const email = this.visitorIdentity?.email;
        if (!email) return;

        try {
            const { ok, json } = await this.request(`/favorites?email=${encodeURIComponent(email)}`);
            if (!ok) return;

            const server = Array.isArray(json.photos) ? json.photos : [];
            const restoreKey = `gallery_favorites_restored_${this.slug}`;
            let alreadyRestored = false;
            try { alreadyRestored = localStorage.getItem(restoreKey) === '1'; } catch {}
            const localOnly = alreadyRestored ? [] : this.favorites.filter(uuid => !server.includes(uuid));
            this.submittedAt = json.submitted_at || null;
            this.changedSinceSubmit = Boolean(json.changed);
            this.limit = json.limit || null;

            const restored = [];
            for (const uuid of localOnly) {
                const res = await this.request('/favorite', {
                    method: 'POST',
                    body: JSON.stringify({ photo_uuid: uuid, is_favorite: true, email, restore: true })
                });
                if (res.ok) restored.push(uuid);
            }

            try { localStorage.setItem(restoreKey, '1'); } catch {}

            this.favorites = [...server, ...restored];
            this.saveFavorites();
            this.applyFavoritesToDOM();
            if (this.showFavoritesOnly) this.notifyFilterChange();
        } catch (err) {
            console.error('Failed to load favorites from server:', err);
        }
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

        this.updateBar();
    }

    async toggle(photoUuid) {
        if (!photoUuid || !this.enabled) return;

        // If email hasn't been collected yet, prompt visitor
        if (!this.visitorIdentity?.email) {
            this.pendingPhotoUuid = photoUuid;
            this.openEmailModal();
            return;
        }

        const currentlyFavorited = this.isFavorited(photoUuid);
        const nextState = !currentlyFavorited;

        if (nextState && this.limit && this.favorites.length >= this.limit) {
            this.flashBar(`You can pick up to ${this.limit} photos. Remove one to add another.`);
            return;
        }

        this.setFavorite(photoUuid, nextState);

        try {
            const { ok, status, json } = await this.request('/favorite', {
                method: 'POST',
                body: JSON.stringify({
                    photo_uuid: photoUuid,
                    is_favorite: nextState,
                    email: this.visitorIdentity.email
                })
            });
            if (ok) {
                if (this.submittedAt) this.changedSinceSubmit = true;
                this.updateBar();
            } else if (status === 422 || status === 403 || status === 404) {
                // The server said no (limit reached, favorites turned off, photo gone): undo
                this.setFavorite(photoUuid, currentlyFavorited);
                if (json.message) this.flashBar(json.message);
            }
        } catch (err) {
            console.error('Failed to sync favorite with server:', err);
        }
    }

    setFavorite(photoUuid, isFavorite) {
        if (isFavorite) {
            if (!this.favorites.includes(photoUuid)) this.favorites.push(photoUuid);
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
            detail: { photoUuid, isFavorite }
        }));
    }

    async submit() {
        const email = this.visitorIdentity?.email;
        const btn = document.getElementById('btn-submit-favorites');
        if (!email || !this.favorites.length) return;

        if (btn) btn.disabled = true;
        try {
            const { ok, json } = await this.request('/favorites/submit', {
                method: 'POST',
                body: JSON.stringify({ email })
            });
            if (ok) {
                this.submittedAt = json.submitted_at;
                this.changedSinceSubmit = false;
                this.flashBar(`Sent. ${this.photographerName} can see your ${this.countLabel()}.`, 5000);
            } else {
                this.flashBar(json.message || "Couldn't send. Try again.");
            }
        } catch {
            this.flashBar("Couldn't send. Check your connection and try again.");
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    countLabel() {
        const n = this.favorites.length;
        return `${n} ${n === 1 ? 'pick' : 'picks'}`;
    }

    flashBar(message, ms = 4000) {
        this.barMessage = message;
        clearTimeout(this.barTimer);
        this.updateBar();
        this.barTimer = setTimeout(() => {
            this.barMessage = null;
            this.updateBar();
        }, ms);
    }

    /**
     * The bar shows while looking at favorites, or briefly with a message.
     */
    updateBar() {
        const bar = document.getElementById('favorites-bar');
        const text = document.getElementById('favorites-bar-text');
        const btn = document.getElementById('btn-submit-favorites');
        if (!bar || !text) return;

        const n = this.favorites.length;
        const visible = Boolean(this.barMessage) || (this.showFavoritesOnly && n > 0);
        bar.classList.toggle('hidden', !visible);
        if (!visible) return;

        const picked = this.limit ? `${n} of ${this.limit} picked.` : `${n} picked.`;
        let status = ` Send them to ${this.photographerName} when you're done.`;
        if (this.submittedAt) {
            status = this.changedSinceSubmit ? ' You changed your picks since you sent them.' : ` Sent to ${this.photographerName}.`;
        }
        text.textContent = this.barMessage || picked + status;

        if (btn) {
            btn.classList.toggle('hidden', n === 0 || (Boolean(this.submittedAt) && !this.changedSinceSubmit));
            btn.textContent = this.submittedAt ? 'Send again' : 'Send my picks';
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

                const targetUuid = this.pendingPhotoUuid;
                this.pendingPhotoUuid = null;
                // Picks made on another device come back first, so the new one adds to them
                this.syncFromServer().then(() => {
                    if (targetUuid && !this.isFavorited(targetUuid)) this.toggle(targetUuid);
                });
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
            this.updateBar();
        });
    }

    notifyFilterChange() {
        this.onFilterChangeCallbacks.forEach(cb => cb(this.showFavoritesOnly, this.favorites));
    }
}

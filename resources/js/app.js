import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import collapse from '@alpinejs/collapse';

Alpine.plugin(focus);
Alpine.plugin(collapse);

window.Alpine = Alpine;

// CSRF token for fetch requests
const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (token) {
    window.csrfToken = token;
}

/* -------------------------------------------------------------------------
 | Pages served from Cloudflare's cache carry a token from a discarded session.
 | When a same-origin POST is rejected as expired (419), fetch a fresh token,
 | which also starts this visitor's session, and retry once. Later requests
 | reuse the fresh token.
 * ---------------------------------------------------------------------- */
const nativeFetch = window.fetch.bind(window);
let freshToken = null;

window.fetch = async (input, init = {}) => {
    const method = (init.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();
    const url = new URL(input instanceof Request ? input.url : input, location.href);
    if (['GET', 'HEAD', 'OPTIONS'].includes(method) || url.origin !== location.origin || input instanceof Request) {
        return nativeFetch(input, init);
    }

    const withToken = (value) => {
        const headers = new Headers(init.headers || {});
        if (value) headers.set('X-CSRF-TOKEN', value);
        return { ...init, headers };
    };

    const response = await nativeFetch(input, freshToken ? withToken(freshToken) : init);
    if (response.status !== 419) {
        return response;
    }

    const tokenResponse = await nativeFetch('/csrf-token', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (!tokenResponse.ok) {
        return response;
    }
    freshToken = (await tokenResponse.json()).token;
    window.csrfToken = freshToken;
    document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', freshToken);

    return nativeFetch(input, withToken(freshToken));
};

/* -------------------------------------------------------------------------
 | Theme (light / dark / system)
 | The initial class is applied by an inline <head> script to avoid a flash.
 * ---------------------------------------------------------------------- */
const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
const forcedTheme = document.documentElement.dataset.forceTheme || null;

function readThemePreference() {
    try {
        return localStorage.getItem('theme') || 'system';
    } catch {
        return 'system';
    }
}

function applyTheme(mode) {
    if (forcedTheme) mode = forcedTheme;
    const dark = mode === 'dark' || (mode === 'system' && prefersDark.matches);
    document.documentElement.classList.toggle('dark', dark);
}

Alpine.store('theme', {
    mode: readThemePreference(),
    get isDark() {
        return this.mode === 'dark' || (this.mode === 'system' && prefersDark.matches);
    },
    set(mode) {
        this.mode = mode;
        try {
            if (mode === 'system') localStorage.removeItem('theme');
            else localStorage.setItem('theme', mode);
        } catch {
            /* storage unavailable */
        }
        applyTheme(mode);
    },
    toggle() {
        this.set(this.isDark ? 'light' : 'dark');
    },
});

prefersDark.addEventListener('change', () => {
    if (Alpine.store('theme').mode === 'system') applyTheme('system');
});

/* -------------------------------------------------------------------------
 | Toasts: window.toast('Saved', 'success') or dispatch a `toast` event
 * ---------------------------------------------------------------------- */
window.toast = (message, type = 'info', timeout = 4500) => {
    window.dispatchEvent(new CustomEvent('toast', { detail: { message, type, timeout } }));
};

const toaster = (initialMessage = '', initialType = 'info') => ({
    toasts: [],
    nextId: 1,
    init() {
        if (initialMessage) {
            this.push(initialMessage, initialType);
        }
        window.addEventListener('toast', (e) => {
            const { message, type = 'info', timeout = 4500 } = e.detail || {};
            if (message) this.push(message, type, timeout);
        });
    },
    push(message, type = 'info', timeout = 4500) {
        const id = this.nextId++;
        this.toasts.push({ id, message, type, visible: true });
        if (this.toasts.length > 4) this.toasts.shift();
        setTimeout(() => this.dismiss(id), timeout);
    },
    dismiss(id) {
        const t = this.toasts.find((x) => x.id === id);
        if (t) t.visible = false;
        setTimeout(() => {
            this.toasts = this.toasts.filter((x) => x.id !== id);
        }, 250);
    },
    // Back-compat with the previous single-toast API
    trigger(msg, type = 'info', timeout = 4500) {
        this.push(msg, type, timeout);
    },
});

Alpine.data('toaster', toaster);
Alpine.data('toastNotification', toaster);

/* -------------------------------------------------------------------------
 | Clipboard helper: <button x-data @click="$clipboard(url)">
 * ---------------------------------------------------------------------- */
Alpine.magic('clipboard', () => async (text, message = 'Copied to clipboard') => {
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
    }
    window.toast(message, 'success', 2500);
});

/* -------------------------------------------------------------------------
 | Studio URL field: x-data="studioUsername({ initial, original, url })"
 | Asks the server whether the typed username is free, after a short pause.
 * ---------------------------------------------------------------------- */
Alpine.data('studioUsername', ({ initial = '', original = null, url }) => ({
    username: initial || '',
    original,
    edited: false,
    status: 'idle', // idle | checking | available | unavailable
    message: '',
    suggestion: null,
    timer: null,
    request: null,
    init() {
        // Prefilled suggestions (Google sign-up) or a value sent back after an error get checked straight away
        if (this.username && this.username !== this.original) this.check();
    },
    onInput(e) {
        this.username = e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '').slice(0, 50);
        this.edited = true;
        this.queue();
    },
    useSuggestion() {
        this.username = this.suggestion;
        this.edited = true;
        this.check();
    },
    queue() {
        clearTimeout(this.timer);
        this.request?.abort();
        this.status = 'idle';
        // Too short to be valid yet: wait for more typing instead of showing an error
        if (this.username.length < 3 || this.username === this.original) return;
        this.timer = setTimeout(() => this.check(), 400);
    },
    async check() {
        clearTimeout(this.timer);
        this.request?.abort();
        const value = this.username;
        const request = (this.request = new AbortController());
        this.status = 'checking';
        try {
            const res = await fetch(`${url}?username=${encodeURIComponent(value)}`, {
                headers: { Accept: 'application/json' },
                signal: request.signal,
            });
            if (!res.ok) throw new Error(res.status);
            const data = await res.json();
            if (value !== this.username) return;
            this.status = data.available ? 'available' : 'unavailable';
            this.message = data.message || '';
            this.suggestion = data.suggestion;
        } catch {
            // Offline, throttled or aborted: say nothing, the form still validates on submit
            if (value === this.username && !request.signal.aborted) this.status = 'idle';
        }
    },
}));

/* -------------------------------------------------------------------------
 | Email sign-up: x-data="signupForm({ step, emailError, checkUrl })"
 | Step 1 asks only for the email and checks it with the server; step 2 has the
 | rest of the details. Both steps are one form, so a single POST creates the studio.
 * ---------------------------------------------------------------------- */
Alpine.data('signupForm', ({ step = 1, emailError = '', checkUrl }) => ({
    step,
    email: '',
    emailError,
    checking: false,
    submitting: false,
    password: '',
    init() {
        this.email = this.$refs.email.value;
        // The page heading sits outside the form and follows the step
        this.$watch('step', (value) => window.dispatchEvent(new CustomEvent('signup-step', { detail: value })));
        // Browser back from step 2 returns to step 1 instead of leaving the page
        window.addEventListener('popstate', () => {
            if (this.step === 2) this.showEmailStep();
        });
    },
    get strength() {
        let s = 0;
        if (this.password.length >= 8) s++;
        if (/[A-Z]/.test(this.password) && /[a-z]/.test(this.password)) s++;
        if (/\d/.test(this.password)) s++;
        if (/[^A-Za-z0-9]/.test(this.password) || this.password.length >= 14) s++;
        return s;
    },
    get strengthLabel() {
        return ['Too short', 'Weak', 'Fair', 'Good', 'Strong'][this.strength];
    },
    onSubmit(e) {
        // Never submit a half-filled form from step 1
        if (this.step === 1) {
            e.preventDefault();
            this.continueWithEmail();
            return;
        }
        this.submitting = true;
    },
    async continueWithEmail() {
        const input = this.$refs.email;
        this.emailError = '';
        if (!input.reportValidity() || this.checking) return;

        this.checking = true;
        try {
            const res = await fetch(checkUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken },
                body: JSON.stringify({ email: this.email }),
            });
            if (res.status === 422) {
                this.emailError = (await res.json()).errors?.email?.[0] || 'Enter a different email address.';
                this.$nextTick(() => input.focus());
                return;
            }
        } catch {
            // Offline or throttled: carry on, the final submit validates the email again
        } finally {
            this.checking = false;
        }
        this.showDetailsStep();
    },
    showDetailsStep() {
        this.step = 2;
        history.pushState({ signupStep: 2 }, '');
        this.$nextTick(() => this.$refs.name.focus());
    },
    changeEmail() {
        // Undo our own history entry when there is one, so back doesn't step through it again
        if (history.state?.signupStep) history.back();
        else this.showEmailStep();
    },
    showEmailStep() {
        this.step = 1;
        this.$nextTick(() => this.$refs.email.focus());
    },
}));

/* -------------------------------------------------------------------------
 | Dropdown menu: x-data="dropdown"
 * ---------------------------------------------------------------------- */
Alpine.data('dropdown', () => ({
    open: false,
    toggle() {
        this.open = !this.open;
    },
    close() {
        this.open = false;
    },
}));

/* -------------------------------------------------------------------------
 | Command palette (⌘K / Ctrl+K)
 | Items come from a JSON <script type="application/json" id="command-items">
 * ---------------------------------------------------------------------- */
Alpine.data('commandPalette', () => ({
    open: false,
    query: '',
    active: 0,
    items: [],
    init() {
        const source = document.getElementById('command-items');
        if (source) {
            try {
                this.items = JSON.parse(source.textContent || '[]');
            } catch {
                this.items = [];
            }
        }
        window.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                this.toggle();
            }
        });
        window.addEventListener('open-command-palette', () => this.show());
        this.$watch('query', () => (this.active = 0));
    },
    get filtered() {
        const q = this.query.trim().toLowerCase();
        if (!q) return this.items;
        return this.items.filter((i) =>
            `${i.label} ${i.group || ''} ${i.keywords || ''}`.toLowerCase().includes(q)
        );
    },
    show() {
        this.open = true;
        this.query = '';
        this.active = 0;
        this.$nextTick(() => this.$refs.input?.focus());
    },
    hide() {
        this.open = false;
    },
    toggle() {
        this.open ? this.hide() : this.show();
    },
    move(delta) {
        const n = this.filtered.length;
        if (!n) return;
        this.active = (this.active + delta + n) % n;
        this.$nextTick(() => {
            this.$refs.list?.querySelector(`[data-index="${this.active}"]`)?.scrollIntoView({ block: 'nearest' });
        });
    },
    go(item = null) {
        const target = item || this.filtered[this.active];
        if (!target) return;
        this.hide();
        if (target.newTab) window.open(target.url, '_blank', 'noopener');
        else window.location.href = target.url;
    },
}));

/* -------------------------------------------------------------------------
 | Scroll reveal for [data-reveal] elements
 * ---------------------------------------------------------------------- */
function initReveal() {
    const els = document.querySelectorAll('[data-reveal]:not(.is-revealed)');
    if (!els.length) return;
    if (!('IntersectionObserver' in window)) {
        els.forEach((el) => el.classList.add('is-revealed'));
        return;
    }
    const io = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed');
                    io.unobserve(entry.target);
                }
            });
        },
        { rootMargin: '0px 0px -8% 0px', threshold: 0.08 }
    );
    els.forEach((el) => io.observe(el));
}

// Alpine.start() dispatches `alpine:init`, so inline page components can register beforehand
Alpine.start();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReveal);
} else {
    initReveal();
}

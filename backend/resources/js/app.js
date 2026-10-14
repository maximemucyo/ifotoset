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
 | Theme (light / dark / system)
 | The initial class is applied by an inline <head> script to avoid a flash.
 * ---------------------------------------------------------------------- */
const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');

function readThemePreference() {
    try {
        return localStorage.getItem('theme') || 'system';
    } catch {
        return 'system';
    }
}

function applyTheme(mode) {
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

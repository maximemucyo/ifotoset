import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Configure global CSRF token helper for fetch requests
const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (token) {
    window.csrfToken = token;
}

// Flash Toast helper
Alpine.data('toastNotification', (initialMessage = '', initialType = 'info') => ({
    show: Boolean(initialMessage),
    message: initialMessage,
    type: initialType,
    init() {
        if (this.show) {
            setTimeout(() => {
                this.show = false;
            }, 5000);
        }
        window.addEventListener('toast', (e) => {
            const { message, type = 'info', timeout = 5000 } = e.detail || {};
            this.trigger(message, type, timeout);
        });
    },
    trigger(msg, type = 'info', timeout = 5000) {
        this.message = msg;
        this.type = type;
        this.show = true;
        setTimeout(() => {
            this.show = false;
        }, timeout);
    },
}));

// Dispatch alpine:init so inline scripts and custom page components can register before start
document.dispatchEvent(new CustomEvent('alpine:init'));

Alpine.start();


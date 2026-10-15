/**
 * Focus management for every [role="dialog"] on the gallery page (modals and
 * the lightbox). Dialogs open and close by toggling the `hidden` class from
 * several modules, so this watches that class instead of wrapping each module:
 *  - on open: remember the focused element, move focus into the dialog
 *  - while open: keep Tab / Shift+Tab inside the topmost open dialog
 *  - on close: return focus to where it was
 * Escape handling stays in index.js / the lightbox.
 */
const FOCUSABLE = [
    'a[href]', 'button:not([disabled])', 'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])', 'textarea:not([disabled])', '[tabindex]:not([tabindex="-1"])',
].join(',');

function isVisible(el) {
    return el.offsetParent !== null || el.getClientRects().length > 0;
}

function focusables(dialog) {
    return Array.from(dialog.querySelectorAll(FOCUSABLE)).filter(isVisible);
}

export function initDialogFocus(root = document) {
    const dialogs = Array.from(root.querySelectorAll('[role="dialog"]'));
    const openStack = [];
    const returnTo = new Map();

    const isOpen = (dialog) => !dialog.classList.contains('hidden');

    const onOpen = (dialog) => {
        if (openStack.includes(dialog)) return;
        returnTo.set(dialog, document.activeElement);
        openStack.push(dialog);

        requestAnimationFrame(() => {
            if (dialog.contains(document.activeElement)) return; // the dialog focused itself
            const first = dialog.querySelector('[autofocus]') || focusables(dialog)[0];
            (first || dialog).focus({ preventScroll: true });
        });
    };

    const onClose = (dialog) => {
        const index = openStack.indexOf(dialog);
        if (index === -1) return;
        openStack.splice(index, 1);

        const target = returnTo.get(dialog);
        returnTo.delete(dialog);
        // Wait a frame: the element may be hidden until the closing animation finishes
        requestAnimationFrame(() => {
            if (target && document.contains(target) && isVisible(target)) {
                target.focus({ preventScroll: true });
            }
        });
    };

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            const dialog = mutation.target;
            isOpen(dialog) ? onOpen(dialog) : onClose(dialog);
        }
    });

    dialogs.forEach((dialog) => {
        if (!dialog.hasAttribute('tabindex')) dialog.setAttribute('tabindex', '-1');
        observer.observe(dialog, { attributes: true, attributeFilter: ['class'] });
        if (isOpen(dialog)) onOpen(dialog);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Tab' || openStack.length === 0) return;

        const dialog = openStack[openStack.length - 1];
        const items = focusables(dialog);
        if (items.length === 0) {
            e.preventDefault();
            dialog.focus({ preventScroll: true });
            return;
        }

        const first = items[0];
        const last = items[items.length - 1];
        const active = document.activeElement;

        if (!dialog.contains(active)) {
            e.preventDefault();
            first.focus();
        } else if (e.shiftKey && (active === first || active === dialog)) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && active === last) {
            e.preventDefault();
            first.focus();
        }
    });
}

/**
 * Icon helper for HTML rendered from JS.
 * Icons are server-rendered once by <x-icon> into <template id="gallery-icon-sprites">
 * so JS-built markup uses the exact same Lucide set as Blade.
 */
let cache = null;

function load() {
    if (cache) return cache;
    cache = {};
    const tpl = document.getElementById('gallery-icon-sprites');
    if (!tpl) return cache;
    tpl.content.querySelectorAll('[data-icon]').forEach(el => {
        const svg = el.querySelector('svg');
        if (svg) cache[el.dataset.icon] = svg.outerHTML;
    });
    return cache;
}

export function icon(name, className = '') {
    const html = load()[name];
    if (!html) return '';
    if (!className) return html;
    // Replace the default class list with the requested one.
    return html.replace(/class="[^"]*"/, `class="${className}"`);
}

export function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/**
 * Gallery Dynamic Header Module
 * The top bar floats transparently over the hero (white text over a cover photo),
 * then slides away once the sticky toolbar (#gallery-action-bar) takes over.
 * Scrolling back up near the top reveals it again.
 */
export function initGalleryHeader() {
    const header = document.getElementById('gallery-header');
    const hero = document.getElementById('gallery-hero');
    if (!header) return;

    const overImage = hero?.dataset.variant === 'image';
    let ticking = false;

    function update() {
        ticking = false;
        const y = window.scrollY;
        const heroBottom = hero ? hero.offsetHeight - 64 : Infinity;
        const pastHero = y >= heroBottom;

        header.dataset.state = overImage && !pastHero ? 'overlay' : 'plain';
        header.style.transform = pastHero ? 'translateY(-100%)' : 'translateY(0)';
        header.style.pointerEvents = pastHero ? 'none' : '';
    }

    window.addEventListener('scroll', () => {
        if (!ticking) {
            ticking = true;
            window.requestAnimationFrame(update);
        }
    }, { passive: true });
    window.addEventListener('resize', update, { passive: true });
    update();

    initToolbarTitle(hero);
}

/**
 * Layouts that set the title on the page (Framed, Split, No cover) would show it twice, close together:
 * the toolbar's copy fades in once the page's title has scrolled away.
 */
function initToolbarTitle(hero) {
    const pageTitle = hero?.querySelector('h1');
    const toolbarTitle = document.getElementById('gallery-action-title');
    if (!pageTitle || !toolbarTitle || !['framed', 'split', 'none'].includes(hero.dataset.variant) || !window.IntersectionObserver) return;

    new IntersectionObserver(([entry]) => {
        toolbarTitle.style.opacity = entry.isIntersecting ? '0' : '1';
    }).observe(pageTitle);
}

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
}

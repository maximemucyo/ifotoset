/**
 * Gallery Dynamic Header Module
 * Handles scroll auto-hide (down hides, up reveals with threshold)
 * and transparency transition over the hero cover image.
 */
export function initGalleryHeader() {
    const header = document.getElementById('gallery-header');
    const hero = document.getElementById('gallery-hero');
    if (!header) return;

    let lastScrollY = window.scrollY;
    const threshold = 10;

    function handleScroll() {
        const currentScrollY = window.scrollY;
        const diff = currentScrollY - lastScrollY;

        // 1. Hide / Reveal on scroll direction
        if (currentScrollY <= 50) {
            header.style.transform = 'translateY(0)';
        } else if (Math.abs(diff) > threshold) {
            if (diff > 0) {
                // Scrolling down -> hide header
                header.style.transform = 'translateY(-100%)';
            } else {
                // Scrolling up -> reveal header
                header.style.transform = 'translateY(0)';
            }
        }

        // 2. Hero Transparency Transition
        if (hero) {
            const heroHeight = hero.offsetHeight;
            if (currentScrollY < heroHeight - 80) {
                header.classList.add('bg-black/20', 'text-white', 'backdrop-blur-[2px]', 'border-white/10');
                header.classList.remove('bg-card/90', 'text-foreground', 'backdrop-blur-md', 'border-border');
            } else {
                header.classList.remove('bg-black/20', 'text-white', 'backdrop-blur-[2px]', 'border-white/10');
                header.classList.add('bg-card/90', 'text-foreground', 'backdrop-blur-md', 'border-border');
            }
        }

        lastScrollY = currentScrollY;
    }

    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll(); // Initial check
}

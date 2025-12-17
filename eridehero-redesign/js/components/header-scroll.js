/**
 * Header Scroll Behavior
 * Hides header on scroll down, shows on scroll up
 * Adds scrolled state for background styling
 */

export function initHeaderScroll() {
    const header = document.querySelector('.header');
    if (!header) return null;

    let lastScrollY = 0;
    let ticking = false;

    // Threshold values
    const SCROLL_THRESHOLD = 10; // Minimum scroll distance to trigger hide/show
    const TOP_THRESHOLD = 200; // Header becomes fixed after scrolling past this
    const TRANSITION_DURATION = 300; // Must match CSS transition duration

    let isTransitioning = false;

    function updateHeader() {
        if (isTransitioning) {
            ticking = false;
            return;
        }

        // Don't update header when mobile menu is open
        if (document.body.classList.contains('menu-open')) {
            ticking = false;
            return;
        }

        const currentScrollY = window.scrollY;
        const isFixed = header.classList.contains('header--fixed');
        const isHidden = header.classList.contains('header--hidden');
        const isVisible = isFixed && !isHidden;

        // At the very top - transition to static
        if (currentScrollY <= 0) {
            if (isFixed) {
                // Remove scrolled styling first (fade out bg + shadow)
                header.classList.remove('header--scrolled');

                // Wait for transition, then make static
                isTransitioning = true;
                setTimeout(() => {
                    header.classList.remove('header--fixed');
                    header.classList.remove('header--hidden');
                    document.body.classList.remove('header-is-fixed');
                    isTransitioning = false;
                }, TRANSITION_DURATION);
            }
            lastScrollY = currentScrollY;
            ticking = false;
            return;
        }

        // Below 200px threshold
        if (currentScrollY < TOP_THRESHOLD) {
            // If header is fixed and visible, keep it visible (don't hide)
            // If header is fixed but hidden, clean up silently
            if (isFixed && isHidden) {
                header.classList.remove('header--fixed');
                header.classList.remove('header--hidden');
                header.classList.remove('header--scrolled');
                document.body.classList.remove('header-is-fixed');
            }
            // If header is visible, keep it as is (stays fixed + visible)
            // If header was never fixed, keep it static

            lastScrollY = currentScrollY;
            ticking = false;
            return;
        }

        // Past 200px threshold - make header fixed if not already
        if (!isFixed) {
            // First time becoming fixed - start hidden with no transition
            header.classList.add('header--no-transition');
            header.classList.add('header--hidden');
            header.classList.add('header--fixed');
            header.classList.add('header--scrolled');
            document.body.classList.add('header-is-fixed');

            // Re-enable transitions after a frame
            requestAnimationFrame(() => {
                header.classList.remove('header--no-transition');
            });
        }

        const scrollDelta = currentScrollY - lastScrollY;

        // Only trigger hide/show if scroll delta exceeds threshold
        if (Math.abs(scrollDelta) < SCROLL_THRESHOLD) {
            ticking = false;
            return;
        }

        // Scrolling down - hide header
        if (scrollDelta > 0) {
            header.classList.add('header--hidden');
        }
        // Scrolling up - show header
        else if (scrollDelta < 0) {
            header.classList.remove('header--hidden');
        }

        lastScrollY = currentScrollY;
        ticking = false;
    }

    function onScroll() {
        if (!ticking) {
            requestAnimationFrame(updateHeader);
            ticking = true;
        }
    }

    // Add scroll listener
    window.addEventListener('scroll', onScroll, { passive: true });

    // Initial state check
    updateHeader();

    return {
        destroy() {
            window.removeEventListener('scroll', onScroll);
        }
    };
}

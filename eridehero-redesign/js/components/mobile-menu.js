/**
 * Mobile Menu Component
 * Handles hamburger toggle, mobile navigation, and accordion submenus
 */

export function initMobileMenu() {
    const menuToggle = document.querySelector('.menu-toggle');
    const mobileMenu = document.getElementById('mobile-menu');
    const mobileNavItems = document.querySelectorAll('.mobile-nav-item[data-has-submenu]');
    const body = document.body;

    if (!menuToggle || !mobileMenu) return null;

    let isOpen = false;
    let focusTrapCleanup = null;
    let scrollPosition = 0;

    // Focus trap for accessibility
    function trapFocus(element) {
        const focusableElements = element.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        const firstFocusable = focusableElements[0];
        const lastFocusable = focusableElements[focusableElements.length - 1];

        function handleTabKey(e) {
            if (e.key !== 'Tab') return;

            if (e.shiftKey) {
                if (document.activeElement === firstFocusable) {
                    lastFocusable.focus();
                    e.preventDefault();
                }
            } else {
                if (document.activeElement === lastFocusable) {
                    firstFocusable.focus();
                    e.preventDefault();
                }
            }
        }

        element.addEventListener('keydown', handleTabKey);
        return () => element.removeEventListener('keydown', handleTabKey);
    }

    function open() {
        isOpen = true;

        // Save scroll position before locking body
        scrollPosition = window.scrollY;
        body.style.top = `-${scrollPosition}px`;

        menuToggle.classList.add('active');
        menuToggle.setAttribute('aria-expanded', 'true');
        menuToggle.setAttribute('aria-label', 'Close menu');
        mobileMenu.classList.add('active');
        body.classList.add('menu-open');

        // Focus first interactive element after animation
        setTimeout(() => {
            const firstLink = mobileMenu.querySelector('.mobile-nav-link');
            if (firstLink) firstLink.focus();
        }, 300);

        // Set up focus trap
        focusTrapCleanup = trapFocus(mobileMenu);
    }

    function close() {
        isOpen = false;
        menuToggle.classList.remove('active');
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.setAttribute('aria-label', 'Open menu');
        mobileMenu.classList.remove('active');
        body.classList.remove('menu-open');

        // Restore scroll position after unlocking body
        body.style.top = '';
        window.scrollTo(0, scrollPosition);

        // Collapse all submenus
        mobileNavItems.forEach(item => {
            item.classList.remove('expanded');
            const btn = item.querySelector('.mobile-nav-link');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        });

        // Clean up focus trap
        if (focusTrapCleanup) {
            focusTrapCleanup();
            focusTrapCleanup = null;
        }

        // Return focus to menu toggle
        menuToggle.focus();
    }

    function toggle() {
        if (isOpen) {
            close();
        } else {
            open();
        }
    }

    function toggleSubmenu(item) {
        const isExpanded = item.classList.contains('expanded');
        const btn = item.querySelector('.mobile-nav-link');

        // Close other submenus (accordion behavior)
        mobileNavItems.forEach(otherItem => {
            if (otherItem !== item) {
                otherItem.classList.remove('expanded');
                const otherBtn = otherItem.querySelector('.mobile-nav-link');
                if (otherBtn) otherBtn.setAttribute('aria-expanded', 'false');
            }
        });

        // Toggle current submenu
        if (isExpanded) {
            item.classList.remove('expanded');
            btn.setAttribute('aria-expanded', 'false');
        } else {
            item.classList.add('expanded');
            btn.setAttribute('aria-expanded', 'true');
        }
    }

    // Event Listeners
    menuToggle.addEventListener('click', toggle);

    // Mobile submenu toggles
    mobileNavItems.forEach(item => {
        const btn = item.querySelector('.mobile-nav-link');
        if (btn) {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                toggleSubmenu(item);
            });
        }
    });

    // Prevent scroll issues on iOS
    mobileMenu.addEventListener('touchmove', (e) => {
        const scrollTop = mobileMenu.scrollTop;
        const scrollHeight = mobileMenu.scrollHeight;
        const height = mobileMenu.clientHeight;

        if ((scrollTop === 0 && e.touches[0].clientY > 0) ||
            (scrollTop + height >= scrollHeight && e.touches[0].clientY < 0)) {
            // At boundaries, prevent overscroll
        }
    }, { passive: true });

    // Public API
    return {
        open,
        close,
        toggle,
        isOpen: () => isOpen
    };
}

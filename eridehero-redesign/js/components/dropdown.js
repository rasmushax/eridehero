/**
 * Desktop Dropdown Component
 * Handles dropdown navigation with full keyboard support
 */

export function initDropdowns() {
    const desktopDropdowns = document.querySelectorAll('.nav-item[data-dropdown]');

    if (!desktopDropdowns.length) return null;

    let activeDropdown = null;

    function openDropdown(navItem) {
        const btn = navItem.querySelector('.nav-link');
        const dropdown = navItem.querySelector('.dropdown');

        // Close any other open dropdown
        if (activeDropdown && activeDropdown !== navItem) {
            closeDropdown(activeDropdown);
        }

        navItem.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
        activeDropdown = navItem;

        // Focus first menu item
        const firstItem = dropdown.querySelector('a[role="menuitem"]');
        if (firstItem) {
            setTimeout(() => firstItem.focus(), 50);
        }
    }

    function closeDropdown(navItem) {
        const btn = navItem.querySelector('.nav-link');
        navItem.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
        if (activeDropdown === navItem) {
            activeDropdown = null;
        }
    }

    function closeAllDropdowns() {
        desktopDropdowns.forEach(item => {
            closeDropdown(item);
        });
    }

    // Set up each dropdown
    desktopDropdowns.forEach(navItem => {
        const btn = navItem.querySelector('.nav-link');
        const dropdown = navItem.querySelector('.dropdown');

        // Click to toggle
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const isOpen = navItem.classList.contains('is-open');
            if (isOpen) {
                closeDropdown(navItem);
                btn.focus();
            } else {
                openDropdown(navItem);
            }
        });

        // Keyboard navigation within dropdown
        dropdown.addEventListener('keydown', (e) => {
            const menuItems = dropdown.querySelectorAll('a[role="menuitem"]');
            const currentIndex = Array.from(menuItems).indexOf(document.activeElement);

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    if (currentIndex < menuItems.length - 1) {
                        menuItems[currentIndex + 1].focus();
                    } else {
                        menuItems[0].focus(); // Loop to first
                    }
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    if (currentIndex > 0) {
                        menuItems[currentIndex - 1].focus();
                    } else {
                        menuItems[menuItems.length - 1].focus(); // Loop to last
                    }
                    break;
                case 'Escape':
                    e.preventDefault();
                    closeDropdown(navItem);
                    btn.focus();
                    break;
                case 'Tab':
                    // Close dropdown when tabbing out
                    if (e.shiftKey && currentIndex === 0) {
                        closeDropdown(navItem);
                    } else if (!e.shiftKey && currentIndex === menuItems.length - 1) {
                        closeDropdown(navItem);
                    }
                    break;
                case 'Home':
                    e.preventDefault();
                    menuItems[0].focus();
                    break;
                case 'End':
                    e.preventDefault();
                    menuItems[menuItems.length - 1].focus();
                    break;
            }
        });

        // Close on mouse leave (maintains hover behavior)
        navItem.addEventListener('mouseleave', () => {
            // Small delay to prevent accidental closes
            setTimeout(() => {
                if (!navItem.contains(document.activeElement)) {
                    closeDropdown(navItem);
                }
            }, 100);
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        if (activeDropdown && !activeDropdown.contains(e.target)) {
            closeAllDropdowns();
        }
    });

    // Public API
    return {
        closeAll: closeAllDropdowns,
        getActive: () => activeDropdown
    };
}

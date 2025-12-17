/**
 * Search Component
 * Handles search toggle, dropdown, and keyboard shortcuts
 */

export function initSearch() {
    const searchToggle = document.querySelector('.search-toggle');
    const searchDropdown = document.getElementById('search-dropdown');
    const searchInput = searchDropdown?.querySelector('.search-input');
    const searchClose = searchDropdown?.querySelector('.search-close');
    const inlineSearch = document.querySelector('.search-inline');
    const inlineInput = inlineSearch?.querySelector('.search-inline-input');

    if (!searchToggle || !searchDropdown) return null;

    let isOpen = false;

    function open() {
        isOpen = true;
        searchToggle.setAttribute('aria-expanded', 'true');
        searchDropdown.classList.add('active');

        // Focus input after animation
        setTimeout(() => {
            if (searchInput) searchInput.focus();
        }, 100);
    }

    function close() {
        isOpen = false;
        searchToggle.setAttribute('aria-expanded', 'false');
        searchDropdown.classList.remove('active');
    }

    function toggle() {
        if (isOpen) {
            close();
            searchToggle.focus();
        } else {
            open();
        }
    }

    function focusInlineSearch() {
        if (inlineSearch && getComputedStyle(inlineSearch).display !== 'none') {
            if (inlineInput) inlineInput.focus();
            return true;
        }
        return false;
    }

    // Event Listeners
    searchToggle.addEventListener('click', toggle);

    if (searchClose) {
        searchClose.addEventListener('click', () => {
            close();
            searchToggle.focus();
        });
    }

    // Close when clicking outside
    document.addEventListener('click', (e) => {
        if (isOpen &&
            !searchDropdown.contains(e.target) &&
            !searchToggle.contains(e.target)) {
            close();
        }
    });

    // Public API
    return {
        open,
        close,
        toggle,
        focusInlineSearch,
        isOpen: () => isOpen
    };
}

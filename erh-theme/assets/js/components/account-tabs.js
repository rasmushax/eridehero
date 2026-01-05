/**
 * Account Tabs Component
 * Hash-based tab navigation for account pages
 */

export function initAccountTabs() {
    const navLinks = document.querySelectorAll('[data-account-nav]');
    const tabs = document.querySelectorAll('[data-account-tab]');

    if (!navLinks.length || !tabs.length) return;

    /**
     * Activate a tab by name
     */
    function activateTab(tabName) {
        // Update nav links
        navLinks.forEach(link => {
            const isActive = link.dataset.accountNav === tabName;
            link.classList.toggle('is-active', isActive);
        });

        // Update tabs
        tabs.forEach(tab => {
            const isActive = tab.dataset.accountTab === tabName;
            tab.hidden = !isActive;
        });
    }

    /**
     * Get tab name from URL hash
     */
    function getTabFromHash() {
        const hash = window.location.hash.slice(1); // Remove #
        // Validate hash matches a tab
        const validTabs = [...tabs].map(tab => tab.dataset.accountTab);
        return validTabs.includes(hash) ? hash : 'trackers'; // Default to trackers
    }

    // Handle nav link clicks
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const tabName = link.dataset.accountNav;

            // Update URL hash
            history.pushState(null, '', `#${tabName}`);

            // Activate tab
            activateTab(tabName);
        });
    });

    // Handle browser back/forward
    window.addEventListener('hashchange', () => {
        activateTab(getTabFromHash());
    });

    // Set initial tab from hash
    activateTab(getTabFromHash());
}

// Auto-initialize if on account page
if (document.querySelector('.account-page')) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAccountTabs);
    } else {
        initAccountTabs();
    }
}

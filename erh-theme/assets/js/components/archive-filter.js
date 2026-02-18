/**
 * Archive Filter Component
 *
 * Handles on-page filtering for archive/listing pages.
 * Uses ?category= query params (SSR-compatible) instead of #hash.
 * Integrates with archive-pagination.js for filter-aware pagination.
 */

import { initPagination } from './archive-pagination.js';

export function initArchiveFilter() {
    const filtersContainer = document.querySelector('[data-archive-filters]');
    const grid = document.querySelector('[data-archive-grid]');
    const emptyState = document.querySelector('[data-archive-empty]');

    if (!filtersContainer || !grid) return;

    // Backward compat: redirect #hash to ?category= (one-time on load).
    const hash = window.location.hash.slice(1);
    if (hash) {
        const url = new URL(window.location);
        url.hash = '';
        url.searchParams.set('category', hash);
        window.location.replace(url);
        return; // Stop — page will reload with the query param.
    }

    const filterLinks = filtersContainer.querySelectorAll('[data-filter]');
    const cards = grid.querySelectorAll('[data-category]');

    // Initialize pagination (after SSR has already set hidden attrs).
    // managed=true: this module handles popstate, not pagination.
    const pagination = initPagination(grid, { managed: true });

    /**
     * Get current filter from URL.
     */
    function getFilterFromUrl() {
        return new URLSearchParams(window.location.search).get('category') || 'all';
    }

    /**
     * Apply filter by category slug.
     */
    function applyFilter(filter, { animate = true, isPopstate = false } = {}) {
        // Find matching link.
        const targetLink = [...filterLinks].find(a => a.dataset.filter === filter);

        // Fall back to 'all' if filter not found.
        const activeFilter = targetLink ? filter : 'all';
        const activeLink = targetLink || filtersContainer.querySelector('[data-filter="all"]');

        // Update active state.
        filterLinks.forEach(a => a.classList.remove('is-active'));
        if (activeLink) activeLink.classList.add('is-active');

        // Filter cards.
        filterCards(activeFilter, cards, emptyState, animate);

        // Popstate: sync pagination to URL's ?page= value.
        // User click: reset pagination to page 1.
        if (isPopstate) {
            pagination.sync();
        } else {
            pagination.update();
        }
    }

    // Handle filter link clicks — prevent default, use pushState.
    filterLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const filter = link.dataset.filter;
            const url = new URL(window.location);

            if (filter === 'all') {
                url.searchParams.delete('category');
            } else {
                url.searchParams.set('category', filter);
            }
            url.searchParams.delete('page');
            history.pushState(null, '', url);

            applyFilter(filter);
        });
    });

    // Handle browser back/forward — re-apply filter and sync pagination from URL.
    window.addEventListener('popstate', () => {
        applyFilter(getFilterFromUrl(), { animate: false, isPopstate: true });
    });

    // On load: SSR already applied hidden attrs, so no filter animation needed.
    // Pagination just needs to kick in for the already-correct state.
}

/**
 * Filter cards by category.
 */
function filterCards(filter, cards, emptyState, animate = true) {
    let visibleIndex = 0;

    cards.forEach((card) => {
        const categories = card.dataset.category ? card.dataset.category.split(' ') : [];
        const shouldShow = filter === 'all' || categories.includes(filter);

        if (shouldShow) {
            card.hidden = false;

            if (animate) {
                const delay = visibleIndex * 25;
                card.style.opacity = '0';
                card.style.transform = 'translateY(8px)';

                requestAnimationFrame(() => {
                    setTimeout(() => {
                        card.style.transition = 'opacity 0.15s ease, transform 0.15s ease';
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, delay);
                });
            } else {
                // No animation — clear inline styles.
                card.style.opacity = '';
                card.style.transform = '';
                card.style.transition = '';
            }

            visibleIndex++;
        } else {
            card.hidden = true;
            card.style.opacity = '';
            card.style.transform = '';
            card.style.transition = '';
            card.style.display = '';
            card.removeAttribute('data-page-hidden');
        }
    });

    // Show/hide empty state.
    if (emptyState) {
        emptyState.hidden = visibleIndex > 0;
    }
}

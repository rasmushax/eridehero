/**
 * Archive Filter Component
 * Handles on-page filtering for archive/listing pages
 * Supports hash-based URLs for shareable filtered views (e.g., /articles/#electric-scooters)
 */

export function initArchiveFilter() {
    const filtersContainer = document.querySelector('[data-archive-filters]');
    const grid = document.querySelector('[data-archive-grid]');
    const emptyState = document.querySelector('[data-archive-empty]');

    if (!filtersContainer || !grid) return;

    const filterButtons = filtersContainer.querySelectorAll('[data-filter]');
    const cards = grid.querySelectorAll('[data-category]');

    /**
     * Apply filter by category slug
     */
    function applyFilter(filter) {
        // Find matching button
        const targetButton = [...filterButtons].find(btn => btn.dataset.filter === filter);

        // Fall back to 'all' if filter not found
        const activeFilter = targetButton ? filter : 'all';
        const activeButton = targetButton || filtersContainer.querySelector('[data-filter="all"]');

        // Update active state
        filterButtons.forEach(btn => btn.classList.remove('is-active'));
        if (activeButton) activeButton.classList.add('is-active');

        // Filter cards
        filterCards(activeFilter, cards, emptyState);
    }

    // Handle filter click
    filterButtons.forEach(button => {
        button.addEventListener('click', () => {
            const filter = button.dataset.filter;

            // Update hash (triggers hashchange, but we apply immediately for responsiveness)
            if (filter === 'all') {
                history.pushState(null, '', window.location.pathname);
            } else {
                history.pushState(null, '', `#${filter}`);
            }

            applyFilter(filter);
        });
    });

    // Handle browser back/forward
    window.addEventListener('hashchange', () => {
        const hash = window.location.hash.slice(1);
        applyFilter(hash || 'all');
    });

    // Apply filter from hash on page load
    const initialHash = window.location.hash.slice(1);
    if (initialHash) {
        applyFilter(initialHash);
    }
}

/**
 * Filter cards by category
 */
function filterCards(filter, cards, emptyState) {
    let visibleIndex = 0;

    cards.forEach((card) => {
        // Support space-separated categories (card can belong to multiple)
        const categories = card.dataset.category ? card.dataset.category.split(' ') : [];
        const shouldShow = filter === 'all' || categories.includes(filter);

        if (shouldShow) {
            // Show card with staggered animation based on visible order
            const delay = visibleIndex * 25;
            card.hidden = false;
            card.style.opacity = '0';
            card.style.transform = 'translateY(8px)';

            requestAnimationFrame(() => {
                setTimeout(() => {
                    card.style.transition = 'opacity 0.15s ease, transform 0.15s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, delay);
            });

            visibleIndex++;
        } else {
            // Hide card immediately
            card.hidden = true;
            card.style.opacity = '';
            card.style.transform = '';
            card.style.transition = '';
        }
    });

    // Show/hide empty state
    if (emptyState) {
        emptyState.hidden = visibleIndex > 0;
    }
}

// Auto-initialize
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initArchiveFilter);
} else {
    initArchiveFilter();
}

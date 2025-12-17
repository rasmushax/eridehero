/**
 * Archive Filter Component
 * Handles on-page filtering for archive/listing pages
 */

export function initArchiveFilter() {
    const filtersContainer = document.querySelector('[data-archive-filters]');
    const grid = document.querySelector('[data-archive-grid]');
    const emptyState = document.querySelector('[data-archive-empty]');

    if (!filtersContainer || !grid) return;

    const filterButtons = filtersContainer.querySelectorAll('[data-filter]');
    const cards = grid.querySelectorAll('[data-category]');

    // Handle filter click
    filterButtons.forEach(button => {
        button.addEventListener('click', () => {
            const filter = button.dataset.filter;

            // Update active state
            filterButtons.forEach(btn => btn.classList.remove('is-active'));
            button.classList.add('is-active');

            // Filter cards with animation
            filterCards(filter, cards, emptyState);
        });
    });
}

/**
 * Filter cards by category
 */
function filterCards(filter, cards, emptyState) {
    let visibleIndex = 0;

    cards.forEach((card) => {
        const category = card.dataset.category;
        const shouldShow = filter === 'all' || category === filter;

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

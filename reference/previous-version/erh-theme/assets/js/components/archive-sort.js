/**
 * Archive Sort Component
 * Handles sorting for archive/listing pages
 *
 * Usage:
 * - Add data-archive-sort to a <select> element
 * - Add data-archive-grid to the container of sortable items
 * - Add data-rating and/or data-date to each sortable item
 *
 * Supported sort values:
 * - rating: Sort by data-rating (highest first)
 * - newest: Sort by data-date (newest first)
 * - oldest: Sort by data-date (oldest first)
 */

export function initArchiveSort() {
    const sortSelects = document.querySelectorAll('[data-archive-sort]');

    sortSelects.forEach(select => {
        // Find the grid - look in main content area (select may be in different section than grid)
        const main = document.querySelector('main') || document;
        const grid = main.querySelector('[data-archive-grid]');

        if (!grid) return;

        // Listen for changes (works with both native and custom select)
        select.addEventListener('change', () => {
            const sortBy = select.value;
            sortCards(grid, sortBy);
        });
    });
}

/**
 * Sort cards within a grid
 */
function sortCards(grid, sortBy) {
    const cards = Array.from(grid.querySelectorAll('.archive-card'));

    if (cards.length === 0) return;

    // Sort based on criteria
    cards.sort((a, b) => {
        switch (sortBy) {
            case 'rating':
                const ratingA = parseFloat(a.dataset.rating) || 0;
                const ratingB = parseFloat(b.dataset.rating) || 0;
                return ratingB - ratingA; // Highest first

            case 'newest':
                const dateA = new Date(a.dataset.date) || 0;
                const dateB = new Date(b.dataset.date) || 0;
                return dateB - dateA; // Newest first

            case 'oldest':
                const oldDateA = new Date(a.dataset.date) || 0;
                const oldDateB = new Date(b.dataset.date) || 0;
                return oldDateA - oldDateB; // Oldest first

            default:
                return 0;
        }
    });

    // Animate out
    cards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(8px)';
    });

    // Reorder after brief delay
    setTimeout(() => {
        // Reorder DOM
        cards.forEach(card => {
            grid.appendChild(card);
        });

        // Animate in with stagger
        cards.forEach((card, index) => {
            const delay = index * 25;

            setTimeout(() => {
                card.style.transition = 'opacity 0.15s ease, transform 0.15s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, delay);
        });

        // Clean up inline styles after animation
        setTimeout(() => {
            cards.forEach(card => {
                card.style.opacity = '';
                card.style.transform = '';
                card.style.transition = '';
            });
        }, cards.length * 25 + 200);
    }, 100);
}

// Auto-initialize
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initArchiveSort);
} else {
    initArchiveSort();
}

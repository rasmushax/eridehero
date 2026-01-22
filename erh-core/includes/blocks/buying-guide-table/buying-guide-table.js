/**
 * Buying Guide Table Block - Scroll Shadow & Drag-to-Scroll
 *
 * Features:
 * - Scroll shadow effect on sticky column when scrolled
 * - Drag-to-scroll on desktop when content overflows
 *
 * Price hydration is handled by erh-theme/assets/js/components/buying-guide-table.js
 */
(function() {
    'use strict';

    /**
     * Initialize a single table's scroll behavior.
     * @param {HTMLElement} table - The buying guide table element.
     */
    function initTable(table) {
        const scrollContainer = table.querySelector('.bgt-scroll-container');
        if (!scrollContainer) return;

        // --- Scroll Shadow ---
        const updateShadow = () => {
            const isScrolled = scrollContainer.scrollLeft > 0;
            scrollContainer.classList.toggle('is-scrolled', isScrolled);
        };

        scrollContainer.addEventListener('scroll', updateShadow, { passive: true });
        updateShadow();

        // --- Drag-to-Scroll (Desktop) ---
        let isDragging = false;
        let lastX = 0;

        /**
         * Check if container has horizontal overflow.
         * @returns {boolean}
         */
        const hasOverflow = () => scrollContainer.scrollWidth > scrollContainer.clientWidth;

        /**
         * Update drag cursor state based on overflow.
         */
        const updateDragState = () => {
            if (hasOverflow()) {
                scrollContainer.classList.add('is-draggable');
            } else {
                scrollContainer.classList.remove('is-draggable');
            }
        };

        // Initial check
        updateDragState();

        // Re-check on resize (debounced)
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(updateDragState, 100);
        });

        // Mouse events for drag-to-scroll
        scrollContainer.addEventListener('mousedown', (e) => {
            // Only enable drag if there's overflow and not clicking a link/button
            if (!hasOverflow()) return;
            if (e.target.closest('a, button')) return;

            isDragging = true;
            lastX = e.pageX;
            scrollContainer.classList.add('is-dragging');
        });

        scrollContainer.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            e.preventDefault();

            // Use delta from last position (not absolute from start)
            // This allows immediate direction change at scroll boundaries
            const deltaX = e.pageX - lastX;
            scrollContainer.scrollLeft -= deltaX * 1.5; // 1.5x speed multiplier
            lastX = e.pageX;
        });

        const stopDragging = () => {
            if (!isDragging) return;
            isDragging = false;
            scrollContainer.classList.remove('is-dragging');
        };

        scrollContainer.addEventListener('mouseup', stopDragging);
        scrollContainer.addEventListener('mouseleave', stopDragging);
    }

    /**
     * Initialize all tables on the page.
     */
    function initAllTables() {
        const tables = document.querySelectorAll('[data-buying-guide-table]');
        tables.forEach(initTable);
    }

    // Initialize on DOM ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllTables);
    } else {
        initAllTables();
    }
})();

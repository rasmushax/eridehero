/**
 * Carousel Utility
 *
 * Shared horizontal scroll carousel with arrow navigation and fade masks.
 * Used by: deals section, similar products, and other carousels.
 *
 * @module utils/carousel
 */

/**
 * Initialize a carousel with arrow navigation and fade masks.
 *
 * @param {HTMLElement} container - Carousel wrapper element (receives fade mask classes)
 * @param {Object} options - Configuration options
 * @param {HTMLElement} options.grid - Scrollable grid element
 * @param {HTMLElement} options.leftArrow - Left arrow button
 * @param {HTMLElement} options.rightArrow - Right arrow button
 * @param {number} [options.scrollAmount=420] - Pixels to scroll per click (default: ~2 cards)
 * @param {number} [options.threshold=5] - Edge detection threshold in pixels
 * @returns {Object} Carousel instance with destroy() and update() methods
 */
export function initCarousel(container, options) {
    const {
        grid,
        leftArrow,
        rightArrow,
        scrollAmount = 420,
        threshold = 5,
    } = options;

    if (!container || !grid || !leftArrow || !rightArrow) {
        return null;
    }

    let resizeTimer = null;

    /**
     * Update arrow disabled states and fade mask classes based on scroll position.
     */
    function updateScrollState() {
        const scrollLeft = grid.scrollLeft;
        const maxScroll = grid.scrollWidth - grid.clientWidth;

        const canScrollLeft = scrollLeft > threshold;
        const canScrollRight = scrollLeft < maxScroll - threshold;

        // Update arrow disabled states
        leftArrow.disabled = !canScrollLeft;
        rightArrow.disabled = !canScrollRight;

        // Update fade mask classes on container
        container.classList.toggle('can-scroll-left', canScrollLeft);
        container.classList.toggle('can-scroll-right', canScrollRight);
    }

    /**
     * Scroll the grid in a direction.
     * @param {number} direction - -1 for left, 1 for right
     */
    function scroll(direction) {
        grid.scrollTo({
            left: grid.scrollLeft + (direction * scrollAmount),
            behavior: 'smooth',
        });
    }

    /**
     * Handle left arrow click.
     */
    function handleLeftClick() {
        scroll(-1);
    }

    /**
     * Handle right arrow click.
     */
    function handleRightClick() {
        scroll(1);
    }

    /**
     * Handle resize with debounce.
     */
    function handleResize() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(updateScrollState, 100);
    }

    // Bind event listeners
    leftArrow.addEventListener('click', handleLeftClick);
    rightArrow.addEventListener('click', handleRightClick);
    grid.addEventListener('scroll', updateScrollState, { passive: true });
    window.addEventListener('resize', handleResize);

    // Initialize state
    updateScrollState();

    // Return public API
    return {
        /**
         * Manually trigger scroll state update (e.g., after content changes).
         */
        update: updateScrollState,

        /**
         * Clean up event listeners.
         */
        destroy() {
            leftArrow.removeEventListener('click', handleLeftClick);
            rightArrow.removeEventListener('click', handleRightClick);
            grid.removeEventListener('scroll', updateScrollState);
            window.removeEventListener('resize', handleResize);
            clearTimeout(resizeTimer);
        },
    };
}

export default { initCarousel };

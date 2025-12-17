/**
 * Sticky Buy Bar Module
 *
 * Shows a persistent buy CTA at the bottom of the viewport
 * after the user scrolls past the main pricing section.
 *
 * @module components/sticky-buy-bar
 */

const SELECTORS = {
    bar: '#sticky-buy-bar',
    priceSection: '#prices'
};

const CLASSES = {
    visible: 'is-visible'
};

let stickyBar = null;
let priceSection = null;
let ticking = false;

/**
 * Update sticky bar visibility based on scroll position
 * Shows when scrolled 50px past the bottom of the price section
 */
function updateVisibility() {
    if (!stickyBar || !priceSection) return;

    const priceSectionRect = priceSection.getBoundingClientRect();
    const offset = 50; // Show bar after scrolling this many px past the price section
    const shouldShow = priceSectionRect.bottom < -offset;

    if (shouldShow) {
        stickyBar.classList.add(CLASSES.visible);
        stickyBar.setAttribute('aria-hidden', 'false');
    } else {
        stickyBar.classList.remove(CLASSES.visible);
        stickyBar.setAttribute('aria-hidden', 'true');
    }
}

/**
 * Handle scroll events with requestAnimationFrame throttling
 */
function onScroll() {
    if (!ticking) {
        window.requestAnimationFrame(() => {
            updateVisibility();
            ticking = false;
        });
        ticking = true;
    }
}

/**
 * Initialize the sticky buy bar
 *
 * @param {Object} [options] - Configuration options
 * @param {string} [options.barSelector] - Selector for the sticky bar
 * @param {string} [options.priceSectionSelector] - Selector for the price section
 */
export function init(options = {}) {
    const selectors = {
        bar: options.barSelector || SELECTORS.bar,
        priceSection: options.priceSectionSelector || SELECTORS.priceSection
    };

    stickyBar = document.querySelector(selectors.bar);
    priceSection = document.querySelector(selectors.priceSection);

    // Exit if required elements not found
    if (!stickyBar || !priceSection) {
        return;
    }

    // Set up scroll listener
    window.addEventListener('scroll', onScroll, { passive: true });

    // Initial check
    updateVisibility();
}

/**
 * Destroy the sticky bar instance and clean up event listeners
 */
export function destroy() {
    window.removeEventListener('scroll', onScroll);
    stickyBar = null;
    priceSection = null;
}

/**
 * Manually show the sticky bar
 */
export function show() {
    if (stickyBar) {
        stickyBar.classList.add(CLASSES.visible);
        stickyBar.setAttribute('aria-hidden', 'false');
    }
}

/**
 * Manually hide the sticky bar
 */
export function hide() {
    if (stickyBar) {
        stickyBar.classList.remove(CLASSES.visible);
        stickyBar.setAttribute('aria-hidden', 'true');
    }
}

export default {
    init,
    destroy,
    show,
    hide
};

/**
 * Compare Results - Constants
 *
 * DOM selectors and layout configuration constants.
 *
 * @module components/compare/constants
 */

// =============================================================================
// DOM Selectors
// =============================================================================

export const SELECTORS = {
    page: '[data-compare-page]',
    header: '[data-compare-header]',
    products: '[data-compare-products]',
    nav: '[data-compare-nav]',
    navLinks: '[data-nav-links]',
    navLink: '[data-nav-link]',
    section: '[data-section]',
    overview: '[data-compare-overview]',
    specs: '[data-compare-specs]',
    addModal: '#compare-add-modal',
    searchInput: '[data-compare-search]',
    searchResults: '[data-compare-results]',
    addBtn: '[data-compare-add]',
    inputs: '[data-compare-inputs]',
};

// =============================================================================
// Layout Constants
// =============================================================================

export const HEADER_HEIGHT = 72;
export const NAV_HEIGHT = 48;
export const SCROLL_OFFSET = HEADER_HEIGHT + NAV_HEIGHT + 24;

// Dynamic full-width calculation
export const CONTAINER_MAX_WIDTH = 1200;
export const CONTAINER_PADDING = 48; // 24px each side
export const LABEL_COL_WIDTH = 200;
export const PRODUCT_COL_MIN_WIDTH = 200;

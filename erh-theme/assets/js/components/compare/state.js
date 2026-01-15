/**
 * Compare Results - State Management
 *
 * Centralized state for the compare module.
 * Uses a simple object-based store pattern.
 *
 * @module components/compare/state
 */

// =============================================================================
// State Store
// =============================================================================

const state = {
    products: [],
    allProducts: [],
    category: 'escooter',
    userGeo: { geo: 'US', currency: 'USD' },
    radarChart: null,
    isFullWidthMode: false,
    scrollSpyHandler: null,
    isNavScrolling: false, // Prevents scroll spy during programmatic navigation
};

// =============================================================================
// Getters
// =============================================================================

export function getProducts() {
    return state.products;
}

export function getAllProducts() {
    return state.allProducts;
}

export function getCategory() {
    return state.category;
}

export function getUserGeo() {
    return state.userGeo;
}

export function getRadarChart() {
    return state.radarChart;
}

export function isFullWidthMode() {
    return state.isFullWidthMode;
}

export function getScrollSpyHandler() {
    return state.scrollSpyHandler;
}

export function isNavScrolling() {
    return state.isNavScrolling;
}

// =============================================================================
// Setters
// =============================================================================

export function setProducts(products) {
    state.products = products;
}

export function setAllProducts(allProducts) {
    state.allProducts = allProducts;
}

export function setCategory(category) {
    state.category = category;
}

export function setUserGeo(userGeo) {
    state.userGeo = userGeo;
}

export function setRadarChart(radarChart) {
    state.radarChart = radarChart;
}

export function setFullWidthMode(isFullWidth) {
    state.isFullWidthMode = isFullWidth;
}

export function setScrollSpyHandler(handler) {
    state.scrollSpyHandler = handler;
}

export function setNavScrolling(isScrolling) {
    state.isNavScrolling = isScrolling;
}

// =============================================================================
// Helpers
// =============================================================================

/**
 * Add a product to the products array.
 * @param {Object} product - Product data.
 */
export function addProduct(product) {
    state.products.push(product);
}

/**
 * Remove a product by ID.
 * @param {number} productId - Product ID to remove.
 * @returns {boolean} True if product was removed.
 */
export function removeProductById(productId) {
    const index = state.products.findIndex(p => p.id === productId);
    if (index > -1) {
        state.products.splice(index, 1);
        return true;
    }
    return false;
}

/**
 * Reset state to defaults.
 */
export function resetState() {
    state.products = [];
    state.allProducts = [];
    state.category = 'escooter';
    state.userGeo = { geo: 'US', currency: 'USD' };
    state.radarChart = null;
    state.isFullWidthMode = false;
    state.scrollSpyHandler = null;
    state.isNavScrolling = false;
}

/**
 * Get the full state object (for debugging).
 * @returns {Object} Current state.
 */
export function getState() {
    return { ...state };
}

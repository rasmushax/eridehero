/**
 * Similar Products Component
 *
 * Loads similar products from REST API with geo-aware pricing.
 * Features:
 * - Geo-aware pricing with fallback to US
 * - Carousel navigation
 * - Price alert integration
 * - Skeleton loading states
 *
 * @module components/similar-products
 */

import { getUserGeo } from '../services/geo-price.js';
import { createProductCard } from '../utils/product-card.js';
import { initCarousel } from '../utils/carousel.js';

/**
 * Get REST URL base from WordPress localized data.
 *
 * @returns {string} REST API base URL
 */
function getRestUrl() {
    if (typeof erhData !== 'undefined' && erhData.restUrl) {
        return erhData.restUrl;
    }
    return '/wp-json/erh/v1/';
}

/**
 * Initialize similar products section.
 *
 * @param {HTMLElement} [container] - Optional specific container to initialize
 * @returns {Promise<Object|null>} Component instance or null
 */
export async function initSimilarProducts(container = null) {
    const section = container || document.querySelector('[data-similar-products]');
    if (!section) {
        return null;
    }

    const productId = section.dataset.productId;
    const limit = parseInt(section.dataset.limit, 10) || 10;
    const grid = section.querySelector('.similar-grid, [data-similar-grid]');
    const template = document.getElementById('similar-card-template');
    const emptyState = section.querySelector('.similar-empty, [data-similar-empty]');
    const carousel = section.querySelector('.similar-carousel');
    const leftArrow = section.querySelector('.carousel-arrow-left');
    const rightArrow = section.querySelector('.carousel-arrow-right');

    if (!grid || !template || !productId) {
        console.warn('[SimilarProducts] Missing required elements', { grid, template, productId });
        return null;
    }

    // State.
    let products = [];
    let userGeo = { geo: 'US', currency: 'USD' };
    let carouselInstance = null;

    // Get user geo.
    try {
        userGeo = await getUserGeo();
    } catch (e) {
        // Use defaults.
    }

    // Load similar products.
    const apiUrl = `${getRestUrl()}products/${productId}/similar?limit=${limit}&geo=${userGeo.geo}`;

    try {
        const response = await fetch(apiUrl);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        products = data.products || [];
    } catch (error) {
        console.error('[SimilarProducts] Failed to load:', error);
        showEmptyState();
        return null;
    }

    // Render products.
    renderProducts(products);

    // Initialize carousel after render.
    if (leftArrow && rightArrow && carousel) {
        // Card width + gap for scroll amount calculation.
        const cardWidth = 200; // similar card width
        const gap = 16; // var(--space-4)
        const scrollAmount = (cardWidth + gap) * 2;

        carouselInstance = initCarousel(carousel, {
            grid,
            leftArrow,
            rightArrow,
            scrollAmount,
            threshold: 5,
        });
    }


    /**
     * Render product cards to the grid.
     *
     * @param {Array} items - Products to render
     */
    function renderProducts(items) {
        // Clear skeletons and existing cards.
        grid.innerHTML = '';

        if (items.length === 0) {
            showEmptyState();
            return;
        }

        hideEmptyState();

        // Render cards.
        items.forEach((product) => {
            const card = createProductCard(product, { template });
            if (card) {
                grid.appendChild(card);
            }
        });

        // Update carousel scroll state.
        if (carouselInstance) {
            carouselInstance.update();
        }
    }

    /**
     * Show empty state.
     */
    function showEmptyState() {
        grid.innerHTML = '';
        if (emptyState) {
            emptyState.style.display = '';
        }
        // Hide section entirely if no products.
        section.style.display = 'none';
    }

    /**
     * Hide empty state.
     */
    function hideEmptyState() {
        if (emptyState) {
            emptyState.style.display = 'none';
        }
        section.style.display = '';
    }

    // Return public API.
    return {
        /**
         * Refresh products with new geo.
         *
         * @param {string} [newGeo] - New geo code
         */
        async refresh(newGeo) {
            const geo = newGeo || userGeo.geo;
            try {
                const response = await fetch(`${getRestUrl()}products/${productId}/similar?limit=${limit}&geo=${geo}`);
                const data = await response.json();
                products = data.products || [];
                renderProducts(products);
            } catch (e) {
                console.error('[SimilarProducts] Refresh failed:', e);
            }
        },

        /**
         * Get current products.
         *
         * @returns {Array} Current products
         */
        getProducts() {
            return products;
        },

        /**
         * Destroy component.
         */
        destroy() {
            if (carouselInstance) {
                carouselInstance.destroy();
            }
        },
    };
}

export default { initSimilarProducts };

/**
 * Deals Hub Component
 *
 * Main deals hub page with multiple category carousels.
 * Features:
 * - Top deals carousel (best across all categories)
 * - Per-category carousels with counts
 * - Geo-aware pricing
 */

import { getUserGeo } from '../services/geo-price.js';
import { initCarousel } from '../utils/carousel.js';
import { fetchDeals, createDealCard, handleTrackClick, groupByCategory } from '../utils/deals-utils.js';

// Configuration
const CONFIG = {
    dealsLimit: 50,
    topDealsLimit: 8,
    categoryDisplayLimit: 15,
    discountThreshold: -5,
    cardWidth: 160,
    cardGap: 12,
    categories: ['escooter', 'ebike', 'eskate', 'euc', 'hoverboard'],
};

/**
 * Initialize the deals hub page
 */
export async function initDealsHub() {
    const hub = document.querySelector('[data-deals-hub]');
    if (!hub) return null;

    // Element references
    const topDealsSection = hub.querySelector('[data-top-deals]');
    const topDealsGrid = hub.querySelector('[data-top-deals-grid]');
    const dealTemplate = document.getElementById('hub-deal-card-template');

    if (!dealTemplate) return null;

    // State
    let userGeo = { geo: 'US', currency: 'USD' };
    const carouselInstances = {};

    // Get user geo
    try {
        userGeo = await getUserGeo();
    } catch (e) {
        // Use defaults
    }

    // Load and render deals
    await loadDeals();

    // Price tracker button handler (event delegation)
    hub.addEventListener('click', (e) => handleTrackClick(e, userGeo));

    /**
     * Load deals from API
     */
    async function loadDeals() {
        try {
            const data = await fetchDeals({
                category: 'all',
                limit: CONFIG.dealsLimit,
                threshold: CONFIG.discountThreshold,
                geo: userGeo.geo,
            });

            const { deals, counts } = data;

            // Render top deals (best across all categories)
            renderTopDeals(deals.slice(0, CONFIG.topDealsLimit));

            // Update category counts in headings
            updateCategoryCounts(counts);

            // Group deals by category and render each section
            const grouped = groupByCategory(deals);

            CONFIG.categories.forEach(category => {
                renderCategorySection(category, grouped[category] || []);
            });

        } catch (error) {
            console.error('[DealsHub] Failed to load:', error);
            showEmptyState();
        }
    }

    /**
     * Update category counts in section headings
     */
    function updateCategoryCounts(counts) {
        CONFIG.categories.forEach(category => {
            const countEl = hub.querySelector(`[data-category-count="${category}"]`);
            if (countEl) {
                if (counts[category]) {
                    countEl.textContent = counts[category];
                    countEl.hidden = false;
                }
                // Keep hidden if no count
            }
        });
    }

    /**
     * Render top deals carousel
     */
    function renderTopDeals(deals) {
        if (!topDealsSection || !topDealsGrid) return;

        topDealsSection.classList.remove('is-loading');

        if (!deals.length) {
            topDealsSection.hidden = true;
            return;
        }

        topDealsGrid.innerHTML = '';

        deals.forEach(deal => {
            topDealsGrid.appendChild(createDealCard(dealTemplate, deal, userGeo));
        });

        initSectionCarousel('top', topDealsSection, topDealsGrid);
    }

    /**
     * Render a category section
     */
    function renderCategorySection(category, deals) {
        const section = hub.querySelector(`[data-category-section="${category}"]`);
        const grid = hub.querySelector(`[data-category-grid="${category}"]`);

        if (!section || !grid) return;

        section.classList.remove('is-loading');

        if (!deals.length) {
            section.hidden = true;
            return;
        }

        grid.innerHTML = '';

        const displayDeals = deals.slice(0, CONFIG.categoryDisplayLimit);
        displayDeals.forEach(deal => {
            grid.appendChild(createDealCard(dealTemplate, deal, userGeo));
        });

        initSectionCarousel(category, section, grid);
    }

    /**
     * Initialize carousel for a section
     */
    function initSectionCarousel(key, section, grid) {
        if (carouselInstances[key]) {
            carouselInstances[key].destroy();
        }

        const carousel = section.querySelector('.deals-carousel');
        const leftArrow = carousel?.querySelector('.carousel-arrow-left');
        const rightArrow = carousel?.querySelector('.carousel-arrow-right');

        if (!carousel || !leftArrow || !rightArrow) return;

        carouselInstances[key] = initCarousel(carousel, {
            grid,
            leftArrow,
            rightArrow,
            scrollAmount: (CONFIG.cardWidth + CONFIG.cardGap) * 2,
            threshold: 1,
        });
    }

    /**
     * Show empty state (hide all sections)
     */
    function showEmptyState() {
        if (topDealsSection) {
            topDealsSection.classList.remove('is-loading');
            topDealsSection.hidden = true;
        }

        CONFIG.categories.forEach(cat => {
            const section = hub.querySelector(`[data-category-section="${cat}"]`);
            if (section) {
                section.classList.remove('is-loading');
                section.hidden = true;
            }
        });
    }

    return {
        refresh: loadDeals,
    };
}

export default { initDealsHub };

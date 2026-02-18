/**
 * Deals Section Component
 *
 * Homepage deals carousel with category tab filtering.
 * Features:
 * - Category tab filtering
 * - Carousel navigation (desktop)
 * - Geo-aware pricing
 * - Skeleton loading states
 */

import { getUserGeo } from '../services/geo-price.js';
import { initCarousel } from '../utils/carousel.js';
import { fetchDeals, createDealCard, handleTrackClick } from '../utils/deals-utils.js';

// Configuration
const CONFIG = {
    dealsLimit: 12,
    discountThreshold: -5,
    cardWidth: 160,
    cardGap: 12,
};

/**
 * Initialize the deals section
 */
export async function initDeals() {
    const section = document.getElementById('deals-section');
    if (!section) return null;

    const grid = section.querySelector('.deals-grid');
    const tabs = section.querySelectorAll('.filter-pill');
    const emptyState = section.querySelector('.deals-empty');
    const template = document.getElementById('deal-card-template');
    const ctaTemplate = document.getElementById('deal-cta-template');
    const leftArrow = section.querySelector('.carousel-arrow-left');
    const rightArrow = section.querySelector('.carousel-arrow-right');
    const carousel = section.querySelector('.deals-carousel');
    const dealsCountEl = document.getElementById('deals-count');

    if (!grid || !template) return null;

    // Category slug → deals page URL path mapping.
    const CATEGORY_URLS = {
        escooter:   '/deals/electric-scooters/',
        ebike:      '/deals/electric-bikes/',
        eskate:     '/deals/electric-skateboards/',
        euc:        '/deals/electric-unicycles/',
        hoverboard: '/deals/hoverboards/',
    };

    // State
    let allDeals = [];
    let currentCategory = 'all';
    let userGeo = { geo: 'US', currency: 'USD' };
    let totalDealsCount = 0;
    let dealCounts = {};
    let carouselInstance = null;

    // Get user geo
    try {
        userGeo = await getUserGeo();
    } catch (e) {
        // Use defaults
    }

    // Determine category: use fixed category from hub pages, or 'all' for homepage.
    const fixedCategory = section.dataset.fixedCategory || '';
    currentCategory = fixedCategory || 'all';

    // Load deals
    try {
        const data = await fetchDeals({
            category: currentCategory,
            limit: CONFIG.dealsLimit,
            threshold: CONFIG.discountThreshold,
            geo: userGeo.geo,
        });

        allDeals = data.deals;
        dealCounts = data.counts || {};

        // Use category-specific count when filtering, otherwise total.
        totalDealsCount = (fixedCategory && dealCounts[fixedCategory] !== undefined)
            ? dealCounts[fixedCategory]
            : (dealCounts.all || 0);

        // Update total count display
        updateCountDisplay(totalDealsCount);
    } catch (error) {
        console.error('[Deals] Failed to load deals:', error);
        showEmptyState(true);
        return null;
    }

    // Render deals
    renderDeals(allDeals);

    // Tab click handlers
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const filter = tab.dataset.filter || 'all';
            setActiveTab(tab);
            filterDeals(filter);
        });
    });

    // Price tracker button handler (event delegation)
    grid.addEventListener('click', (e) => handleTrackClick(e, userGeo));

    // Carousel navigation
    if (leftArrow && rightArrow && carousel) {
        const scrollAmount = (CONFIG.cardWidth + CONFIG.cardGap) * 2;

        carouselInstance = initCarousel(carousel, {
            grid,
            leftArrow,
            rightArrow,
            scrollAmount,
            threshold: 1,
        });
    }

    /**
     * Render deal cards to the grid
     */
    function renderDeals(deals) {
        grid.innerHTML = '';

        if (deals.length === 0) {
            showEmptyState(true);
            return;
        }

        hideEmptyState();

        // Render deal cards
        deals.forEach(deal => {
            grid.appendChild(createDealCard(template, deal, userGeo));
        });

        // Add CTA card if there are more deals beyond what's displayed.
        if (ctaTemplate && totalDealsCount > deals.length) {
            const ctaCard = ctaTemplate.content.cloneNode(true);
            grid.appendChild(ctaCard);
            // Use updateCtaCard to set count and link consistently.
            updateCtaCard(deals.length, totalDealsCount, currentCategory);
        }

        // Update carousel scroll state
        if (carouselInstance) {
            carouselInstance.update();
        }
    }

    /**
     * Filter deals by category
     */
    function filterDeals(category) {
        currentCategory = category;
        const cards = grid.querySelectorAll('.deal-card');

        let visibleCount = 0;
        cards.forEach(card => {
            const cardCategory = card.dataset.category;

            // Skip CTA card — handled separately below.
            if (card.classList.contains('deal-card-cta')) return;

            const show = category === 'all' || cardCategory === category;
            card.classList.toggle('hidden', !show);
            if (show) visibleCount++;
        });

        // Update header stat and CTA card for active category.
        const categoryTotal = category === 'all'
            ? (dealCounts.all || 0)
            : (dealCounts[category] || 0);

        updateCountDisplay(categoryTotal);
        updateCtaCard(visibleCount, categoryTotal, category);

        if (visibleCount === 0) {
            showEmptyState();
        } else {
            hideEmptyState();
        }

        // Reset scroll position and update carousel state
        grid.scrollLeft = 0;
        if (carouselInstance) {
            carouselInstance.update();
        }
    }

    /**
     * Set active tab styling
     */
    function setActiveTab(activeTab) {
        tabs.forEach(tab => {
            const isActive = tab === activeTab;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    }

    /**
     * Update the header deal count display.
     */
    function updateCountDisplay(count) {
        if (!dealsCountEl) return;
        if (count > 0) {
            dealsCountEl.textContent = `🔥 ${count} deals cooking`;
            dealsCountEl.style.display = '';
        } else {
            dealsCountEl.style.display = 'none';
        }
    }

    /**
     * Update the CTA card count, link, and visibility.
     */
    function updateCtaCard(visibleCount, categoryTotal, category) {
        const ctaCard = grid.querySelector('.deal-card-cta');
        if (!ctaCard) return;

        const remaining = categoryTotal - visibleCount;

        if (remaining <= 0) {
            ctaCard.classList.add('hidden');
            return;
        }

        ctaCard.classList.remove('hidden');

        const countEl = ctaCard.querySelector('.deal-card-cta-count');
        if (countEl) {
            countEl.textContent = `+${remaining}`;
        }

        // Point CTA to the category-specific deals page.
        if (category !== 'all' && CATEGORY_URLS[category]) {
            ctaCard.href = (window.erhData?.siteUrl || '') + CATEGORY_URLS[category];
        } else {
            ctaCard.href = (window.erhData?.siteUrl || '') + '/deals/';
        }
    }

    /**
     * Show empty state
     */
    function showEmptyState(clearGrid = false) {
        if (clearGrid) grid.innerHTML = '';
        if (emptyState) emptyState.style.display = '';
    }

    /**
     * Hide empty state
     */
    function hideEmptyState() {
        if (emptyState) emptyState.style.display = 'none';
    }

    return {
        refresh: async () => {
            try {
                const data = await fetchDeals({
                    category: fixedCategory || 'all',
                    limit: CONFIG.dealsLimit,
                    geo: userGeo.geo,
                });
                allDeals = data.deals;
                renderDeals(allDeals);
                filterDeals(currentCategory);
            } catch (e) {
                console.error('[Deals] Refresh failed:', e);
            }
        },
        filterDeals,
    };
}

export default { initDeals };

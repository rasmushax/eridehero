/**
 * Deals Section Component
 *
 * Loads deals from REST API and displays with geo-aware pricing.
 * Features:
 * - Category tab filtering
 * - Carousel navigation (desktop)
 * - Dynamic price loading per geo
 * - Skeleton loading states
 */

import { getUserGeo, getBestPrices, formatPrice } from '../services/geo-price.js';

// Configuration
const CONFIG = {
    apiEndpoint: '/wp-json/erh/v1/deals',
    pricesEndpoint: '/wp-json/erh/v1/prices/best',
    dealsLimit: 12,
    discountThreshold: -5,
};

// Category labels for display
const CATEGORY_LABELS = {
    'escooter': 'E-Scooter',
    'ebike': 'E-Bike',
    'eskate': 'E-Skateboard',
    'euc': 'EUC',
    'hoverboard': 'Hoverboard',
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
    const leftArrow = section.querySelector('.carousel-arrow-left');
    const rightArrow = section.querySelector('.carousel-arrow-right');

    if (!grid || !template) return null;

    // State
    let allDeals = [];
    let currentCategory = 'all';
    let userGeo = { geo: 'US', currency: 'USD' };

    // Get user geo
    try {
        userGeo = await getUserGeo();
    } catch (e) {
        console.warn('[Deals] Failed to detect geo, using defaults');
    }

    // Load deals
    try {
        const response = await fetch(`${CONFIG.apiEndpoint}?category=all&limit=${CONFIG.dealsLimit}&threshold=${CONFIG.discountThreshold}`);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const data = await response.json();
        allDeals = data.deals || [];
    } catch (error) {
        console.error('[Deals] Failed to load deals:', error);
        showEmptyState();
        return null;
    }

    // Load deal counts for tabs
    loadDealCounts();

    // Render deals
    renderDeals(allDeals);

    // Load geo-aware prices
    await loadPrices(allDeals);

    // Tab click handlers
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const filter = tab.dataset.filter || 'all';
            setActiveTab(tab);
            filterDeals(filter);
        });
    });

    // Carousel navigation
    if (leftArrow && rightArrow) {
        leftArrow.addEventListener('click', () => scrollCarousel(-1));
        rightArrow.addEventListener('click', () => scrollCarousel(1));
        grid.addEventListener('scroll', updateScrollButtons);
        window.addEventListener('resize', updateScrollButtons);
        updateScrollButtons();
    }

    /**
     * Load deal counts for each category tab
     */
    async function loadDealCounts() {
        try {
            const response = await fetch(`${CONFIG.apiEndpoint}/counts?threshold=${CONFIG.discountThreshold}`);
            if (!response.ok) return;

            const data = await response.json();
            const counts = data.counts || {};

            // Update tab count badges
            Object.entries(counts).forEach(([category, count]) => {
                const badge = section.querySelector(`[data-count="${category}"]`);
                if (badge && count > 0) {
                    badge.textContent = `(${count})`;
                }
            });
        } catch (e) {
            // Silently fail - counts are optional
        }
    }

    /**
     * Render deal cards to the grid
     */
    function renderDeals(deals) {
        // Clear skeletons and existing cards
        grid.innerHTML = '';

        if (deals.length === 0) {
            showEmptyState();
            return;
        }

        hideEmptyState();

        deals.forEach(deal => {
            const card = createDealCard(deal);
            grid.appendChild(card);
        });

        updateScrollButtons();
    }

    /**
     * Create a deal card from template
     */
    function createDealCard(deal) {
        const clone = template.content.cloneNode(true);
        const article = clone.querySelector('.deal-card');
        const link = clone.querySelector('a');
        const thumbnail = clone.querySelector('.deal-thumbnail');
        const discount = clone.querySelector('.deal-discount');
        const category = clone.querySelector('.deal-category');
        const name = clone.querySelector('.deal-name');
        const priceContainer = clone.querySelector('[data-geo-price]');
        const avgPrice = clone.querySelector('.deal-avg-price');

        // Set data attributes
        article.dataset.category = deal.category;
        priceContainer.dataset.productId = deal.id;

        // Set content
        link.href = deal.url || '#';
        thumbnail.src = deal.thumbnail || '';
        thumbnail.alt = deal.name || '';
        discount.textContent = Math.round(deal.discount_percent);
        category.textContent = CATEGORY_LABELS[deal.category] || deal.category_label || '';
        name.textContent = deal.name || '';

        // Show average price (crossed out)
        if (deal.average_price > 0) {
            avgPrice.textContent = formatPrice(deal.average_price, 'USD', false);
        }

        // Handle image error
        thumbnail.onerror = () => {
            thumbnail.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="200" height="150"%3E%3Crect fill="%23f0f0f0" width="200" height="150"/%3E%3C/svg%3E';
        };

        return clone;
    }

    /**
     * Load geo-aware prices for all deals
     */
    async function loadPrices(deals) {
        if (deals.length === 0) return;

        const productIds = deals.map(d => d.id);

        try {
            const prices = await getBestPrices(productIds, userGeo.geo, userGeo.currency);

            // Update price elements
            deals.forEach(deal => {
                const priceEl = grid.querySelector(`[data-product-id="${deal.id}"] [data-price]`);
                if (!priceEl) return;

                const priceData = prices[deal.id];
                if (priceData) {
                    priceEl.textContent = formatPrice(
                        priceData.price,
                        priceData.currency,
                        priceData.converted
                    );
                } else {
                    // Fallback to base price (USD)
                    priceEl.textContent = formatPrice(deal.base_price, 'USD', false);
                }
            });
        } catch (error) {
            console.error('[Deals] Failed to load prices:', error);
            // Fallback: show base prices
            deals.forEach(deal => {
                const priceEl = grid.querySelector(`[data-product-id="${deal.id}"] [data-price]`);
                if (priceEl && deal.base_price > 0) {
                    priceEl.textContent = formatPrice(deal.base_price, 'USD', false);
                }
            });
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
            const show = category === 'all' || cardCategory === category;

            card.classList.toggle('hidden', !show);
            if (show) visibleCount++;
        });

        if (visibleCount === 0) {
            showEmptyState();
        } else {
            hideEmptyState();
        }

        // Reset scroll position
        grid.scrollLeft = 0;
        updateScrollButtons();
    }

    /**
     * Set active tab styling
     */
    function setActiveTab(activeTab) {
        tabs.forEach(tab => {
            const isActive = tab === activeTab;
            tab.classList.toggle('bg-clmain', isActive);
            tab.classList.toggle('text-white', isActive);
            tab.classList.toggle('bg-white', !isActive);
            tab.classList.toggle('text-clbody', !isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    }

    /**
     * Scroll carousel left/right
     */
    function scrollCarousel(direction) {
        const cardWidth = grid.querySelector('.deal-card')?.offsetWidth || 288;
        const gap = 16; // gap-4 = 1rem = 16px
        const scrollAmount = (cardWidth + gap) * 2;

        grid.scrollBy({
            left: direction * scrollAmount,
            behavior: 'smooth'
        });
    }

    /**
     * Update scroll button states
     */
    function updateScrollButtons() {
        if (!leftArrow || !rightArrow) return;

        const scrollLeft = grid.scrollLeft;
        const maxScroll = grid.scrollWidth - grid.clientWidth;

        leftArrow.disabled = scrollLeft <= 0;
        rightArrow.disabled = scrollLeft >= maxScroll - 1;
    }

    /**
     * Show empty state
     */
    function showEmptyState() {
        grid.innerHTML = '';
        emptyState?.classList.remove('hidden');
    }

    /**
     * Hide empty state
     */
    function hideEmptyState() {
        emptyState?.classList.add('hidden');
    }

    return {
        refresh: async () => {
            try {
                const response = await fetch(`${CONFIG.apiEndpoint}?category=all&limit=${CONFIG.dealsLimit}`);
                const data = await response.json();
                allDeals = data.deals || [];
                renderDeals(allDeals);
                await loadPrices(allDeals);
                filterDeals(currentCategory);
            } catch (e) {
                console.error('[Deals] Refresh failed:', e);
            }
        },
        filterDeals,
    };
}

export default { initDeals };

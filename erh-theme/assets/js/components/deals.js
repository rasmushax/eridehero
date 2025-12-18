/**
 * Deals Section Component
 *
 * Loads deals from REST API with geo-aware pricing.
 * Features:
 * - Category tab filtering
 * - Carousel navigation (desktop)
 * - Prices included in deals API response (single request)
 * - Skeleton loading states
 */

import { getUserGeo } from '../services/geo-price.js';

// Configuration
const CONFIG = {
    dealsLimit: 12,
    discountThreshold: -5,
};

/**
 * Get REST URL base from WordPress localized data
 */
function getRestUrl() {
    // Use WordPress localized data if available
    if (typeof erhData !== 'undefined' && erhData.restUrl) {
        return erhData.restUrl;
    }
    // Fallback - should not happen in production
    return '/wp-json/erh/v1/';
}

/**
 * Initialize the deals section
 */
export async function initDeals() {
    console.group('[Deals] initDeals()');
    console.log('Initializing deals section...');

    const section = document.getElementById('deals-section');
    if (!section) {
        console.warn('deals-section element not found - skipping');
        console.groupEnd();
        return null;
    }
    console.log('Found deals-section element');

    const grid = section.querySelector('.deals-grid');
    const tabs = section.querySelectorAll('.filter-pill');
    const emptyState = section.querySelector('.deals-empty');
    const template = document.getElementById('deal-card-template');
    const ctaTemplate = document.getElementById('deal-cta-template');
    const leftArrow = section.querySelector('.carousel-arrow-left');
    const rightArrow = section.querySelector('.carousel-arrow-right');
    const carousel = section.querySelector('.deals-carousel');
    const dealsCountEl = document.getElementById('deals-count');

    if (!grid || !template) {
        console.warn('Missing required elements - grid:', !!grid, 'template:', !!template);
        console.groupEnd();
        return null;
    }
    console.log('All required DOM elements found');

    // State
    let allDeals = [];
    let currentCategory = 'all';
    let userGeo = { geo: 'US', currency: 'USD' };
    let totalDealsCount = 0;

    // Get user geo
    console.log('--- GEO DETECTION ---');
    try {
        userGeo = await getUserGeo();
        console.log('[Deals] User geo detected:', userGeo);
    } catch (e) {
        console.warn('[Deals] Failed to detect geo, using defaults:', e);
    }

    // Load deals
    console.log('--- LOADING DEALS ---');
    const dealsUrl = `${getRestUrl()}deals?category=all&limit=${CONFIG.dealsLimit}&threshold=${CONFIG.discountThreshold}&geo=${userGeo.geo}`;
    console.log('[Deals] Fetching from:', dealsUrl);
    try {
        const response = await fetch(dealsUrl);
        console.log('[Deals] Response status:', response.status, response.statusText);

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const data = await response.json();
        console.log('[Deals] Raw API response:', data);
        console.log('[Deals] Response keys:', Object.keys(data));
        console.log('[Deals] Deals count from API:', data.count);
        console.log('[Deals] Geo from API:', data.geo);
        console.log('[Deals] Period from API:', data.period);

        allDeals = data.deals || [];
        console.log('[Deals] Number of deals received:', allDeals.length);

        if (allDeals.length > 0) {
            console.log('[Deals] First deal sample:', allDeals[0]);
            console.log('[Deals] All deal IDs:', allDeals.map(d => d.id));
            console.log('[Deals] All deal names:', allDeals.map(d => d.name));
            console.log('[Deals] All discount %:', allDeals.map(d => d.discount_percent));
        } else {
            console.warn('[Deals] NO DEALS RETURNED! This might indicate:');
            console.warn('  1. No products in wp_product_data table');
            console.warn('  2. No products with price_history for geo:', data.geo);
            console.warn('  3. No products meet the -5% threshold');
            console.warn('  4. All products are out of stock');
        }
    } catch (error) {
        console.error('[Deals] Failed to load deals:', error);
        showEmptyState(true);
        console.groupEnd();
        return null;
    }

    // Load deal counts
    console.log('--- LOADING DEAL COUNTS ---');
    await loadDealCounts();

    // Render deals (prices already included from deals API - geo-specific)
    console.log('--- RENDERING DEALS ---');
    console.log('[Deals] About to render', allDeals.length, 'deals');
    renderDeals(allDeals);

    console.log('--- DEALS INITIALIZATION COMPLETE ---');
    console.log('[Deals] Summary:');
    console.log('  - User geo:', userGeo);
    console.log('  - Deals loaded:', allDeals.length);
    console.log('  - Total deals available:', totalDealsCount);
    console.groupEnd();

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
        grid.addEventListener('scroll', updateScrollState);
        window.addEventListener('resize', updateScrollState);
        updateScrollState();
    }

    /**
     * Load deal counts for total display
     */
    async function loadDealCounts() {
        const countsUrl = `${getRestUrl()}deals/counts?threshold=${CONFIG.discountThreshold}&geo=${userGeo.geo}`;
        console.log('[Deals] Fetching counts from:', countsUrl);
        try {
            const response = await fetch(countsUrl);
            console.log('[Deals] Counts response status:', response.status);
            if (!response.ok) {
                console.warn('[Deals] Counts request failed:', response.status);
                return;
            }

            const data = await response.json();
            console.log('[Deals] Counts data:', data);
            const counts = data.counts || {};
            console.log('[Deals] Deal counts by category:', counts);

            totalDealsCount = counts.all || 0;
            console.log('[Deals] Total deals count:', totalDealsCount);

            // Update total count display
            if (dealsCountEl && totalDealsCount > 0) {
                dealsCountEl.textContent = `🔥 ${totalDealsCount} deals cooking`;
                console.log('[Deals] Updated count display element');
            }
        } catch (e) {
            console.warn('[Deals] Failed to load counts:', e);
        }
    }

    /**
     * Render deal cards to the grid
     */
    function renderDeals(deals) {
        // Clear skeletons and existing cards
        grid.innerHTML = '';

        if (deals.length === 0) {
            showEmptyState(true);
            return;
        }

        hideEmptyState();

        // Render deal cards
        deals.forEach(deal => {
            const card = createDealCard(deal);
            grid.appendChild(card);
        });

        // Add CTA card if there are more deals
        if (ctaTemplate && totalDealsCount > deals.length) {
            const ctaCard = ctaTemplate.content.cloneNode(true);
            const countEl = ctaCard.querySelector('.deal-card-cta-count');
            if (countEl) {
                countEl.textContent = `+${totalDealsCount - deals.length}`;
            }
            grid.appendChild(ctaCard);
        }

        updateScrollState();
    }

    /**
     * Create a deal card from template
     */
    function createDealCard(deal) {
        const clone = template.content.cloneNode(true);
        const card = clone.querySelector('.deal-card');
        const thumbnail = clone.querySelector('.deal-thumbnail');
        const priceContainer = clone.querySelector('[data-geo-price]');
        const priceValue = clone.querySelector('.deal-price-value');
        const priceCurrency = clone.querySelector('.deal-price-currency');
        const title = clone.querySelector('.deal-card-title');
        const discountText = clone.querySelector('.deal-discount-text');

        // Set data attributes
        card.dataset.category = deal.category;
        card.href = deal.url || '#';

        if (priceContainer) {
            priceContainer.dataset.productId = deal.id;
        }

        // Set content
        if (thumbnail) {
            thumbnail.src = deal.thumbnail || '';
            thumbnail.alt = deal.name || '';
            thumbnail.onerror = () => {
                thumbnail.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="80" height="80"%3E%3Crect fill="%23f0f0f0" width="80" height="80"/%3E%3C/svg%3E';
            };
        }

        if (title) {
            title.textContent = deal.name || '';
        }

        if (discountText) {
            discountText.textContent = `${Math.round(deal.discount_percent)}% below avg`;
        }

        // Set price from deals API response (already geo-specific)
        if (priceValue && deal.current_price > 0) {
            const priceParts = splitPrice(deal.current_price);
            priceValue.textContent = priceParts.whole;
            if (priceCurrency) {
                const symbols = { 'USD': '$', 'EUR': '€', 'GBP': '£', 'CAD': 'CA$', 'AUD': 'A$' };
                priceCurrency.textContent = symbols[deal.currency] || '$';
            }
        }

        return clone;
    }

    /**
     * Split price into whole and cents
     */
    function splitPrice(price) {
        const whole = Math.floor(price);
        const cents = Math.round((price - whole) * 100);
        return {
            whole: whole.toLocaleString(),
            cents: cents.toString().padStart(2, '0')
        };
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

            // Always show CTA card
            if (card.classList.contains('deal-card-cta')) {
                return;
            }

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
        updateScrollState();
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
     * Scroll carousel left/right
     */
    function scrollCarousel(direction) {
        const cardWidth = grid.querySelector('.deal-card')?.offsetWidth || 180;
        const gap = 12; // var(--space-5)
        const scrollAmount = (cardWidth + gap) * 2;

        grid.scrollBy({
            left: direction * scrollAmount,
            behavior: 'smooth'
        });
    }

    /**
     * Update scroll button states and fade masks
     */
    function updateScrollState() {
        if (!leftArrow || !rightArrow || !carousel) return;

        const scrollLeft = grid.scrollLeft;
        const maxScroll = grid.scrollWidth - grid.clientWidth;

        const canScrollLeft = scrollLeft > 0;
        const canScrollRight = scrollLeft < maxScroll - 1;

        leftArrow.disabled = !canScrollLeft;
        rightArrow.disabled = !canScrollRight;

        // Update fade mask classes
        carousel.classList.toggle('can-scroll-left', canScrollLeft);
        carousel.classList.toggle('can-scroll-right', canScrollRight);
    }

    /**
     * Show empty state
     */
    function showEmptyState(clearGrid = false) {
        if (clearGrid) {
            grid.innerHTML = '';
        }
        if (emptyState) {
            emptyState.style.display = '';
        }
    }

    /**
     * Hide empty state
     */
    function hideEmptyState() {
        if (emptyState) {
            emptyState.style.display = 'none';
        }
    }

    return {
        refresh: async () => {
            try {
                const response = await fetch(`${getRestUrl()}deals?category=all&limit=${CONFIG.dealsLimit}&geo=${userGeo.geo}`);
                const data = await response.json();
                allDeals = data.deals || [];
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

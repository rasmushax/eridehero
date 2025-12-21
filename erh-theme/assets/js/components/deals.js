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
    const section = document.getElementById('deals-section');
    if (!section) {
        return null;
    }

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
        return null;
    }

    // State
    let allDeals = [];
    let currentCategory = 'all';
    let userGeo = { geo: 'US', currency: 'USD' };
    let totalDealsCount = 0;

    // Get user geo
    try {
        userGeo = await getUserGeo();
    } catch (e) {
        // Use defaults
    }

    // Load deals (includes counts in response)
    const dealsUrl = `${getRestUrl()}deals?category=all&limit=${CONFIG.dealsLimit}&threshold=${CONFIG.discountThreshold}&geo=${userGeo.geo}`;
    try {
        const response = await fetch(dealsUrl);

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const data = await response.json();
        allDeals = data.deals || [];

        // Extract counts from response
        const counts = data.counts || {};
        totalDealsCount = counts.all || 0;

        // Update total count display
        if (dealsCountEl && totalDealsCount > 0) {
            dealsCountEl.textContent = `🔥 ${totalDealsCount} deals cooking`;
        }
    } catch (error) {
        console.error('[Deals] Failed to load deals:', error);
        showEmptyState(true);
        return null;
    }

    // Render deals (prices already included from deals API - geo-specific)
    renderDeals(allDeals);

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

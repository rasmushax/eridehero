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

import { getUserGeo, formatPrice } from '../services/geo-price.js';
import { PriceAlertModal } from './price-alert.js';
import { initCarousel } from '../utils/carousel.js';

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
    let carouselInstance = null;

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

    // Price tracker button handler (event delegation)
    grid.addEventListener('click', (e) => {
        const trackBtn = e.target.closest('[data-track-price]');
        if (!trackBtn) return;

        e.preventDefault();
        e.stopPropagation();

        const productId = parseInt(trackBtn.dataset.trackPrice, 10);
        const productName = trackBtn.dataset.productName || '';
        const productImage = trackBtn.dataset.productImage || '';
        const currentPrice = parseFloat(trackBtn.dataset.currentPrice) || 0;
        const currency = trackBtn.dataset.currency || userGeo.currency;

        PriceAlertModal.open({
            productId,
            productName,
            productImage,
            currentPrice,
            currency
        });
    });

    // Carousel navigation using shared utility
    if (leftArrow && rightArrow && carousel) {
        // Card width + gap for scroll amount calculation
        const cardWidth = 180; // deal card width
        const gap = 12; // var(--space-5)
        const scrollAmount = (cardWidth + gap) * 2;

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

        // Update carousel scroll state
        if (carouselInstance) {
            carouselInstance.update();
        }
    }

    /**
     * Create a deal card from template
     */
    function createDealCard(deal) {
        const clone = template.content.cloneNode(true);
        const card = clone.querySelector('.deal-card');
        const trackBtn = clone.querySelector('[data-track-price]');
        const imageContainer = clone.querySelector('.deal-card-image');
        const img = imageContainer?.querySelector('img');
        const title = clone.querySelector('.deal-card-title');
        const priceEl = clone.querySelector('.deal-card-price');
        const indicator = clone.querySelector('.deal-card-indicator');
        const indicatorValue = clone.querySelector('[data-indicator-value]');

        // Set data attributes
        card.dataset.category = deal.category;
        card.href = deal.url || '#';

        // Set tracker button data
        if (trackBtn) {
            trackBtn.dataset.trackPrice = deal.id;
            trackBtn.dataset.productName = deal.name;
            trackBtn.dataset.productImage = deal.thumbnail || '';
            trackBtn.dataset.currentPrice = deal.current_price || '';
            trackBtn.dataset.currency = deal.currency || 'USD';
        }

        // Set image
        if (img) {
            img.src = deal.thumbnail || '';
            img.alt = deal.name || '';
            img.onerror = () => {
                img.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="80" height="80"%3E%3Crect fill="%23f0f0f0" width="80" height="80"/%3E%3C/svg%3E';
            };
        }

        // Set title
        if (title) {
            title.textContent = deal.name || '';
        }

        // Set price (formatted with decimals)
        if (priceEl && deal.current_price > 0) {
            priceEl.textContent = formatPrice(deal.current_price, deal.currency || 'USD');
        } else if (priceEl) {
            priceEl.textContent = '';
            priceEl.classList.add('deal-card-no-price');
        }

        // Set discount indicator
        // Note: discount_percent is positive for deals (e.g., 22 means 22% below average)
        if (indicator && indicatorValue) {
            const discountPercent = Math.abs(Math.round(deal.discount_percent));
            indicatorValue.textContent = `${discountPercent}%`;

            const iconUse = indicator.querySelector('use');

            // Toggle indicator style based on above/below average
            // Positive discount_percent = below average (good deal)
            // Negative or zero = at or above average
            if (deal.discount_percent > 0) {
                // Below average (good deal)
                indicator.classList.remove('deal-card-indicator--above');
                indicator.classList.add('deal-card-indicator--below');
                if (iconUse) iconUse.setAttribute('href', '#icon-arrow-down');
            } else {
                // At or above average - should not typically show in deals
                indicator.classList.remove('deal-card-indicator--below');
                indicator.classList.add('deal-card-indicator--above');
                if (iconUse) iconUse.setAttribute('href', '#icon-arrow-up');
            }
        }

        return clone;
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

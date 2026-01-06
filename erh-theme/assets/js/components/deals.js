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

import { getUserGeo, getCurrencySymbol } from '../services/geo-price.js';
import { splitPrice } from '../utils/product-card.js';
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
    let carouselInstance = null;
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
        const thumbnail = clone.querySelector('.deal-thumbnail');
        const priceContainer = clone.querySelector('[data-geo-price]');
        const priceValue = clone.querySelector('.deal-price-value');
        const priceCurrency = clone.querySelector('.deal-price-currency');
        const title = clone.querySelector('.deal-card-title');
        const discountText = clone.querySelector('.deal-discount-text');

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
                priceCurrency.textContent = getCurrencySymbol(deal.currency);
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

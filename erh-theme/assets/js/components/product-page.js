/**
 * Product Page Orchestrator
 *
 * Coordinates all functionality for single product pages:
 * - Loads product data from finder JSON (for performance profile)
 * - Initializes RadarChart for performance profile
 * - Loads geo-aware prices for product cards
 *
 * Note: Specs are rendered server-side by PHP from wp_product_data cache.
 * Note: "What to Know" insights are currently hardcoded in PHP.
 *       Phase 11 TODO: Implement dynamic insights based on percentile data.
 *
 * @module components/product-page
 */

import { RadarChart } from './radar-chart.js';
import { SPEC_GROUPS } from '../config/compare-config.js';
import { getUserGeo, formatPrice } from '../services/geo-price.js';

class ProductPage {
    /**
     * Initialize product page.
     *
     * @param {HTMLElement} container - The product page container
     */
    constructor(container) {
        this.container = container;
        this.productId = parseInt(container.dataset.productId, 10);
        this.category = container.dataset.category || 'escooter';
        this.product = null;
        this.radarChart = null;

        this.init();
    }

    /**
     * Initialize all components.
     */
    async init() {
        // Load product data for performance profile
        await this.loadProductData();

        if (!this.product) {
            console.warn('ProductPage: Could not load product data');
            // Performance profile will show empty state
        }

        // Initialize components that need product data
        this.initPerformanceProfile();
        this.loadHeroPrice();
        this.loadProductCardPrices();
    }

    /**
     * Load product data from finder JSON.
     */
    async loadProductData() {
        try {
            // Try to get from inline data first
            if (window.erhData?.productData) {
                this.product = window.erhData.productData;
                return;
            }

            // Otherwise load from finder JSON
            const baseUrl = window.erhData?.siteUrl || '';
            const jsonUrl = `${baseUrl}/wp-content/uploads/finder_${this.category}.json`;
            const response = await fetch(jsonUrl);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const products = await response.json();
            this.product = products.find(p => p.id === this.productId);

        } catch (error) {
            console.warn('ProductPage: Failed to load product data:', error);
        }
    }

    /**
     * Load and display hero price.
     * Fetches from prices API for both price and store count (appear together).
     */
    async loadHeroPrice() {
        const heroPrice = this.container.querySelector('[data-hero-price]');
        if (!heroPrice) return;

        const amountEl = heroPrice.querySelector('.hero-price-amount');
        const storesEl = heroPrice.querySelector('.hero-price-stores');

        if (!amountEl) return;

        try {
            // Detect user's geo (same pattern as finder, deals, price-intel)
            const { geo, currency } = await getUserGeo();

            // Fetch from prices API for both price and store count
            const baseUrl = window.erhData?.siteUrl || '';
            const response = await fetch(`${baseUrl}/wp-json/erh/v1/prices/${this.productId}?geo=${geo}`);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            const offers = data.offers || [];

            // Filter offers to only show geo-appropriate ones (same logic as price-intel)
            const filteredOffers = this.filterOffersForGeo(offers, geo, currency);

            // Get best price from filtered offers
            const best = filteredOffers.find(o => o.in_stock) || filteredOffers[0];

            if (best?.price) {
                // Show best price
                amountEl.textContent = formatPrice(best.price, best.currency || currency);
                heroPrice.classList.add('has-price');

                // Count in-stock offers from filtered list
                const inStockCount = filteredOffers.filter(o => o.in_stock === true).length;
                if (storesEl && inStockCount > 0) {
                    storesEl.textContent = inStockCount === 1 ? 'at 1 store' : `at ${inStockCount} stores`;
                }
            } else {
                amountEl.textContent = 'See prices';
                heroPrice.classList.add('no-price');
            }
        } catch (error) {
            console.warn('ProductPage: Failed to load hero price:', error);
            amountEl.textContent = 'See prices';
            heroPrice.classList.add('no-price');
        }
    }

    /**
     * Filter offers to only show geo-appropriate ones.
     * Same logic as price-intel.js filterOffersForGeo().
     *
     * @param {Array} offers - Array of offer objects
     * @param {string} userGeo - User's geo region (US, GB, EU, CA, AU)
     * @param {string} userCurrency - User's currency (USD, GBP, EUR, etc.)
     * @returns {Array} Filtered offers
     */
    filterOffersForGeo(offers, userGeo, userCurrency) {
        const euCountries = ['DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'AT', 'IE', 'PT', 'FI', 'GR', 'LU', 'SK', 'SI', 'EE', 'LV', 'LT', 'CY', 'MT'];

        return offers.filter(offer => {
            const offerCurrency = offer.currency || 'USD';
            const offerGeo = offer.geo;

            // Primary filter: currency must match user's currency
            if (offerCurrency !== userCurrency) {
                return false;
            }

            // If offer has explicit geo, it must match user's region
            if (offerGeo) {
                if (offerGeo === userGeo) return true;
                if (userGeo === 'EU' && (offerGeo === 'EU' || euCountries.includes(offerGeo))) return true;
                if (euCountries.includes(userGeo) && offerGeo === 'EU') return true;
                return false;
            }

            // Offer has no explicit geo (global) - accept if currency matches
            return true;
        });
    }

    /**
     * Initialize performance profile section (radar chart).
     * Note: "What to Know" insights are hardcoded in PHP for now.
     * Phase 11 TODO: Implement dynamic insights based on percentile data.
     */
    initPerformanceProfile() {
        const profileSection = this.container.querySelector('[data-performance-profile]');
        if (!profileSection) return;

        const radarContainer = profileSection.querySelector('[data-radar-chart]');

        // Calculate category scores
        const scores = this.calculateCategoryScores();

        if (!scores || Object.keys(scores).length === 0) {
            // Hide radar loading if no scores available
            const loadingEl = profileSection.querySelector('[data-radar-loading]');
            if (loadingEl) loadingEl.style.display = 'none';
            return;
        }

        // Render radar chart
        if (radarContainer) {
            this.renderRadarChart(radarContainer, scores);
        }
    }

    /**
     * Calculate category scores for the product.
     * Uses pre-calculated scores from finder JSON (specs.scores).
     *
     * @returns {Object} Category scores keyed by category id
     */
    calculateCategoryScores() {
        if (!this.product?.specs?.scores) return {};

        const preCalculatedScores = this.product.specs.scores;
        const specGroups = SPEC_GROUPS[this.category];

        if (!specGroups) return {};

        // Map of score keys to display names and icons
        const scoreConfig = {
            motor_performance: { name: 'Motor Performance', icon: 'zap' },
            range_battery: { name: 'Range & Battery', icon: 'battery' },
            ride_quality: { name: 'Ride Quality', icon: 'smile' },
            portability: { name: 'Portability & Fit', icon: 'box' },
            safety: { name: 'Safety', icon: 'shield' },
            features: { name: 'Features', icon: 'settings' },
            maintenance: { name: 'Maintenance', icon: 'tool' },
        };

        const scores = {};

        for (const [key, config] of Object.entries(scoreConfig)) {
            if (preCalculatedScores[key] !== undefined && preCalculatedScores[key] !== null) {
                scores[key] = {
                    name: config.name,
                    score: Math.round(preCalculatedScores[key]),
                    icon: config.icon,
                };
            }
        }

        return scores;
    }

    /**
     * Render radar chart.
     *
     * @param {HTMLElement} container - Container element
     * @param {Object} scores - Category scores
     */
    renderRadarChart(container, scores) {
        // Hide loading state
        const loadingEl = container.querySelector('[data-radar-loading]');
        if (loadingEl) loadingEl.style.display = 'none';

        // Prepare data for radar chart
        const categories = Object.entries(scores).map(([key, data]) => ({
            key,
            name: data.name,
        }));

        const productData = [{
            id: this.productId,
            name: this.product.name,
            scores: Object.fromEntries(
                Object.entries(scores).map(([key, data]) => [key, data.score])
            ),
        }];

        // Create radar chart (no legend for single product)
        this.radarChart = new RadarChart(container, {
            size: 300,
            maxValue: 100,
            showLegend: false, // Hide legend for single product view
        });

        this.radarChart.setData(productData, categories);

        // Hide legend element if it exists (RadarChart creates it by default)
        const legend = container.querySelector('.radar-chart-legend');
        if (legend) legend.style.display = 'none';
    }

    /**
     * Load prices for product cards in related section.
     */
    async loadProductCardPrices() {
        const priceEls = this.container.querySelectorAll('[data-product-price]');
        if (priceEls.length === 0) return;

        try {
            const { geo, currency } = await getUserGeo();

            // Load finder JSON if not already loaded
            if (!this.finderProducts) {
                const baseUrl = window.erhData?.siteUrl || '';
                const jsonUrl = `${baseUrl}/wp-content/uploads/finder_${this.category}.json`;
                const response = await fetch(jsonUrl);
                if (response.ok) {
                    this.finderProducts = await response.json();
                }
            }

            if (!this.finderProducts) return;

            // Create lookup map
            const productMap = new Map(this.finderProducts.map(p => [p.id, p]));

            priceEls.forEach(el => {
                const productId = parseInt(el.dataset.productPrice, 10);
                const product = productMap.get(productId);

                if (product?.pricing) {
                    const pricing = product.pricing[geo] || product.pricing['US'];
                    if (pricing?.current_price) {
                        const displayCurrency = product.pricing[geo] ? currency : 'USD';
                        el.textContent = formatPrice(pricing.current_price, displayCurrency);
                        el.classList.add('has-price');
                    } else {
                        el.innerHTML = '<span class="no-price">Price unavailable</span>';
                    }
                } else {
                    el.innerHTML = '<span class="no-price">Price unavailable</span>';
                }
            });

        } catch (error) {
            console.warn('ProductPage: Failed to load prices:', error);
            priceEls.forEach(el => {
                el.innerHTML = '<span class="no-price">—</span>';
            });
        }
    }

    /**
     * Escape HTML entities.
     *
     * @param {string} str - String to escape
     * @returns {string} Escaped string
     */
    escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[c]);
    }

    /**
     * Destroy the instance.
     */
    destroy() {
        if (this.radarChart) {
            this.radarChart.destroy();
            this.radarChart = null;
        }
    }
}

// =============================================================================
// Auto-initialization
// =============================================================================

/**
 * Initialize product pages on DOM ready.
 */
function initProductPages() {
    document.querySelectorAll('[data-product-page]').forEach(container => {
        new ProductPage(container);
    });
}

// Auto-init on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProductPages);
} else {
    initProductPages();
}

export { ProductPage, initProductPages };

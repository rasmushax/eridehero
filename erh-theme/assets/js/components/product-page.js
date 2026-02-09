/**
 * Product Page Orchestrator
 *
 * Coordinates all functionality for single product pages:
 * - Fetches analysis data (geo-dependent) for radar chart with bracket average
 * - Initializes ProductAnalysis for strengths/weaknesses (shares API cache)
 * - Manages similar products carousel
 *
 * Note: Hero price is updated by price-intel.js (shares the same API call).
 * Note: Specs and similar products are rendered server-side by PHP.
 *
 * @module components/product-page
 */

import { RadarChart } from './radar-chart.js';
import { ProductAnalysis } from './product-analysis.js';
import { initCarousel } from '../utils/carousel.js';
import { getUserGeo } from '../services/geo-price.js';

// =============================================================================
// Configuration
// =============================================================================

/**
 * Score category configuration by product type.
 * Maps score keys to display names.
 */
const SCORE_CONFIG = {
    escooter: {
        motor_performance: { name: 'Motor', icon: 'zap' },
        range_battery: { name: 'Range & Battery', icon: 'battery' },
        ride_quality: { name: 'Ride Quality', icon: 'smile' },
        portability: { name: 'Portability', icon: 'box' },
        safety: { name: 'Safety', icon: 'shield' },
        features: { name: 'Features', icon: 'settings' },
        maintenance: { name: 'Maintenance', icon: 'tool' },
    },
    ebike: {
        motor_drive: { name: 'Motor & Drive', icon: 'zap' },
        battery_range: { name: 'Battery & Range', icon: 'battery' },
        component_quality: { name: 'Component Quality', icon: 'settings' },
        comfort: { name: 'Comfort', icon: 'smile' },
        practicality: { name: 'Practicality', icon: 'box' },
    },
    hoverboard: {
        motor_performance: { name: 'Motor', icon: 'zap' },
        battery_range: { name: 'Battery', icon: 'battery' },
        portability: { name: 'Portability', icon: 'box' },
        ride_comfort: { name: 'Ride Comfort', icon: 'smile' },
        features: { name: 'Features', icon: 'settings' },
    },
    euc: {
        motor_performance: { name: 'Motor', icon: 'zap' },
        battery_range: { name: 'Battery & Range', icon: 'battery' },
        ride_quality: { name: 'Ride Quality', icon: 'smile' },
        safety: { name: 'Safety', icon: 'shield' },
        portability: { name: 'Portability', icon: 'box' },
        features: { name: 'Features', icon: 'settings' },
    },
    eskateboard: {
        motor_performance: { name: 'Motor', icon: 'zap' },
        battery_range: { name: 'Battery & Range', icon: 'battery' },
        ride_quality: { name: 'Ride Quality', icon: 'smile' },
        portability: { name: 'Portability', icon: 'box' },
        features: { name: 'Features', icon: 'settings' },
    },
};

/**
 * Get score config for a product category.
 * @param {string} category - Product category (escooter, ebike)
 * @returns {Object} Score configuration
 */
function getScoreConfig(category) {
    return SCORE_CONFIG[category] || SCORE_CONFIG.escooter;
}

/**
 * Product type labels for display.
 */
const PRODUCT_TYPE_LABELS = {
    escooter: { singular: 'scooter', plural: 'scooters' },
    ebike: { singular: 'e-bike', plural: 'e-bikes' },
    euc: { singular: 'EUC', plural: 'EUCs' },
    eskateboard: { singular: 'e-skateboard', plural: 'e-skateboards' },
    hoverboard: { singular: 'hoverboard', plural: 'hoverboards' },
};

/**
 * Get product type labels for a category.
 * @param {string} category - Category key
 * @returns {{ singular: string, plural: string }}
 */
function getProductLabels(category) {
    return PRODUCT_TYPE_LABELS[category] || { singular: 'product', plural: 'products' };
}

// =============================================================================
// ProductPage Class
// =============================================================================

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
        this.radarChart = null;
        this.productAnalysis = null;
        this.carousel = null;

        // DOM elements for radar
        this.radarContainer = container.querySelector('[data-radar-chart]');
        this.radarSkeleton = container.querySelector('[data-radar-skeleton]');
        this.radarContent = container.querySelector('[data-radar-content]');

        this.init();
    }

    /**
     * Initialize all components.
     */
    async init() {
        // Track product view (fire-and-forget, doesn't block page)
        this.trackProductView();

        // Initialize non-geo components immediately
        this.initSimilarCarousel();

        // Initialize geo-dependent components (single API call)
        await this.initPerformanceSection();
    }

    /**
     * Track product view via AJAX.
     *
     * Uses AJAX to bypass page caching (LiteSpeed/Cloudflare).
     * Session deduplication prevents multiple counts per visit.
     */
    trackProductView() {
        if (!this.productId || typeof this.productId !== 'number') return;

        // Session deduplication - only track once per session per product
        const sessionKey = `erh_viewed_${this.productId}`;
        try {
            if (sessionStorage.getItem(sessionKey)) {
                return;
            }
        } catch (e) {
            // sessionStorage may be disabled (private browsing, security settings)
            // Continue with tracking - server-side will handle deduplication
        }

        const { restUrl, nonce } = window.erhData || {};
        if (!restUrl) return;

        // Fire-and-forget request
        fetch(`${restUrl}products/${this.productId}/view`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
            },
        })
            .then(response => {
                if (response.ok) {
                    // Mark as tracked for this session
                    try {
                        sessionStorage.setItem(sessionKey, '1');
                    } catch (e) {
                        // Storage may be disabled - that's fine
                    }
                }
            })
            .catch(() => {
                // Silent fail - view tracking is non-critical
            });
    }

    /**
     * Initialize performance section (radar chart + strengths/weaknesses).
     * Single API call, data shared between components.
     */
    async initPerformanceSection() {
        try {
            const geoData = await getUserGeo();
            const geo = geoData.geo;
            const data = await this.fetchAnalysis(geo);

            // Render radar chart with the data
            this.initRadarChart(data);

            // Pass same data to ProductAnalysis (no duplicate fetch)
            this.initProductAnalysis(data);
        } catch (error) {
            console.error('[ProductPage] Failed to load analysis:', error);
            this.hideRadarSection();
            this.showAnalysisError();
        }
    }

    /**
     * Initialize radar chart with bracket average.
     *
     * @param {Object} data - Analysis data from API
     */
    initRadarChart(data) {
        if (!this.radarContainer || !this.radarContent) return;

        if (data?.product?.scores && Object.keys(data.product.scores).length > 0) {
            this.renderRadarChart(data);
        } else {
            // No scores available - hide the section
            this.hideRadarSection();
        }
    }

    /**
     * Fetch analysis data from API.
     *
     * @param {string} geo - Geo region code
     * @returns {Promise<Object>} Analysis data
     */
    async fetchAnalysis(geo) {
        const { restUrl, nonce } = window.erhData || {};

        if (!restUrl || !this.productId) {
            throw new Error('Missing required data');
        }

        const url = `${restUrl}products/${this.productId}/analysis?geo=${geo}`;

        const response = await fetch(url, {
            headers: {
                'X-WP-Nonce': nonce,
            },
        });

        if (!response.ok) {
            throw new Error(`API error: ${response.status}`);
        }

        return response.json();
    }

    /**
     * Render radar chart with product scores and bracket average.
     *
     * @param {Object} data - Analysis data from API
     */
    renderRadarChart(data) {
        const { product, bracket_scores, price_context } = data;

        // Build categories array from score config (product-type aware)
        const scoreConfig = getScoreConfig(this.category);
        const categories = Object.entries(scoreConfig)
            .filter(([key]) => product.scores[key] !== undefined)
            .map(([key, config]) => ({
                key,
                name: config.name,
            }));

        if (categories.length === 0) {
            this.hideRadarSection();
            return;
        }

        // Build product data for radar chart
        // Bracket avg first (drawn behind), product second (drawn in front)
        const productData = [];
        const chartColors = [];

        // Add bracket average first (if available) - grey, at the back
        if (bracket_scores && Object.keys(bracket_scores).length > 0) {
            const bracket = price_context?.bracket;
            const labels = getProductLabels(this.category);
            const bracketLabel = bracket
                ? `${bracket.label} ${labels.plural} ($${bracket.min.toLocaleString()}–$${bracket.max >= 2147483647 ? '+' : bracket.max.toLocaleString()})`
                : 'Bracket average';

            productData.push({
                id: 'bracket-avg',
                name: bracketLabel,
                scores: bracket_scores,
            });
            chartColors.push('var(--color-muted)');
        }

        // Add product second - primary color, in front
        productData.push({
            id: product.id,
            name: product.name,
            scores: product.scores,
        });
        chartColors.push('var(--color-primary)');

        // Create radar chart with custom colors
        this.radarChart = new RadarChart(this.radarContent, {
            size: 300,
            maxValue: 100,
            fillOpacity: 0.12,
            strokeWidth: 2,
            colors: chartColors,
        });

        this.radarChart.setData(productData, categories);

        // Make legend non-interactive (display only, no toggle)
        const legend = this.radarContent.querySelector('.radar-chart-legend');
        if (legend) {
            legend.classList.add('radar-chart-legend--static');
        }

        // Show content, hide skeleton
        this.showRadarContent();
    }

    /**
     * Show radar content, hide skeleton.
     */
    showRadarContent() {
        if (this.radarSkeleton) this.radarSkeleton.style.display = 'none';
        if (this.radarContent) this.radarContent.style.display = '';
    }

    /**
     * Hide entire radar section on error/no data.
     */
    hideRadarSection() {
        if (this.radarSkeleton) this.radarSkeleton.style.display = 'none';
        // Could also hide the entire radar container if desired
    }

    /**
     * Initialize product analysis (strengths/weaknesses).
     * Receives pre-fetched data from initPerformanceSection.
     *
     * @param {Object} data - Analysis data from API
     */
    initProductAnalysis(data) {
        const analysisContainer = this.container.querySelector('[data-product-analysis]');
        if (!analysisContainer) return;

        this.productAnalysis = new ProductAnalysis(analysisContainer, {
            productId: this.productId,
            category: this.category,
            data: data, // Pass pre-fetched data, skips duplicate API call
        });
    }

    /**
     * Show error state for analysis section.
     */
    showAnalysisError() {
        const analysisContainer = this.container.querySelector('[data-product-analysis]');
        if (!analysisContainer) return;

        const skeleton = analysisContainer.querySelector('[data-analysis-skeleton]');
        const empty = analysisContainer.querySelector('[data-analysis-empty]');

        if (skeleton) skeleton.style.display = 'none';
        if (empty) {
            const messageEl = empty.querySelector('.analysis-empty-message');
            if (messageEl) {
                messageEl.textContent = 'Unable to load comparison data.';
            }
            empty.style.display = '';
        }
    }

    /**
     * Initialize similar products carousel.
     * Uses shared carousel utility for arrow navigation and fade effects.
     */
    initSimilarCarousel() {
        const section = this.container.querySelector('[data-similar-carousel]');
        if (!section) return;

        const carousel = section.querySelector('.similar-carousel');
        const grid = section.querySelector('.similar-grid');
        const leftArrow = section.querySelector('.carousel-arrow-left');
        const rightArrow = section.querySelector('.carousel-arrow-right');

        if (!carousel || !grid || !leftArrow || !rightArrow) return;

        this.carousel = initCarousel(carousel, {
            grid,
            leftArrow,
            rightArrow,
            scrollAmount: 420, // ~2 cards
        });
    }

    /**
     * Destroy the instance.
     */
    destroy() {
        if (this.radarChart) {
            this.radarChart.destroy();
            this.radarChart = null;
        }
        if (this.productAnalysis) {
            this.productAnalysis = null;
        }
        if (this.carousel) {
            this.carousel.destroy();
            this.carousel = null;
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

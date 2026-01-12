/**
 * Product Page Orchestrator
 *
 * Coordinates all functionality for single product pages:
 * - Loads product data (inline or from finder JSON) for performance profile
 * - Initializes RadarChart for performance profile
 * - Manages similar products carousel
 *
 * Note: Hero price is updated by price-intel.js (shares the same API call).
 * Note: Specs and similar products are rendered server-side by PHP.
 * Note: "What to Know" insights are currently hardcoded in PHP.
 *
 * @module components/product-page
 */

import { RadarChart } from './radar-chart.js';
import { initCarousel } from '../utils/carousel.js';

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
        this.carousel = null;

        this.init();
    }

    /**
     * Initialize all components.
     */
    init() {
        // Load product data for performance profile (synchronous - reads from inline data)
        this.loadProductData();

        if (!this.product) {
            console.warn('ProductPage: Could not load product data');
            // Performance profile will show empty state
        }

        // Initialize components that need product data
        this.initPerformanceProfile();
        this.initSimilarCarousel();
        // Note: Hero price is updated by price-intel.js to avoid duplicate API calls
    }

    /**
     * Load product data for performance profile.
     * Reads inline data from PHP (erhData.productData) - no async fetch needed.
     */
    loadProductData() {
        // Get from inline data (provided by PHP via wp_localize_script)
        if (window.erhData?.productData) {
            this.product = window.erhData.productData;
        }
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
     * Uses pre-calculated scores from inline product data (specs.scores).
     *
     * @returns {Object} Category scores keyed by category id
     */
    calculateCategoryScores() {
        if (!this.product?.specs?.scores) return {};

        const preCalculatedScores = this.product.specs.scores;

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

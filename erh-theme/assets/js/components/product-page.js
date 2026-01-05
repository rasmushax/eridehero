/**
 * Product Page Orchestrator
 *
 * Coordinates all functionality for single product pages:
 * - Loads product data from finder JSON (for performance profile)
 * - Initializes RadarChart for performance profile
 * - Displays "Great for..." highlights based on scores
 * - Handles specs collapse/expand
 * - Loads geo-aware prices for product cards
 *
 * Note: Specs are rendered server-side by PHP from wp_product_data cache.
 *
 * @module components/product-page
 */

import { RadarChart } from './radar-chart.js';
import { SPEC_GROUPS } from '../config/compare-config.js';
import { getUserGeo, formatPrice } from '../services/geo-price.js';

// =============================================================================
// Constants
// =============================================================================

/**
 * Maps high-scoring categories to consumer-friendly descriptions.
 * Used for "Great for..." highlights.
 */
const HIGHLIGHT_MAP = {
    motor_performance: {
        label: 'Speed enthusiasts',
        description: 'Excellent acceleration and top speed',
        icon: 'zap',
    },
    range_battery: {
        label: 'Long-range commuting',
        description: 'Go further on a single charge',
        icon: 'battery',
    },
    ride_quality: {
        label: 'Comfort seekers',
        description: 'Smooth ride with quality suspension',
        icon: 'smile',
    },
    portability: {
        label: 'Urban commuters',
        description: 'Lightweight and easy to carry',
        icon: 'box',
    },
    safety: {
        label: 'Safety-conscious riders',
        description: 'Quality brakes and lighting',
        icon: 'shield',
    },
    features: {
        label: 'Tech enthusiasts',
        description: 'Packed with modern features',
        icon: 'settings',
    },
    maintenance: {
        label: 'Low-maintenance riders',
        description: 'Reliable with minimal upkeep',
        icon: 'tool',
    },
};

/**
 * Score threshold for highlighting a category (out of 100).
 */
const HIGHLIGHT_THRESHOLD = 75;

// =============================================================================
// Main Class
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
        this.product = null;
        this.radarChart = null;

        this.init();
    }

    /**
     * Initialize all components.
     */
    async init() {
        // Initialize specs toggle immediately (PHP-rendered content is already there)
        this.initSpecsToggle();

        // Load product data for performance profile
        await this.loadProductData();

        if (!this.product) {
            console.warn('ProductPage: Could not load product data');
            // Performance profile will show empty state
        }

        // Initialize components that need product data
        this.initPerformanceProfile();
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
     * Initialize performance profile section (radar chart + highlights).
     */
    initPerformanceProfile() {
        const profileSection = this.container.querySelector('[data-performance-profile]');
        if (!profileSection) return;

        const radarContainer = profileSection.querySelector('[data-radar-chart]');
        const highlightsList = profileSection.querySelector('[data-highlights-list]');
        const scoreBarContainer = profileSection.querySelector('[data-score-bars]');

        // Calculate category scores
        const scores = this.calculateCategoryScores();

        if (!scores || Object.keys(scores).length === 0) {
            // Show empty state
            const loadingEl = profileSection.querySelector('[data-radar-loading]');
            if (loadingEl) loadingEl.style.display = 'none';

            const emptyEl = profileSection.querySelector('[data-highlights-empty]');
            if (emptyEl) {
                emptyEl.textContent = 'Performance data not available for this product.';
                emptyEl.style.display = 'block';
            }
            return;
        }

        // Render radar chart
        if (radarContainer) {
            this.renderRadarChart(radarContainer, scores);
        }

        // Render highlights
        if (highlightsList) {
            this.renderHighlights(highlightsList, scores);
        }

        // Render score bars
        if (scoreBarContainer) {
            this.renderScoreBars(scoreBarContainer, scores);
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
     * Render "Great for..." highlights.
     *
     * @param {HTMLElement} list - List container
     * @param {Object} scores - Category scores
     */
    renderHighlights(list, scores) {
        // Remove skeletons
        list.querySelectorAll('[data-highlight-skeleton]').forEach(el => el.remove());

        // Get high-scoring categories
        const highlights = Object.entries(scores)
            .filter(([, data]) => data.score >= HIGHLIGHT_THRESHOLD)
            .sort((a, b) => b[1].score - a[1].score)
            .slice(0, 4); // Show top 4

        if (highlights.length === 0) {
            // Show message if no highlights
            const emptyEl = list.closest('.performance-highlights')?.querySelector('[data-highlights-empty]');
            if (emptyEl) {
                emptyEl.textContent = 'This product performs consistently across all categories.';
                emptyEl.style.display = 'block';
            }
            return;
        }

        // Render highlights
        const html = highlights.map(([key, data]) => {
            const highlight = HIGHLIGHT_MAP[key] || {
                label: data.name,
                description: `Strong ${data.name.toLowerCase()} performance`,
                icon: 'check',
            };

            return `
                <li class="performance-highlight-item">
                    <svg class="icon" aria-hidden="true">
                        <use href="#icon-${highlight.icon}"></use>
                    </svg>
                    <span class="highlight-text">${this.escapeHtml(highlight.label)}</span>
                    <span class="highlight-score">${data.score}</span>
                </li>
            `;
        }).join('');

        list.innerHTML = html;
    }

    /**
     * Render score bars grid.
     *
     * @param {HTMLElement} container - Container element
     * @param {Object} scores - Category scores
     */
    renderScoreBars(container, scores) {
        const html = Object.entries(scores).map(([key, data]) => {
            const colorClass = data.score >= 80 ? 'success' : data.score >= 60 ? 'warning' : 'muted';

            return `
                <div class="performance-score-item">
                    <div class="performance-score-header">
                        <span class="performance-score-label">${this.escapeHtml(data.name)}</span>
                        <span class="performance-score-value">${data.score}</span>
                    </div>
                    <div class="performance-score-bar">
                        <div class="performance-score-fill" style="width: ${data.score}%; background: var(--color-${colorClass})"></div>
                    </div>
                </div>
            `;
        }).join('');

        container.innerHTML = html;

        // Animate bars in
        requestAnimationFrame(() => {
            container.querySelectorAll('.performance-score-fill').forEach(bar => {
                bar.style.width = bar.style.width; // Trigger reflow
            });
        });
    }

    /**
     * Initialize specs collapse/expand functionality.
     * Specs are rendered server-side by PHP, this just adds interactivity.
     */
    initSpecsToggle() {
        const specsSection = this.container.querySelector('[data-specs-grouped]');
        if (!specsSection) return;

        specsSection.querySelectorAll('[data-specs-toggle]').forEach(toggle => {
            toggle.addEventListener('click', () => {
                const category = toggle.closest('[data-specs-category]');
                if (category) {
                    const isOpen = category.classList.toggle('is-open');
                    toggle.setAttribute('aria-expanded', isOpen.toString());
                }
            });
        });
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

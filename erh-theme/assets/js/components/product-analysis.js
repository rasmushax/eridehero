/**
 * Product Analysis Component
 *
 * Fetches and displays strengths/weaknesses for a product
 * based on price bracket comparison (geo-dependent).
 *
 * @module components/product-analysis
 */

import { escapeHtml } from '../utils/dom.js';
import { getUserGeo } from '../services/geo-price.js';
import { initPopovers } from './popover.js';

// =============================================================================
// Configuration
// =============================================================================

/**
 * Tier labels based on percentile ranking.
 * Used to generate qualitative descriptions like "Excellent range".
 *
 * Percentile = percentage of products beaten (higher = better).
 * For strengths: 95%+ = Best (top 5%), 90%+ = Excellent (top 10%), 80%+ = Strong (top 20%)
 * For weaknesses: 5%- = Very low (bottom 5%), 10%- = Low, 20%- = Below average
 */
const STRENGTH_TIERS = [
    { minPercentile: 95, label: 'Best' },         // Top 5%
    { minPercentile: 90, label: 'Excellent' },    // Top 10%
    { minPercentile: 80, label: 'Strong' },       // Top 20%
    { minPercentile: 0, label: 'Good' },          // Triggered by pct_vs_avg
];

const WEAKNESS_TIERS = [
    { maxPercentile: 5, label: 'Very low' },      // Bottom 5%
    { maxPercentile: 10, label: 'Low' },          // Bottom 10%
    { maxPercentile: 20, label: 'Below average' }, // Bottom 20%
    { maxPercentile: 100, label: 'Weak' },        // Triggered by pct_vs_avg
];

/**
 * Get tooltip with fallback chain from centralized tooltips.
 *
 * Fallback chain: erhData.tooltips[key].comparison → .methodology
 *
 * @param {string} specKey - Spec key
 * @param {string} tier - Tooltip tier ('comparison', 'methodology')
 * @returns {string|null} Tooltip text
 */
function getTooltipFromCentralized(specKey, tier = 'comparison') {
    const tooltips = window.erhData?.tooltips;
    if (!tooltips) return null;

    // Normalize geo-specific keys: value_metrics.US.price_per_wh → value_metrics.price_per_wh
    const normalizedKey = specKey.replace(/\.(US|GB|EU|CA|AU)\./, '.');

    const tooltipData = tooltips[normalizedKey];
    if (!tooltipData) return null;

    // Apply fallback chain based on tier
    // comparison → methodology (comparison context falls back to full explanation)
    // methodology is the default (full explanation)
    switch (tier) {
        case 'comparison':
            return tooltipData.comparison || tooltipData.methodology || null;
        case 'methodology':
        default:
            return tooltipData.methodology || null;
    }
}

// =============================================================================
// Helper Functions
// =============================================================================

/**
 * Get tier label for a strength based on percentile.
 * Higher percentile = better (beats more products).
 *
 * @param {number} percentile - Product's percentile (0-100, higher is better)
 * @returns {string} Tier label
 */
function getStrengthTier(percentile) {
    for (const tier of STRENGTH_TIERS) {
        if (percentile >= tier.minPercentile) {
            return tier.label;
        }
    }
    return 'Good';
}

/**
 * Get tier label for a weakness based on percentile.
 * Lower percentile = worse (beats fewer products).
 *
 * @param {number} percentile - Product's percentile (0-100, lower is worse)
 * @returns {string} Tier label
 */
function getWeaknessTier(percentile) {
    for (const tier of WEAKNESS_TIERS) {
        if (percentile <= tier.maxPercentile) {
            return tier.label;
        }
    }
    return 'Weak';
}

/**
 * Get tooltip text for a metric.
 *
 * Uses centralized tooltips from erhData.tooltips with 'comparison' tier
 * (appropriate for product analysis context - explains why spec matters).
 *
 * @param {string} specKey - Spec key (e.g., 'tested_top_speed')
 * @returns {string} Tooltip text
 */
function getMetricTooltip(specKey) {
    // Use centralized tooltips with 'comparison' tier for analysis context
    const tooltip = getTooltipFromCentralized(specKey, 'comparison');
    if (tooltip) {
        return tooltip;
    }

    // Fallback for any specs not in centralized tooltips
    return 'Performance metric compared against similar scooters.';
}

/**
 * Get comparison text for display.
 * PHP generates this via the 'comparison' field - JS just displays it.
 *
 * @param {Object} item - Analysis item with comparison field from PHP
 * @returns {string} Comparison text
 */
function getComparisonText(item) {
    // PHP provides the formatted comparison text directly.
    return item.comparison || '';
}

/**
 * Render a single analysis item.
 *
 * Uses PHP-generated `text` field as single source of truth for labels.
 *
 * @param {Object} item - Analysis item from API
 * @param {string} type - 'strength' or 'weakness'
 * @returns {string} HTML string
 */
function renderAnalysisItem(item, type) {
    const isStrength = type === 'strength';
    const icon = isStrength ? 'check' : 'x';

    // Use PHP-generated fields as single source of truth.
    const label = item.text || formatFallbackLabel(item, type);
    const comparison = getComparisonText(item);
    const tooltip = item.tooltip || getMetricTooltip(item.spec_key);

    // Only show comparison line if there's content.
    const comparisonHtml = comparison
        ? `<div class="analysis-item-comparison"><span class="analysis-item-value">${escapeHtml(comparison)}</span></div>`
        : '';

    return `
        <li class="analysis-item">
            <div class="analysis-item-icon">
                <svg class="icon" aria-hidden="true">
                    <use href="#icon-${icon}"></use>
                </svg>
            </div>
            <div class="analysis-item-content">
                <div class="analysis-item-header">
                    <span class="analysis-item-label">${escapeHtml(label)}</span>
                    <button type="button" class="info-trigger" data-tooltip="${escapeHtml(tooltip)}" data-tooltip-trigger="click" aria-label="More info">
                        <svg class="icon" aria-hidden="true">
                            <use href="#icon-info"></use>
                        </svg>
                    </button>
                </div>
                ${comparisonHtml}
            </div>
        </li>
    `;
}

/**
 * Format fallback label when PHP text not provided.
 *
 * @param {Object} item - Analysis item
 * @param {string} type - 'strength' or 'weakness'
 * @returns {string} Fallback label
 */
function formatFallbackLabel(item, type) {
    const tierLabel = type === 'strength'
        ? getStrengthTier(item.percentile)
        : getWeaknessTier(item.percentile);
    return `${tierLabel} ${item.label.toLowerCase()}`;
}

/**
 * Format the bracket context text.
 *
 * @param {Object} priceContext - Price context from API
 * @returns {string} Context text
 */
function formatBracketContextText(priceContext) {
    const { bracket, products_in_bracket, comparison_mode } = priceContext;

    if (comparison_mode === 'category') {
        return 'Compared to all electric scooters';
    }

    const bracketLabel = bracket?.label || 'this price range';
    const bracketRange = bracket
        ? `$${bracket.min.toLocaleString()}–${bracket.max === 2147483647 ? '+' : '$' + bracket.max.toLocaleString()}`
        : '';

    return `Compared to ${products_in_bracket} scooters in the ${bracketLabel} bracket${bracketRange ? ` (${bracketRange})` : ''}`;
}

// =============================================================================
// Main Component Class
// =============================================================================

export class ProductAnalysis {
    /**
     * Create a ProductAnalysis instance.
     *
     * @param {HTMLElement} container - Container element with [data-product-analysis]
     * @param {Object} options - Configuration options
     * @param {number} options.productId - Product ID (only needed if fetching)
     * @param {string} options.category - Category key (e.g., 'escooter')
     * @param {Object} options.data - Pre-fetched analysis data (skips API call if provided)
     */
    constructor(container, options = {}) {
        this.container = container;
        this.productId = options.productId;
        this.category = options.category;

        this.skeletonEl = container.querySelector('[data-analysis-skeleton]');
        this.contentEl = container.querySelector('[data-analysis-content]');
        this.emptyEl = container.querySelector('[data-analysis-empty]');
        this.strengthsList = container.querySelector('[data-strengths-list]');
        this.weaknessesList = container.querySelector('[data-weaknesses-list]');
        this.contextTextEl = container.querySelector('[data-context-text]');

        // If data is provided, render immediately; otherwise fetch
        if (options.data) {
            this.renderWithData(options.data);
        } else {
            this.init();
        }
    }

    /**
     * Render with pre-fetched data (no API call).
     *
     * @param {Object} data - Analysis data
     */
    renderWithData(data) {
        if (data) {
            this.render(data);
        } else {
            this.showError();
        }
    }

    /**
     * Initialize the component (fetches data).
     * Only called if data wasn't provided in constructor.
     */
    async init() {
        try {
            const geoData = await getUserGeo();
            const geo = geoData.geo;
            const data = await this.fetchAnalysis(geo);

            if (data) {
                this.render(data);
            } else {
                this.showError();
            }
        } catch (error) {
            console.error('[ProductAnalysis] Failed to load:', error);
            this.showError();
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
     * Render the analysis content.
     *
     * @param {Object} data - Analysis data from API
     */
    render(data) {
        const { advantages = [], weaknesses = [], price_context = {} } = data;

        const hasAdvantages = advantages.length > 0;
        const hasWeaknesses = weaknesses.length > 0;

        // Get group containers
        const strengthsGroup = this.container.querySelector('.analysis-strengths');
        const weaknessesGroup = this.container.querySelector('.analysis-weaknesses');

        // Handle case where neither has items - show balanced message
        if (!hasAdvantages && !hasWeaknesses) {
            this.showBalanced(price_context);
            return;
        }

        // Render strengths or hide group
        if (hasAdvantages && this.strengthsList) {
            this.strengthsList.innerHTML = advantages
                .map(item => renderAnalysisItem(item, 'strength'))
                .join('');
            if (strengthsGroup) strengthsGroup.style.display = '';
        } else if (strengthsGroup) {
            strengthsGroup.style.display = 'none';
        }

        // Render weaknesses or hide group
        if (hasWeaknesses && this.weaknessesList) {
            this.weaknessesList.innerHTML = weaknesses
                .map(item => renderAnalysisItem(item, 'weakness'))
                .join('');
            if (weaknessesGroup) weaknessesGroup.style.display = '';
        } else if (weaknessesGroup) {
            weaknessesGroup.style.display = 'none';
        }

        // Update context text (popover is already in the HTML template)
        if (this.contextTextEl) {
            this.contextTextEl.textContent = formatBracketContextText(price_context);
        }

        // Show content, hide skeleton
        this.showContent();

        // Re-initialize popovers to pick up the new trigger
        // (The tooltip system uses event delegation, so it auto-handles new elements)
        initPopovers();
    }

    /**
     * Show the content, hide skeleton.
     */
    showContent() {
        if (this.skeletonEl) this.skeletonEl.style.display = 'none';
        if (this.contentEl) this.contentEl.style.display = '';
        if (this.emptyEl) this.emptyEl.style.display = 'none';
    }

    /**
     * Show balanced state (no significant strengths or weaknesses).
     *
     * @param {Object} priceContext - Price context from API
     */
    showBalanced(priceContext) {
        if (this.skeletonEl) this.skeletonEl.style.display = 'none';
        if (this.contentEl) this.contentEl.style.display = 'none';

        // Show empty element with balanced message
        if (this.emptyEl) {
            const bracketLabel = priceContext?.bracket?.label || 'its price range';
            const messageEl = this.emptyEl.querySelector('.analysis-empty-message');
            if (messageEl) {
                messageEl.textContent = `This scooter performs close to average for the ${bracketLabel} bracket — no significant strengths or weaknesses identified.`;
            }
            this.emptyEl.style.display = '';
        }
    }

    /**
     * Show error state (API failure).
     */
    showError() {
        if (this.skeletonEl) this.skeletonEl.style.display = 'none';
        if (this.contentEl) this.contentEl.style.display = 'none';
        if (this.emptyEl) {
            const messageEl = this.emptyEl.querySelector('.analysis-empty-message');
            if (messageEl) {
                messageEl.textContent = 'Unable to load comparison data.';
            }
            this.emptyEl.style.display = '';
        }
    }
}

/**
 * Initialize product analysis on a page.
 *
 * @param {number} productId - Product ID
 * @param {string} category - Category key
 */
export function initProductAnalysis(productId, category) {
    const container = document.querySelector('[data-product-analysis]');
    if (!container) return null;

    return new ProductAnalysis(container, { productId, category });
}

export default ProductAnalysis;

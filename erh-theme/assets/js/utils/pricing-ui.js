/**
 * Pricing UI Utilities
 *
 * Shared rendering functions for retailer lists, price verdicts, stock status, etc.
 * Used by: price-intel.js, sticky-buy-bar.js, listicle-item.js, deals-utils.js
 */

import { formatPrice } from '../services/geo-price.js';

/**
 * Default verdict threshold configuration.
 * Prices within this percentage of average show no verdict.
 */
const DEFAULT_THRESHOLD = 3;

/**
 * Calculate price verdict (% vs average).
 *
 * @param {number} currentPrice - Current/best price
 * @param {number} avgPrice - Average price to compare against
 * @param {Object} [options] - Configuration options
 * @param {number} [options.threshold=3] - % threshold for neutral zone (no verdict)
 * @returns {Object|null} Verdict object or null if no average price
 */
export function calculatePriceVerdict(currentPrice, avgPrice, options = {}) {
    const { threshold = DEFAULT_THRESHOLD } = options;

    if (!avgPrice || avgPrice <= 0 || !currentPrice) {
        return null;
    }

    const diff = ((currentPrice - avgPrice) / avgPrice) * 100;

    // Within threshold = neutral (no verdict shown)
    if (Math.abs(diff) <= threshold) {
        return {
            percent: 0,
            type: 'neutral',
            text: '',
            shouldShow: false
        };
    }

    if (diff < 0) {
        // Below average (good deal)
        return {
            percent: Math.abs(Math.round(diff)),
            type: 'below',
            text: `${Math.abs(Math.round(diff))}% below avg`,
            shouldShow: true
        };
    }

    // Above average
    return {
        percent: Math.round(diff),
        type: 'above',
        text: `${Math.round(diff)}% above avg`,
        shouldShow: true
    };
}

/**
 * Render a single retailer row HTML.
 *
 * @param {Object} offer - Offer data from API
 * @param {Object} [options] - Configuration options
 * @param {boolean} [options.isBest=false] - Whether this is the best price
 * @param {string} [options.currency='USD'] - Currency code for formatting
 * @param {string} [options.classPrefix='price-intel'] - CSS class prefix
 * @returns {string} HTML string
 */
export function renderRetailerRow(offer, options = {}) {
    const {
        isBest = false,
        currency = 'USD',
        classPrefix = 'price-intel'
    } = options;

    const bestClass = isBest ? `${classPrefix}-retailer-row--best` : '';
    const stockClass = offer.in_stock ? `${classPrefix}-retailer-stock--in` : `${classPrefix}-retailer-stock--out`;
    const stockIcon = offer.in_stock ? 'check' : 'x';
    const stockText = offer.in_stock ? 'In stock' : 'Out of stock';

    const logoHtml = offer.logo_url
        ? `<img src="${offer.logo_url}" alt="${offer.retailer}" class="${classPrefix}-retailer-logo">`
        : `<span class="${classPrefix}-retailer-logo-text">${offer.retailer}</span>`;

    const badgeHtml = isBest
        ? `<span class="${classPrefix}-retailer-badge">Best price</span>`
        : '';

    return `
        <a href="${offer.tracked_url || offer.url || '#'}" class="${classPrefix}-retailer-row ${bestClass}" target="_blank" rel="nofollow noopener">
            ${logoHtml}
            <div class="${classPrefix}-retailer-info">
                <span class="${classPrefix}-retailer-name">${offer.retailer}</span>
                <span class="${classPrefix}-retailer-stock ${stockClass}">
                    <svg class="icon" aria-hidden="true"><use href="#icon-${stockIcon}"></use></svg>
                    ${stockText}
                </span>
            </div>
            <div class="${classPrefix}-retailer-price">
                <span class="${classPrefix}-retailer-amount">${formatPrice(offer.price, currency)}</span>
                ${badgeHtml}
            </div>
            <svg class="icon ${classPrefix}-retailer-arrow" aria-hidden="true"><use href="#icon-external-link"></use></svg>
        </a>
    `;
}

/**
 * Render price verdict badge HTML.
 *
 * @param {Object} verdict - Verdict from calculatePriceVerdict()
 * @param {Object} [options] - Configuration options
 * @param {string} [options.classPrefix='price-intel'] - CSS class prefix
 * @returns {string} HTML string (empty if neutral/null)
 */
export function renderVerdictBadge(verdict, options = {}) {
    if (!verdict || !verdict.shouldShow) {
        return '';
    }

    const { classPrefix = 'price-intel' } = options;
    const icon = verdict.type === 'below' ? 'arrow-down' : 'arrow-up';
    const typeClass = verdict.type === 'below' ? 'low' : 'high';

    return `
        <span class="${classPrefix}-verdict ${classPrefix}-verdict--${typeClass}">
            <svg class="icon ${classPrefix}-verdict-icon" aria-hidden="true"><use href="#icon-${icon}"></use></svg>
            ${verdict.text}
        </span>
    `;
}

/**
 * Render stock status HTML.
 *
 * @param {boolean} inStock - Whether item is in stock
 * @param {Object} [options] - Configuration options
 * @param {string} [options.classPrefix='price-intel'] - CSS class prefix
 * @returns {string} HTML string
 */
export function renderStockStatus(inStock, options = {}) {
    const { classPrefix = 'price-intel' } = options;
    const stockClass = inStock ? `${classPrefix}-stock--in` : `${classPrefix}-stock--out`;
    const stockIcon = inStock ? 'check' : 'x';
    const stockText = inStock ? 'In stock' : 'Out of stock';

    return `
        <span class="${classPrefix}-stock ${stockClass}">
            <svg class="icon" aria-hidden="true"><use href="#icon-${stockIcon}"></use></svg>
            ${stockText}
        </span>
    `;
}

/**
 * Render retailer logo or text fallback.
 *
 * @param {Object} offer - Offer with logo_url and retailer name
 * @param {Object} [options] - Configuration options
 * @param {string} [options.classPrefix='price-intel'] - CSS class prefix
 * @returns {string} HTML string
 */
export function renderRetailerLogo(offer, options = {}) {
    const { classPrefix = 'price-intel' } = options;

    if (offer.logo_url) {
        return `<img src="${offer.logo_url}" alt="${offer.retailer}" class="${classPrefix}-retailer-logo">`;
    }
    return `<span class="${classPrefix}-retailer-logo-text">${offer.retailer}</span>`;
}

/**
 * Calculate discount indicator for deal cards.
 * Similar to verdict but uses discount_percent from API directly.
 *
 * @param {number} discountPercent - Discount percentage from API (positive = below avg)
 * @returns {Object|null} Indicator object or null if no discount
 */
export function calculateDiscountIndicator(discountPercent) {
    const absPercent = Math.abs(Math.round(discountPercent || 0));

    if (absPercent === 0) {
        return null;
    }

    // Positive discount_percent = below average (good deal)
    const isBelow = discountPercent > 0;

    return {
        percent: absPercent,
        type: isBelow ? 'below' : 'above',
        text: `${absPercent}%`,
        icon: isBelow ? 'arrow-down' : 'arrow-up'
    };
}

export default {
    calculatePriceVerdict,
    renderRetailerRow,
    renderVerdictBadge,
    renderStockStatus,
    renderRetailerLogo,
    calculateDiscountIndicator
};

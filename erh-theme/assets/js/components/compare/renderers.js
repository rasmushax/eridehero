/**
 * Compare Renderers - Shared Micro-Functions for Compare UI
 *
 * These functions mirror the PHP equivalents in template-parts/compare/components/
 * to ensure consistent rendering between SSR and client-side hydration.
 *
 * SYNC: Must match PHP components:
 * - score-ring.php      -> renderScoreRing()
 * - winner-badge.php    -> renderWinnerBadge(), renderFeatureBadge()
 * - spec-value.php      -> renderSpecValue(), renderSpecCell(), formatSpecValue()
 * - product-thumb.php   -> renderProductThumb(), renderMobileSpecValue()
 *
 * @module components/compare/renderers
 */

import { formatSpecValue as formatValue, compareValues } from '../../config/compare-config.js';

// =============================================================================
// Utility Functions
// =============================================================================

/**
 * Escape HTML entities for safe insertion.
 * @param {string} str - Raw string
 * @returns {string} Escaped string
 */
export function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/**
 * Render an SVG icon from sprite.
 * @param {string} name - Icon name (without 'icon-' prefix)
 * @param {number} [width=16] - Icon width
 * @param {number} [height=16] - Icon height
 * @returns {string} SVG HTML
 */
export function renderIcon(name, width = 16, height = 16) {
    return `<svg class="icon" width="${width}" height="${height}" aria-hidden="true"><use href="#icon-${name}"></use></svg>`;
}

// =============================================================================
// Score Ring (matches score-ring.php)
// =============================================================================

/**
 * Render a circular score ring.
 *
 * @param {number} score - Score value (0-100)
 * @param {string} [size='md'] - Size variant: 'sm' (mini-header), 'md' (header cards)
 * @param {string} [extraClass=''] - Additional CSS classes
 * @returns {string} HTML output
 */
export function renderScoreRing(score, size = 'md', extraClass = '') {
    if (score === null || score === undefined || score === '') {
        return '';
    }

    // Constants matching PHP
    const radius = 15;
    const viewbox = 36;
    const center = 18;
    const circumference = 2 * Math.PI * radius;

    // Clamp score to 0-100
    const scorePercent = Math.min(100, Math.max(0, parseFloat(score)));
    const offset = circumference - (scorePercent / 100) * circumference;
    const roundedScore = Math.round(score);

    // Size-specific class prefix
    const prefix = size === 'sm' ? 'compare-mini-score' : 'compare-product-score';
    const extra = extraClass ? ' ' + extraClass : '';

    return `
        <div class="${prefix}${extra}" title="${roundedScore} points">
            <svg class="${prefix}-ring" viewBox="0 0 ${viewbox} ${viewbox}">
                <circle class="${prefix}-track" cx="${center}" cy="${center}" r="${radius}" />
                <circle class="${prefix}-progress" cx="${center}" cy="${center}" r="${radius}"
                        style="stroke-dasharray: ${circumference}; stroke-dashoffset: ${offset};" />
            </svg>
            <span class="${prefix}-value">${roundedScore}</span>
        </div>
    `;
}

// =============================================================================
// Winner Badge (matches winner-badge.php)
// =============================================================================

/**
 * Render a winner badge (check icon in colored circle).
 *
 * @param {string} [className='compare-spec-badge'] - CSS class for the badge
 * @returns {string} HTML output
 */
export function renderWinnerBadge(className = 'compare-spec-badge') {
    return `<span class="${className}">${renderIcon('check')}</span>`;
}

/**
 * Render a feature badge (yes/no indicator).
 *
 * @param {boolean} isYes - Whether the feature is present/true
 * @param {string} [extraClass=''] - Additional CSS classes
 * @returns {string} HTML output
 */
export function renderFeatureBadge(isYes, extraClass = '') {
    const icon = isYes ? 'check' : 'x';
    const baseClass = isYes ? 'compare-feature-badge feature-yes' : 'compare-feature-badge feature-no';
    const fullClass = extraClass ? `${baseClass} ${extraClass}` : baseClass;

    return `<span class="${fullClass}">${renderIcon(icon)}</span>`;
}

// =============================================================================
// Spec Value (matches spec-value.php)
// =============================================================================

/**
 * Format a spec value for display.
 * Re-exports from compare-config.js for convenience.
 */
export const formatSpecValue = formatValue;

/**
 * Render a spec value for comparison table cells.
 *
 * @param {*} value - Raw spec value
 * @param {Object} spec - Spec configuration
 * @param {boolean} [isWinner=false] - Whether this value is the winner
 * @returns {string} HTML output for cell content (not the <td> wrapper)
 */
export function renderSpecValue(value, spec, isWinner = false) {
    // Handle missing values
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const format = spec.format || '';

    // Boolean formatting
    if (format === 'boolean') {
        return renderSpecValueBoolean(value);
    }

    // Format the display value
    const formatted = formatSpecValue(value, spec);

    // Winner cell with badge
    if (isWinner) {
        return `
            <div class="compare-spec-value-inner">
                ${renderWinnerBadge('compare-spec-badge')}
                <span class="compare-spec-value-text">${escapeHtml(formatted)}</span>
            </div>
        `;
    }

    return escapeHtml(formatted);
}

/**
 * Render a boolean spec value with feature badge.
 *
 * @param {*} value - Value to evaluate as boolean
 * @returns {string} HTML output
 */
export function renderSpecValueBoolean(value) {
    const isYes = value === true || value === 'Yes' || value === 'yes' || value === '1' || value === 1;
    const statusClass = isYes ? 'feature-yes' : 'feature-no';
    const icon = isYes ? 'check' : 'x';
    const text = isYes ? 'Yes' : 'No';

    return `
        <div class="compare-spec-value-inner ${statusClass}">
            <span class="compare-feature-badge">${renderIcon(icon)}</span>
            <span class="compare-feature-text">${text}</span>
        </div>
    `;
}

/**
 * Render a complete table cell (<td>) with spec value.
 *
 * @param {*} value - Raw spec value
 * @param {Object} spec - Spec configuration
 * @param {boolean} [isWinner=false] - Whether this value is the winner
 * @returns {string} HTML output for <td> element
 */
export function renderSpecCell(value, spec, isWinner = false) {
    const format = spec.format || '';

    // Boolean cells get special class
    if (format === 'boolean') {
        const isYes = value === true || value === 'Yes' || value === 'yes' || value === '1' || value === 1;
        const cellClass = isYes ? 'feature-yes' : 'feature-no';
        return `<td class="${cellClass}">${renderSpecValue(value, spec, false)}</td>`;
    }

    // Winner cells
    if (isWinner) {
        return `<td class="is-winner">${renderSpecValue(value, spec, true)}</td>`;
    }

    // Standard cells
    return `<td>${renderSpecValue(value, spec, false)}</td>`;
}

// =============================================================================
// Product Thumbnail (matches product-thumb.php)
// =============================================================================

/**
 * Render a product thumbnail with name (for mobile spec cards).
 *
 * @param {Object} product - Product data with thumbnail and name
 * @param {string} [className='compare-spec-card-product'] - CSS class
 * @returns {string} HTML output
 */
export function renderProductThumb(product, className = 'compare-spec-card-product') {
    const thumbnail = product.thumbnail || '';
    const name = product.name || '';

    const img = thumbnail ? `<img src="${thumbnail}" alt="">` : '';

    return `<span class="${className}">${img}${escapeHtml(name)}</span>`;
}

/**
 * Render a mobile spec card value (product thumb + data value).
 *
 * @param {Object} product - Product data
 * @param {*} value - Spec value
 * @param {Object} spec - Spec configuration
 * @param {boolean} [isWinner=false] - Whether this value is the winner
 * @returns {string} HTML output
 */
export function renderMobileSpecValue(product, value, spec, isWinner = false) {
    const format = spec.format || '';

    // Handle boolean values specially
    if (format === 'boolean') {
        return renderMobileBooleanValue(product, value);
    }

    const formatted = formatSpecValue(value, spec);

    if (isWinner) {
        return `
            <div class="compare-spec-card-value is-winner">
                ${renderProductThumb(product)}
                <span class="compare-spec-card-data">
                    <span class="compare-spec-badge">${renderIcon('check')}</span>
                    <span class="compare-spec-card-text">${escapeHtml(formatted)}</span>
                </span>
            </div>
        `;
    }

    return `
        <div class="compare-spec-card-value">
            ${renderProductThumb(product)}
            <span class="compare-spec-card-data">
                <span class="compare-spec-card-text">${escapeHtml(formatted)}</span>
            </span>
        </div>
    `;
}

/**
 * Render a mobile boolean value with feature badge.
 *
 * @param {Object} product - Product data
 * @param {*} value - Boolean value
 * @returns {string} HTML output
 */
export function renderMobileBooleanValue(product, value) {
    const isYes = value === true || value === 'Yes' || value === 'yes' || value === 1;
    const hasValue = value !== null && value !== '' && value !== undefined;
    const statusClass = hasValue ? (isYes ? 'feature-yes' : 'feature-no') : '';
    const icon = isYes ? 'check' : 'x';
    const text = hasValue ? (isYes ? 'Yes' : 'No') : '—';

    const badgeHtml = hasValue
        ? `<span class="compare-feature-badge">${renderIcon(icon)}</span>`
        : '';

    return `
        <div class="compare-spec-card-value ${statusClass}">
            ${renderProductThumb(product)}
            <span class="compare-spec-card-data">${badgeHtml}${text}</span>
        </div>
    `;
}

// =============================================================================
// Winner Detection (shared logic)
// =============================================================================

/**
 * Find winner indices for a set of values.
 *
 * @param {Array} values - Array of values to compare
 * @param {Object} spec - Spec configuration
 * @returns {Array<number>} Indices of winning values (can be multiple for ties)
 */
export function findWinners(values, spec) {
    const validIndices = values
        .map((v, i) => ({ value: v, index: i }))
        .filter(({ value }) => value !== null && value !== undefined && value !== '');

    if (validIndices.length < 2) return [];

    // Compare all pairs to find the best
    let bestIndices = [validIndices[0].index];
    let bestValue = validIndices[0].value;

    for (let i = 1; i < validIndices.length; i++) {
        const { value, index } = validIndices[i];
        const result = compareValues(bestValue, value, spec);

        if (result > 0) {
            // Current best loses to this value
            bestIndices = [index];
            bestValue = value;
        } else if (result === 0) {
            // Tie
            bestIndices.push(index);
        }
        // result < 0: current best wins, do nothing
    }

    // Only return winners if there's a clear winner (not everyone tied)
    if (bestIndices.length === validIndices.length) {
        return []; // Everyone tied, no winner
    }

    return bestIndices;
}

// =============================================================================
// Default Export
// =============================================================================

export default {
    escapeHtml,
    renderIcon,
    renderScoreRing,
    renderWinnerBadge,
    renderFeatureBadge,
    formatSpecValue,
    renderSpecValue,
    renderSpecValueBoolean,
    renderSpecCell,
    renderProductThumb,
    renderMobileSpecValue,
    renderMobileBooleanValue,
    findWinners,
};

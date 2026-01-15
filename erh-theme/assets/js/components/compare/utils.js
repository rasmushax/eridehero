/**
 * Compare Results - Utility Functions
 *
 * Helper functions for the compare module.
 *
 * @module components/compare/utils
 */

import { compareValues } from '../../config/compare-config.js';
import { SELECTORS } from './constants.js';

// =============================================================================
// Spec Value Utilities
// =============================================================================

/**
 * Get nested spec value using dot notation.
 *
 * @param {Object} product - Product data object.
 * @param {string} key - Dot-notation key (e.g., 'motor.power_nominal').
 * @returns {*} Spec value or null.
 */
export function getSpec(product, key) {
    const specs = product.specs || product;
    return key.split('.').reduce((obj, k) => obj?.[k], specs) ?? null;
}

/**
 * Find winner indices for a spec value comparison.
 *
 * @param {Array} values - Array of values from each product.
 * @param {Object} spec - Spec configuration object.
 * @returns {number[]} Indices of winning products.
 */
export function findWinners(values, spec) {
    // Specs marked noWinner should not have winner highlighting
    if (spec.noWinner) return [];

    const valid = values.map((v, i) => ({ v, i })).filter(x => x.v != null && x.v !== '');
    if (valid.length < 2) return [];

    let best = valid[0];
    for (const item of valid.slice(1)) {
        if (compareValues(item.v, best.v, spec) < 0) best = item;
    }

    const winners = valid.filter(x => compareValues(x.v, best.v, spec) === 0).map(x => x.i);
    return winners.length === valid.length ? [] : winners;
}

// =============================================================================
// URL Utilities
// =============================================================================

/**
 * Build compare URL from product IDs.
 *
 * @param {number[]} ids - Array of product IDs.
 * @param {Array} allProducts - Full product list for slug lookup.
 * @returns {string} Compare URL.
 */
export function buildCompareUrl(ids, allProducts) {
    const base = window.erhData?.siteUrl || '';
    if (ids.length <= 4) {
        const slugs = ids.map(id => {
            const p = allProducts.find(x => x.id === id);
            if (p?.slug) return p.slug;
            const match = p?.url?.match(/\/([^/]+)\/?$/);
            return match?.[1] || null;
        }).filter(Boolean);

        if (slugs.length === ids.length) {
            return `${base}/compare/${slugs.join('-vs-')}/`;
        }
    }
    return `${base}/compare/?products=${ids.join(',')}`;
}

// =============================================================================
// Modal Utilities
// =============================================================================

/**
 * Close the add product modal.
 */
export function closeModal() {
    const modal = document.querySelector(SELECTORS.addModal);
    modal?.querySelector('[data-modal-close]')?.click();
}

// =============================================================================
// Error Display
// =============================================================================

/**
 * Show an error message in the overview container.
 *
 * @param {string} msg - Error message to display.
 */
export function showError(msg) {
    const container = document.querySelector(SELECTORS.overview);
    if (container) {
        container.innerHTML = `<div class="compare-error"><p>${msg}</p></div>`;
    }
}

// =============================================================================
// Timing Utilities
// =============================================================================

/**
 * Throttle a function.
 *
 * @param {Function} fn - Function to throttle.
 * @param {number} wait - Minimum time between calls in ms.
 * @returns {Function} Throttled function.
 */
export function throttle(fn, wait) {
    let last = 0;
    return function(...args) {
        const now = Date.now();
        if (now - last >= wait) {
            last = now;
            fn.apply(this, args);
        }
    };
}

/**
 * Debounce a function.
 *
 * @param {Function} fn - Function to debounce.
 * @param {number} wait - Delay in ms.
 * @returns {Function} Debounced function.
 */
export function debounce(fn, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn.apply(this, args), wait);
    };
}

// =============================================================================
// View Tracking
// =============================================================================

/**
 * Track comparison view via REST API.
 * Uses sessionStorage to deduplicate - only tracks once per session per unique pair.
 *
 * @param {number[]} productIds - Array of product IDs being compared.
 */
export async function trackComparisonView(productIds) {
    if (!productIds || productIds.length < 2) return;

    // Generate all unique pairs from the product IDs.
    const pairs = generatePairs(productIds);
    if (!pairs.length) return;

    // Filter to pairs not yet tracked this session.
    const untracked = pairs.filter(pair => !isTrackedThisSession(pair));
    if (!untracked.length) return;

    try {
        const restUrl = window.erhData?.restUrl || '/wp-json/erh/v1/';
        const nonce = window.erhData?.nonce || '';

        await fetch(`${restUrl}compare/track`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
            },
            body: JSON.stringify({ product_ids: productIds }),
        });

        // Mark all pairs as tracked for this session.
        untracked.forEach(pair => markTrackedThisSession(pair));
    } catch (e) {
        // Silent fail - tracking is non-critical.
        console.debug('Comparison tracking failed:', e);
    }
}

/**
 * Generate all unique pairs from an array of product IDs.
 * Each pair is normalized with lower ID first.
 *
 * @param {number[]} ids - Array of product IDs.
 * @returns {string[]} Array of pair keys like "123-456".
 */
function generatePairs(ids) {
    const pairs = [];
    for (let i = 0; i < ids.length; i++) {
        for (let j = i + 1; j < ids.length; j++) {
            // Canonical order: lower ID first.
            const a = Math.min(ids[i], ids[j]);
            const b = Math.max(ids[i], ids[j]);
            pairs.push(`${a}-${b}`);
        }
    }
    return pairs;
}

/**
 * Check if a pair has been tracked this session.
 *
 * @param {string} pairKey - Pair key like "123-456".
 * @returns {boolean} True if already tracked.
 */
function isTrackedThisSession(pairKey) {
    try {
        return sessionStorage.getItem(`erh_compared_${pairKey}`) === '1';
    } catch {
        // sessionStorage may be unavailable (private mode, etc.).
        return false;
    }
}

/**
 * Mark a pair as tracked for this session.
 *
 * @param {string} pairKey - Pair key like "123-456".
 */
function markTrackedThisSession(pairKey) {
    try {
        sessionStorage.setItem(`erh_compared_${pairKey}`, '1');
    } catch {
        // Silent fail if sessionStorage unavailable.
    }
}

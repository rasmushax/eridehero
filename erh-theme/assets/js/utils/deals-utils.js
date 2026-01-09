/**
 * Shared Deals Utilities
 *
 * Common functions used by both homepage deals section and deals hub page.
 */

import { formatPrice } from '../services/geo-price.js';
import { PriceAlertModal } from '../components/price-alert.js';
import { calculateDiscountIndicator } from './pricing-ui.js';

/**
 * Get REST URL base from WordPress localized data
 */
export function getRestUrl() {
    if (typeof erhData !== 'undefined' && erhData.restUrl) {
        return erhData.restUrl;
    }
    return '/wp-json/erh/v1/';
}

/**
 * Create a deal card from a template
 *
 * @param {HTMLTemplateElement} template - The card template element
 * @param {Object} deal - Deal data from API
 * @param {Object} userGeo - User's geo data { geo, currency }
 * @param {Object} options - Optional configuration
 * @returns {DocumentFragment} Cloned and populated card
 */
export function createDealCard(template, deal, userGeo, options = {}) {
    const {
        placeholderImage = '/wp-content/themes/erh-theme/assets/images/placeholder.svg',
    } = options;

    const clone = template.content.cloneNode(true);
    const card = clone.querySelector('.deal-card');
    const trackBtn = clone.querySelector('[data-track-price]');
    const img = clone.querySelector('.deal-card-image img');
    const title = clone.querySelector('.deal-card-title');
    const priceEl = clone.querySelector('[data-price]') || clone.querySelector('.deal-card-price');
    const indicator = clone.querySelector('[data-indicator]') || clone.querySelector('.deal-card-indicator');
    const indicatorValue = clone.querySelector('[data-indicator-value]');

    // Set link and category
    if (card) {
        card.href = deal.url || '#';
        card.dataset.category = deal.category || '';
    }

    // Track button data
    if (trackBtn) {
        trackBtn.dataset.trackPrice = deal.id;
        trackBtn.dataset.productName = deal.name || '';
        trackBtn.dataset.productImage = deal.thumbnail || '';
        trackBtn.dataset.currentPrice = deal.current_price || '';
        trackBtn.dataset.currency = deal.currency || userGeo.currency;
    }

    // Image
    if (img) {
        img.src = deal.thumbnail || '';
        img.alt = deal.name || '';
        img.onerror = () => {
            img.src = placeholderImage;
        };
    }

    // Title
    if (title) {
        title.textContent = deal.name || '';
    }

    // Price
    if (priceEl) {
        if (deal.current_price > 0) {
            priceEl.textContent = formatPrice(deal.current_price, deal.currency || userGeo.currency);
        } else {
            priceEl.textContent = '';
            priceEl.classList.add('deal-card-no-price');
        }
    }

    // Discount indicator using shared utility
    if (indicator && indicatorValue) {
        const discountInfo = calculateDiscountIndicator(deal.discount_percent);

        if (discountInfo) {
            indicatorValue.textContent = discountInfo.text;
            const iconUse = indicator.querySelector('use');
            indicator.classList.remove('deal-card-indicator--above', 'deal-card-indicator--below');
            indicator.classList.add(`deal-card-indicator--${discountInfo.type}`);
            if (iconUse) iconUse.setAttribute('href', `#icon-${discountInfo.icon}`);
        } else {
            indicator.style.display = 'none';
        }
    } else if (indicator) {
        indicator.style.display = 'none';
    }

    return clone;
}

/**
 * Handle track price button click (for event delegation)
 *
 * @param {Event} e - Click event
 * @param {Object} userGeo - User's geo data { geo, currency }
 * @returns {boolean} True if handled, false if not a track button
 */
export function handleTrackClick(e, userGeo) {
    const trackBtn = e.target.closest('[data-track-price]');
    if (!trackBtn) return false;

    e.preventDefault();
    e.stopPropagation();

    PriceAlertModal.open({
        productId: parseInt(trackBtn.dataset.trackPrice, 10),
        productName: trackBtn.dataset.productName || '',
        productImage: trackBtn.dataset.productImage || '',
        currentPrice: parseFloat(trackBtn.dataset.currentPrice) || 0,
        currency: trackBtn.dataset.currency || userGeo.currency,
    });

    return true;
}

/**
 * Fetch deals from API
 *
 * @param {Object} params - Query parameters
 * @param {string} params.category - Category filter ('all' or specific)
 * @param {number} params.limit - Max deals to fetch
 * @param {number} params.threshold - Discount threshold
 * @param {string} params.geo - User's geo code
 * @returns {Promise<{deals: Array, counts: Object}>}
 */
export async function fetchDeals({ category = 'all', limit = 12, threshold = -5, geo = 'US' }) {
    const url = `${getRestUrl()}deals?category=${category}&limit=${limit}&threshold=${threshold}&geo=${geo}`;
    const response = await fetch(url);

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();
    return {
        deals: data.deals || [],
        counts: data.counts || {},
    };
}

/**
 * Group deals by category
 *
 * @param {Array} deals - Array of deal objects
 * @returns {Object} Deals grouped by category
 */
export function groupByCategory(deals) {
    const grouped = {};
    deals.forEach(deal => {
        const cat = deal.category || 'other';
        if (!grouped[cat]) grouped[cat] = [];
        grouped[cat].push(deal);
    });
    return grouped;
}

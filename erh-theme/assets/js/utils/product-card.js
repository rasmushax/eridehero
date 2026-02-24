/**
 * Product Card Utilities
 *
 * Shared utilities for rendering product cards across components.
 * Used by: deals, similar products, finder, etc.
 *
 * @module utils/product-card
 */

import { escapeHtml } from './dom.js';
import { formatPrice, getCurrencySymbol } from '../services/geo-price.js';

// Re-export for backwards compatibility
export { formatPrice, getCurrencySymbol };

/**
 * Split price into whole and decimal parts.
 *
 * @param {number} price - Price value
 * @returns {{ whole: string, decimal: string }} Price parts
 */
export function splitPrice(price) {
    const whole = Math.floor(price);
    const decimal = Math.round((price - whole) * 100);
    return {
        whole: whole.toLocaleString('en-US'),
        decimal: decimal.toString().padStart(2, '0'),
    };
}

/**
 * Create a product card element.
 *
 * @param {Object} product - Product data
 * @param {Object} options - Card options
 * @param {HTMLTemplateElement} options.template - Card template element
 * @param {string} [options.variant='default'] - Card variant (default, deal, compact)
 * @param {Function} [options.onTrackClick] - Callback for track button click
 * @returns {DocumentFragment} Cloned and populated card
 */
export function createProductCard(product, options = {}) {
    const { template, variant = 'default', onTrackClick } = options;

    if (!template) {
        console.error('[ProductCard] No template provided');
        return null;
    }

    const clone = template.content.cloneNode(true);
    const card = clone.querySelector('a, .product-card, .deal-card, .similar-card');

    if (!card) {
        console.error('[ProductCard] No card element found in template');
        return null;
    }

    // Set common attributes.
    if (product.url) {
        card.href = product.url;
    }
    if (product.category) {
        card.dataset.category = product.category;
    }
    if (product.id) {
        card.dataset.productId = product.id;
    }

    // Set image.
    const img = clone.querySelector('img');
    if (img && product.thumbnail) {
        img.src = product.thumbnail;
        img.alt = product.name || '';
        img.onerror = () => {
            img.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="80" height="80"%3E%3Crect fill="%23f0f0f0" width="80" height="80"/%3E%3C/svg%3E';
        };
    }

    // Set title/name.
    const titleEl = clone.querySelector('.deal-card-title, .similar-card-name, [data-card-name]');
    if (titleEl) {
        titleEl.textContent = product.name || '';
    }

    // Set specs line (for similar products).
    const specsEl = clone.querySelector('.similar-card-specs, [data-card-specs]');
    if (specsEl && product.specs_line) {
        specsEl.textContent = product.specs_line;
    }

    // Set price.
    const priceValue = clone.querySelector('.deal-price-value, .similar-card-price, [data-card-price]');
    const priceCurrency = clone.querySelector('.deal-price-currency, [data-card-currency]');

    if (priceValue && product.price > 0) {
        const symbol = getCurrencySymbol(product.currency || 'USD');
        const formatted = product.price.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        // Check if price element expects split format or full format.
        if (priceCurrency) {
            priceCurrency.textContent = symbol;
            priceValue.textContent = formatted;
        } else {
            priceValue.textContent = `${symbol}${formatted}`;
        }
    }

    // Set price indicator (% vs average).
    const indicatorEl = clone.querySelector('.similar-card-indicator, .deal-trend, [data-card-indicator]');
    if (indicatorEl && product.price_indicator !== null && product.price_indicator !== undefined) {
        const indicator = product.price_indicator;

        if (indicator < -5) {
            // Below average - good deal.
            indicatorEl.classList.add('similar-card-indicator--below');
            indicatorEl.innerHTML = `
                <svg class="icon" aria-hidden="true"><use href="#icon-arrow-down"></use></svg>
                ${Math.abs(indicator)}%
            `;
            indicatorEl.style.display = '';
        } else if (indicator > 10) {
            // Above average.
            indicatorEl.classList.add('similar-card-indicator--above');
            indicatorEl.innerHTML = `
                <svg class="icon" aria-hidden="true"><use href="#icon-arrow-up"></use></svg>
                ${indicator}%
            `;
            indicatorEl.style.display = '';
        } else {
            // Near average - hide indicator.
            indicatorEl.style.display = 'none';
        }
    }

    // Set discount text (for deals).
    const discountEl = clone.querySelector('.deal-discount-text, [data-card-discount]');
    if (discountEl && product.discount_percent) {
        discountEl.textContent = `${Math.round(product.discount_percent)}% below avg`;
    }

    // Set up track/alert button (only if product has price).
    const trackBtn = clone.querySelector('[data-track-price]');
    if (trackBtn) {
        if (!product.price) {
            // Hide track button if no regional pricing
            trackBtn.style.display = 'none';
        } else {
            trackBtn.dataset.trackPrice = product.id;
            trackBtn.dataset.productName = product.name || '';
            trackBtn.dataset.productImage = product.thumbnail || '';
            trackBtn.dataset.currentPrice = product.price;
            trackBtn.dataset.currency = product.currency || 'USD';

            if (onTrackClick) {
                trackBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    onTrackClick(product, trackBtn);
                });
            }
        }
    }

    return clone;
}

/**
 * Render a grid of product cards.
 *
 * @param {HTMLElement} container - Container element to render into
 * @param {Array} products - Array of product data
 * @param {Object} options - Rendering options
 * @param {HTMLTemplateElement} options.template - Card template
 * @param {boolean} [options.clearFirst=true] - Whether to clear container first
 * @param {Function} [options.onTrackClick] - Track button callback
 * @returns {void}
 */
export function renderProductGrid(container, products, options = {}) {
    const { template, clearFirst = true, onTrackClick } = options;

    if (!container || !template) {
        return;
    }

    if (clearFirst) {
        container.innerHTML = '';
    }

    products.forEach((product) => {
        const card = createProductCard(product, { template, onTrackClick });
        if (card) {
            container.appendChild(card);
        }
    });
}

export default {
    getCurrencySymbol,
    formatPrice,
    splitPrice,
    createProductCard,
    renderProductGrid,
};

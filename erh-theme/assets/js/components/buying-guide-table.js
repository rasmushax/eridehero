/**
 * Buying Guide Table - Geo Price Hydration
 *
 * Handles:
 * - User geo detection
 * - Fetching prices for all products in table
 * - Rendering price buttons in price row
 */

import { getUserGeo, getProductPrices, formatPrice } from '../services/geo-price.js';
import { ensureAbsoluteUrl } from '../utils/dom.js';

/**
 * Initialize all buying guide tables on the page.
 */
export function initBuyingGuideTables() {
    const tables = document.querySelectorAll('[data-buying-guide-table]');
    tables.forEach(table => new BuyingGuideTable(table));
}

/**
 * Buying Guide Table Component.
 */
class BuyingGuideTable {
    constructor(element) {
        this.el = element;
        this.config = JSON.parse(element.dataset.buyingGuideTable || '{}');
        this.productIds = this.config.productIds || [];

        // Build Map for O(1) cell lookup instead of re-querying DOM.
        this.priceCellMap = new Map();
        element.querySelectorAll('.bgt-col-price[data-product-id]').forEach(cell => {
            this.priceCellMap.set(cell.dataset.productId, cell);
        });

        // Skip if no products with pricing.
        if (this.productIds.length === 0 || this.priceCellMap.size === 0) {
            return;
        }

        this.init();
    }

    /**
     * Initialize: detect geo and hydrate prices.
     */
    async init() {
        try {
            const userGeo = await getUserGeo();
            await this.hydratePrices(userGeo);
        } catch (error) {
            console.error('[BuyingGuideTable] Init error:', error);
            this.showPriceErrors();
        }
    }

    /**
     * Fetch prices for all products and render.
     * @param {Object} userGeo - User geo info { geo, currency, symbol }
     */
    async hydratePrices(userGeo) {
        const { geo, symbol } = userGeo;

        // Fetch all prices in parallel.
        const pricePromises = this.productIds.map(async (productId) => {
            try {
                const priceData = await getProductPrices(productId, geo);
                return { productId, priceData, error: null };
            } catch (error) {
                return { productId, priceData: null, error };
            }
        });

        const results = await Promise.all(pricePromises);

        // Render each price cell using Map for O(1) lookup.
        results.forEach(({ productId, priceData, error }) => {
            const cell = this.priceCellMap.get(String(productId));
            if (!cell) return;

            if (error || !priceData) {
                this.renderPriceError(cell);
                return;
            }

            this.renderPrice(cell, priceData, symbol);
        });
    }

    /**
     * Render price button in a cell.
     * @param {HTMLElement} cell - Price cell element
     * @param {Object} priceData - Price data from API
     * @param {string} symbol - Currency symbol
     */
    renderPrice(cell, priceData, symbol) {
        const offers = priceData.offers || [];

        if (offers.length === 0) {
            // No offers for this region.
            cell.innerHTML = '<span class="bgt-na">&mdash;</span>';
            return;
        }

        // Get best (lowest in-stock) offer.
        const bestOffer = offers.find(o => o.in_stock) || offers[0];

        if (!bestOffer || !bestOffer.price) {
            cell.innerHTML = '<span class="bgt-na">&mdash;</span>';
            return;
        }

        // Format price.
        const formattedPrice = formatPrice(bestOffer.price, symbol);
        const url = ensureAbsoluteUrl(bestOffer.tracked_url || bestOffer.url);

        // Render button.
        cell.innerHTML = `
            <a href="${url}" class="bgt-price-btn" target="_blank" rel="sponsored noopener">
                ${formattedPrice}
                <svg class="icon" aria-hidden="true"><use href="#icon-external-link"></use></svg>
            </a>
        `;
    }

    /**
     * Render error/unavailable state for a price cell.
     * @param {HTMLElement} cell - Price cell element
     */
    renderPriceError(cell) {
        cell.innerHTML = '<span class="bgt-na">&mdash;</span>';
    }

    /**
     * Show error state for all price cells.
     */
    showPriceErrors() {
        this.priceCellMap.forEach(cell => this.renderPriceError(cell));
    }
}

export default BuyingGuideTable;

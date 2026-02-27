/**
 * Shortlist Block Hydration
 *
 * Hydrates shortlist items with geo-aware pricing data.
 * Same pattern as bfdeal.js - single geo lookup shared across all items.
 */

import { getUserGeo, formatPrice, getProductPrices, filterOffersForGeo } from '../services/geo-price.js';
import { ensureAbsoluteUrl } from '../utils/dom.js';

/**
 * Initialize all shortlist blocks on the page.
 */
export function initShortlist() {
    const items = document.querySelectorAll('[data-shortlist-product]');
    if (!items.length) return;

    getUserGeo().then(userGeo => {
        items.forEach(el => hydrateItem(el, userGeo));
    });
}

/**
 * Hydrate a single shortlist item with geo-aware pricing.
 */
async function hydrateItem(el, userGeo) {
    const config = JSON.parse(el.dataset.shortlistProduct || '{}');
    const productId = config.productId;

    if (!productId || !userGeo) {
        hideBuyButton(el);
        return;
    }

    try {
        const data = await getProductPrices(productId, userGeo.geo);

        if (!data?.offers?.length) {
            throw new Error('No pricing data');
        }

        const filteredOffers = filterOffersForGeo(data.offers, userGeo);

        if (!filteredOffers.length) {
            throw new Error('No offers for region');
        }

        const bestOffer = filteredOffers[0];
        const offerUrl = ensureAbsoluteUrl(bestOffer.tracked_url || bestOffer.url);
        const currency = bestOffer.currency || userGeo.currency;
        const retailer = bestOffer.retailer || '';

        // Build button text: "$799 at Amazon" or just "$799"
        const priceText = formatPrice(bestOffer.price, currency);
        const btnText = retailer ? `${priceText} at ${retailer}` : priceText;

        // Update buy button.
        const buyBtn = el.querySelector('[data-shortlist-buy]');
        if (buyBtn) {
            buyBtn.href = offerUrl;

            const textEl = buyBtn.querySelector('[data-shortlist-buy-text]');
            const iconEl = buyBtn.querySelector('[data-shortlist-buy-icon]');
            const spinner = buyBtn.querySelector('[data-shortlist-spinner]');

            if (textEl) {
                textEl.textContent = btnText;
                textEl.style.display = '';
            }
            if (iconEl) iconEl.style.display = '';
            if (spinner) spinner.style.display = 'none';
        }
    } catch {
        hideBuyButton(el);
    }
}

/**
 * Hide the buy button on failure (remove it entirely).
 */
function hideBuyButton(el) {
    const buyBtn = el.querySelector('[data-shortlist-buy]');
    if (buyBtn) buyBtn.remove();
}

export default { init: initShortlist };

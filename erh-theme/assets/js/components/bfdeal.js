/**
 * Black Friday Deal Block Hydration
 *
 * Hydrates bfdeal blocks that have no manual_link with geo-aware
 * pricing data (best offer URL from the HFT pricing system).
 *
 * Same pattern as listicle-item.js hydratePriceBar().
 */

import { getUserGeo, formatPrice, getProductPrices, filterOffersForGeo } from '../services/geo-price.js';
import { ensureAbsoluteUrl } from '../utils/dom.js';

/**
 * Initialize all bfdeal blocks that need hydration
 */
export function initBfdeals() {
    const items = document.querySelectorAll('[data-bfdeal-item]');
    if (!items.length) return;

    // Share a single geo lookup across all bfdeal blocks on the page.
    getUserGeo().then(userGeo => {
        items.forEach(el => hydrateBfdeal(el, userGeo));
    });
}

/**
 * Hydrate a single bfdeal block with geo-aware pricing
 */
async function hydrateBfdeal(el, userGeo) {
    const config = JSON.parse(el.dataset.bfdealItem || '{}');
    const productId = config.productId;

    if (!productId || !userGeo) return;

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

        // Update all deal links to use the tracked URL.
        el.querySelectorAll('[data-bfdeal-link]').forEach(link => {
            link.href = offerUrl;
            if (!link.hasAttribute('target')) {
                link.setAttribute('target', '_blank');
                link.setAttribute('rel', 'sponsored noopener');
            }
        });

        // Show CTA text, hide spinner.
        const ctaText = el.querySelector('[data-bfdeal-cta-text]');
        const ctaIcon = el.querySelector('[data-bfdeal-cta-icon]');
        const spinner = el.querySelector('[data-bfdeal-spinner]');
        if (ctaText) ctaText.style.display = '';
        if (ctaIcon) ctaIcon.style.display = '';
        if (spinner) spinner.style.display = 'none';

        // Hydrate price skeleton if no manual prices were set.
        const pricingSkeleton = el.querySelector('[data-bfdeal-pricing]');
        if (pricingSkeleton) {
            pricingSkeleton.innerHTML = `
                <a href="${offerUrl}" target="_blank" rel="sponsored noopener" class="erh-bfdeal__pricing">
                    <span class="erh-bfdeal__price-now">${formatPrice(bestOffer.price, currency)}</span>
                </a>
            `;
        }

    } catch (error) {
        // On failure: hide the CTA spinner, show text pointing to product page.
        const ctaText = el.querySelector('[data-bfdeal-cta-text]');
        const ctaIcon = el.querySelector('[data-bfdeal-cta-icon]');
        const spinner = el.querySelector('[data-bfdeal-spinner]');
        if (ctaText) {
            ctaText.textContent = 'View Product';
            ctaText.style.display = '';
        }
        if (ctaIcon) ctaIcon.style.display = '';
        if (spinner) spinner.style.display = 'none';

        // Remove skeleton pricing.
        const pricingSkeleton = el.querySelector('[data-bfdeal-pricing]');
        if (pricingSkeleton) pricingSkeleton.remove();
    }
}

export default { init: initBfdeals };

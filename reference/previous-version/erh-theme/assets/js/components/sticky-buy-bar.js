/**
 * Sticky Buy Bar Module
 *
 * Shows a persistent buy CTA at the bottom of the viewport
 * after the user scrolls past the main pricing section.
 * Fetches geo-aware price data from REST API.
 *
 * @module components/sticky-buy-bar
 */

import { getUserGeo, formatPrice, getProductPrices, filterOffersForGeo } from '../services/geo-price.js';
import { PriceAlertModal } from './price-alert.js';
import { calculatePriceVerdict } from '../utils/pricing-ui.js';

const SELECTORS = {
    bar: '#sticky-buy-bar',
    priceSection: '#prices',
    // Dynamic content elements
    price: '[data-sticky-price]',
    verdict: '[data-sticky-verdict]',
    verdictText: '[data-sticky-verdict-text]',
    verdictIcon: '[data-sticky-verdict-icon]',
    buyLink: '[data-sticky-buy-link]',
    retailer: '[data-sticky-retailer]',
    trackPriceBtn: '[data-modal-trigger="price-alert-modal"]',
    compareLink: '.sticky-buy-bar-link[href*="compare"]'
};

const CLASSES = {
    visible: 'is-visible'
};

let stickyBar = null;
let priceSection = null;
let ticking = false;
let priceDataLoaded = false;

// Product info for price alert modal
let productInfo = {
    id: 0,
    name: '',
    image: '',
    currentPrice: 0,
    currentCurrency: 'USD'
};

/**
 * Update sticky bar visibility based on scroll position
 * Shows when scrolled 50px past the bottom of the price section
 */
function updateVisibility() {
    if (!stickyBar || !priceSection) return;

    const priceSectionRect = priceSection.getBoundingClientRect();
    const offset = 50; // Show bar after scrolling this many px past the price section
    const shouldShow = priceSectionRect.bottom < -offset;

    if (shouldShow && priceDataLoaded) {
        stickyBar.classList.add(CLASSES.visible);
        stickyBar.setAttribute('aria-hidden', 'false');
    } else {
        stickyBar.classList.remove(CLASSES.visible);
        stickyBar.setAttribute('aria-hidden', 'true');
    }
}

/**
 * Handle scroll events with requestAnimationFrame throttling
 */
function onScroll() {
    if (!ticking) {
        window.requestAnimationFrame(() => {
            updateVisibility();
            ticking = false;
        });
        ticking = true;
    }
}

/**
 * Fetch and populate price data
 * Uses shared getProductPrices() which has in-memory caching (5 min)
 * so if price-intel already fetched, this is instant.
 */
async function loadPriceData() {
    if (!stickyBar) return;

    const productId = stickyBar.dataset.productId;
    if (!productId) return;

    try {
        // Get user's geo
        const userGeo = await getUserGeo();
        const { geo, currency } = userGeo;

        // Fetch price data using shared cached function
        const data = await getProductPrices(parseInt(productId, 10), geo);
        if (!data || !data.offers || data.offers.length === 0) return;

        // Filter offers using shared utility (handles EU special case)
        const filteredOffers = filterOffersForGeo(data.offers, userGeo);

        if (filteredOffers.length === 0) return;

        // Best offer is first (already sorted by price, in-stock first)
        const bestOffer = filteredOffers[0];

        // Populate price
        const priceEl = stickyBar.querySelector(SELECTORS.price);
        if (priceEl) {
            priceEl.textContent = formatPrice(bestOffer.price, bestOffer.currency || currency);
        }

        // Populate verdict if we have history statistics
        const verdictEl = stickyBar.querySelector(SELECTORS.verdict);
        const verdictTextEl = stickyBar.querySelector(SELECTORS.verdictText);
        const verdictIconEl = stickyBar.querySelector(SELECTORS.verdictIcon);
        if (verdictEl && verdictTextEl && data.history?.statistics?.average) {
            const verdict = calculatePriceVerdict(bestOffer.price, data.history.statistics.average);

            if (verdict && verdict.shouldShow) {
                verdictTextEl.textContent = verdict.text;
                verdictEl.setAttribute('data-verdict-type', verdict.type);
                if (verdictIconEl) {
                    const icon = verdict.type === 'below' ? 'arrow-down' : 'arrow-up';
                    verdictIconEl.querySelector('use')?.setAttribute('href', `#icon-${icon}`);
                }
                verdictEl.style.display = '';
            }
        }

        // Populate buy link
        const buyLink = stickyBar.querySelector(SELECTORS.buyLink);
        const retailerEl = stickyBar.querySelector(SELECTORS.retailer);
        if (buyLink && bestOffer.url) {
            buyLink.href = bestOffer.url;
            if (retailerEl && bestOffer.retailer) {
                retailerEl.textContent = `Buy at ${bestOffer.retailer}`;
            }
        }

        // Store current price and currency for price alert modal
        productInfo.currentPrice = bestOffer.price;
        productInfo.currentCurrency = bestOffer.currency || currency;

        priceDataLoaded = true;

    } catch (error) {
        console.error('Failed to load sticky bar price data:', error);
    }
}

/**
 * Set up button handlers for Track Price and Compare
 */
function setupButtons() {
    if (!stickyBar) return;

    // Store product info from data attributes
    productInfo.id = parseInt(stickyBar.dataset.productId, 10) || 0;
    productInfo.name = stickyBar.dataset.productName || '';

    // Get product image from the bar itself
    const img = stickyBar.querySelector('.sticky-buy-bar-img');
    productInfo.image = img?.src || '';

    // Track Price button - opens price alert modal
    const trackPriceBtn = stickyBar.querySelector(SELECTORS.trackPriceBtn);
    if (trackPriceBtn) {
        trackPriceBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();

            PriceAlertModal.open({
                productId: productInfo.id,
                productName: productInfo.name,
                productImage: productInfo.image,
                currentPrice: productInfo.currentPrice,
                currency: productInfo.currentCurrency
            });
        });
    }

    // Compare link - append product ID to URL
    const compareLink = stickyBar.querySelector(SELECTORS.compareLink);
    if (compareLink && productInfo.id) {
        const currentHref = compareLink.href;
        const separator = currentHref.includes('?') ? '&' : '?';
        compareLink.href = `${currentHref}${separator}products=${productInfo.id}`;
    }
}

/**
 * Initialize the sticky buy bar
 *
 * @param {Object} [options] - Configuration options
 * @param {string} [options.barSelector] - Selector for the sticky bar
 * @param {string} [options.priceSectionSelector] - Selector for the price section
 */
export function init(options = {}) {
    const selectors = {
        bar: options.barSelector || SELECTORS.bar,
        priceSection: options.priceSectionSelector || SELECTORS.priceSection
    };

    stickyBar = document.querySelector(selectors.bar);
    priceSection = document.querySelector(selectors.priceSection);

    // Exit if required elements not found
    if (!stickyBar || !priceSection) {
        return;
    }

    // Set up button handlers
    setupButtons();

    // Load price data
    loadPriceData();

    // Set up scroll listener
    window.addEventListener('scroll', onScroll, { passive: true });

    // Initial check
    updateVisibility();
}

/**
 * Destroy the sticky bar instance and clean up event listeners
 */
export function destroy() {
    window.removeEventListener('scroll', onScroll);
    stickyBar = null;
    priceSection = null;
    priceDataLoaded = false;
}

/**
 * Manually show the sticky bar
 */
export function show() {
    if (stickyBar && priceDataLoaded) {
        stickyBar.classList.add(CLASSES.visible);
        stickyBar.setAttribute('aria-hidden', 'false');
    }
}

/**
 * Manually hide the sticky bar
 */
export function hide() {
    if (stickyBar) {
        stickyBar.classList.remove(CLASSES.visible);
        stickyBar.setAttribute('aria-hidden', 'true');
    }
}

export default {
    init,
    destroy,
    show,
    hide
};

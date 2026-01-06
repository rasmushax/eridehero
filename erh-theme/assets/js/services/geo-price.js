/**
 * Geo-Aware Price Service
 *
 * Handles:
 * - User region detection (via IPInfo country → region mapping)
 * - Fetching prices from REST API
 * - Currency display preferences
 * - Price caching
 * - Manual region override
 *
 * Architecture:
 * - IPInfo returns country code (e.g., 'DE', 'FR')
 * - We map countries to 5 regions: US, GB, EU, CA, AU
 * - Prices are stored and displayed per-region
 *
 * This allows LSCache to serve the same HTML to all users,
 * then JavaScript fetches region-specific prices after page load.
 */

import {
    REGIONS,
    DEFAULT_REGION,
    getRegionForCountry,
    getRegionConfig,
    getAvailableRegions,
    isValidRegion,
} from './geo-config.js';

// Cache duration (1 hour for prices - prices update twice daily)
const CACHE_DURATION = 60 * 60 * 1000;

// Region detection cache keys (localStorage)
const REGION_STORAGE_KEY = 'erh_user_region';
const REGION_EXPIRY_KEY = 'erh_user_region_expiry';
const REGION_OVERRIDE_KEY = 'erh_region_override'; // Manual override (no expiry)

// Price cache (in-memory)
const priceCache = new Map();

// In-flight request deduplication (prevents duplicate concurrent requests)
const pendingRequests = new Map();

// Geo detection Promise (for deduplication of concurrent getUserGeo calls)
let geoDetectionPromise = null;

/**
 * Get REST URL base from WordPress localized data
 * @returns {string}
 */
function getRestUrl() {
    // Use WordPress localized data if available
    if (typeof erhData !== 'undefined' && erhData.restUrl) {
        return erhData.restUrl;
    }
    // Fallback - should not happen in production
    return '/wp-json/erh/v1/';
}

/**
 * Get user's region (mapped from IPInfo country)
 *
 * Returns the user's region code (US, GB, EU, CA, AU) along with
 * currency info. Checks for manual override first, then cached value,
 * then detects via IPInfo.
 *
 * @returns {Promise<{geo: string, currency: string, symbol: string, region: string, country: string|null}>}
 */
export async function getUserGeo() {
    // DEV OVERRIDE: Hardcode country for testing (set to country code to enable, null to disable)
    const DEV_COUNTRY_OVERRIDE = null; // Set to 'DK', 'DE', 'GB', etc. for testing
    if (DEV_COUNTRY_OVERRIDE) {
        const region = getRegionForCountry(DEV_COUNTRY_OVERRIDE);
        const config = getRegionConfig(region);
        return {
            geo: region,
            region: region,
            currency: config.currency,
            symbol: config.symbol,
            country: DEV_COUNTRY_OVERRIDE,
        };
    }

    // Return window-cached result if available (fastest path)
    if (window.erhUserGeoData) {
        return window.erhUserGeoData;
    }

    // If detection already in progress, wait for it (prevents duplicate IPInfo calls)
    if (geoDetectionPromise) {
        return geoDetectionPromise;
    }

    // Start detection and cache the Promise
    geoDetectionPromise = detectUserGeo();
    const result = await geoDetectionPromise;
    geoDetectionPromise = null;

    // Cache on window for instant access by other components
    window.erhUserGeoData = result;

    return result;
}

/**
 * Internal function to detect user's geo (called once, result cached)
 * @returns {Promise<{geo: string, currency: string, symbol: string, region: string, country: string|null}>}
 */
async function detectUserGeo() {
    // Check for manual override first (user selected a different region)
    const override = getRegionOverride();
    if (override) {
        const config = getRegionConfig(override);
        return {
            geo: override,
            region: override,
            currency: config.currency,
            symbol: config.symbol,
            country: null,
        };
    }

    // Check cached region
    const cached = getCachedRegion();
    if (cached) {
        return cached;
    }

    // Detect via IPInfo
    let detectedCountry = null;

    try {
        const ipInfoToken = window.erhConfig?.ipinfoToken;

        if (ipInfoToken) {
            const response = await fetch(`https://ipinfo.io/json?token=${ipInfoToken}`);

            if (response.ok) {
                const data = await response.json();
                detectedCountry = data.country || null;
            }
        }
    } catch (error) {
        console.error('[GeoPriceService] IPInfo detection failed:', error.message);
    }

    // Map country to region
    const region = detectedCountry ? getRegionForCountry(detectedCountry) : DEFAULT_REGION;
    const config = getRegionConfig(region);

    const regionData = {
        geo: region,
        region: region,
        currency: config.currency,
        symbol: config.symbol,
        country: detectedCountry,
    };

    cacheRegion(regionData);

    return regionData;
}

/**
 * Get cached region from localStorage
 * @returns {{geo: string, region: string, currency: string, symbol: string, country: string|null}|null}
 */
function getCachedRegion() {
    try {
        const expiry = localStorage.getItem(REGION_EXPIRY_KEY);
        if (expiry && Date.now() < parseInt(expiry, 10)) {
            const data = localStorage.getItem(REGION_STORAGE_KEY);
            if (data) {
                return JSON.parse(data);
            }
        }
    } catch (e) {
        // localStorage not available
    }
    return null;
}

/**
 * Cache region data to localStorage
 * @param {{geo: string, region: string, currency: string, symbol: string, country: string|null}} data
 */
function cacheRegion(data) {
    try {
        localStorage.setItem(REGION_STORAGE_KEY, JSON.stringify(data));
        // Cache for 24 hours
        localStorage.setItem(REGION_EXPIRY_KEY, String(Date.now() + 24 * 60 * 60 * 1000));
    } catch (e) {
        // localStorage not available
    }
}

/**
 * Get manual region override from localStorage
 * @returns {string|null} Region code or null if no override
 */
function getRegionOverride() {
    try {
        const override = localStorage.getItem(REGION_OVERRIDE_KEY);
        if (override && isValidRegion(override)) {
            return override;
        }
    } catch (e) {
        // localStorage not available
    }
    return null;
}

/**
 * Set manual region override
 * Used when user manually selects a different region.
 *
 * @param {string} regionCode - Region code (US, GB, EU, CA, AU)
 * @returns {boolean} True if set successfully
 */
export function setUserRegion(regionCode) {
    if (!isValidRegion(regionCode)) {
        console.warn(`[GeoPriceService] Invalid region code: ${regionCode}`);
        return false;
    }

    try {
        localStorage.setItem(REGION_OVERRIDE_KEY, regionCode);
        // Clear cached auto-detected region so it doesn't conflict
        localStorage.removeItem(REGION_STORAGE_KEY);
        localStorage.removeItem(REGION_EXPIRY_KEY);

        // Clear window-level cache so next getUserGeo() returns new region
        delete window.erhUserGeoData;

        // Clear price caches (they're geo-specific)
        priceCache.clear();

        // Dispatch event for components to update
        const config = getRegionConfig(regionCode);
        window.dispatchEvent(new CustomEvent('erh:region-changed', {
            detail: {
                region: regionCode,
                currency: config.currency,
                symbol: config.symbol,
            },
        }));

        return true;
    } catch (e) {
        console.error('[GeoPriceService] Failed to set region override:', e);
        return false;
    }
}

/**
 * Clear manual region override (revert to auto-detection)
 */
export function clearUserRegion() {
    try {
        localStorage.removeItem(REGION_OVERRIDE_KEY);
        localStorage.removeItem(REGION_STORAGE_KEY);
        localStorage.removeItem(REGION_EXPIRY_KEY);

        // Clear window-level cache so next getUserGeo() re-detects
        delete window.erhUserGeoData;

        // Clear price caches (they're geo-specific)
        priceCache.clear();

        window.dispatchEvent(new CustomEvent('erh:region-changed', {
            detail: { region: null, autoDetect: true },
        }));
    } catch (e) {
        // Ignore errors
    }
}

/**
 * Check if user has a manual region override set
 * @returns {boolean}
 */
export function hasRegionOverride() {
    return getRegionOverride() !== null;
}

/**
 * Get all available regions for region selector UI
 * @returns {Array<{code: string, currency: string, symbol: string, flag: string, label: string}>}
 */
export { getAvailableRegions };

/**
 * Fetch best prices for multiple products
 * @param {number[]} productIds Array of product IDs
 * @param {string} geo Geo code
 * @param {string} [convertTo] Optional currency to convert to
 * @returns {Promise<Object>} Prices keyed by product ID
 */
export async function getBestPrices(productIds, geo, convertTo = null) {
    const cacheKey = `best:${productIds.join(',')}:${geo}:${convertTo || 'native'}`;

    // Check cache first
    const cached = priceCache.get(cacheKey);
    if (cached && Date.now() < cached.expiry) {
        return cached.data;
    }

    // Check if request is already in flight (deduplication)
    if (pendingRequests.has(cacheKey)) {
        return pendingRequests.get(cacheKey);
    }

    // Create the fetch promise
    const fetchPromise = (async () => {
        try {
            const params = new URLSearchParams({
                ids: productIds.join(','),
                geo: geo
            });
            if (convertTo) {
                params.append('convert_to', convertTo);
            }

            const url = `${getRestUrl()}prices/best?${params}`;
            const response = await fetch(url);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            // Cache the result
            priceCache.set(cacheKey, {
                data: result.prices,
                expiry: Date.now() + CACHE_DURATION
            });

            return result.prices;
        } catch (error) {
            console.error('[GeoPriceService] Failed to fetch prices:', error);
            return {};
        } finally {
            pendingRequests.delete(cacheKey);
        }
    })();

    pendingRequests.set(cacheKey, fetchPromise);

    return fetchPromise;
}

/**
 * Fetch all prices for a single product
 * @param {number} productId Product ID
 * @param {string} geo Geo code
 * @param {string} [convertTo] Optional currency to convert to
 * @returns {Promise<Object>} Product price data with all offers
 */
export async function getProductPrices(productId, geo, convertTo = null) {
    const cacheKey = `product:${productId}:${geo}:${convertTo || 'native'}`;

    // Check cache first
    const cached = priceCache.get(cacheKey);
    if (cached && Date.now() < cached.expiry) {
        return cached.data;
    }

    // Check if request is already in flight (deduplication)
    if (pendingRequests.has(cacheKey)) {
        return pendingRequests.get(cacheKey);
    }

    // Create the fetch promise
    const fetchPromise = (async () => {
        try {
            const params = new URLSearchParams({ geo: geo });
            if (convertTo) {
                params.append('convert_to', convertTo);
            }

            const response = await fetch(`${getRestUrl()}prices/${productId}?${params}`);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            // Cache the result
            priceCache.set(cacheKey, {
                data: result,
                expiry: Date.now() + CACHE_DURATION
            });

            return result;
        } catch (error) {
            console.error('[GeoPriceService] Failed to fetch product prices:', error);
            return null;
        } finally {
            // Remove from pending requests when done
            pendingRequests.delete(cacheKey);
        }
    })();

    // Store the pending promise
    pendingRequests.set(cacheKey, fetchPromise);

    return fetchPromise;
}

/**
 * Format a price for display
 *
 * @param {number} price - The price value
 * @param {string} currency - Currency code (USD, EUR, GBP, CAD, AUD)
 * @param {boolean} [isConverted=false] - Whether this is a converted/approximate price
 * @returns {string} Formatted price string (e.g., "$499", "€399")
 */
export function formatPrice(price, currency, isConverted = false) {
    if (price === null || price === undefined) {
        return '';
    }

    // Get symbol from REGIONS config or use fallback
    const symbols = {
        'USD': '$',
        'EUR': '€',
        'GBP': '£',
        'CAD': 'CA$',
        'AUD': 'A$',
    };

    const symbol = symbols[currency] || currency + ' ';
    const formatted = price.toLocaleString(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    });

    // Add ~ prefix for converted prices to indicate approximation
    return isConverted ? `~${symbol}${formatted}` : `${symbol}${formatted}`;
}

/**
 * Get currency symbol for a currency code
 *
 * @param {string} currency - Currency code
 * @returns {string} Currency symbol
 */
export function getCurrencySymbol(currency) {
    const symbols = {
        'USD': '$',
        'EUR': '€',
        'GBP': '£',
        'CAD': 'CA$',
        'AUD': 'A$',
    };
    return symbols[currency] || currency;
}

/**
 * Filter offers to show only those matching user's geo/currency.
 *
 * @param {Array} offers - Array of price offers from API
 * @param {{geo: string, currency: string}} userGeo - User's geo info
 * @returns {Array} Filtered offers matching user's region
 */
export function filterOffersForGeo(offers, userGeo) {
    if (!offers || !userGeo) return [];

    const userCurrency = userGeo.currency;
    const userGeoCode = userGeo.geo;

    // EU countries that should be treated as EU
    const euCountries = ['DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'AT', 'IE', 'PT', 'FI', 'GR', 'LU', 'SK', 'SI', 'EE', 'LV', 'LT', 'CY', 'MT'];

    return offers.filter(offer => {
        const offerCurrency = offer.currency || 'USD';
        const offerGeo = offer.geo;

        // Primary filter: currency must match user's currency
        if (offerCurrency !== userCurrency) {
            return false;
        }

        // If offer has explicit geo, it must match user's region
        if (offerGeo) {
            // Direct match
            if (offerGeo === userGeoCode) return true;
            // EU: accept EU-tagged or EU country-tagged offers
            if (userGeoCode === 'EU' && (offerGeo === 'EU' || euCountries.includes(offerGeo))) return true;
            // User in EU country: accept EU offers
            if (euCountries.includes(userGeoCode) && offerGeo === 'EU') return true;
            // No match
            return false;
        }

        // Offer has no explicit geo (global) - accept if currency matches
        return true;
    });
}

/**
 * Update price elements on the page with geo-aware prices
 * @param {string} selector CSS selector for price containers
 * @param {string} geo User's geo
 * @param {string} [convertTo] Currency to convert to
 */
export async function updatePriceElements(selector, geo, convertTo = null) {
    const elements = document.querySelectorAll(selector);
    if (elements.length === 0) return;

    // Collect product IDs
    const productIds = [];
    elements.forEach(el => {
        const productId = el.dataset.productId;
        if (productId && !productIds.includes(parseInt(productId, 10))) {
            productIds.push(parseInt(productId, 10));
        }
    });

    if (productIds.length === 0) return;

    // Fetch prices in batch
    const prices = await getBestPrices(productIds, geo, convertTo);

    // Update elements
    elements.forEach(el => {
        const productId = el.dataset.productId;
        const priceData = prices[productId];

        if (priceData) {
            // Update price display
            const priceEl = el.querySelector('[data-price]');
            if (priceEl) {
                priceEl.textContent = formatPrice(
                    priceData.price,
                    priceData.currency,
                    priceData.converted
                );
            }

            // Update retailer
            const retailerEl = el.querySelector('[data-retailer]');
            if (retailerEl) {
                retailerEl.textContent = priceData.retailer || '';
            }

            // Update link
            const linkEl = el.querySelector('[data-buy-link]');
            if (linkEl && priceData.url) {
                linkEl.href = priceData.url;
            }

            // Update stock status
            const stockEl = el.querySelector('[data-stock]');
            if (stockEl) {
                stockEl.classList.toggle('in-stock', priceData.in_stock);
                stockEl.classList.toggle('out-of-stock', !priceData.in_stock);
                stockEl.textContent = priceData.in_stock ? 'In Stock' : 'Out of Stock';
            }

            // Show the element (may have been hidden while loading)
            el.classList.remove('loading');
            el.classList.add('loaded');
        } else {
            // No price available for this geo
            el.classList.remove('loading');
            el.classList.add('no-price');
        }
    });
}

/**
 * Initialize geo-aware pricing for the page
 * Call this on DOMContentLoaded
 */
export async function initGeoPricing() {
    // Detect user's geo
    const { geo, currency } = await getUserGeo();

    // Store on window for other components
    window.erhUserGeo = geo;
    window.erhUserCurrency = currency;

    // Dispatch event for other components
    window.dispatchEvent(new CustomEvent('erh:geo-detected', {
        detail: { geo, currency }
    }));

    // Auto-update any price elements on the page
    // These are elements with data-product-id and [data-geo-price] attribute
    await updatePriceElements('[data-geo-price]', geo, currency);
}

// Export for use in other modules
export default {
    // Region detection
    getUserGeo,
    setUserRegion,
    clearUserRegion,
    hasRegionOverride,
    getAvailableRegions,
    // Price fetching
    getBestPrices,
    getProductPrices,
    // Filtering
    filterOffersForGeo,
    // Formatting
    formatPrice,
    getCurrencySymbol,
    // DOM helpers
    updatePriceElements,
    initGeoPricing,
};

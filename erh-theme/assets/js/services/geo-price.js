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

// Cache duration (5 minutes for prices)
const CACHE_DURATION = 5 * 60 * 1000;

// Region detection cache keys (localStorage)
const REGION_STORAGE_KEY = 'erh_user_region';
const REGION_EXPIRY_KEY = 'erh_user_region_expiry';
const REGION_OVERRIDE_KEY = 'erh_region_override'; // Manual override (no expiry)

// Price cache (in-memory)
const priceCache = new Map();

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
    console.group('[GeoPriceService] getUserGeo()');
    console.log('Starting geo detection...');

    // ========== TEMPORARY TEST OVERRIDE ==========
    // Hardcode to Denmark (DK) -> EU region for testing
    // REMOVE THIS AFTER TESTING!
    const TEST_GEO = {
        geo: 'EU',
        region: 'EU',
        currency: 'EUR',
        symbol: '€',
        country: 'DK',
    };
    console.log('⚠️ USING HARDCODED TEST GEO:', TEST_GEO);
    console.groupEnd();
    return TEST_GEO;
    // ========== END TEST OVERRIDE ==========

    // Check for manual override first (user selected a different region)
    const override = getRegionOverride();
    if (override) {
        const config = getRegionConfig(override);
        const result = {
            geo: override,        // Region code (for backwards compatibility)
            region: override,     // Explicit region code
            currency: config.currency,
            symbol: config.symbol,
            country: null,        // Unknown when manually set
        };
        console.log('Using MANUAL OVERRIDE:', result);
        console.groupEnd();
        return result;
    }
    console.log('No manual override set');

    // Check cached region
    const cached = getCachedRegion();
    if (cached) {
        console.log('Using CACHED region:', cached);
        console.groupEnd();
        return cached;
    }
    console.log('No cached region found');

    // Detect via IPInfo
    let detectedCountry = null;

    try {
        const ipInfoToken = window.erhConfig?.ipinfoToken;
        console.log('IPInfo token present:', !!ipInfoToken, ipInfoToken ? `(${ipInfoToken.substring(0, 4)}...)` : '');

        if (ipInfoToken) {
            console.log('Fetching from IPInfo...');
            const response = await fetch(`https://ipinfo.io/json?token=${ipInfoToken}`);
            console.log('IPInfo response status:', response.status, response.statusText);

            if (response.ok) {
                const data = await response.json();
                console.log('IPInfo response data:', data);
                detectedCountry = data.country || null;
                console.log('Detected country code:', detectedCountry);
            } else {
                console.warn('IPInfo request failed:', response.status);
            }
        } else {
            console.warn('No IPInfo token configured! Check erhConfig.ipinfoToken');
        }
    } catch (error) {
        console.error('[GeoPriceService] IPInfo detection failed:', error.message);
    }

    // Map country to region
    const region = detectedCountry ? getRegionForCountry(detectedCountry) : DEFAULT_REGION;
    console.log('Country to region mapping:', detectedCountry, '->', region);

    const config = getRegionConfig(region);
    console.log('Region config:', config);

    const regionData = {
        geo: region,              // For backwards compatibility with existing code
        region: region,           // Explicit region code
        currency: config.currency,
        symbol: config.symbol,
        country: detectedCountry, // Original country code (e.g., 'DE')
    };

    console.log('Final region data:', regionData);
    cacheRegion(regionData);
    console.log('Region cached for 24 hours');
    console.groupEnd();

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

        // Dispatch event for components to update
        window.dispatchEvent(new CustomEvent('erh:region-changed', {
            detail: {
                region: regionCode,
                ...getRegionConfig(regionCode),
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
    console.group('[GeoPriceService] getBestPrices()');
    console.log('Product IDs:', productIds);
    console.log('Geo:', geo);
    console.log('Convert to:', convertTo);

    // Check cache for all products
    const cacheKey = `best:${productIds.join(',')}:${geo}:${convertTo || 'native'}`;
    const cached = priceCache.get(cacheKey);
    if (cached && Date.now() < cached.expiry) {
        console.log('Returning CACHED prices');
        console.groupEnd();
        return cached.data;
    }
    console.log('No valid cache, fetching from API...');

    try {
        const params = new URLSearchParams({
            ids: productIds.join(','),
            geo: geo
        });
        if (convertTo) {
            params.append('convert_to', convertTo);
        }

        const url = `${getRestUrl()}prices/best?${params}`;
        console.log('Fetching prices from:', url);

        const response = await fetch(url);
        console.log('Response status:', response.status, response.statusText);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const result = await response.json();
        console.log('API response:', result);
        console.log('Prices received for IDs:', Object.keys(result.prices || {}));

        // Cache the result
        priceCache.set(cacheKey, {
            data: result.prices,
            expiry: Date.now() + CACHE_DURATION
        });

        console.log('Prices cached for 5 minutes');
        console.groupEnd();
        return result.prices;
    } catch (error) {
        console.error('[GeoPriceService] Failed to fetch prices:', error);
        console.groupEnd();
        return {};
    }
}

/**
 * Fetch all prices for a single product
 * @param {number} productId Product ID
 * @param {string} geo Geo code
 * @param {string} [convertTo] Optional currency to convert to
 * @returns {Promise<Object>} Product price data with all offers
 */
export async function getProductPrices(productId, geo, convertTo = null) {
    // Check cache
    const cacheKey = `product:${productId}:${geo}:${convertTo || 'native'}`;
    const cached = priceCache.get(cacheKey);
    if (cached && Date.now() < cached.expiry) {
        return cached.data;
    }

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
    }
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
    // Formatting
    formatPrice,
    getCurrencySymbol,
    // DOM helpers
    updatePriceElements,
    initGeoPricing,
};

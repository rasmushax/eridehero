/**
 * Geo-Aware Price Service
 *
 * Handles:
 * - User geo detection (via IPInfo)
 * - Fetching prices from REST API
 * - Currency display preferences
 * - Price caching
 *
 * This allows LSCache to serve the same HTML to all users,
 * then JavaScript fetches geo-specific prices after page load.
 */

// Default geo when detection fails
const DEFAULT_GEO = 'US';
const DEFAULT_CURRENCY = 'USD';

// Cache duration (5 minutes)
const CACHE_DURATION = 5 * 60 * 1000;

// Geo detection cache key
const GEO_STORAGE_KEY = 'erh_user_geo';
const GEO_EXPIRY_KEY = 'erh_user_geo_expiry';

// Price cache
const priceCache = new Map();

/**
 * Get user's geo from IPInfo or cached value
 * @returns {Promise<{geo: string, currency: string}>}
 */
export async function getUserGeo() {
    // Check cached geo
    const cached = getCachedGeo();
    if (cached) {
        return cached;
    }

    try {
        // Try IPInfo (requires token in erhConfig)
        const ipInfoToken = window.erhConfig?.ipinfoToken;
        if (ipInfoToken) {
            const response = await fetch(`https://ipinfo.io/json?token=${ipInfoToken}`);
            if (response.ok) {
                const data = await response.json();
                const geoData = {
                    geo: data.country || DEFAULT_GEO,
                    currency: getCurrencyForGeo(data.country || DEFAULT_GEO)
                };
                cacheGeo(geoData);
                return geoData;
            }
        }
    } catch (error) {
        console.warn('[GeoPriceService] IPInfo detection failed:', error.message);
    }

    // Fallback to default
    const fallback = { geo: DEFAULT_GEO, currency: DEFAULT_CURRENCY };
    cacheGeo(fallback);
    return fallback;
}

/**
 * Get cached geo from localStorage
 * @returns {{geo: string, currency: string}|null}
 */
function getCachedGeo() {
    try {
        const expiry = localStorage.getItem(GEO_EXPIRY_KEY);
        if (expiry && Date.now() < parseInt(expiry, 10)) {
            const data = localStorage.getItem(GEO_STORAGE_KEY);
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
 * Cache geo data to localStorage
 * @param {{geo: string, currency: string}} data
 */
function cacheGeo(data) {
    try {
        localStorage.setItem(GEO_STORAGE_KEY, JSON.stringify(data));
        // Cache for 24 hours
        localStorage.setItem(GEO_EXPIRY_KEY, String(Date.now() + 24 * 60 * 60 * 1000));
    } catch (e) {
        // localStorage not available
    }
}

/**
 * Get currency for a geo code
 * @param {string} geo
 * @returns {string}
 */
function getCurrencyForGeo(geo) {
    const geoToCurrency = {
        'US': 'USD',
        'CA': 'CAD',
        'GB': 'GBP',
        'AU': 'AUD',
        // Euro countries
        'DE': 'EUR', 'FR': 'EUR', 'IT': 'EUR', 'ES': 'EUR', 'NL': 'EUR',
        'BE': 'EUR', 'AT': 'EUR', 'IE': 'EUR', 'FI': 'EUR', 'PT': 'EUR',
        'GR': 'EUR', 'LU': 'EUR', 'SK': 'EUR', 'SI': 'EUR', 'EE': 'EUR',
        'LV': 'EUR', 'LT': 'EUR', 'CY': 'EUR', 'MT': 'EUR',
        // Nordic (non-Euro)
        'DK': 'DKK',
        'SE': 'SEK',
        'NO': 'NOK',
        // Other
        'JP': 'JPY',
        'CN': 'CNY',
    };
    return geoToCurrency[geo] || 'USD';
}

/**
 * Fetch best prices for multiple products
 * @param {number[]} productIds Array of product IDs
 * @param {string} geo Geo code
 * @param {string} [convertTo] Optional currency to convert to
 * @returns {Promise<Object>} Prices keyed by product ID
 */
export async function getBestPrices(productIds, geo, convertTo = null) {
    // Check cache for all products
    const cacheKey = `best:${productIds.join(',')}:${geo}:${convertTo || 'native'}`;
    const cached = priceCache.get(cacheKey);
    if (cached && Date.now() < cached.expiry) {
        return cached.data;
    }

    try {
        const params = new URLSearchParams({
            ids: productIds.join(','),
            geo: geo
        });
        if (convertTo) {
            params.append('convert_to', convertTo);
        }

        const response = await fetch(`/wp-json/erh/v1/prices/best?${params}`);
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

        const response = await fetch(`/wp-json/erh/v1/prices/${productId}?${params}`);
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
 * @param {number} price
 * @param {string} currency
 * @param {boolean} [isConverted=false]
 * @returns {string}
 */
export function formatPrice(price, currency, isConverted = false) {
    if (price === null || price === undefined) {
        return '';
    }

    const symbols = {
        'USD': '$',
        'EUR': '\u20AC',
        'GBP': '\u00A3',
        'CAD': 'CA$',
        'AUD': 'A$',
        'DKK': 'kr',
        'SEK': 'kr',
        'NOK': 'kr',
        'JPY': '\u00A5',
        'CNY': '\u00A5'
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
    getUserGeo,
    getBestPrices,
    getProductPrices,
    formatPrice,
    updatePriceElements,
    initGeoPricing
};

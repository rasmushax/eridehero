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
import { ensureAbsoluteUrl } from '../utils/dom.js';

// Cache duration (1 hour for prices - prices update twice daily)
const CACHE_DURATION = 60 * 60 * 1000;

// Region detection cache keys (localStorage)
const REGION_STORAGE_KEY = 'erh_user_region';
const REGION_EXPIRY_KEY = 'erh_user_region_expiry';
const REGION_OVERRIDE_KEY = 'erh_region_override'; // Manual override (no expiry)

// Debug logging (enabled via localStorage or URL param)
const DEBUG = localStorage.getItem('erh_geo_debug') === 'true' ||
              new URLSearchParams(window.location.search).has('geo_debug');

/**
 * Log debug message if debugging is enabled
 * @param {string} message
 * @param {Object} [data]
 */
function log(message, data = null) {
    if (!DEBUG) return;
    const prefix = '[GeoPriceService]';
    if (data) {
        console.log(prefix, message, data);
    } else {
        console.log(prefix, message);
    }
}

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
 * @returns {Promise<{geo: string, currency: string, symbol: string, region: string, country: string|null, isUserPreference: boolean}>}
 */
async function detectUserGeo() {
    const startTime = performance.now();

    // 1. If logged in with user preference from database, use it (source of truth)
    if (window.erhData?.user?.geo) {
        const userGeo = window.erhData.user.geo;
        if (isValidRegion(userGeo)) {
            const config = getRegionConfig(userGeo);
            log('Using database user preference', { region: userGeo, time_ms: (performance.now() - startTime).toFixed(2) });
            return {
                geo: userGeo,
                region: userGeo,
                currency: config.currency,
                symbol: config.symbol,
                country: userGeo, // For logged-in users, country = region
                isUserPreference: true,
            };
        }
    }

    // 2. Check for manual override (user selected a different region via localStorage)
    const override = getRegionOverride();
    if (override) {
        const config = getRegionConfig(override);
        log('Using manual override', { region: override, time_ms: (performance.now() - startTime).toFixed(2) });
        return {
            geo: override,
            region: override,
            currency: config.currency,
            symbol: config.symbol,
            country: null,
            isUserPreference: false,
        };
    }

    // 3. Check cached region
    const cached = getCachedRegion();
    if (cached) {
        log('Using cached region', { region: cached.region, time_ms: (performance.now() - startTime).toFixed(2) });
        return { ...cached, isUserPreference: false };
    }

    // Detect via IPInfo
    let detectedCountry = null;

    try {
        const ipInfoToken = window.erhConfig?.ipinfoToken;

        if (ipInfoToken) {
            log('Calling IPInfo API...');
            const apiStart = performance.now();
            const response = await fetch(`https://ipinfo.io/json?token=${ipInfoToken}`);
            const apiTime = (performance.now() - apiStart).toFixed(2);

            if (response.ok) {
                const data = await response.json();
                detectedCountry = data.country || null;
                log('IPInfo response', { country: detectedCountry, ip: data.ip, bogon: data.bogon, time_ms: apiTime });
            } else {
                log('IPInfo request failed', { status: response.status, time_ms: apiTime });
            }
        } else {
            log('No IPInfo token configured');
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
        isUserPreference: false,
    };

    cacheRegion(regionData);

    log('Geo detection complete', {
        region,
        country: detectedCountry,
        source: detectedCountry ? 'ipinfo' : 'default',
        total_time_ms: (performance.now() - startTime).toFixed(2)
    });

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
                const parsed = JSON.parse(data);
                log('Found valid cached region', { region: parsed.region, expires_in_hours: ((parseInt(expiry, 10) - Date.now()) / 3600000).toFixed(1) });
                return parsed;
            }
        } else if (expiry) {
            log('Cached region expired', { expired_ago_hours: ((Date.now() - parseInt(expiry, 10)) / 3600000).toFixed(1) });
        } else {
            log('No cached region found');
        }
    } catch (e) {
        log('localStorage error', { error: e.message });
    }
    return null;
}

/**
 * Cache region data to localStorage and set cookies for server-side access.
 * @param {{geo: string, region: string, currency: string, symbol: string, country: string|null}} data
 */
function cacheRegion(data) {
    try {
        localStorage.setItem(REGION_STORAGE_KEY, JSON.stringify(data));
        // Cache for 24 hours
        localStorage.setItem(REGION_EXPIRY_KEY, String(Date.now() + 24 * 60 * 60 * 1000));
        log('Cached region to localStorage', { region: data.region, ttl_hours: 24 });
    } catch (e) {
        log('Failed to cache to localStorage', { error: e.message });
    }

    // Set cookies for server-side click tracking.
    setGeoCookie(data.geo);
    if (data.country) {
        setCountryCookie(data.country);
    }
}

/**
 * Set the erh_geo cookie for server-side geo detection.
 * Used by click tracking to determine user's region.
 * Only sets if cookie doesn't exist or has different value (optimization).
 * @param {string} geo - Region code (US, GB, EU, CA, AU)
 */
function setGeoCookie(geo) {
    // Check if cookie already exists with same value
    const existingCookie = document.cookie
        .split('; ')
        .find(row => row.startsWith('erh_geo='));

    if (existingCookie) {
        const existingGeo = existingCookie.split('=')[1];
        if (existingGeo === geo) {
            log('Cookie already set with correct value', { geo, action: 'skipped' });
            return; // Cookie already set with correct value
        }
        log('Cookie exists with different value', { existing: existingGeo, new: geo, action: 'updating' });
    } else {
        log('No existing cookie found', { geo, action: 'creating' });
    }

    const expires = new Date();
    expires.setFullYear(expires.getFullYear() + 1); // 1 year expiry

    // Set cookie with SameSite=Lax for cross-site compatibility (YouTube links, etc.)
    document.cookie = `erh_geo=${geo}; expires=${expires.toUTCString()}; path=/; SameSite=Lax`;

    // Verify cookie was set
    const verifyCookie = document.cookie.split('; ').find(row => row.startsWith('erh_geo='));
    if (verifyCookie) {
        log('Cookie set successfully', { geo, expires: expires.toUTCString() });
    } else {
        log('WARNING: Cookie may not have been set (check browser settings)', { geo });
    }
}

/**
 * Set the erh_country cookie for granular analytics.
 * Stores the specific country code (DK, DE, FR, etc.) for detailed tracking.
 * @param {string} country - Two-letter country code
 */
function setCountryCookie(country) {
    if (!country) return;

    // Check if cookie already exists with same value
    const existingCookie = document.cookie
        .split('; ')
        .find(row => row.startsWith('erh_country='));

    if (existingCookie) {
        const existingCountry = existingCookie.split('=')[1];
        if (existingCountry === country) {
            return; // Cookie already set with correct value
        }
    }

    const expires = new Date();
    expires.setFullYear(expires.getFullYear() + 1); // 1 year expiry

    document.cookie = `erh_country=${country}; expires=${expires.toUTCString()}; path=/; SameSite=Lax`;
    log('Country cookie set', { country, expires: expires.toUTCString() });
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

        // Update cookie for server-side click tracking
        setGeoCookie(regionCode);

        // Clear country cookie since this is a manual override (we don't know their actual country)
        document.cookie = 'erh_country=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';

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
 * Set user geo preference (updates database for logged-in users, localStorage for guests)
 *
 * @param {string} regionCode - Region code (US, GB, EU, CA, AU)
 * @returns {Promise<{success: boolean, method: 'database'|'localStorage'}>}
 */
export async function setUserGeoPreference(regionCode) {
    if (!isValidRegion(regionCode)) {
        console.warn(`[GeoPriceService] Invalid region code: ${regionCode}`);
        return { success: false, method: null };
    }

    // Not logged in - use localStorage override
    if (!window.erhData?.isLoggedIn) {
        const result = setUserRegion(regionCode);
        return { success: result, method: 'localStorage' };
    }

    // Logged in - update via REST API
    try {
        const response = await fetch(`${window.erhData.restUrl}user/preferences`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': window.erhData.nonce,
            },
            credentials: 'same-origin',
            body: JSON.stringify({ geo: regionCode }),
        });

        if (response.ok) {
            // Update local state immediately
            if (window.erhData.user) {
                window.erhData.user.geo = regionCode;
            }

            // Clear window-level geo cache so next getUserGeo() returns new region
            delete window.erhUserGeoData;

            // Clear price caches (they're geo-specific)
            priceCache.clear();

            // Also update cookie for server-side reads
            setCookie('erh_geo', regionCode, 365);

            // Clear localStorage override (database is now source of truth)
            try {
                localStorage.removeItem(REGION_OVERRIDE_KEY);
            } catch (e) {
                // Ignore
            }

            // Dispatch event for components to update
            const config = getRegionConfig(regionCode);
            window.dispatchEvent(new CustomEvent('erh:region-changed', {
                detail: {
                    region: regionCode,
                    currency: config.currency,
                    symbol: config.symbol,
                },
            }));

            log('User geo preference saved to database', { region: regionCode });
            return { success: true, method: 'database' };
        }

        console.error('[GeoPriceService] Failed to save geo preference:', response.status);
        return { success: false, method: null };
    } catch (error) {
        console.error('[GeoPriceService] Error saving geo preference:', error);
        return { success: false, method: null };
    }
}

/**
 * Set a cookie with given name, value, and days until expiry
 * @param {string} name
 * @param {string} value
 * @param {number} days
 */
function setCookie(name, value, days) {
    const expires = new Date();
    expires.setDate(expires.getDate() + days);
    document.cookie = `${name}=${value}; expires=${expires.toUTCString()}; path=/; SameSite=Lax`;
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
        log('getProductPrices: cache hit', { productId, geo });
        return cached.data;
    }

    // Check if request is already in flight (deduplication)
    if (pendingRequests.has(cacheKey)) {
        log('getProductPrices: request in flight, waiting', { productId, geo });
        return pendingRequests.get(cacheKey);
    }

    // Create the fetch promise
    const fetchPromise = (async () => {
        try {
            const params = new URLSearchParams({ geo: geo });
            if (convertTo) {
                params.append('convert_to', convertTo);
            }

            const url = `${getRestUrl()}prices/${productId}?${params}`;
            log('getProductPrices: fetching', { url });

            const startTime = performance.now();
            const response = await fetch(url);
            const fetchTime = (performance.now() - startTime).toFixed(2);

            if (!response.ok) {
                log('getProductPrices: HTTP error', { status: response.status, fetchTime });
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();
            log('getProductPrices: success', { fetchTime, offersCount: result.offers?.length });

            // Cache the result
            priceCache.set(cacheKey, {
                data: result,
                expiry: Date.now() + CACHE_DURATION
            });

            return result;
        } catch (error) {
            log('getProductPrices: error', { error: error.message });
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
 * @returns {string} Formatted price string (e.g., "$599.99", "€399.00")
 */
export function formatPrice(price, currency, isConverted = false) {
    if (price === null || price === undefined) {
        return '';
    }

    // Get symbol and locale per currency
    const currencyConfig = {
        'USD': { symbol: '$', locale: 'en-US' },
        'EUR': { symbol: '€', locale: 'de-DE' },
        'GBP': { symbol: '£', locale: 'en-GB' },
        'CAD': { symbol: 'CA$', locale: 'en-CA' },
        'AUD': { symbol: 'A$', locale: 'en-AU' },
    };

    const config = currencyConfig[currency] || { symbol: currency + ' ', locale: 'en-US' };
    const formatted = price.toLocaleString(config.locale, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    // Add ~ prefix for converted prices to indicate approximation
    return isConverted ? `~${config.symbol}${formatted}` : `${config.symbol}${formatted}`;
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
 * @param {{geo: string, currency: string, country: string}} userGeo - User's geo info
 * @returns {Array} Filtered offers matching user's region
 */
export function filterOffersForGeo(offers, userGeo) {
    if (!offers || !userGeo) return [];

    const userCurrency = userGeo.currency;
    const userGeoCode = userGeo.geo; // Region: US, GB, EU, CA, AU
    const userCountry = userGeo.country; // Country code: DK, DE, FR, etc.

    // EU countries that should be treated as EU region
    const euCountries = ['DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'AT', 'IE', 'PT', 'FI', 'GR', 'LU', 'SK', 'SI', 'EE', 'LV', 'LT', 'CY', 'MT', 'BG', 'HR', 'CZ', 'DK', 'HU', 'PL', 'RO', 'SE', 'NO', 'CH'];

    return offers.filter(offer => {
        const offerCurrency = offer.currency || 'USD';
        const offerGeo = offer.geo;

        // Primary filter: currency must match user's currency
        if (offerCurrency !== userCurrency) {
            log('Offer rejected: currency mismatch', {
                retailer: offer.retailer,
                offerCurrency,
                userCurrency
            });
            return false;
        }

        // If offer has explicit geo, check if it matches user's region or country
        if (offerGeo) {
            // Handle comma-separated geo codes (e.g., "AT,BE,DE,DK,FR,...")
            const offerGeoCodes = offerGeo.includes(',')
                ? offerGeo.split(',').map(g => g.trim().toUpperCase())
                : [offerGeo.toUpperCase()];

            // Direct match with user's region code
            if (offerGeoCodes.includes(userGeoCode)) {
                log('Offer accepted: direct geo match', { retailer: offer.retailer, offerGeo });
                return true;
            }

            // Match user's specific country code (e.g., DK in "AT,BE,DK,...")
            if (userCountry && offerGeoCodes.includes(userCountry.toUpperCase())) {
                log('Offer accepted: country code match', { retailer: offer.retailer, userCountry, offerGeo });
                return true;
            }

            // EU region: accept if offer has any EU country code
            if (userGeoCode === 'EU') {
                const hasEuCountry = offerGeoCodes.some(code => euCountries.includes(code) || code === 'EU');
                if (hasEuCountry) {
                    log('Offer accepted: EU geo match', { retailer: offer.retailer, offerGeo });
                    return true;
                }
            }

            // User in EU country: accept EU-tagged offers
            if (euCountries.includes(userGeoCode) && offerGeoCodes.includes('EU')) {
                log('Offer accepted: EU country accepts EU offer', { retailer: offer.retailer, offerGeo });
                return true;
            }

            // No match
            log('Offer rejected: geo mismatch', {
                retailer: offer.retailer,
                offerGeo,
                userGeoCode,
                userCountry
            });
            return false;
        }

        // Offer has no explicit geo (global) - accept if currency matches
        log('Offer accepted: no geo restriction, currency matched', { retailer: offer.retailer });
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
            if (linkEl && (priceData.tracked_url || priceData.url)) {
                linkEl.href = ensureAbsoluteUrl(priceData.tracked_url || priceData.url);
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
    setUserGeoPreference,
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

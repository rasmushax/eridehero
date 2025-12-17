// Housefresh Tools Frontend Core Functions
window.HFT_Frontend = window.HFT_Frontend || {};

(function (HFT) {
    'use strict';
    const DEBUG = false; // Set to false for production
    const DISABLE_CACHE = false; // Set to true to disable caching for testing
    const CACHE_VERSION = 'v1.0';
    const CACHE_DURATION = 6 * 60 * 60 * 1000; // 6 hours in milliseconds
    const CACHE_KEY = `hft_geo_cache_${CACHE_VERSION}`;

    function log(...args) {
        if (DEBUG) {
            console.log('[HFT Frontend]', ...args);
        }
    }

    /**
     * Detects user's likely GEO based on IP address via WP REST API with localStorage caching.
     * This is a shared utility function.
     * @returns {Promise<string>} Promise resolving to Uppercase 2-letter GEO code (e.g., US, GB) or default ('US').
     */
    HFT.detectUserGeo = async function () {
        const defaultGeo = 'US';

        log('Detecting GEO with localStorage caching (shared function)...');

        // First, check localStorage cache (unless disabled for testing)
        if (!DISABLE_CACHE) {
            try {
                const cachedData = localStorage.getItem(CACHE_KEY);
                if (cachedData) {
                    const parsed = JSON.parse(cachedData);
                    if (parsed.expires && parsed.expires > Date.now() && parsed.geo) {
                        log(` - Using cached GEO from localStorage: ${parsed.geo} (expires: ${new Date(parsed.expires).toLocaleString()})`);
                        return parsed.geo.toUpperCase();
                    } else {
                        log(' - localStorage cache expired or invalid, removing...');
                        localStorage.removeItem(CACHE_KEY);
                    }
                } else {
                    log(' - No GEO cache found in localStorage');
                }
            } catch (error) {
                log(' - Error reading localStorage cache:', error);
                // Continue with API call
            }
        } else {
            log(' - Cache disabled for testing, skipping localStorage check');
        }
        
        log(' - Fetching fresh GEO data via WP REST API...');
        
        // Ensure hft_frontend_data and rest_url are available.
        if (typeof hft_frontend_data === 'undefined' || typeof hft_frontend_data.rest_url === 'undefined') {
            log(' - GEO detection cannot proceed without REST URL, defaulting.');
            return defaultGeo; 
        }

        let apiUrl = hft_frontend_data.rest_url + 'housefresh-tools/v1/detect-geo';

        try {
            const response = await fetch(apiUrl);
            if (!response.ok) {
                throw new Error(`GEO API (WP) response not OK: ${response.status}`);
            }
            const data = await response.json();
            log(' - WP GEO API JSON response (IPInfo via shared function):', data);

            // Log additional debug info for IPInfo migration (only when DEBUG enabled)
            if (DEBUG) {
                if (data.source) {
                    console.log('[HFT IPInfo Debug] Geo source:', data.source);
                }
                if (data.error) {
                    console.warn('[HFT IPInfo Debug] Geo error:', data.error);
                }
                if (data.note) {
                    console.log('[HFT IPInfo Debug] Geo note:', data.note);
                }
            }

            let detectedGeo = defaultGeo;
            if (data && data.country_code && /^[A-Z]{2}$/.test(data.country_code)) {
                detectedGeo = data.country_code.toUpperCase();
                log(` - Detected GEO from API: ${detectedGeo}`);
            } else {
                log(' - WP GEO API response missing valid country_code, using default.');
            }
            
            // Cache the result in localStorage (unless disabled for testing)
            if (!DISABLE_CACHE) {
                try {
                    const cacheData = {
                        geo: detectedGeo,
                        expires: Date.now() + CACHE_DURATION,
                        cached_at: Date.now()
                    };
                    localStorage.setItem(CACHE_KEY, JSON.stringify(cacheData));
                    log(` - Cached GEO (${detectedGeo}) in localStorage for 6 hours`);
                } catch (error) {
                    log(' - Warning: Could not cache GEO in localStorage:', error);
                    // Continue without caching
                }
            } else {
                log(` - Cache disabled for testing, not storing GEO (${detectedGeo}) in localStorage`);
            }
            
            return detectedGeo;
        } catch (error) {
            log(' - Error during GEO detection via WP API (shared function), defaulting.');
            return defaultGeo;
        }
    };

    /**
     * Clears the cached GEO data from localStorage.
     * Useful for testing or when user wants to refresh their location.
     * @returns {boolean} True if cache was cleared, false if no cache existed.
     */
    HFT.clearGeoCache = function() {
        try {
            const existed = localStorage.getItem(CACHE_KEY) !== null;
            localStorage.removeItem(CACHE_KEY);
            if (existed) {
                log('GEO cache cleared from localStorage');
                return true;
            } else {
                log('No GEO cache found to clear');
                return false;
            }
        } catch (error) {
            log('Error clearing GEO cache:', error);
            return false;
        }
    };

    /**
     * Gets information about the current GEO cache status.
     * Useful for debugging and admin purposes.
     * @returns {object|null} Cache info object or null if no cache.
     */
    HFT.getGeoCacheInfo = function() {
        try {
            const cachedData = localStorage.getItem(CACHE_KEY);
            if (cachedData) {
                const parsed = JSON.parse(cachedData);
                return {
                    geo: parsed.geo,
                    cached_at: new Date(parsed.cached_at).toLocaleString(),
                    expires: new Date(parsed.expires).toLocaleString(),
                    is_expired: parsed.expires <= Date.now(),
                    time_remaining: Math.max(0, parsed.expires - Date.now())
                };
            }
            return null;
        } catch (error) {
            log('Error reading GEO cache info:', error);
            return null;
        }
    };

    // Other global frontend functions for Housefresh Tools can be added here.

})(window.HFT_Frontend); 
/**
 * Geo Region Configuration
 *
 * Defines the 5 supported regions and country-to-region mappings.
 * This is the single source of truth for geo configuration across the frontend.
 *
 * Architecture:
 * - HFT stores prices per-country (DE, FR, IT, etc.)
 * - ERH cache groups into regions (US, GB, EU, CA, AU)
 * - Frontend detects country via IPInfo, maps to region
 */

/**
 * Supported regions with their configuration
 */
export const REGIONS = {
    US: {
        currency: 'USD',
        symbol: '$',
        flag: '🇺🇸',
        label: 'United States',
    },
    GB: {
        currency: 'GBP',
        symbol: '£',
        flag: '🇬🇧',
        label: 'United Kingdom',
    },
    EU: {
        currency: 'EUR',
        symbol: '€',
        flag: '🇪🇺',
        label: 'Europe',
    },
    CA: {
        currency: 'CAD',
        symbol: 'CA$',
        flag: '🇨🇦',
        label: 'Canada',
    },
    AU: {
        currency: 'AUD',
        symbol: 'A$',
        flag: '🇦🇺',
        label: 'Australia',
    },
};

/**
 * Default region when detection fails or country is unknown
 */
export const DEFAULT_REGION = 'US';

/**
 * IPInfo country code to region mapping
 *
 * Countries not listed here will default to US.
 * This mapping determines which region a user sees prices for.
 */
export const COUNTRY_TO_REGION = {
    // North America
    'US': 'US',
    'CA': 'CA',

    // United Kingdom
    'GB': 'GB',

    // Australia/Oceania
    'AU': 'AU',
    'NZ': 'AU', // New Zealand shows Australian prices

    // EU Eurozone countries (EUR currency)
    'DE': 'EU', // Germany
    'FR': 'EU', // France
    'IT': 'EU', // Italy
    'ES': 'EU', // Spain
    'NL': 'EU', // Netherlands
    'BE': 'EU', // Belgium
    'AT': 'EU', // Austria
    'IE': 'EU', // Ireland
    'PT': 'EU', // Portugal
    'FI': 'EU', // Finland
    'GR': 'EU', // Greece
    'LU': 'EU', // Luxembourg
    'SK': 'EU', // Slovakia
    'SI': 'EU', // Slovenia
    'EE': 'EU', // Estonia
    'LV': 'EU', // Latvia
    'LT': 'EU', // Lithuania
    'CY': 'EU', // Cyprus
    'MT': 'EU', // Malta

    // EU non-Eurozone (still show EUR for simplicity)
    'PL': 'EU', // Poland (PLN)
    'CZ': 'EU', // Czech Republic (CZK)
    'HU': 'EU', // Hungary (HUF)
    'RO': 'EU', // Romania (RON)
    'BG': 'EU', // Bulgaria (BGN)
    'HR': 'EU', // Croatia
    'DK': 'EU', // Denmark (DKK) - often ships from EU
    'SE': 'EU', // Sweden (SEK) - often ships from EU

    // EEA/EFTA (show EU prices)
    'NO': 'EU', // Norway
    'IS': 'EU', // Iceland
    'CH': 'EU', // Switzerland
    'LI': 'EU', // Liechtenstein
};

/**
 * Get region for a country code
 *
 * @param {string} countryCode - IPInfo country code (e.g., 'DE', 'FR')
 * @returns {string} Region code (US, GB, EU, CA, AU)
 */
export function getRegionForCountry(countryCode) {
    if (!countryCode) return DEFAULT_REGION;
    return COUNTRY_TO_REGION[countryCode.toUpperCase()] || DEFAULT_REGION;
}

/**
 * Get region configuration
 *
 * @param {string} regionCode - Region code (e.g., 'EU')
 * @returns {object|null} Region config or null if invalid
 */
export function getRegionConfig(regionCode) {
    return REGIONS[regionCode] || null;
}

/**
 * Get all available regions for region selector
 *
 * @returns {Array<{code: string, ...config}>} Array of region configs with code
 */
export function getAvailableRegions() {
    return Object.entries(REGIONS).map(([code, config]) => ({
        code,
        ...config,
    }));
}

/**
 * Check if a region code is valid
 *
 * @param {string} regionCode - Region code to check
 * @returns {boolean} True if valid region
 */
export function isValidRegion(regionCode) {
    return regionCode in REGIONS;
}

export default {
    REGIONS,
    DEFAULT_REGION,
    COUNTRY_TO_REGION,
    getRegionForCountry,
    getRegionConfig,
    getAvailableRegions,
    isValidRegion,
};

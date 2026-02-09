/**
 * Shared REST API URL utility.
 *
 * Single source of truth for getting the ERH REST base URL.
 * Uses WordPress localized data (erhData.restUrl) which handles
 * subfolder installs correctly.
 *
 * @module utils/api
 */

/**
 * Get REST URL base from WordPress localized data.
 *
 * @returns {string} REST URL with trailing slash (e.g., "https://eridehero.com/wp-json/erh/v1/")
 * @throws {Error} If erhData.restUrl is not available
 */
export function getRestUrl() {
    if (!window.erhData?.restUrl) {
        throw new Error('erhData.restUrl not available');
    }
    return window.erhData.restUrl;
}

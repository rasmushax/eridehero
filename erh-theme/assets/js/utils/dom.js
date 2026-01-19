/**
 * DOM Utilities
 *
 * Shared DOM helper functions used across components.
 *
 * @module utils/dom
 */

/**
 * Escape HTML entities to prevent XSS.
 *
 * @param {string} str - String to escape
 * @returns {string} Escaped string safe for HTML insertion
 */
export function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, c => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    })[c]);
}

/**
 * Ensure URL is absolute (handles subfolder installs).
 * Relative URLs like "/go/product/123/" become "http://localhost/eridehero/go/product/123/".
 *
 * @param {string} url - URL that may be relative or absolute
 * @returns {string} Absolute URL
 */
export function ensureAbsoluteUrl(url) {
    if (!url) return '';
    // Already absolute (has protocol)
    if (url.startsWith('http://') || url.startsWith('https://')) {
        return url;
    }
    // Relative URL - prepend site URL
    const siteUrl = window.erhData?.siteUrl || '';
    // Handle both "/path" and "path" formats
    if (url.startsWith('/')) {
        return siteUrl + url;
    }
    return siteUrl + '/' + url;
}

export default { escapeHtml, ensureAbsoluteUrl };

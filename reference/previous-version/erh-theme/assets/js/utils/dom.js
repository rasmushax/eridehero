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

export default { escapeHtml };

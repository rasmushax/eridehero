/**
 * Search Utilities
 * Shared functions for search components
 */

export const DEBOUNCE_MS = 200;

/**
 * Escape HTML to prevent XSS
 * @param {string} str - String to escape
 * @returns {string} Escaped string
 */
export function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/**
 * Escape regex special characters
 * @param {string} str - String to escape
 * @returns {string} Escaped string safe for RegExp
 */
export function escapeRegex(str) {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

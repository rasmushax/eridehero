/**
 * Spec Editor - Utility Functions
 *
 * @package ERH\Admin
 */

/**
 * Debounce function execution.
 * @param {Function} func - Function to debounce.
 * @param {number} wait - Wait time in ms.
 * @returns {Function} Debounced function.
 */
export function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Format a value for display.
 * @param {*} value - Value to format.
 * @param {Object} column - Column schema.
 * @param {Object} i18n - Translations.
 * @returns {string} Formatted value.
 */
export function formatValue(value, column, i18n) {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const type = column.type || 'text';

    switch (type) {
        case 'boolean':
            return value ? i18n.true : i18n.false;

        case 'select':
            if (column.choices && column.choices[value]) {
                return column.choices[value];
            }
            return String(value);

        case 'checkbox':
            if (Array.isArray(value)) {
                return value.map(v => column.choices?.[v] || v).join(', ');
            }
            return String(value);

        case 'number':
            const num = parseFloat(value);
            if (isNaN(num)) return String(value);

            // Add unit suffix if present.
            const suffix = column.append ? ` ${column.append}` : '';
            return num.toLocaleString() + suffix;

        default:
            return String(value);
    }
}

/**
 * Parse a raw value to the appropriate type.
 * @param {*} value - Raw value.
 * @param {Object} column - Column schema.
 * @returns {*} Parsed value.
 */
export function parseValue(value, column) {
    const type = column.type || 'text';

    switch (type) {
        case 'boolean':
            return value === true || value === 'true' || value === '1' || value === 1;

        case 'number':
            if (value === '' || value === null || value === undefined) {
                return '';
            }
            const num = parseFloat(value);
            return isNaN(num) ? '' : num;

        case 'checkbox':
            if (Array.isArray(value)) return value;
            if (typeof value === 'string' && value) {
                return value.split(',').map(v => v.trim()).filter(Boolean);
            }
            return [];

        default:
            return value;
    }
}

/**
 * Compare two values for equality.
 * @param {*} a - First value.
 * @param {*} b - Second value.
 * @returns {boolean} True if equal.
 */
export function valuesEqual(a, b) {
    if (a === b) return true;
    if (a === null || a === undefined) a = '';
    if (b === null || b === undefined) b = '';

    if (Array.isArray(a) && Array.isArray(b)) {
        if (a.length !== b.length) return false;
        return a.every((v, i) => v === b[i]);
    }

    return String(a) === String(b);
}

/**
 * Escape HTML entities.
 * @param {string} str - String to escape.
 * @returns {string} Escaped string.
 */
export function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}

/**
 * Generate a unique ID.
 * @returns {string} Unique ID.
 */
export function generateId() {
    return `se_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
}

/**
 * Format relative time (e.g., "2 minutes ago").
 * @param {Date|string} date - Date to format.
 * @returns {string} Formatted time.
 */
export function formatRelativeTime(date) {
    const now = new Date();
    const then = new Date(date);
    const diffMs = now - then;
    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
    const diffHour = Math.floor(diffMin / 60);

    if (diffSec < 60) return 'Just now';
    if (diffMin < 60) return `${diffMin}m ago`;
    if (diffHour < 24) return `${diffHour}h ago`;

    return then.toLocaleDateString();
}

/**
 * Sort products by a column.
 * @param {Array} products - Products array.
 * @param {string} columnKey - Column key to sort by.
 * @param {string} direction - 'asc' or 'desc'.
 * @returns {Array} Sorted products.
 */
export function sortProducts(products, columnKey, direction) {
    return [...products].sort((a, b) => {
        let aVal = columnKey === 'post_title' ? a.title : a.specs[columnKey];
        let bVal = columnKey === 'post_title' ? b.title : b.specs[columnKey];

        // Handle null/undefined.
        if (aVal === null || aVal === undefined) aVal = '';
        if (bVal === null || bVal === undefined) bVal = '';

        // Handle numbers.
        const aNum = parseFloat(aVal);
        const bNum = parseFloat(bVal);
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return direction === 'asc' ? aNum - bNum : bNum - aNum;
        }

        // String comparison.
        const aStr = String(aVal).toLowerCase();
        const bStr = String(bVal).toLowerCase();

        if (direction === 'asc') {
            return aStr.localeCompare(bStr);
        }
        return bStr.localeCompare(aStr);
    });
}

/**
 * Filter products by search query.
 * @param {Array} products - Products array.
 * @param {string} query - Search query.
 * @returns {Array} Filtered products.
 */
export function filterProducts(products, query) {
    if (!query || !query.trim()) {
        return products;
    }

    const searchLower = query.toLowerCase().trim();
    return products.filter(product => {
        return product.title.toLowerCase().includes(searchLower);
    });
}

/**
 * Get the storage key for column visibility.
 * @param {string} productType - Product type.
 * @returns {string} Storage key.
 */
export function getColumnStorageKey(productType) {
    return `erh_se_columns_${productType}`;
}

/**
 * Get the storage key for history.
 * @returns {string} Storage key.
 */
export function getHistoryStorageKey() {
    return 'erh_se_history';
}

/**
 * Make a REST API request.
 * @param {string} endpoint - Endpoint path.
 * @param {Object} options - Fetch options.
 * @returns {Promise<Object>} Response data.
 */
export async function apiRequest(endpoint, options = {}) {
    const { restUrl, nonce } = window.erhSpecEditor;

    const url = restUrl + endpoint;
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce,
        },
    };

    const response = await fetch(url, { ...defaultOptions, ...options });
    const data = await response.json();

    if (!response.ok) {
        throw new Error(data.message || 'API request failed');
    }

    return data;
}

/**
 * Get column schema by key.
 * @param {Array} columns - Columns array.
 * @param {string} key - Column key.
 * @returns {Object|null} Column schema.
 */
export function getColumnByKey(columns, key) {
    return columns.find(col => col.key === key) || null;
}

/**
 * Check if a column should be visible by default.
 * @param {Object} column - Column schema.
 * @returns {boolean} True if visible by default.
 */
export function isColumnVisibleByDefault(column) {
    // Always show pinned columns.
    if (column.pinned) return true;

    // Hide instructions/help text columns by default.
    if (column.key.includes('instructions') || column.key.includes('help')) {
        return false;
    }

    return true;
}

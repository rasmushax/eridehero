/**
 * Calculator Utilities
 *
 * Shared functions for calculator components.
 */

/**
 * Format a number with locale-specific thousand separators.
 * @param {number} num - Number to format
 * @param {number} decimals - Decimal places (default: 0)
 * @returns {string} Formatted number
 */
export function formatNumber(num, decimals = 0) {
    return num.toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

/**
 * Format a number as percentage.
 * @param {number} num - Number to format (0-100 scale)
 * @param {number} decimals - Decimal places (default: 1)
 * @returns {string} Formatted percentage with % symbol
 */
export function formatPercent(num, decimals = 1) {
    return num.toFixed(decimals) + '%';
}

/**
 * Parse a numeric input value, returning 0 for invalid input.
 * @param {HTMLInputElement} input - Input element
 * @param {number} fallback - Fallback value (default: 0)
 * @returns {number} Parsed number
 */
export function parseInputValue(input, fallback = 0) {
    const value = parseFloat(input?.value);
    return isNaN(value) ? fallback : value;
}

/**
 * Get all input values from a container as an object.
 * @param {HTMLElement} container - Container element
 * @returns {Object} Object with input names as keys
 */
export function getInputValues(container) {
    const inputs = container.querySelectorAll('[data-input]');
    const values = {};

    inputs.forEach(input => {
        const name = input.dataset.input;
        values[name] = parseInputValue(input);
    });

    return values;
}

/**
 * Set result values in the DOM.
 * @param {HTMLElement} container - Container element
 * @param {Object} results - Object with result names as keys
 */
export function setResults(container, results) {
    Object.entries(results).forEach(([name, value]) => {
        const el = container.querySelector(`[data-result="${name}"]`);
        if (el) {
            el.textContent = value;
            el.classList.remove('result-value--loading');
        }
    });
}

/**
 * Create a debounced version of a function.
 * @param {Function} fn - Function to debounce
 * @param {number} delay - Delay in milliseconds (default: 150)
 * @returns {Function} Debounced function
 */
export function debounce(fn, delay = 150) {
    let timer = null;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
}

/**
 * Clamp a number between min and max values.
 * @param {number} num - Number to clamp
 * @param {number} min - Minimum value
 * @param {number} max - Maximum value
 * @returns {number} Clamped number
 */
export function clamp(num, min, max) {
    return Math.min(Math.max(num, min), max);
}

/**
 * Bind input change handlers with debounced calculation.
 * @param {HTMLElement} container - Container element
 * @param {Function} calculateFn - Function to call on input change
 * @param {number} debounceMs - Debounce delay (default: 150)
 */
export function bindInputHandlers(container, calculateFn, debounceMs = 150) {
    const debouncedCalc = debounce(calculateFn, debounceMs);

    container.addEventListener('input', (e) => {
        if (e.target.matches('[data-input]')) {
            debouncedCalc();
        }
    });

    container.addEventListener('change', (e) => {
        if (e.target.matches('[data-input]')) {
            calculateFn();
        }
    });
}

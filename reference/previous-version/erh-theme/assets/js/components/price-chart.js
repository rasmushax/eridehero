/**
 * Price Chart Module
 *
 * Handles price history chart rendering using Frappe Charts.
 * Designed to be initialized with data passed from PHP (wp_localize_script).
 *
 * @module components/price-chart
 */

/**
 * Format price for chart display (consistent 2-decimal formatting)
 * @param {number} price - Price value
 * @param {string} currency - Currency code
 * @returns {string} Formatted price
 */
function formatChartPrice(price, currency = 'USD') {
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
    return `${config.symbol}${formatted}`;
}

/**
 * Create default options with currency support
 * @param {string} currency - Currency code for formatting
 * @returns {Object} Default chart options
 */
function createDefaultOptions(currency = 'USD') {
    return {
        height: 180,
        colors: ['#5e2ced'],
        axisOptions: {
            xAxisMode: 'tick',
            xIsSeries: true,
            shortenYAxisNumbers: true,
            yAxisMode: 'tick'   // Minimal y-axis
        },
        lineOptions: {
            regionFill: 1,      // Fill area under line (like PriceRunner)
            hideDots: 1,        // Hide dots by default (CSS shows on hover)
            dotSize: 5,
            heatline: 0,
            spline: 0           // Sharp lines for price data (not curved)
        },
        tooltipOptions: {
            formatTooltipX: d => d,
            formatTooltipY: d => formatChartPrice(d, currency)
        },
        animate: 0, // Disable animation for performance
        barOptions: {
            spaceRatio: 0.2
        },
        truncateLegends: true
    };
}

/**
 * Initialize a price history chart
 *
 * @param {string|HTMLElement} container - Selector or element for chart container
 * @param {Object} data - Chart data
 * @param {string[]} data.labels - Date/time labels for x-axis
 * @param {number[]} data.values - Price values for y-axis
 * @param {Object} [options] - Override default chart options
 * @param {string} [options.currency] - Currency code for price formatting (default: 'USD')
 * @returns {Object|null} Frappe Chart instance or null if initialization fails
 */
export function initPriceChart(container, data, options = {}) {
    // Validate Frappe Charts is loaded
    if (typeof frappe === 'undefined' || !frappe.Chart) {
        console.warn('Frappe Charts not loaded. Price chart cannot be initialized.');
        return null;
    }

    // Get container element
    const el = typeof container === 'string'
        ? document.querySelector(container)
        : container;

    if (!el) {
        console.warn('Price chart container not found:', container);
        return null;
    }

    // Validate data
    if (!data?.labels?.length || !data?.values?.length) {
        console.warn('Invalid chart data provided');
        return null;
    }

    // Get currency from options or default to USD
    const currency = options.currency || 'USD';

    // Create default options with currency support
    const defaultOptions = createDefaultOptions(currency);

    // Merge options (user options override defaults)
    const chartOptions = {
        ...defaultOptions,
        ...options,
        data: {
            labels: data.labels,
            datasets: [{ values: data.values }]
        },
        type: 'line'
    };

    try {
        const chart = new frappe.Chart(el, chartOptions);

        // Store reference on element for later access
        el._priceChart = chart;
        el._priceChartCurrency = currency;

        return chart;
    } catch (error) {
        console.error('Failed to initialize price chart:', error);
        return null;
    }
}

/**
 * Update an existing price chart with new data
 *
 * @param {string|HTMLElement} container - Selector or element for chart container
 * @param {Object} data - New chart data
 * @param {string[]} data.labels - Date/time labels
 * @param {number[]} data.values - Price values
 */
export function updatePriceChart(container, data) {
    const el = typeof container === 'string'
        ? document.querySelector(container)
        : container;

    if (!el?._priceChart) {
        console.warn('No chart instance found on container');
        return;
    }

    el._priceChart.update({
        labels: data.labels,
        datasets: [{ values: data.values }]
    });
}

/**
 * Initialize price chart period toggles
 * Handles switching between different time periods (3M, 6M, 1Y, All)
 *
 * @param {string|HTMLElement} container - Chart container
 * @param {Object} periodData - Data for each period
 * @param {Object} periodData.3m - 3-month data { labels, values }
 * @param {Object} periodData.6m - 6-month data { labels, values }
 * @param {Object} periodData.1y - 1-year data { labels, values }
 * @param {Object} periodData.all - All-time data { labels, values }
 */
export function initPeriodToggles(container, periodData) {
    const chartContainer = typeof container === 'string'
        ? document.querySelector(container)
        : container;

    if (!chartContainer) return;

    // Find the period toggle buttons (sibling/parent structure)
    // Look for either .price-intel-history (new) or .price-intel-chart (legacy)
    const chartSection = chartContainer.closest('.price-intel-history') ||
                        chartContainer.closest('.price-intel-chart');
    if (!chartSection) return;

    const toggles = chartSection.querySelectorAll('.price-intel-chart-period button');
    if (!toggles.length) return;

    toggles.forEach(btn => {
        btn.addEventListener('click', () => {
            // Get period from button text
            const period = btn.textContent.trim().toLowerCase().replace(' ', '');
            const data = periodData[period];

            if (!data) {
                console.warn('No data for period:', period);
                return;
            }

            // Update active state
            toggles.forEach(b => b.classList.remove('is-active'));
            btn.classList.add('is-active');

            // Update chart
            updatePriceChart(chartContainer, data);
        });
    });
}

/**
 * Auto-initialize all price charts on page
 * Looks for elements with [data-price-chart] attribute
 * Expects data to be in window.erhData.priceChartData[chartId]
 * Chart data can include 'currency' property for geo-aware formatting
 */
export function autoInit() {
    const charts = document.querySelectorAll('[data-price-chart]');

    charts.forEach(el => {
        const chartId = el.dataset.priceChart || 'default';
        const chartData = window.erhData?.priceChartData?.[chartId];

        if (chartData) {
            // Pass currency from chart data for geo-aware price formatting
            const options = chartData.currency ? { currency: chartData.currency } : {};
            const chart = initPriceChart(el, chartData.current || chartData, options);

            // Initialize period toggles if period data provided
            if (chartData.periods) {
                initPeriodToggles(el, chartData.periods);
            }
        }
    });
}

export default {
    init: initPriceChart,
    update: updatePriceChart,
    initPeriodToggles,
    autoInit
};

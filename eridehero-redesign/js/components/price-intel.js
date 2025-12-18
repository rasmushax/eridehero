/**
 * Price Intelligence Component
 *
 * Displays geo-aware pricing with retailer comparison and price history.
 * Hydrates server-rendered shell with dynamic data based on user's region.
 */

import { getUserGeo, formatPrice, getCurrencySymbol } from '../services/geo-price.js';
import { createChart } from './chart.js';

// API endpoint
const ERH_REST_URL = window.erhData?.restUrl || '/wp-json/erh/v1/';

/**
 * Initialize all price intel components on the page
 */
export function initPriceIntel() {
    const components = document.querySelectorAll('[data-price-intel]');
    components.forEach(component => {
        new PriceIntelComponent(component);
    });
}

/**
 * Price Intel Component Class
 */
class PriceIntelComponent {
    constructor(element) {
        this.el = element;
        this.productId = element.dataset.productId;
        this.userGeo = null;
        this.prices = [];
        this.priceHistory = null;
        this.currentPeriod = '6m';

        // Element references
        this.bestPriceLink = element.querySelector('[data-best-price-link]');
        this.bestPriceEl = element.querySelector('[data-best-price]');
        this.bestRetailerLogo = element.querySelector('[data-best-retailer-logo]');
        this.priceVerdict = element.querySelector('[data-price-verdict]');
        this.buyCta = element.querySelector('[data-buy-cta]');
        this.buyCtaText = element.querySelector('[data-buy-cta-text]');
        this.updatedTime = element.querySelector('[data-updated-time]');
        this.retailerList = element.querySelector('[data-retailer-list]');
        this.priceHistorySection = element.querySelector('[data-price-history]');
        this.periodButtons = element.querySelector('[data-period-buttons]');
        this.chartContainer = element.querySelector('[data-erh-chart]');
        this.periodLabel = element.querySelector('[data-period-label]');
        this.periodAvg = element.querySelector('[data-period-avg]');
        this.periodLowLabel = element.querySelector('[data-period-low-label]');
        this.periodLow = element.querySelector('[data-period-low]');
        this.periodLowMeta = element.querySelector('[data-period-low-meta]');
        this.noPricesEl = element.querySelector('[data-no-prices]');
        this.regionNotice = element.querySelector('[data-region-notice]');
        this.regionNoticeText = element.querySelector('[data-region-notice-text]');

        this.init();
    }

    async init() {
        try {
            // Detect user's geo
            this.userGeo = await getUserGeo();
            console.log('[PriceIntel] User geo:', this.userGeo);
            console.log('[PriceIntel] Product ID:', this.productId);

            // Fetch prices
            await this.fetchPrices();

            // Fetch price history (parallel, non-blocking)
            this.fetchPriceHistory();

            // Set up period button listeners
            this.setupPeriodButtons();

        } catch (error) {
            console.error('[PriceIntel] Initialization failed:', error);
            this.showNoPrices();
        }
    }

    /**
     * Fetch prices from the ERH REST API
     */
    async fetchPrices() {
        try {
            const url = `${ERH_REST_URL}prices/${this.productId}?geo=${this.userGeo.geo}`;
            console.log('[PriceIntel] Fetching prices from:', url);

            const response = await fetch(url);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            console.log('[PriceIntel] Prices response:', data);

            if (!data.offers || data.offers.length === 0) {
                console.log('[PriceIntel] No offers found');
                this.showNoPrices();
                return;
            }

            this.prices = data.offers;
            console.log('[PriceIntel] Offers:', this.prices);
            this.renderPrices();

        } catch (error) {
            console.error('[PriceIntel] Failed to fetch prices:', error);
            this.showNoPrices();
        }
    }

    /**
     * Fetch price history from the ERH REST API
     */
    async fetchPriceHistory() {
        try {
            const url = `${ERH_REST_URL}prices/${this.productId}/history?geo=${this.userGeo.geo}`;
            console.log('[PriceIntel] Fetching price history from:', url);

            const response = await fetch(url);

            if (!response.ok) {
                console.log('[PriceIntel] Price history response not OK:', response.status);
                // Price history is optional, don't show error
                return;
            }

            const data = await response.json();
            console.log('[PriceIntel] Price history response:', data);

            if (!data.history || data.history.length === 0) {
                console.log('[PriceIntel] No price history data found');
                // No price history available
                return;
            }

            this.priceHistory = data;
            console.log('[PriceIntel] Price history data:', this.priceHistory);
            this.renderPriceHistory();

        } catch (error) {
            console.error('[PriceIntel] Failed to fetch price history:', error);
            // Price history is optional, component still works without it
        }
    }

    /**
     * Render prices to the DOM
     */
    renderPrices() {
        if (this.prices.length === 0) {
            this.showNoPrices();
            return;
        }

        const best = this.prices[0];
        const currency = best.currency || 'USD';
        const symbol = getCurrencySymbol(currency);

        // Update best price header
        if (this.bestPriceEl) {
            this.bestPriceEl.textContent = formatPrice(best.price, currency);
        }

        if (this.bestPriceLink) {
            this.bestPriceLink.href = best.url || '#';
        }

        // Update retailer logo
        if (this.bestRetailerLogo) {
            if (best.logo_url) {
                this.bestRetailerLogo.innerHTML = `<img src="${best.logo_url}" alt="${best.retailer}" class="price-intel-retailer-logo">`;
            } else {
                this.bestRetailerLogo.innerHTML = `<span class="price-intel-retailer-name">${best.retailer}</span>`;
            }
        }

        // Update buy CTA
        if (this.buyCta) {
            this.buyCta.href = best.url || '#';
        }
        if (this.buyCtaText) {
            this.buyCtaText.textContent = `Buy at ${best.retailer}`;
        }

        // Update timestamp
        if (this.updatedTime && best.last_updated) {
            this.updatedTime.textContent = `Updated ${this.formatTimeAgo(best.last_updated)}`;
        }

        // Render retailer list
        this.renderRetailerList();
    }

    /**
     * Render the retailer comparison list
     */
    renderRetailerList() {
        if (!this.retailerList || this.prices.length === 0) return;

        const best = this.prices[0];
        const currency = best.currency || 'USD';

        const html = this.prices.map((offer, index) => {
            const isBest = index === 0;
            const bestClass = isBest ? 'price-intel-retailer-row--best' : '';
            const stockClass = offer.in_stock ? 'price-intel-retailer-stock--in' : 'price-intel-retailer-stock--out';
            const stockIcon = offer.in_stock ? 'check' : 'x';
            const stockText = offer.in_stock ? 'In stock' : 'Out of stock';

            // Logo or text fallback
            const logoHtml = offer.logo_url
                ? `<img src="${offer.logo_url}" alt="${offer.retailer}" class="price-intel-retailer-logo">`
                : `<span class="price-intel-retailer-logo-text">${offer.retailer}</span>`;

            // Badge for best price
            const badgeHtml = isBest
                ? '<span class="price-intel-retailer-badge">Best price</span>'
                : '';

            return `
                <a href="${offer.url || '#'}" class="price-intel-retailer-row ${bestClass}" target="_blank" rel="nofollow noopener">
                    ${logoHtml}
                    <div class="price-intel-retailer-info">
                        <span class="price-intel-retailer-name">${offer.retailer}</span>
                        <span class="price-intel-retailer-stock ${stockClass}">
                            <svg class="icon" aria-hidden="true"><use href="#icon-${stockIcon}"></use></svg>
                            ${stockText}
                        </span>
                    </div>
                    <div class="price-intel-retailer-price">
                        <span class="price-intel-retailer-amount">${formatPrice(offer.price, currency)}</span>
                        ${badgeHtml}
                    </div>
                    <svg class="icon price-intel-retailer-arrow" aria-hidden="true"><use href="#icon-external-link"></use></svg>
                </a>
            `;
        }).join('');

        this.retailerList.innerHTML = html;
    }

    /**
     * Render price history chart and stats
     */
    renderPriceHistory() {
        if (!this.priceHistory || !this.priceHistory.history || this.priceHistory.history.length === 0) {
            return;
        }

        // Show the price history section
        if (this.priceHistorySection) {
            this.priceHistorySection.style.display = '';
        }

        const history = this.priceHistory.history;
        const currencySymbol = this.priceHistory.currency_symbol || '$';

        if (history.length === 0) return;

        // Show fallback notice if using US prices for non-US user
        if (this.priceHistory.used_fallback && this.regionNotice) {
            this.showRegionNotice('Showing US price history. Local price history not yet available.');
        }

        // Prepare chart data
        const chartData = this.prepareChartData(history);

        // Initialize chart
        this.initChart(chartData, currencySymbol);

        // Update stats using server-provided statistics
        this.updateStatsFromServer(currencySymbol);
    }

    /**
     * Prepare chart data with period filtering
     */
    prepareChartData(history) {
        const now = new Date();
        const periods = {
            '3m': 90,
            '6m': 180,
            '1y': 365,
            'all': Infinity
        };

        const result = {};

        Object.keys(periods).forEach(period => {
            const days = periods[period];
            const cutoff = days === Infinity ? new Date(0) : new Date(now - days * 24 * 60 * 60 * 1000);

            const filteredData = history.filter(point => {
                const date = new Date(point.x || point.date);
                return date >= cutoff;
            });

            result[period] = {
                values: filteredData.map(p => p.y),
                dates: filteredData.map(p => this.formatChartDate(p.x || p.date)),
                stores: filteredData.map(p => p.domain || ''),
                raw: filteredData
            };
        });

        return result;
    }

    /**
     * Initialize the chart
     */
    initChart(chartData, currencySymbol) {
        if (!this.chartContainer) {
            console.log('[PriceIntel] No chart container found');
            return;
        }

        const periodData = chartData[this.currentPeriod];

        if (!periodData || periodData.values.length === 0) {
            console.log('[PriceIntel] No period data for', this.currentPeriod);
            return;
        }

        console.log('[PriceIntel] Initializing chart with', periodData.values.length, 'points');

        // Create the chart
        this.chart = createChart(this.chartContainer, {
            formatValue: (v) => `${currencySymbol}${v}`,
            lineColor: '#5e2ced',
            areaColor: 'rgba(94, 44, 237, 0.1)',
            showYAxis: true,
            yAxisInside: true,
            showXAxis: true,
            animate: true
        });

        if (this.chart) {
            this.chart.setData(periodData);
        }
    }

    /**
     * Update stats from server-provided statistics (more accurate).
     */
    updateStatsFromServer(currencySymbol) {
        const stats = this.priceHistory.statistics;
        if (!stats) {
            // Fall back to client-side calculation if no server stats
            const chartData = this.prepareChartData(this.priceHistory.history);
            this.updateStats(chartData, currencySymbol);
            return;
        }

        // Period label (server returns all-time stats, we label based on data range)
        const periodLabels = {
            '3m': '3-month',
            '6m': '6-month',
            '1y': '12-month',
            'all': 'All-time'
        };

        // Update avg
        if (this.periodLabel) {
            this.periodLabel.textContent = `${periodLabels[this.currentPeriod]} avg`;
        }
        if (this.periodAvg) {
            this.periodAvg.textContent = `${currencySymbol}${Math.round(stats.average)}`;
        }

        // Update low
        if (this.periodLowLabel) {
            this.periodLowLabel.textContent = `${periodLabels[this.currentPeriod]} low`;
        }
        if (this.periodLow) {
            this.periodLow.textContent = `${currencySymbol}${Math.round(stats.lowest)}`;
        }
        if (this.periodLowMeta && stats.lowest_date) {
            const dateStr = this.formatChartDate(stats.lowest_date);
            const store = stats.lowest_store || '';
            this.periodLowMeta.textContent = store ? `${dateStr} · ${store}` : dateStr;
        }

        // Update price verdict
        this.updatePriceVerdict(stats.average, currencySymbol);
    }

    /**
     * Update stats based on current period (client-side calculation).
     */
    updateStats(chartData, currencySymbol) {
        const periodData = chartData[this.currentPeriod];

        if (!periodData || periodData.values.length === 0) return;

        const values = periodData.values;
        const avg = values.reduce((a, b) => a + b, 0) / values.length;
        const min = Math.min(...values);
        const minIndex = values.indexOf(min);

        // Period label mapping
        const periodLabels = {
            '3m': '3-month',
            '6m': '6-month',
            '1y': '12-month',
            'all': 'All-time'
        };

        // Update avg
        if (this.periodLabel) {
            this.periodLabel.textContent = `${periodLabels[this.currentPeriod]} avg`;
        }
        if (this.periodAvg) {
            this.periodAvg.textContent = `${currencySymbol}${Math.round(avg)}`;
        }

        // Update low
        if (this.periodLowLabel) {
            this.periodLowLabel.textContent = `${periodLabels[this.currentPeriod]} low`;
        }
        if (this.periodLow) {
            this.periodLow.textContent = `${currencySymbol}${Math.round(min)}`;
        }
        if (this.periodLowMeta && periodData.dates[minIndex]) {
            const store = periodData.stores[minIndex] || '';
            const date = periodData.dates[minIndex];
            this.periodLowMeta.textContent = store ? `${date} · ${store}` : date;
        }

        // Update price verdict
        this.updatePriceVerdict(avg, currencySymbol);
    }

    /**
     * Update the price verdict badge
     */
    updatePriceVerdict(avgPrice, currencySymbol) {
        if (!this.priceVerdict || this.prices.length === 0) return;

        const currentPrice = this.prices[0].price;
        const diff = ((currentPrice - avgPrice) / avgPrice) * 100;

        if (Math.abs(diff) < 1) {
            // Within 1% of average
            this.priceVerdict.style.display = 'none';
            return;
        }

        this.priceVerdict.style.display = '';

        if (diff < 0) {
            // Below average (good)
            this.priceVerdict.textContent = `↓ ${Math.abs(Math.round(diff))}% below avg`;
            this.priceVerdict.classList.remove('price-intel-verdict--high');
            this.priceVerdict.classList.add('price-intel-verdict--low');
        } else {
            // Above average (not great)
            this.priceVerdict.textContent = `↑ ${Math.round(diff)}% above avg`;
            this.priceVerdict.classList.remove('price-intel-verdict--low');
            this.priceVerdict.classList.add('price-intel-verdict--high');
        }
    }

    /**
     * Set up period button click handlers
     */
    setupPeriodButtons() {
        if (!this.periodButtons) return;

        const buttons = this.periodButtons.querySelectorAll('button');
        buttons.forEach(btn => {
            btn.addEventListener('click', () => {
                const period = btn.dataset.period;
                if (!period) return;

                // Update active state
                buttons.forEach(b => b.classList.remove('is-active'));
                btn.classList.add('is-active');

                // Update current period and refresh
                this.currentPeriod = period;

                if (this.priceHistory && this.priceHistory.history) {
                    const chartData = this.prepareChartData(this.priceHistory.history);
                    const periodData = chartData[period];
                    const currencySymbol = this.priceHistory.currency_symbol || '$';

                    // Update chart
                    if (this.chart && periodData) {
                        this.chart.setData(periodData);
                    }

                    // Update stats (use client-side for period filtering)
                    this.updateStats(chartData, currencySymbol);
                }
            });
        });
    }

    /**
     * Show no prices state
     */
    showNoPrices() {
        if (this.noPricesEl) {
            this.noPricesEl.style.display = '';
        }

        // Hide other sections
        const header = this.el.querySelector('.price-intel-header');
        const retailers = this.el.querySelector('.price-intel-retailers');

        if (header) header.style.display = 'none';
        if (retailers) retailers.style.display = 'none';
    }

    /**
     * Show region fallback notice
     */
    showRegionNotice(message) {
        if (this.regionNotice && this.regionNoticeText) {
            this.regionNoticeText.textContent = message;
            this.regionNotice.style.display = '';
        }
    }

    /**
     * Format a date for chart display
     */
    formatChartDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    /**
     * Format time ago string
     */
    formatTimeAgo(dateStr) {
        const date = new Date(dateStr);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);

        if (diff < 60) return 'just now';
        if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
        if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;

        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric'
        });
    }
}

// Auto-initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPriceIntel);
} else {
    initPriceIntel();
}

export default { initPriceIntel, PriceIntelComponent };

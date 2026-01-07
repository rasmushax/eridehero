/**
 * Price Intelligence Component
 *
 * Displays geo-aware pricing with retailer comparison and price history.
 * Hydrates server-rendered shell with dynamic data based on user's region.
 */

import { getUserGeo, formatPrice, getCurrencySymbol, getProductPrices, filterOffersForGeo } from '../services/geo-price.js';
import { createChart } from './chart.js';
import { PriceAlertModal } from './price-alert.js';

// Period labels for stats display
const PERIOD_LABELS = {
    '3m': '3-month',
    '6m': '6-month',
    '1y': '12-month',
    'all': 'All-time'
};

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
        this.data = null; // Combined API response
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
        this.buyCtaIcon = element.querySelector('[data-buy-cta-icon]');
        this.buyCtaSpinner = element.querySelector('[data-buy-cta-spinner]');
        this.updatedTime = element.querySelector('[data-updated-time]');
        this.retailerList = element.querySelector('[data-retailer-list]');
        this.priceHistorySection = element.querySelector('[data-price-history]');
        this.periodButtons = element.querySelector('[data-period-buttons]');
        this.chartContainer = element.querySelector('[data-erh-chart]');
        this.chartSkeleton = element.querySelector('[data-chart-skeleton]');
        this.periodLabel = element.querySelector('[data-period-label]');
        this.periodAvg = element.querySelector('[data-period-avg]');
        this.periodLowLabel = element.querySelector('[data-period-low-label]');
        this.periodLow = element.querySelector('[data-period-low]');
        this.periodLowMeta = element.querySelector('[data-period-low-meta]');
        this.noPricesEl = element.querySelector('[data-no-prices]');
        this.regionNotice = element.querySelector('[data-region-notice]');
        this.regionNoticeText = element.querySelector('[data-region-notice-text]');
        this.priceAlertBtn = element.querySelector('[data-price-alert-trigger]');

        // Product info for price alert
        this.productName = '';
        this.productImage = '';
        this.currentPrice = 0;
        this.currentCurrency = 'USD';

        this.init();
    }

    async init() {
        try {
            // Get product info from the page (for price alert modal)
            this.extractProductInfo();

            // Set up price alert button handler
            this.setupPriceAlertButton();

            // Detect user's geo
            this.userGeo = await getUserGeo();

            // Fetch all data (retailers + price history) in one call
            await this.fetchPriceData();

            // Set up period button listeners
            this.setupPeriodButtons();

        } catch (error) {
            this.showNoPrices();
        }
    }

    /**
     * Extract product info from the page for price alert modal
     */
    extractProductInfo() {
        // Try to get from the section header (section-product-name, section-product-img)
        const section = this.el.closest('section');
        if (section) {
            const nameEl = section.querySelector('.section-product-name');
            const imgEl = section.querySelector('.section-product-img');

            this.productName = nameEl?.textContent?.trim() || '';
            this.productImage = imgEl?.src || imgEl?.getAttribute('data-src') || '';
        }

        // Fallback to page title or other sources
        if (!this.productName) {
            const pageTitle = document.querySelector('.review-header-title, h1');
            this.productName = pageTitle?.textContent?.trim() || '';
        }

        // Fallback thumbnail from various sources
        if (!this.productImage) {
            // Try the review hero gallery
            const galleryImg = document.querySelector('.gallery-main-img');
            if (galleryImg) {
                this.productImage = galleryImg.src || galleryImg.getAttribute('data-src') || '';
            }
        }

        // Final fallback: Open Graph image
        if (!this.productImage) {
            const ogImage = document.querySelector('meta[property="og:image"]');
            this.productImage = ogImage?.content || '';
        }
    }

    /**
     * Set up price alert button click handler
     */
    setupPriceAlertButton() {
        if (!this.priceAlertBtn) return;

        // Prevent the default modal trigger behavior
        this.priceAlertBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();

            PriceAlertModal.open({
                productId: parseInt(this.productId, 10),
                productName: this.productName,
                productImage: this.productImage,
                currentPrice: this.currentPrice,
                currency: this.currentCurrency
            });
        });
    }

    /**
     * Fetch all price data (retailers + history) from the ERH REST API
     * Uses shared getProductPrices() for caching and request deduplication.
     */
    async fetchPriceData() {
        try {
            const data = await getProductPrices(parseInt(this.productId, 10), this.userGeo.geo);

            if (!data) {
                throw new Error('No data returned');
            }

            this.data = data;

            // Handle retailers - filter to only show geo-appropriate offers
            if (!data.offers || data.offers.length === 0) {
                this.showNoPrices();
                return;
            }

            // Filter offers to only show those matching user's currency/geo
            this.prices = filterOffersForGeo(data.offers, this.userGeo);

            if (this.prices.length === 0) {
                this.showNoPrices();
                return;
            }

            this.renderPrices();

            // Handle price history - DON'T show fallback US data, show "no history" instead
            if (data.history && data.history.data && data.history.data.length > 0 && !data.history.used_fallback) {
                this.priceHistory = {
                    history: data.history.data,
                    currency_symbol: data.history.currency_symbol,
                    statistics: data.history.statistics,
                    used_fallback: false,
                    available_geos: data.history.available_geos
                };
                this.renderPriceHistory();
            } else {
                // No local price history (don't use US fallback)
                this.showNoPriceHistory();
            }

        } catch (error) {
            this.showNoPrices();
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

        // Store current price and currency for price alert modal
        this.currentPrice = best.price || 0;
        this.currentCurrency = currency;

        // Update hero price (shares API data, avoids duplicate fetch)
        this.updateHeroPrice(best, currency);

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
            this.buyCtaText.style.display = '';
        }
        if (this.buyCtaIcon) {
            this.buyCtaIcon.style.display = '';
        }
        if (this.buyCtaSpinner) {
            this.buyCtaSpinner.style.display = 'none';
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
     * Update hero price element (outside price-intel component).
     * Shares the same API data to avoid duplicate fetches.
     */
    updateHeroPrice(best, currency) {
        const heroPrice = document.querySelector('[data-hero-price]');
        if (!heroPrice) return;

        const amountEl = heroPrice.querySelector('.hero-price-amount');
        const storesEl = heroPrice.querySelector('.hero-price-stores');

        if (amountEl && best?.price) {
            amountEl.textContent = formatPrice(best.price, currency);
            heroPrice.classList.add('has-price');

            // Count in-stock offers
            const inStockCount = this.prices.filter(o => o.in_stock === true).length;
            if (storesEl && inStockCount > 0) {
                storesEl.textContent = inStockCount === 1 ? 'at 1 store' : `at ${inStockCount} stores`;
            }
        } else if (amountEl) {
            amountEl.textContent = 'See prices';
            heroPrice.classList.add('no-price');
        }
    }

    /**
     * Render price history chart and stats
     */
    renderPriceHistory() {
        if (!this.priceHistory || !this.priceHistory.history || this.priceHistory.history.length === 0) {
            this.hidePriceHistory();
            return;
        }

        const history = this.priceHistory.history;
        const currencySymbol = this.priceHistory.currency_symbol || '$';

        if (history.length === 0) {
            this.hidePriceHistory();
            return;
        }

        // Remove chart skeleton
        if (this.chartSkeleton) {
            this.chartSkeleton.remove();
        }

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
     * Hide price history section when no data available
     */
    hidePriceHistory() {
        if (this.priceHistorySection) {
            this.priceHistorySection.style.display = 'none';
        }
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
            return;
        }

        const periodData = chartData[this.currentPeriod];

        if (!periodData || periodData.values.length === 0) {
            return;
        }

        // Create the chart with currency-aware formatting
        this.chart = createChart(this.chartContainer, {
            currency: this.currentCurrency, // Pass currency for geo-aware formatting
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

        // Update avg
        if (this.periodLabel) {
            this.periodLabel.textContent = `${PERIOD_LABELS[this.currentPeriod]} avg`;
        }
        if (this.periodAvg) {
            this.periodAvg.textContent = formatPrice(stats.average, this.currentCurrency);
        }

        // Update low
        if (this.periodLowLabel) {
            this.periodLowLabel.textContent = `${PERIOD_LABELS[this.currentPeriod]} low`;
        }
        if (this.periodLow) {
            this.periodLow.textContent = formatPrice(stats.lowest, this.currentCurrency);
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

        // Update avg
        if (this.periodLabel) {
            this.periodLabel.textContent = `${PERIOD_LABELS[this.currentPeriod]} avg`;
        }
        if (this.periodAvg) {
            this.periodAvg.textContent = formatPrice(avg, this.currentCurrency);
        }

        // Update low
        if (this.periodLowLabel) {
            this.periodLowLabel.textContent = `${PERIOD_LABELS[this.currentPeriod]} low`;
        }
        if (this.periodLow) {
            this.periodLow.textContent = formatPrice(min, this.currentCurrency);
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
            this.priceVerdict.innerHTML = `<svg class="icon price-intel-verdict-icon" aria-hidden="true"><use href="#icon-arrow-down"></use></svg> ${Math.abs(Math.round(diff))}% below avg`;
            this.priceVerdict.classList.remove('price-intel-verdict--high');
            this.priceVerdict.classList.add('price-intel-verdict--low');
        } else {
            // Above average (not great)
            this.priceVerdict.innerHTML = `<svg class="icon price-intel-verdict-icon" aria-hidden="true"><use href="#icon-arrow-up"></use></svg> ${Math.round(diff)}% above avg`;
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
                buttons.forEach(b => {
                    b.classList.remove('is-active');
                    b.setAttribute('aria-selected', 'false');
                });
                btn.classList.add('is-active');
                btn.setAttribute('aria-selected', 'true');

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
     * Show empty state when no local price history is available.
     * Keep the price alert button visible since we do have current prices.
     */
    showNoPriceHistory() {
        if (!this.priceHistorySection) return;

        // Remove chart skeleton
        if (this.chartSkeleton) {
            this.chartSkeleton.remove();
        }

        // Hide period buttons (3M, 6M, 1Y, All)
        if (this.periodButtons) {
            this.periodButtons.style.display = 'none';
        }

        // Hide only the stat values (avg, low) but keep the price alert button
        // The button is in .price-intel-stat--action which we want to keep
        const statsSection = this.priceHistorySection.querySelector('.price-intel-stats');
        if (statsSection) {
            const statItems = statsSection.querySelectorAll('.price-intel-stat:not(.price-intel-stat--action)');
            statItems.forEach(item => {
                item.style.display = 'none';
            });
        }

        // Hide region notice if present
        if (this.regionNotice) {
            this.regionNotice.style.display = 'none';
        }

        // Show "No price history" message in the chart area
        const chartVisual = this.priceHistorySection.querySelector('.price-intel-chart-visual');
        if (chartVisual) {
            chartVisual.innerHTML = `
                <div class="price-intel-no-history">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 3v5h5"></path>
                        <path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"></path>
                        <path d="M12 7v5l4 2"></path>
                    </svg>
                    <span>No price history available for your region</span>
                </div>
            `;
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

/**
 * Initialize discontinued/no-pricing alternatives sections.
 * These are rendered with skeleton cards and hydrated with geo-aware pricing.
 */
async function initAlternatives() {
    const alternativesSections = document.querySelectorAll('[data-alternatives]');
    if (alternativesSections.length === 0) return;

    // Get user's geo
    const userGeo = await getUserGeo();
    const region = userGeo.geo;
    const currency = userGeo.currency;

    alternativesSections.forEach(section => {
        try {
            const data = JSON.parse(section.dataset.alternatives);
            if (!data || !Array.isArray(data)) return;

            const grid = section.querySelector('.discontinued-alternatives-grid');
            if (!grid) return;

            // Render the cards with geo-aware pricing
            grid.innerHTML = data.map(product => {
                const pricing = product.pricing || {};
                const regionPricing = pricing[region] || pricing['US'] || {};
                const price = regionPricing.current_price;
                const avg6m = regionPricing.avg_6m;
                const displayCurrency = pricing[region] ? currency : 'USD';

                // Calculate price indicator
                let indicatorHtml = '';
                if (price && avg6m && avg6m > 0) {
                    const diff = Math.round(((price - avg6m) / avg6m) * 100);
                    if (diff < -5) {
                        indicatorHtml = `
                            <span class="discontinued-alternative-indicator discontinued-alternative-indicator--below">
                                <svg class="icon" aria-hidden="true"><use href="#icon-arrow-down"></use></svg>${Math.abs(diff)}%
                            </span>`;
                    } else if (diff > 10) {
                        indicatorHtml = `
                            <span class="discontinued-alternative-indicator discontinued-alternative-indicator--above">
                                <svg class="icon" aria-hidden="true"><use href="#icon-arrow-up"></use></svg>${diff}%
                            </span>`;
                    }
                }

                // Price row HTML
                const priceRowHtml = price ? `
                    <div class="discontinued-alternative-price-row">
                        <span class="discontinued-alternative-price">${formatPrice(price, displayCurrency)}</span>
                        ${indicatorHtml}
                    </div>` : '';

                // Image HTML
                const imageHtml = product.image
                    ? `<img src="${product.image}" alt="" class="discontinued-alternative-img" loading="lazy">`
                    : `<div class="discontinued-alternative-img discontinued-alternative-img--placeholder"></div>`;

                // Specs HTML
                const specsHtml = product.specs
                    ? `<span class="discontinued-alternative-specs">${product.specs}</span>`
                    : '';

                return `
                    <a href="${product.url}" class="discontinued-alternative-card">
                        <div class="discontinued-alternative-img-wrap">
                            ${imageHtml}
                            ${priceRowHtml}
                        </div>
                        <div class="discontinued-alternative-info">
                            <span class="discontinued-alternative-name">${product.name}</span>
                            ${specsHtml}
                        </div>
                    </a>`;
            }).join('');
        } catch (e) {
            console.error('[PriceIntel] Failed to parse alternatives data:', e);
        }
    });
}

/**
 * Initialize successor card pricing (geo-aware).
 * The card is pre-rendered but the price is a skeleton until JS hydrates it.
 */
async function initSuccessorPricing() {
    const successorSections = document.querySelectorAll('[data-successor]');
    if (successorSections.length === 0) return;

    // Get user's geo
    const userGeo = await getUserGeo();
    const region = userGeo.geo;
    const currency = userGeo.currency;

    successorSections.forEach(section => {
        try {
            const data = JSON.parse(section.dataset.successor);
            if (!data || !data.pricing) return;

            const priceEl = section.querySelector('[data-successor-price]');
            if (!priceEl) return;

            const pricing = data.pricing;
            const regionPricing = pricing[region] || pricing['US'] || {};
            const price = regionPricing.current_price;
            const displayCurrency = pricing[region] ? currency : 'USD';

            if (price) {
                priceEl.textContent = formatPrice(price, displayCurrency);
            } else {
                // No price available - hide the price element
                priceEl.style.display = 'none';
            }
        } catch (e) {
            console.error('[PriceIntel] Failed to parse successor data:', e);
        }
    });
}

/**
 * Initialize all price intel functionality
 */
function initAll() {
    initPriceIntel();
    initAlternatives();
    initSuccessorPricing();
}

// Auto-initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
} else {
    initAll();
}

export default { initPriceIntel, PriceIntelComponent, initAlternatives, initSuccessorPricing };

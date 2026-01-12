/**
 * Listicle Item Block Component
 *
 * Handles:
 * - Tab switching with ARIA
 * - AJAX lazy-loading for specs and pricing tabs
 * - Geo-aware pricing via geo-price.js
 * - Price chart via chart.js
 * - Price alert modal integration
 * - YouTube video lightbox
 */

import { getUserGeo, formatPrice, getProductPrices, filterOffersForGeo, getCurrencySymbol } from '../services/geo-price.js';
import { createChart } from './chart.js';
import { PriceAlertModal } from './price-alert.js';
import { initPopovers } from './popover.js';
import { calculatePriceVerdict } from '../utils/pricing-ui.js';
import { videoLightbox } from './gallery.js';

// Period labels for stats
const PERIOD_LABELS = {
    '3m': '3-month',
    '6m': '6-month',
    '1y': '12-month',
    'all': 'All-time'
};

/**
 * Initialize all listicle item components on the page
 */
export function initListicleItems() {
    const items = document.querySelectorAll('[data-listicle-item]');
    items.forEach(item => new ListicleItemComponent(item));
}

/**
 * Listicle Item Component Class
 */
class ListicleItemComponent {
    constructor(element) {
        this.el = element;
        this.config = JSON.parse(element.dataset.listicleItem || '{}');
        this.productId = this.config.productId;
        this.categoryKey = this.config.categoryKey || 'escooter';
        this.hasPricing = this.config.hasPricing;

        // State
        this.userGeo = null;
        this.priceData = null;
        this.filteredOffers = null;
        this.specsLoaded = false;
        this.pricingLoaded = false;
        this.currentPeriod = '6m';
        this.chart = null;

        // Tab elements
        this.tabs = element.querySelectorAll('.listicle-item-tab');
        this.panels = element.querySelectorAll('.listicle-item-panel');

        // Initialize
        this.init();
    }

    async init() {
        this.setupTabNavigation();
        this.setupPriceAlertButtons();
        this.setupVideoLightbox();

        // Load geo and hydrate price bar immediately (not on tab switch)
        if (this.hasPricing) {
            this.userGeo = await getUserGeo();
            await this.hydratePriceBar();
        }
    }

    /**
     * Set up tab navigation with ARIA
     */
    setupTabNavigation() {
        this.tabs.forEach(tab => {
            tab.addEventListener('click', () => this.switchTab(tab.dataset.tab));

            // Keyboard navigation
            tab.addEventListener('keydown', (e) => {
                const tabsArray = Array.from(this.tabs);
                const currentIndex = tabsArray.indexOf(tab);

                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    const prevIndex = currentIndex > 0 ? currentIndex - 1 : tabsArray.length - 1;
                    tabsArray[prevIndex].focus();
                    this.switchTab(tabsArray[prevIndex].dataset.tab);
                } else if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    const nextIndex = currentIndex < tabsArray.length - 1 ? currentIndex + 1 : 0;
                    tabsArray[nextIndex].focus();
                    this.switchTab(tabsArray[nextIndex].dataset.tab);
                } else if (e.key === 'Home') {
                    e.preventDefault();
                    tabsArray[0].focus();
                    this.switchTab(tabsArray[0].dataset.tab);
                } else if (e.key === 'End') {
                    e.preventDefault();
                    tabsArray[tabsArray.length - 1].focus();
                    this.switchTab(tabsArray[tabsArray.length - 1].dataset.tab);
                }
            });
        });
    }

    /**
     * Switch to a tab
     */
    async switchTab(tabName) {
        // Update tab states
        this.tabs.forEach(tab => {
            const isActive = tab.dataset.tab === tabName;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tab.setAttribute('tabindex', isActive ? '0' : '-1');
        });

        // Update panel states
        this.panels.forEach(panel => {
            const isActive = panel.dataset.panel === tabName;
            panel.classList.toggle('is-active', isActive);
            panel.hidden = !isActive;
        });

        // Load tab content if needed
        if (tabName === 'specs' && !this.specsLoaded) {
            await this.loadSpecsTab();
        } else if (tabName === 'pricing' && !this.pricingLoaded && this.hasPricing) {
            await this.loadPricingTab();
        }
    }

    /**
     * Load specs tab via AJAX
     */
    async loadSpecsTab() {
        const panel = this.el.querySelector('[data-panel="specs"]');
        const loader = panel.querySelector('[data-loader]');
        const content = panel.querySelector('[data-specs-content]');

        if (!content) return;

        try {
            const response = await fetch(`${window.erhData.restUrl}listicle/specs`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.erhData.nonce
                },
                body: JSON.stringify({
                    product_id: this.productId,
                    category_key: this.categoryKey
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            // Hide loader and show content
            if (loader) loader.style.display = 'none';
            content.innerHTML = result.html;

            // Set up accordion toggle
            this.setupSpecsAccordion(content);

            // Re-initialize popovers for AJAX-loaded content
            initPopovers();

            this.specsLoaded = true;

        } catch (error) {
            console.error('[ListicleItem] Failed to load specs:', error);
            if (loader) loader.style.display = 'none';
            content.innerHTML = '<p class="listicle-item-error">Failed to load specifications.</p>';
        }
    }

    /**
     * Set up accordion toggle for spec groups
     */
    setupSpecsAccordion(container) {
        const accordion = container.querySelector('[data-specs-accordion]');
        if (!accordion) return;

        accordion.addEventListener('click', (e) => {
            const header = e.target.closest('.listicle-specs-group-header');
            if (!header) return;

            const group = header.closest('.listicle-specs-group');
            const content = group.querySelector('.listicle-specs-group-content');
            const isOpen = group.classList.contains('is-open');

            // Toggle state
            group.classList.toggle('is-open', !isOpen);
            header.setAttribute('aria-expanded', !isOpen);
            content.hidden = isOpen;
        });
    }

    /**
     * Hydrate price bar with geo-aware data (called on page load)
     */
    async hydratePriceBar() {
        const priceBar = this.el.querySelector('[data-price-bar]');
        if (!priceBar || !this.userGeo) return;

        try {
            // Fetch price data
            const data = await getProductPrices(this.productId, this.userGeo.geo);

            if (!data || !data.offers || data.offers.length === 0) {
                throw new Error('No pricing data');
            }

            this.priceData = data;

            // Filter offers for user's geo
            const filteredOffers = filterOffersForGeo(data.offers, this.userGeo);

            if (filteredOffers.length === 0) {
                throw new Error('No offers for region');
            }

            this.filteredOffers = filteredOffers;
            const bestOffer = filteredOffers[0];
            const currency = bestOffer.currency || this.userGeo.currency;

            // Store for price alert
            this.currentPrice = bestOffer.price;
            this.currentCurrency = currency;

            // Build verdict HTML using shared utility
            let verdictHtml = '';
            const avg6m = data.history?.statistics?.average;
            const verdict = calculatePriceVerdict(bestOffer.price, avg6m);
            if (verdict && verdict.shouldShow) {
                const icon = verdict.type === 'below' ? 'arrow-down' : 'arrow-up';
                const verdictClass = verdict.type === 'below' ? 'good' : 'high';
                verdictHtml = `<span class="listicle-item-verdict listicle-item-verdict--${verdictClass}">
                    <svg class="icon" aria-hidden="true"><use href="#icon-${icon}"></use></svg>
                    ${verdict.text}
                </span>`;
            }

            // Build retailer display
            const retailerHtml = bestOffer.logo_url
                ? `<span class="listicle-item-retailer-logo"><img src="${bestOffer.logo_url}" alt="${bestOffer.retailer}"></span>`
                : `<span class="listicle-item-retailer-name-text">${bestOffer.retailer}</span>`;

            // Hydrate best price container
            const bestPriceEl = priceBar.querySelector('[data-best-price]');
            if (bestPriceEl) {
                bestPriceEl.innerHTML = `
                    <a href="${bestOffer.tracked_url || bestOffer.url}" class="listicle-item-price-link" target="_blank" rel="nofollow noopener">
                        <span class="listicle-item-price-amount">${formatPrice(bestOffer.price, currency)}</span>
                        <span class="listicle-item-price-at">at</span>
                        ${retailerHtml}
                    </a>
                    ${verdictHtml}
                `;
            }

            // Hydrate buy button
            const buyBtn = priceBar.querySelector('[data-buy-btn]');
            if (buyBtn) {
                buyBtn.href = bestOffer.tracked_url || bestOffer.url;
                const buyText = buyBtn.querySelector('[data-buy-text]');
                const buyIcon = buyBtn.querySelector('[data-buy-icon]');
                const buySpinner = buyBtn.querySelector('[data-buy-spinner]');
                if (buyText) buyText.style.display = '';
                if (buyIcon) buyIcon.style.display = '';
                if (buySpinner) buySpinner.style.display = 'none';
            }

        } catch (error) {
            console.error('[ListicleItem] Failed to hydrate price bar:', error);
            priceBar.style.display = 'none';
        }
    }

    /**
     * Load pricing tab content (retailers + history) - uses cached priceData
     */
    async loadPricingTab() {
        const panel = this.el.querySelector('[data-panel="pricing"]');
        const loader = panel.querySelector('[data-loader]');
        const content = panel.querySelector('[data-pricing-content]');

        if (!content || !this.userGeo) return;

        try {
            // Use cached price data if available, otherwise fetch
            if (!this.priceData) {
                const data = await getProductPrices(this.productId, this.userGeo.geo);
                if (!data || !data.offers || data.offers.length === 0) {
                    throw new Error('No pricing data');
                }
                this.priceData = data;
                this.filteredOffers = filterOffersForGeo(data.offers, this.userGeo);
            }

            if (!this.filteredOffers || this.filteredOffers.length === 0) {
                throw new Error('No offers for region');
            }

            // Hide loader and render pricing tab content (retailers + history)
            if (loader) loader.style.display = 'none';
            content.innerHTML = this.renderPricingTabContent(this.filteredOffers, this.priceData);

            // Set up chart and interactions after rendering
            this.setupPricingInteractions(content, this.priceData);

            this.pricingLoaded = true;

        } catch (error) {
            console.error('[ListicleItem] Failed to load pricing:', error);
            if (loader) loader.style.display = 'none';
            content.innerHTML = this.renderNoPricing();
        }
    }

    /**
     * Render pricing tab content - retailers list + price history
     */
    renderPricingTabContent(offers, data) {
        const bestOffer = offers[0];
        const currency = bestOffer.currency || this.userGeo.currency;

        // Retailers list
        const retailersHtml = offers.map((offer, index) => {
            const isBest = index === 0;
            const rowClass = isBest ? 'listicle-item-retailer-row listicle-item-retailer-row--best' : 'listicle-item-retailer-row';

            const logoHtml = offer.logo_url
                ? `<div class="listicle-item-retailer-logo-wrap"><img src="${offer.logo_url}" alt="${offer.retailer}"></div>`
                : `<span class="listicle-item-retailer-logo-text">${offer.retailer}</span>`;

            const stockIcon = offer.in_stock ? 'check' : 'x';
            const stockClass = offer.in_stock ? '' : 'listicle-item-retailer-stock--out';
            const stockText = offer.in_stock ? 'In Stock' : 'Out of Stock';

            const badgeHtml = isBest ? '<span class="listicle-item-retailer-badge">Best price</span>' : '';

            return `
                <a href="${offer.tracked_url || offer.url}" class="${rowClass}" target="_blank" rel="nofollow noopener">
                    ${logoHtml}
                    <div class="listicle-item-retailer-info">
                        <span class="listicle-item-retailer-name">${offer.retailer}</span>
                        <span class="listicle-item-retailer-stock ${stockClass}">
                            <svg class="icon" aria-hidden="true"><use href="#icon-${stockIcon}"></use></svg>
                            ${stockText}
                        </span>
                    </div>
                    <div class="listicle-item-retailer-price-wrap">
                        <span class="listicle-item-retailer-price">${formatPrice(offer.price, currency)}</span>
                        ${badgeHtml}
                    </div>
                    <svg class="icon listicle-item-retailer-arrow" aria-hidden="true"><use href="#icon-chevron-right"></use></svg>
                </a>
            `;
        }).join('');

        // Check if we have valid price history
        const hasHistory = data.history?.data?.length > 0 && !data.history?.used_fallback;

        // Price history section
        let historyHtml = '';
        if (hasHistory) {
            const stats = data.history.statistics || {};
            const avgPrice = stats.average ? formatPrice(stats.average, currency) : '—';
            const lowPrice = stats.lowest ? formatPrice(stats.lowest, currency) : '—';
            const lowDate = stats.lowest_date || '';

            historyHtml = `
                <div class="listicle-item-history">
                    <div class="listicle-item-history-header">
                        <span class="listicle-item-history-title">Price history</span>
                        <div class="listicle-item-chart-periods" data-period-buttons>
                            <button type="button" data-period="3m">3M</button>
                            <button type="button" data-period="6m" class="is-active">6M</button>
                            <button type="button" data-period="1y">1Y</button>
                            <button type="button" data-period="all">All</button>
                        </div>
                    </div>
                    <div class="listicle-item-chart-visual" data-chart></div>
                    <div class="listicle-item-stats">
                        <div class="listicle-item-stat">
                            <span class="listicle-item-stat-label" data-period-label>6-month avg</span>
                            <span class="listicle-item-stat-value" data-period-avg>${avgPrice}</span>
                        </div>
                        <div class="listicle-item-stat">
                            <span class="listicle-item-stat-label" data-period-low-label>6-month low</span>
                            <span class="listicle-item-stat-value listicle-item-stat-value--low" data-period-low>${lowPrice}</span>
                            <span class="listicle-item-stat-meta" data-period-low-meta>${lowDate}</span>
                        </div>
                        <div class="listicle-item-stat listicle-item-stat--action">
                            <button type="button" class="btn btn-secondary btn-sm" data-pricing-alert>
                                <svg class="icon" aria-hidden="true"><use href="#icon-bell"></use></svg>
                                Set price alert
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        return `
            <div class="listicle-item-retailers">
                <div class="listicle-item-retailers-header">
                    <span class="listicle-item-retailers-title">Compare prices</span>
                </div>
                <div class="listicle-item-retailer-list">
                    ${retailersHtml}
                </div>
                <p class="listicle-item-disclosure">We may earn a commission from purchases.</p>
            </div>
            ${historyHtml}
        `;
    }

    /**
     * Render no pricing state
     */
    renderNoPricing() {
        return `
            <div class="listicle-item-no-pricing">
                <svg class="icon"><use href="#icon-tag"></use></svg>
                <p>No pricing data available for your region.</p>
            </div>
        `;
    }

    /**
     * Set up pricing interactions after rendering
     */
    setupPricingInteractions(container, data) {
        // Set up chart if we have history
        const chartContainer = container.querySelector('[data-chart]');
        if (chartContainer && data.history?.data?.length > 0) {
            this.initChart(chartContainer, data.history);
        }

        // Set up period buttons
        const periodButtons = container.querySelector('[data-period-buttons]');
        if (periodButtons) {
            periodButtons.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-period]');
                if (!btn) return;

                const period = btn.dataset.period;
                this.changePeriod(period, container, data);

                // Update button states
                periodButtons.querySelectorAll('button').forEach(b => {
                    b.classList.toggle('is-active', b === btn);
                });
            });
        }

        // Set up price alert button in pricing tab (could be in stats or standalone)
        const alertBtns = container.querySelectorAll('[data-pricing-alert]');
        alertBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                this.openPriceAlert();
            });
        });
    }

    /**
     * Initialize price history chart
     */
    initChart(container, historyData) {
        const currency = this.userGeo?.currency || 'USD';

        this.chart = createChart(container, {
            height: 180,
            type: 'line',
            showArea: true,
            showXAxis: true,
            showYAxis: true,
            yAxisInside: true,
            currency: currency
        });

        // Get 6m data by default
        const chartData = this.getChartDataForPeriod('6m', historyData.data);
        if (chartData) {
            this.chart.setData(chartData);
        }
    }

    /**
     * Get chart data for a period
     * History items have: x (date string), y (price value), domain (store name)
     */
    getChartDataForPeriod(period, historyData) {
        if (!historyData || !historyData.length) return null;

        const now = new Date();
        const periods = {
            '3m': 90,
            '6m': 180,
            '1y': 365,
            'all': Infinity
        };

        const days = periods[period] || 180;
        const cutoffDate = days === Infinity ? new Date(0) : new Date(now - days * 24 * 60 * 60 * 1000);

        const filtered = historyData.filter(item => {
            const itemDate = new Date(item.x || item.date);
            return itemDate >= cutoffDate;
        });

        if (filtered.length === 0) return null;

        return {
            dates: filtered.map(item => this.formatChartDate(item.x || item.date)),
            values: filtered.map(item => item.y),
            stores: filtered.map(item => item.domain || ''),
            raw: filtered
        };
    }

    /**
     * Format date for chart display (e.g., "Jan 15")
     */
    formatChartDate(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    /**
     * Change price history period
     * Recalculates stats client-side for the selected period (like price-intel)
     */
    changePeriod(period, container, data) {
        this.currentPeriod = period;

        // Get chart data for the new period
        const chartData = data.history?.data ? this.getChartDataForPeriod(period, data.history.data) : null;

        // Update chart
        if (this.chart && chartData) {
            this.chart.setData(chartData);
        }

        // Calculate stats client-side for this period
        const currency = this.userGeo?.currency || 'USD';

        const periodLabel = container.querySelector('[data-period-label]');
        const periodAvg = container.querySelector('[data-period-avg]');
        const periodLowLabel = container.querySelector('[data-period-low-label]');
        const periodLow = container.querySelector('[data-period-low]');
        const periodLowMeta = container.querySelector('[data-period-low-meta]');

        if (chartData && chartData.values.length > 0) {
            const values = chartData.values;
            const avg = values.reduce((a, b) => a + b, 0) / values.length;
            const min = Math.min(...values);
            const minIndex = values.indexOf(min);

            if (periodLabel) periodLabel.textContent = `${PERIOD_LABELS[period]} avg`;
            if (periodAvg) periodAvg.textContent = formatPrice(avg, currency);
            if (periodLowLabel) periodLowLabel.textContent = `${PERIOD_LABELS[period]} low`;
            if (periodLow) periodLow.textContent = formatPrice(min, currency);
            if (periodLowMeta) {
                const store = chartData.stores?.[minIndex] || '';
                const date = chartData.dates?.[minIndex] || '';
                periodLowMeta.textContent = store ? `${date} · ${store}` : date;
            }
        } else {
            if (periodLabel) periodLabel.textContent = `${PERIOD_LABELS[period]} avg`;
            if (periodAvg) periodAvg.textContent = '—';
            if (periodLowLabel) periodLowLabel.textContent = `${PERIOD_LABELS[period]} low`;
            if (periodLow) periodLow.textContent = '—';
            if (periodLowMeta) periodLowMeta.textContent = '';
        }
    }

    /**
     * Set up price alert buttons (image overlay and pricing tab)
     */
    setupPriceAlertButtons() {
        // Track button on image overlay
        const trackBtn = this.el.querySelector('.listicle-item-track-btn');
        if (trackBtn) {
            trackBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.openPriceAlert();
            });
        }
    }

    /**
     * Open price alert modal
     */
    openPriceAlert() {
        const productName = this.config.productName || this.el.querySelector('.listicle-item-title')?.textContent || '';
        const productImage = this.priceData?.product_image || '';

        PriceAlertModal.open({
            productId: this.productId,
            productName: productName,
            productImage: productImage,
            currentPrice: this.currentPrice || 0,
            currency: this.currentCurrency || this.userGeo?.currency || 'USD'
        });
    }

    /**
     * Set up YouTube video lightbox
     * Uses shared videoLightbox singleton from gallery.js
     */
    setupVideoLightbox() {
        const videoCard = this.el.querySelector('.listicle-item-video-card');
        if (!videoCard) return;

        videoCard.addEventListener('click', () => {
            const videoId = videoCard.dataset.video;
            if (!videoId) return;

            videoLightbox.open(videoId);
        });
    }
}

// Export for use in app.js
export default {
    init: initListicleItems
};

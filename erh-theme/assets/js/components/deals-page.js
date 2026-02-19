/**
 * Deals Page Component
 *
 * Handles the dedicated deals category page with:
 * - Period toggle (3m/6m/12m)
 * - Sort options
 * - Product cards (matching finder exactly)
 * - Compare checkboxes with comparison bar
 * - Geo-aware pricing
 */

import { getUserGeo, formatPrice, getCurrencySymbol } from '../services/geo-price.js';
import { PriceAlertModal } from './price-alert.js';
import { initCustomSelects } from './custom-select.js';
import { ComparisonBar } from './comparison-bar.js';
import { getRestUrl } from '../utils/api.js';

// Configuration
const CONFIG = {
    discountThreshold: -5,
    defaultPeriod: '6m',
    defaultSort: 'discount',
    maxCompare: 4,
};

/**
 * Initialize the deals page
 */
export async function initDealsPage() {
    const page = document.querySelector('[data-deals-page]');
    if (!page) return null;

    const category = page.dataset.category || 'escooter';
    const grid = page.querySelector('[data-deals-grid]');
    const emptyState = page.querySelector('[data-deals-empty]');
    const template = document.getElementById('deal-card-template');
    const infoCardTemplate = document.getElementById('deals-info-card-template');
    const periodSelect = page.querySelector('[data-deals-period]');
    const sortSelect = page.querySelector('[data-deals-sort]');
    const priceFilterSelect = page.querySelector('[data-deals-price-filter]');
    const countEl = page.querySelector('[data-deals-count]');

    if (!grid || !template) return null;

    // State
    let deals = [];
    let currentPeriod = periodSelect?.value || CONFIG.defaultPeriod;
    let currentSort = sortSelect?.value || CONFIG.defaultSort;
    let currentPriceFilter = priceFilterSelect?.value || 'all';
    let userGeo = { geo: 'US', currency: 'USD' };
    let isLoading = false;

    // Initialize comparison bar with shared module
    const comparisonBar = new ComparisonBar({
        container: page,
        grid: grid,
        getProductById: (id) => deals.find(d => String(d.id) === String(id)),
        maxCompare: CONFIG.maxCompare,
    });

    // Get user geo
    try {
        userGeo = await getUserGeo();
        updateCurrencySymbols(userGeo.currency);
    } catch (e) {
        // Use defaults
    }

    // Initial load
    await loadDeals();

    // Period change handler
    if (periodSelect) {
        periodSelect.addEventListener('change', async (e) => {
            currentPeriod = e.target.value;
            await loadDeals();
        });
    }

    // Sort change handler
    if (sortSelect) {
        sortSelect.addEventListener('change', (e) => {
            currentSort = e.target.value;
            filterSortAndRender();
        });
    }

    // Price filter change handler
    if (priceFilterSelect) {
        priceFilterSelect.addEventListener('change', (e) => {
            currentPriceFilter = e.target.value;
            filterSortAndRender();
        });
    }

    // Track price button handler (event delegation)
    grid.addEventListener('click', (e) => {
        const trackBtn = e.target.closest('[data-track-price]');
        if (!trackBtn) return;

        e.preventDefault();
        e.stopPropagation();

        const productId = parseInt(trackBtn.dataset.trackPrice, 10);
        const deal = deals.find(d => d.id === productId);

        if (deal) {
            PriceAlertModal.open({
                productId,
                productName: deal.name,
                productImage: deal.thumbnail || '',
                currentPrice: deal.current_price || 0,
                currency: deal.currency || userGeo.currency
            });
        }
    });

    // Compare checkbox handler (event delegation)
    grid.addEventListener('change', (e) => {
        const checkbox = e.target.closest('[data-compare-select]');
        if (!checkbox) return;

        comparisonBar.handleCheckboxChange(checkbox);
    });

    // Initialize custom selects
    initCustomSelects(page);

    /**
     * Load deals from API
     */
    async function loadDeals() {
        if (isLoading) return;
        isLoading = true;

        // Show skeleton state
        showSkeletons();

        try {
            const url = `${getRestUrl()}deals?category=${category}&limit=50&threshold=${CONFIG.discountThreshold}&geo=${userGeo.geo}&period=${currentPeriod}`;
            const response = await fetch(url);

            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const data = await response.json();
            deals = data.deals || [];

            // Update count
            updateCount(deals.length);

            // Filter, sort and render
            filterSortAndRender();

        } catch (error) {
            console.error('[DealsPage] Failed to load:', error);
            showEmptyState();
        } finally {
            isLoading = false;
        }
    }

    /**
     * Update currency symbols in price filter select
     */
    function updateCurrencySymbols(currency) {
        if (!priceFilterSelect) return;

        const symbol = getCurrencySymbol(currency);
        const options = priceFilterSelect.options;

        for (let i = 0; i < options.length; i++) {
            const option = options[i];
            // Replace $ with the correct symbol
            option.text = option.text.replace(/[$£€A-Z]{1,3}(?=\d)/g, symbol);
        }

        // Refresh existing CustomSelect instance to pick up new text
        const instance = priceFilterSelect._customSelect;
        if (instance) {
            instance.renderOptions();
            instance.syncFromNative();
        }
    }

    /**
     * Filter deals by price range
     */
    function filterByPrice(dealsToFilter) {
        if (currentPriceFilter === 'all') return dealsToFilter;

        return dealsToFilter.filter(deal => {
            const price = deal.current_price || 0;

            switch (currentPriceFilter) {
                case '0-500':
                    return price < 500;
                case '500-1000':
                    return price >= 500 && price < 1000;
                case '1000-1500':
                    return price >= 1000 && price < 1500;
                case '1500-2000':
                    return price >= 1500 && price < 2000;
                case '2000+':
                    return price >= 2000;
                default:
                    return true;
            }
        });
    }

    /**
     * Filter, sort deals and render
     */
    function filterSortAndRender() {
        // Filter by price
        const filtered = filterByPrice(deals);

        // Sort
        const sorted = [...filtered].sort((a, b) => {
            switch (currentSort) {
                case 'discount':
                    return b.discount_percent - a.discount_percent;
                case 'price-asc':
                    return a.current_price - b.current_price;
                case 'price-desc':
                    return b.current_price - a.current_price;
                case 'name':
                    return a.name.localeCompare(b.name);
                default:
                    return 0;
            }
        });

        // Update visible count
        updateCount(sorted.length);

        renderDeals(sorted);
    }

    /**
     * Render deal cards
     */
    function renderDeals(dealsToRender) {
        grid.innerHTML = '';

        if (dealsToRender.length === 0) {
            showEmptyState();
            return;
        }

        hideEmptyState();

        // Info card position depends on grid columns (first item of row 2)
        // Desktop 5 cols: position 5, 1024px 4 cols: position 4, 850px 3 cols: position 6
        let infoCardPosition = 4;
        if (window.innerWidth > 1024) {
            infoCardPosition = 5;
        } else if (window.innerWidth <= 850) {
            infoCardPosition = 6;
        }
        const minDealsForInfoCard = infoCardPosition + 3; // Need enough deals after info card

        dealsToRender.forEach((deal, index) => {
            // Insert info card at position if we have enough deals
            if (index === infoCardPosition && infoCardTemplate && dealsToRender.length >= minDealsForInfoCard) {
                const infoCard = infoCardTemplate.content.cloneNode(true);
                grid.appendChild(infoCard);
            }

            const card = createDealCard(deal);
            grid.appendChild(card);
        });

        // Sync checkbox states after re-render
        comparisonBar.syncCheckboxes();
    }

    /**
     * Create a deal card (matching finder product-card exactly)
     */
    function createDealCard(deal) {
        const clone = template.content.cloneNode(true);
        const card = clone.querySelector('.product-card');
        const checkbox = clone.querySelector('[data-compare-select]');
        const trackBtn = clone.querySelector('[data-track-price]');
        const cardLink = clone.querySelector('.product-card-link');
        const image = clone.querySelector('.product-card-image img');
        const priceEl = clone.querySelector('[data-price]');
        const indicator = clone.querySelector('[data-indicator]');
        const indicatorValue = clone.querySelector('[data-indicator-value]');
        const nameLink = clone.querySelector('[data-product-link]');

        // Set product ID
        card.dataset.productId = deal.id;

        // Checkbox
        if (checkbox) {
            checkbox.value = deal.id;
            checkbox.checked = comparisonBar.isSelected(deal.id);
        }

        // Track button (only if deal has price)
        if (trackBtn) {
            if (!deal.current_price) {
                trackBtn.style.display = 'none';
            } else {
                trackBtn.dataset.trackPrice = deal.id;
            }
        }

        // Links
        if (cardLink) cardLink.href = deal.url || '#';
        if (nameLink) {
            nameLink.href = deal.url || '#';
            nameLink.textContent = deal.name || '';
        }

        // Image
        if (image) {
            image.src = deal.thumbnail || '/wp-content/themes/erh-theme/assets/images/placeholder.svg';
            image.alt = deal.name || '';
            image.onerror = () => {
                image.src = '/wp-content/themes/erh-theme/assets/images/placeholder.svg';
            };
        }

        // Price
        if (priceEl && deal.current_price > 0) {
            priceEl.textContent = formatPrice(deal.current_price, deal.currency);
        }

        // Discount indicator
        if (indicator && indicatorValue && deal.discount_percent > 0) {
            indicatorValue.textContent = `${Math.round(deal.discount_percent)}%`;
        } else if (indicator) {
            indicator.style.display = 'none';
        }

        // Comparison average (shows only selected period)
        const compareLabel = clone.querySelector('[data-compare-label]');
        const comparePrice = clone.querySelector('[data-compare-price]');

        if (compareLabel && comparePrice) {
            // Set label based on current period
            const periodLabels = {
                '3m': '3-mo avg',
                '6m': '6-mo avg',
                '12m': '12-mo avg',
            };
            compareLabel.textContent = periodLabels[currentPeriod] || '6-mo avg';

            // Set price for the selected period
            const avgPrice = getAvgForPeriod(deal, currentPeriod);
            comparePrice.textContent = avgPrice > 0 ? formatPrice(avgPrice, deal.currency) : '—';
        }

        return clone;
    }

    /**
     * Get average price for the selected period
     */
    function getAvgForPeriod(deal, period) {
        switch (period) {
            case '3m': return deal.avg_price_3m || deal.avg_price;
            case '6m': return deal.avg_price_6m || deal.avg_price;
            case '12m': return deal.avg_price_12m || deal.avg_price;
            default: return deal.avg_price;
        }
    }

    /**
     * Show skeleton loading state
     */
    function showSkeletons() {
        grid.innerHTML = '';
        for (let i = 0; i < 8; i++) {
            const skeleton = document.createElement('div');
            skeleton.className = 'product-card product-card-skeleton';
            skeleton.innerHTML = `
                <div class="product-card-image">
                    <div class="skeleton skeleton-img"></div>
                </div>
                <div class="product-card-content">
                    <div class="skeleton skeleton-text"></div>
                    <div class="skeleton skeleton-text-sm"></div>
                </div>
            `;
            grid.appendChild(skeleton);
        }
    }

    /**
     * Update deal count in hero
     */
    function updateCount(count) {
        if (countEl) {
            countEl.innerHTML = `<strong>${count}</strong> deals available`;
        }
    }

    /**
     * Show empty state
     */
    function showEmptyState() {
        if (emptyState) {
            emptyState.hidden = false;
        }
    }

    /**
     * Hide empty state
     */
    function hideEmptyState() {
        if (emptyState) {
            emptyState.hidden = true;
        }
    }

    return {
        refresh: loadDeals,
        comparisonBar,
    };
}

export default { initDealsPage };

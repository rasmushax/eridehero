/**
 * Compare Results Module
 *
 * Head-to-head product comparison with:
 * - Sticky product header with scores
 * - Sticky section navigation with scroll spy
 * - Overview with advantage lists and score bars
 * - Spec tables with winner highlighting
 * - Geo-aware pricing with tracker integration
 *
 * @module components/compare-results
 */

import { getUserGeo, formatPrice, getCurrencySymbol } from '../services/geo-price.js';
import { PriceAlertModal } from './price-alert.js';
import { Modal } from './modal.js';
import { RadarChart } from './radar-chart.js';
import { escapeHtml } from '../utils/dom.js';
import {
    formatSpecValue,
    compareValues,
    calculatePercentDiff,
} from '../config/compare-config.js';

// Import from modular files
import {
    SELECTORS,
    HEADER_HEIGHT,
    NAV_HEIGHT,
    SCROLL_OFFSET,
    CONTAINER_MAX_WIDTH,
    CONTAINER_PADDING,
    LABEL_COL_WIDTH,
    PRODUCT_COL_MIN_WIDTH,
} from './compare/constants.js';
import {
    getSpec,
    findWinners,
    buildCompareUrl,
    closeModal,
    showError,
    throttle,
    debounce,
    trackComparisonView,
} from './compare/utils.js';

// Shared UI renderers (matches PHP components)
import {
    renderScoreRing,
    renderWinnerBadge,
    renderSpecCell,
    renderMobileSpecValue as renderMobileValue,
    renderMobileBooleanValue as renderMobileBoolean,
    renderIcon,
} from './compare/renderers.js';

// =============================================================================
// Config from PHP (Single Source of Truth)
// =============================================================================
// Spec config is injected by PHP via window.erhData.specConfig
// This includes: specGroups, categoryWeights, thresholds, ranking arrays

/**
 * Get spec config from PHP-injected data.
 * Falls back to empty defaults if not available.
 */
function getSpecConfig() {
    return window.erhData?.specConfig || {};
}

/**
 * Get spec groups for the current category.
 */
function getSpecGroups() {
    const config = getSpecConfig();
    return config.specGroups || {};
}

/**
 * Get category weights for the current category.
 */
function getCategoryWeights() {
    const config = getSpecConfig();
    return config.categoryWeights || {};
}

/**
 * Get advantage threshold (min % diff to show as advantage).
 */
function getAdvantageThreshold() {
    const config = getSpecConfig();
    return config.advantageThreshold || 5;
}

/**
 * Get max advantages to show per product.
 */
function getMaxAdvantages() {
    const config = getSpecConfig();
    return config.maxAdvantages || 5;
}

// =============================================================================
// State
// =============================================================================

let products = [];
let allProducts = [];
let category = 'escooter';
let userGeo = { geo: 'US', currency: 'USD' };
let radarChart = null;
let isFullWidthMode = false;
let scrollSpyHandler = null;
let isNavScrolling = false; // Prevents scroll spy during programmatic navigation

// =============================================================================
// Geo-Aware Spec Resolution
// =============================================================================

/**
 * Resolve geo placeholders in a spec definition.
 * Replaces {geo} in key and {symbol} in label with actual geo/currency values.
 *
 * @param {Object} spec - The spec definition from config
 * @returns {Object} - Resolved spec with geo placeholders replaced
 */
function resolveGeoSpec(spec) {
    if (!spec.geoAware) return spec;

    const geo = userGeo.geo || 'US';
    // Use symbol from getUserGeo() or derive from currency CODE (not geo code)
    const symbol = userGeo.symbol || getCurrencySymbol(userGeo.currency || 'USD');

    return {
        ...spec,
        key: spec.key.replace('{geo}', geo),
        label: spec.label.replace('{symbol}', symbol),
        currencySymbol: symbol, // Pass symbol to formatSpecValue() for currency formatting
    };
}

// =============================================================================
// Initialization
// =============================================================================

/**
 * Initialize compare page.
 *
 * Supports two modes:
 * 1. SSR Hydration: Content is server-rendered, JS just attaches handlers
 * 2. Client-side: JS fetches data and renders everything
 */
export async function init() {
    const page = document.querySelector(SELECTORS.page);
    if (!page) return;

    const config = window.erhData?.compareConfig;
    category = config?.category || page.dataset.category || 'escooter';

    // Empty state
    if (!config?.productIds?.length || config.productIds.length < 2) {
        await initEmptyState();
        return;
    }

    // Load geo data
    try {
        userGeo = await getUserGeo();
    } catch (e) {
        console.warn('Geo detection failed, using defaults');
        // Fallback to config if available
        if (config.geo) {
            userGeo = { geo: config.geo, currency: config.currencySymbol };
        }
    }

    // Check if content is server-rendered (SSR hydration mode)
    const ssrMarker = document.querySelector('[data-ssr-rendered]');

    if (ssrMarker) {
        // SSR Hydration Mode: Content already rendered, just hydrate
        await initHydrationMode(config);
    } else {
        // Client-side Mode: Fetch data and render
        await initClientMode(config);
    }
}

/**
 * SSR Hydration Mode: Content is already rendered server-side.
 * Just load product data and attach event handlers.
 *
 * @param {Object} config - Compare page config from PHP
 */
async function initHydrationMode(config) {
    // Parse products from embedded JSON (already has geo pricing from PHP)
    const productsJson = document.querySelector('[data-products-json]');
    if (productsJson) {
        try {
            products = JSON.parse(productsJson.textContent || '[]');
        } catch (e) {
            console.error('Failed to parse products JSON:', e);
            products = [];
        }
    }

    // Load all products for search modal
    await loadAllProducts();

    // Update layout mode
    updateLayoutMode();

    // Hydrate price-related elements (not cached by PHP)
    hydrateProductPricing();

    // Hydrate Value Analysis section with geo-aware metrics
    hydrateValueAnalysis();

    // Render only the overview section (radar chart + advantages)
    // Specs are already rendered by PHP
    renderOverview();

    // Attach all event handlers
    setupScrollSpy();
    setupAddProduct();
    setupNavStuckState();
    setupDiffToggle();
    setupResizeHandler();
    attachProductCardHandlers();

    // Track view
    trackComparisonView(config.productIds);

    // Load related if curated
    if (config.isCurated) {
        loadRelatedComparisons(config.productIds, category);
    }
}

/**
 * Hydrate price-related elements in SSR product cards.
 * Prices can't be cached so PHP renders placeholders, JS fills them.
 */
function hydrateProductPricing() {
    products.forEach(p => {
        const card = document.querySelector(`.compare-product[data-product-id="${p.id}"]`);
        if (!card) return;

        // Handle both camelCase (from enrichProduct) and snake_case (from PHP JSON)
        const currentPrice = p.currentPrice || p.current_price;
        const buyLink = p.buyLink || p.buy_link;
        const hasPrice = currentPrice && p.retailer;
        const price = hasPrice ? formatPrice(currentPrice, p.currency) : null;

        // 1. Inject price overlay into image
        const imageContainer = card.querySelector('.compare-product-image');
        if (imageContainer && price) {
            const priceRow = document.createElement('div');
            priceRow.className = 'compare-product-price-row';
            priceRow.innerHTML = `<span class="compare-product-price">${price}</span>`;
            imageContainer.appendChild(priceRow);
        }

        // 2. Inject track button into actions
        const actions = card.querySelector('.compare-product-actions');
        if (actions && hasPrice) {
            const trackBtn = document.createElement('button');
            trackBtn.className = 'compare-product-track';
            trackBtn.dataset.track = p.id;
            trackBtn.dataset.name = p.name;
            trackBtn.dataset.image = p.thumbnail || '';
            trackBtn.dataset.price = currentPrice;
            trackBtn.dataset.currency = p.currency || 'USD';
            trackBtn.setAttribute('aria-label', 'Track price');
            trackBtn.innerHTML = `<svg class="icon" width="16" height="16"><use href="#icon-bell"></use></svg>`;
            actions.appendChild(trackBtn);
        }

        // 3. Replace CTA placeholder
        const ctaPlaceholder = card.querySelector('[data-cta-placeholder]');
        if (ctaPlaceholder) {
            if (hasPrice && buyLink) {
                // Replace with actual CTA
                const cta = document.createElement('a');
                cta.href = buyLink;
                cta.className = 'compare-product-cta btn btn-primary btn-sm';
                cta.target = '_blank';
                cta.rel = 'noopener';
                cta.innerHTML = `Buy at ${p.retailer} <svg class="icon" width="14" height="14"><use href="#icon-external-link"></use></svg>`;
                ctaPlaceholder.replaceWith(cta);
            } else {
                // No price - hide placeholder
                ctaPlaceholder.remove();
            }
        }
    });
}

/**
 * Hydrate Value Analysis section with geo-aware pricing metrics.
 * PHP renders empty cells, JS fills them based on user's geo.
 */
function hydrateValueAnalysis() {
    const container = document.querySelector('[data-value-analysis]');
    if (!container) return;

    const geo = userGeo.geo || 'US';
    // Use symbol from getUserGeo() or derive from currency code
    const symbol = userGeo.symbol || getCurrencySymbol(userGeo.currency || 'USD');

    // Process each row
    const rows = container.querySelectorAll('tr[data-spec-key]');
    rows.forEach(row => {
        const specKey = row.dataset.specKey;
        // Replace {geo} placeholder with actual geo
        const resolvedKey = specKey.replace('{geo}', geo);

        // Check if this is a currency metric (has {symbol} in label) or efficiency metric
        const labelEl = row.querySelector('[data-label-template]');
        const template = labelEl?.dataset.labelTemplate || '';
        const isCurrencyMetric = template.includes('{symbol}');

        // Extract unit suffix from template for value display
        // e.g., "{symbol}/Wh" → "/Wh", "mph/lb" → "mph/lb"
        const unitSuffix = isCurrencyMetric
            ? template.replace('{symbol}', '') // e.g., "/Wh"
            : template; // e.g., "mph/lb"

        // Update label with correct currency symbol
        if (labelEl) {
            labelEl.textContent = template.replace('{symbol}', symbol);
        }

        // Update each product cell
        const cells = row.querySelectorAll('td[data-product-id]');
        cells.forEach(cell => {
            const productId = parseInt(cell.dataset.productId, 10);
            const product = products.find(p => p.id === productId);

            if (!product) {
                cell.textContent = '—';
                return;
            }

            // Get nested value from specs
            const value = getNestedValue(product.specs, resolvedKey);

            if (value === null || value === undefined) {
                cell.textContent = '—';
            } else if (isCurrencyMetric) {
                // Currency metrics: $24.22/Wh format
                cell.textContent = symbol + value.toFixed(2) + unitSuffix;
            } else {
                // Efficiency metrics: 0.45 mph/lb format
                cell.textContent = value.toFixed(2) + ' ' + unitSuffix;
            }
        });
    });
}

/**
 * Get nested value from object using dot notation path.
 * @param {Object} obj - Source object.
 * @param {string} path - Dot notation path (e.g., "value_metrics.US.price_per_tested_mile").
 * @returns {*} Value at path or undefined.
 */
function getNestedValue(obj, path) {
    if (!obj || !path) return undefined;
    return path.split('.').reduce((current, key) => current?.[key], obj);
}

/**
 * Client-side Mode: Fetch product data and render everything.
 *
 * @param {Object} config - Compare page config from PHP
 */
async function initClientMode(config) {
    // Load products
    await loadProducts(config.productIds);

    if (products.length < 2) {
        showError('Could not load product data.');
        return;
    }

    // Update layout mode based on viewport and product count
    updateLayoutMode();

    // Render all sections
    render();

    // Set up interactions
    setupScrollSpy();
    setupAddProduct();
    setupNavStuckState();
    setupDiffToggle();
    setupResizeHandler();

    // Track comparison view (fire and forget).
    trackComparisonView(config.productIds);

    // Load related comparisons if curated.
    if (config.isCurated) {
        loadRelatedComparisons(config.productIds, category);
    }
}

/**
 * Attach event handlers to SSR-rendered product cards.
 * Handles remove and track buttons.
 */
function attachProductCardHandlers() {
    const productsContainer = document.querySelector(SELECTORS.products);
    if (!productsContainer) return;

    // Remove buttons
    productsContainer.querySelectorAll('[data-remove]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const productId = parseInt(btn.dataset.remove, 10);
            removeProduct(productId);
        });
    });

    // Track buttons
    productsContainer.querySelectorAll('[data-track]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            PriceAlertModal.open({
                productId: parseInt(btn.dataset.track, 10),
                productName: btn.dataset.name,
                productImage: btn.dataset.image,
                currentPrice: parseFloat(btn.dataset.price) || 0,
                currency: btn.dataset.currency || 'USD',
            });
        });
    });

    // Add button
    const addBtn = productsContainer.querySelector('[data-open-add-modal]');
    if (addBtn) {
        addBtn.addEventListener('click', () => {
            Modal.openById('compare-add-modal', addBtn);
            setTimeout(() => {
                document.querySelector(SELECTORS.searchInput)?.focus();
            }, 100);
        });
    }
}

/**
 * Initialize empty state.
 */
async function initEmptyState() {
    await loadAllProducts();

    const addBtn = document.querySelector(SELECTORS.addBtn);
    if (addBtn) {
        addBtn.addEventListener('click', () => {
            Modal.openById('compare-add-modal', addBtn);
            setTimeout(() => {
                document.querySelector(SELECTORS.searchInput)?.focus();
            }, 100);
        });
    }

    setupEmptyStateSearch();
}

/**
 * Set up search for empty state.
 */
function setupEmptyStateSearch() {
    const input = document.querySelector(SELECTORS.searchInput);
    const results = document.querySelector(SELECTORS.searchResults);
    const inputsContainer = document.querySelector(SELECTORS.inputs);
    if (!input || !results) return;

    let selected = [];
    let debounce = null;

    input.addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            const q = input.value.trim().toLowerCase();
            if (q.length < 2) {
                results.innerHTML = '<p class="compare-search-hint">Type to search...</p>';
                return;
            }
            renderSearchResults(q, results, selected, (product) => {
                selected.push(product);
                updateSelectedDisplay(inputsContainer, selected, () => {
                    renderCompareButton(selected);
                });
                closeModal();
                input.value = '';
                results.innerHTML = '';
                if (selected.length >= 2) {
                    renderCompareButton(selected);
                }
            });
        }, 150);
    });
}

/**
 * Update selected products display.
 */
function updateSelectedDisplay(container, selected, onRemove) {
    if (!container) return;

    container.innerHTML = selected.map(p => `
        <div class="compare-selector-product">
            <img src="${p.thumbnail || ''}" alt="" class="compare-selector-thumb">
            <span class="compare-selector-name">${escapeHtml(p.name)}</span>
            <button type="button" class="compare-selector-remove" data-remove="${p.id}" aria-label="Remove">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>
    `).join('');

    container.querySelectorAll('[data-remove]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = parseInt(btn.dataset.remove, 10);
            const idx = selected.findIndex(p => p.id === id);
            if (idx > -1) selected.splice(idx, 1);
            updateSelectedDisplay(container, selected, onRemove);
            onRemove?.();
        });
    });
}

/**
 * Render compare button for empty state.
 */
function renderCompareButton(selected) {
    let btn = document.querySelector('.compare-selector-submit');

    if (selected.length < 2) {
        btn?.remove();
        return;
    }

    if (!btn) {
        btn = document.createElement('button');
        btn.className = 'compare-selector-submit btn btn--primary';
        btn.textContent = 'Compare Now';
        document.querySelector('.compare-selector')?.appendChild(btn);
    }

    btn.onclick = () => {
        window.location.href = buildCompareUrl(selected.map(p => p.id), allProducts);
    };
}

// =============================================================================
// Layout Mode (Dynamic Full-Width)
// =============================================================================

/**
 * Calculate if full-width mode is needed based on viewport and product count.
 * Returns true if products don't fit within the standard container.
 */
function calculateNeedsFullWidth() {
    const viewportWidth = window.innerWidth;
    // Use smaller of viewport or max container width
    const containerWidth = Math.min(viewportWidth, CONTAINER_MAX_WIDTH) - CONTAINER_PADDING;
    // Available width for product columns (subtract label column)
    const availableForProducts = containerWidth - LABEL_COL_WIDTH;
    // Calculate how many products fit
    const productsFit = Math.floor(availableForProducts / PRODUCT_COL_MIN_WIDTH);

    return products.length > productsFit;
}

/**
 * Update layout mode (full-width vs container) based on current state.
 * Called on init, product add/remove, and window resize.
 */
function updateLayoutMode() {
    const page = document.querySelector(SELECTORS.page);
    const specsSection = document.querySelector('.compare-section--specs');
    if (!page) return;

    const needsFullWidth = calculateNeedsFullWidth();
    const wasFullWidth = isFullWidthMode;

    // Update state
    isFullWidthMode = needsFullWidth;

    // Update page element
    page.dataset.productCount = products.length;
    page.style.setProperty('--product-count', products.length);

    // Update specs section container class
    if (specsSection) {
        const container = specsSection.querySelector('.container, .compare-section-full');
        if (needsFullWidth) {
            specsSection.classList.add('compare-section--full');
            if (container) {
                container.classList.remove('container');
                container.classList.add('compare-section-full');
            }
        } else {
            specsSection.classList.remove('compare-section--full');
            if (container) {
                container.classList.remove('compare-section-full');
                container.classList.add('container');
            }
        }
    }

    // If mode changed, re-setup scroll sync
    if (wasFullWidth !== needsFullWidth) {
        setupScrollSync();
    }
}

/**
 * Set up window resize handler with debounce.
 */
function setupResizeHandler() {
    let resizeTimeout = null;

    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            updateLayoutMode();
        }, 150);
    }, { passive: true });
}

// =============================================================================
// Data Loading
// =============================================================================

/**
 * Load product data from finder JSON.
 */
async function loadProducts(ids) {
    try {
        await loadAllProducts();
        products = ids
            .map(id => allProducts.find(p => p.id === id))
            .filter(Boolean)
            .map(enrichProduct);
    } catch (e) {
        console.error('Failed to load products:', e);
    }
}

/**
 * Load all products for search.
 */
async function loadAllProducts() {
    if (allProducts.length) return;

    const baseUrl = window.erhData?.siteUrl || '';
    const url = `${baseUrl}/wp-content/uploads/finder_${category}.json`;

    const res = await fetch(url);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    allProducts = await res.json();
}

/**
 * Enrich product with geo pricing.
 */
function enrichProduct(product) {
    const geo = userGeo.geo || 'US';
    const pricing = product.pricing || {};
    const regionPricing = pricing[geo] || pricing.US || {};
    const currentPrice = regionPricing.current_price || null;
    const avg3m = regionPricing.avg_3m || null;

    return {
        ...product,
        currentPrice,
        currency: pricing[geo] ? userGeo.currency : 'USD',
        priceData: regionPricing,
        inStock: regionPricing.instock !== false,
        buyLink: regionPricing.tracked_url || product.url,
        retailer: regionPricing.retailer || null,
        priceIndicator: calculatePriceIndicator(currentPrice, avg3m),
    };
}

/**
 * Calculate price indicator (% vs 3-month average).
 * @param {number|null} currentPrice - Current price.
 * @param {number|null} avg3m - 3-month average price.
 * @returns {number|null} Percentage difference (negative = below avg).
 */
function calculatePriceIndicator(currentPrice, avg3m) {
    if (!currentPrice || !avg3m || avg3m <= 0) return null;
    return Math.round(((currentPrice - avg3m) / avg3m) * 100);
}

// =============================================================================
// Rendering
// =============================================================================

/**
 * Render all sections.
 * Note: Verdict section is rendered via PHP in single-comparison.php for curated comparisons.
 */
function render() {
    renderProducts();
    renderOverview();
    renderSpecs();
    // Re-hydrate Value Analysis with correct currency symbols (renderSpecs doesn't have symbol context)
    hydrateValueAnalysis();
}

/**
 * Render product header cards (finder-style with score + geo-aware CTA).
 * Handles both regular and curated comparisons with winner highlighting.
 */
function renderProducts() {
    const container = document.querySelector(SELECTORS.products);
    if (!container) return;

    const config = window.erhData?.compareConfig;
    const verdictWinner = config?.verdictWinner;
    const isCurated = config?.isCurated;

    const cards = products.map((p, idx) => {
        const score = calculateProductScore(p);
        const price = p.currentPrice ? formatPrice(p.currentPrice, p.currency) : null;
        const isWinner = isCurated && verdictWinner === `product_${idx + 1}`;

        // Price indicator badge (below/above avg)
        const indicatorHtml = renderPriceIndicator(p.priceIndicator);

        // CTA only if retailer exists
        const hasCta = p.retailer && p.buyLink;

        return `
            <article class="compare-product" data-product-id="${p.id}">
                <!-- Score ring (top-left, uses shared renderer) -->
                ${renderScoreRing(score, 'md')}

                <div class="compare-product-actions">
                    <button class="compare-product-remove" data-remove-product="${p.id}" aria-label="Remove from comparison">
                        <svg class="icon" width="14" height="14"><use href="#icon-x"></use></svg>
                    </button>
                    ${p.currentPrice ? `
                        <button class="compare-product-track" data-track="${p.id}"
                                data-name="${escapeHtml(p.name)}"
                                data-image="${p.thumbnail || ''}"
                                data-price="${p.currentPrice}"
                                data-currency="${p.currency}"
                                aria-label="Track price">
                            <svg class="icon" width="16" height="16"><use href="#icon-bell"></use></svg>
                        </button>
                    ` : ''}
                </div>

                <a href="${p.url}" class="compare-product-link">
                    <div class="compare-product-image">
                        <img src="${p.thumbnail || ''}" alt="${escapeHtml(p.name)}">
                        ${price ? `
                            <div class="compare-product-price-row">
                                <span class="compare-product-price">${price}</span>
                                ${indicatorHtml}
                            </div>
                        ` : ''}
                    </div>
                </a>

                <div class="compare-product-content">
                    <a href="${p.url}" class="compare-product-name">${escapeHtml(p.name)}</a>
                    ${hasCta ? `
                        <a href="${p.buyLink}" class="compare-product-cta btn btn-primary btn-sm" target="_blank" rel="noopener">
                            Buy at ${p.retailer}
                            <svg class="icon" width="14" height="14"><use href="#icon-external-link"></use></svg>
                        </a>
                    ` : ''}
                </div>
            </article>
        `;
    }).join('');

    const addCard = `
        <div class="compare-product compare-product--add-wrap">
            <button class="compare-product-add-btn" data-open-add-modal>
                <svg class="icon" width="24" height="24"><use href="#icon-plus"></use></svg>
                <span>Add</span>
            </button>
        </div>
    `;

    container.innerHTML = cards + addCard;

    // Track button handlers
    container.querySelectorAll('[data-track]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            PriceAlertModal.open({
                productId: parseInt(btn.dataset.track, 10),
                productName: btn.dataset.name,
                productImage: btn.dataset.image,
                currentPrice: parseFloat(btn.dataset.price) || 0,
                currency: btn.dataset.currency || 'USD',
            });
        });
    });

    // Remove handlers
    container.querySelectorAll('[data-remove-product]').forEach(btn => {
        btn.addEventListener('click', () => removeProduct(parseInt(btn.dataset.removeProduct, 10)));
    });

    // Add handler
    container.querySelector('[data-open-add-modal]')?.addEventListener('click', () => {
        Modal.openById('compare-add-modal');
        setTimeout(() => document.querySelector(SELECTORS.searchInput)?.focus(), 100);
    });
}

/**
 * Render price indicator badge.
 * @param {number|null} indicator - Percentage difference from avg.
 * @returns {string} HTML string for indicator badge.
 */
function renderPriceIndicator(indicator) {
    if (indicator === null || indicator === undefined) return '';

    // Only show if significant (< -5% below or > 10% above)
    if (indicator >= -5 && indicator <= 10) return '';

    const isBelow = indicator < 0;
    const cls = isBelow ? 'compare-product-indicator--below' : 'compare-product-indicator--above';
    const icon = isBelow ? 'trending-down' : 'trending-up';
    const absPercent = Math.abs(indicator);

    return `
        <span class="compare-product-indicator ${cls}">
            <svg class="compare-product-indicator-icon"><use href="#icon-${icon}"></use></svg>
            ${absPercent}%
        </span>
    `;
}

/**
 * Render overview section.
 *
 * In SSR mode: Just renders radar chart (skeleton hidden via CSS :not(:empty)).
 * In client mode: Renders everything from scratch.
 */
function renderOverview() {
    const container = document.querySelector(SELECTORS.overview);
    if (!container || products.length < 2) return;

    // Check if SSR content exists (has radar loading skeleton)
    const ssrRadar = container.querySelector('[data-radar-container]');
    const ssrAdvantages = container.querySelector('.compare-advantages');

    if (ssrRadar && ssrAdvantages) {
        // SSR hydration mode: Just render radar chart
        // CSS will auto-hide skeleton via .compare-radar-chart:not(:empty) + .compare-radar-loading
        renderRadarChart();
        return;
    }

    // Client-side mode: Render everything
    const advantages = products.map((p, i) => {
        const others = products.filter((_, j) => j !== i);
        return renderAdvantageCard(p, generateAdvantages(p, others));
    }).join('');

    container.innerHTML = `
        <div class="compare-overview-grid">
            <div class="compare-radar">
                <h3 class="compare-radar-title">Category Scores</h3>
                <div class="compare-radar-chart" data-radar-chart></div>
            </div>
            <div class="compare-advantages">${advantages}</div>
        </div>
    `;

    // Initialize radar chart
    renderRadarChart();
}

/**
 * Render radar chart with category scores.
 */
function renderRadarChart() {
    const container = document.querySelector('[data-radar-chart]');
    if (!container) return;

    // Destroy previous instance
    if (radarChart) {
        radarChart.destroy();
    }

    // Build category data for radar
    const weights = getCategoryWeights();
    const categories = Object.entries(weights)
        .filter(([_, weight]) => weight > 0)
        .map(([name]) => ({
            key: name,
            name: name,
        }));

    // Calculate scores for each product
    const radarData = products.map(p => ({
        id: p.id,
        name: p.name,
        scores: Object.fromEntries(
            categories.map(cat => [cat.key, calculateCategoryScore(p, cat.key)])
        ),
    }));

    // Create chart
    radarChart = new RadarChart(container, {
        size: 340,
        levels: 5,
        maxValue: 100,
        labelOffset: 28,
    });

    radarChart.setData(radarData, categories);
}

/**
 * Generate advantages for a product.
 */
function generateAdvantages(product, others) {
    const advantages = [];
    const groups = getSpecGroups();

    for (const group of Object.values(groups)) {
        for (const spec of group.specs || []) {
            const val = getSpec(product, spec.key);
            if (val == null || val === '') continue;

            let winsAll = true;
            let bestDiff = 0;
            let bestOther = null;

            for (const other of others) {
                const otherVal = getSpec(other, spec.key);
                if (otherVal == null || otherVal === '') continue;

                const cmp = compareValues(val, otherVal, spec);
                if (cmp >= 0) {
                    winsAll = false;
                    break;
                }

                const diff = Math.abs(calculatePercentDiff(val, otherVal, spec.higherBetter !== false));
                if (diff > bestDiff) {
                    bestDiff = diff;
                    bestOther = otherVal;
                }
            }

            if (winsAll && bestDiff >= getAdvantageThreshold()) {
                advantages.push({
                    label: spec.label,
                    value: formatSpecValue(val, spec),
                    otherValue: bestOther != null ? formatSpecValue(bestOther, spec) : null,
                    diff: Math.round(bestDiff),
                    better: spec.higherBetter !== false,
                });
            }
        }
    }

    return advantages.sort((a, b) => b.diff - a.diff).slice(0, getMaxAdvantages());
}

/**
 * Render advantage card.
 */
function renderAdvantageCard(product, advantages) {
    if (!advantages.length) {
        return `
            <div class="compare-advantage">
                <h4 class="compare-advantage-title">${escapeHtml(product.name)}</h4>
                <p class="compare-advantage-empty">No clear advantages</p>
            </div>
        `;
    }

    const items = advantages.map(a => `
        <li>
            <svg class="icon" width="16" height="16"><use href="#icon-check"></use></svg>
            <span><strong>${a.diff}%</strong> ${a.better ? 'higher' : 'lower'} ${a.label.toLowerCase()}
            ${a.otherValue ? `<span class="compare-advantage-values">(${a.value} vs ${a.otherValue})</span>` : ''}</span>
        </li>
    `).join('');

    return `
        <div class="compare-advantage">
            <h4 class="compare-advantage-title">Why ${escapeHtml(product.name)} wins</h4>
            <ul class="compare-advantage-list">${items}</ul>
        </div>
    `;
}

/**
 * Render sticky mini-header for specs section.
 * Shows product thumbnails, scores, names, and CTAs.
 */
function renderMiniHeader() {
    const radius = 15;
    const circumference = 2 * Math.PI * radius;

    // Build colgroup for consistent column widths (matches spec tables)
    const colgroup = `
        <colgroup>
            <col class="compare-spec-col-label">
            ${products.map(() => '<col>').join('')}
        </colgroup>
    `;

    return `
        <div class="compare-mini-header">
            <table class="compare-mini-table">
                ${colgroup}
                <tr>
                    <td class="compare-mini-label">
                        <label class="compare-diff-toggle">
                            <input type="checkbox" data-diff-toggle>
                            <span class="compare-diff-toggle-switch"></span>
                            <span class="compare-diff-toggle-label">Differences only</span>
                        </label>
                    </td>
                    ${products.map(p => {
                        const score = calculateProductScore(p);
                        const scorePercent = Math.min(100, Math.max(0, score));
                        const offset = circumference - (scorePercent / 100) * circumference;
                        const price = p.currentPrice ? formatPrice(p.currentPrice, p.currency) : '';
                        const hasRetailer = p.retailer && p.buyLink;

                        return `
                            <td>
                                <div class="compare-mini-product">
                                    <div class="compare-mini-thumb-wrap">
                                        <img src="${p.thumbnail || ''}" alt="" class="compare-mini-thumb">
                                    </div>
                                    <div class="compare-mini-score" title="${score} points">
                                        <svg class="compare-mini-score-ring" viewBox="0 0 36 36">
                                            <circle class="compare-mini-score-track" cx="18" cy="18" r="${radius}" />
                                            <circle class="compare-mini-score-progress" cx="18" cy="18" r="${radius}"
                                                    style="stroke-dasharray: ${circumference}; stroke-dashoffset: ${offset};" />
                                        </svg>
                                        <span class="compare-mini-score-value">${score}</span>
                                    </div>
                                    <div class="compare-mini-info">
                                        <span class="compare-mini-name">${escapeHtml(p.name)}</span>
                                        ${hasRetailer ? `
                                            <a href="${p.buyLink}" class="compare-mini-price" target="_blank" rel="noopener">
                                                ${price}
                                                <svg class="icon" width="12" height="12"><use href="#icon-external-link"></use></svg>
                                            </a>
                                        ` : ''}
                                    </div>
                                </div>
                            </td>
                        `;
                    }).join('')}
                </tr>
            </table>
        </div>
    `;
}

/**
 * Render specs section (simplified layout).
 * All categories expanded, no accordions, inline scores per category.
 * Renders both desktop table and mobile stacked cards (CSS handles visibility).
 */
function renderSpecs() {
    const container = document.querySelector(SELECTORS.specs);
    if (!container) return;

    const groups = getSpecGroups();
    const categories = [];
    const categoryNav = []; // Track categories for nav population
    const needsScroll = isFullWidthMode;

    for (const [name, group] of Object.entries(groups)) {
        const specsWithValues = (group.specs || []).filter(spec => {
            // Resolve geo placeholders before checking values
            const resolved = resolveGeoSpec(spec);
            const values = products.map(p => getSpec(p, resolved.key));
            return !values.every(v => v == null || v === '');
        });

        if (!specsWithValues.length) continue;

        // Create slug for ID and navigation
        const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
        categoryNav.push({ name, slug });

        // Calculate category scores for inline display
        const categoryScores = products.map(p => calculateCategoryScore(p, name));

        // Find winner(s)
        let scoreWinnerIdx = -1;
        const validScores = categoryScores.filter(s => s > 0);
        if (validScores.length >= 2) {
            const maxScore = Math.max(...validScores);
            const winners = categoryScores.map((s, i) => s === maxScore ? i : -1).filter(i => i >= 0);
            if (winners.length === 1) scoreWinnerIdx = winners[0];
        }

        // Build desktop table rows
        const rows = renderSpecRows(specsWithValues);
        const valueMetricRow = group.valueMetric ? renderValueMetricRow(group.valueMetric) : '';

        // Build mobile stacked cards
        const mobileCards = renderMobileSpecCards(specsWithValues);

        // Build colgroup for consistent column widths
        const colgroup = `
            <colgroup>
                <col class="compare-spec-col-label">
                ${products.map(() => '<col>').join('')}
            </colgroup>
        `;

        categories.push(`
            <div id="${slug}" class="compare-spec-category" data-section="${slug}">
                <h3 class="compare-spec-category-title">${name}</h3>
                <!-- Desktop table -->
                <table class="compare-spec-table">
                    ${colgroup}
                    <tbody>${rows.join('')}${valueMetricRow}</tbody>
                </table>
                <!-- Mobile stacked cards -->
                <div class="compare-spec-cards">
                    ${mobileCards}
                </div>
            </div>
        `);
    }

    // Build final HTML: mini-header + categories (with scroll wrapper for 4+ products)
    const miniHeader = renderMiniHeader();
    const categoriesHtml = categories.join('');

    if (needsScroll) {
        container.innerHTML = `
            ${miniHeader}
            <div class="compare-specs-scroll">${categoriesHtml}</div>
        `;
        // Sync horizontal scroll between mini-header and tables
        setupScrollSync();
    } else {
        container.innerHTML = miniHeader + categoriesHtml;
    }

    // Populate nav with category links
    populateNav(categoryNav);
}

/**
 * Render mobile stacked cards for specs.
 * Each spec becomes a card showing all products' values.
 * Handles winners (purple badge), booleans (green/red), feature arrays (expanded),
 * and geo-aware specs.
 */
function renderMobileSpecCards(specs) {
    const cards = [];

    // Formats that should NOT be expanded even if they're arrays
    const noExpandFormats = ['suspensionArray', 'suspension'];

    for (const rawSpec of specs) {
        // Resolve geo placeholders for geoAware specs
        const spec = resolveGeoSpec(rawSpec);

        const values = products.map(p => getSpec(p, spec.key));

        // Check if this is a feature array (expand into multiple cards)
        // But skip expansion for certain formats like suspension
        const hasArrayValues = values.some(v => Array.isArray(v));
        const shouldExpand = hasArrayValues && !noExpandFormats.includes(spec.format);
        if (shouldExpand) {
            cards.push(...renderMobileFeatureCards(spec, values));
            continue;
        }

        // Check if this is a boolean spec
        const isBoolean = spec.type === 'boolean' || values.every(v =>
            v === null || v === '' || v === true || v === false ||
            v === 'Yes' || v === 'No' || v === 'yes' || v === 'no'
        );

        const winners = findWinners(values, spec);
        const tooltipHtml = spec.tooltip ? `
            <span class="info-trigger" data-tooltip="${escapeHtml(spec.tooltip)}" data-tooltip-trigger="click">
                ${renderIcon('info', 14, 14)}
            </span>
        ` : '';

        const productValues = products.map((p, i) => {
            const value = values[i];
            const isWinner = winners.includes(i);

            if (isBoolean) {
                return renderMobileBoolean(p, value);
            }

            return renderMobileValue(p, value, spec, isWinner);
        }).join('');

        cards.push(`
            <div class="compare-spec-card">
                <div class="compare-spec-card-label">
                    ${spec.label}${tooltipHtml}
                </div>
                <div class="compare-spec-card-values">
                    ${productValues}
                </div>
            </div>
        `);
    }

    return cards.join('');
}

// renderMobileSpecValue and renderMobileBooleanValue now imported from ./compare/renderers.js
// as renderMobileValue and renderMobileBoolean (see imports at top)

/**
 * Render mobile feature array cards (expanded into individual feature rows).
 */
function renderMobileFeatureCards(spec, values) {
    const cards = [];

    // Collect all unique features across all products
    const allFeatures = new Set();
    values.forEach(v => {
        if (Array.isArray(v)) {
            v.forEach(f => allFeatures.add(f));
        }
    });

    if (allFeatures.size === 0) return cards;

    // Render a card for each feature
    for (const feature of allFeatures) {
        const productValues = products.map((p, i) => {
            const productFeatures = values[i];
            const hasFeature = Array.isArray(productFeatures) && productFeatures.includes(feature);
            return renderMobileBoolean(p, hasFeature);
        }).join('');

        cards.push(`
            <div class="compare-spec-card">
                <div class="compare-spec-card-label">${feature}</div>
                <div class="compare-spec-card-values">
                    ${productValues}
                </div>
            </div>
        `);
    }

    return cards;
}

/**
 * Render value metric row for a category.
 */
function renderValueMetricRow(metric) {
    const values = products.map(p => {
        // Try primary key first, then fallback
        let val = getSpec(p, metric.key);
        if ((val == null || val === '') && metric.fallback) {
            val = getSpec(p, metric.fallback);
        }
        return val;
    });

    // Skip if all values are missing
    if (values.every(v => v == null || v === '')) return '';

    // Find winner (lower is better for value metrics)
    const valid = values.map((v, i) => ({ v: parseFloat(v) || Infinity, i })).filter(x => isFinite(x.v) && x.v > 0);
    let winnerIdx = -1;
    if (valid.length >= 2) {
        const best = metric.lowerBetter
            ? Math.min(...valid.map(x => x.v))
            : Math.max(...valid.map(x => x.v));
        const winners = valid.filter(x => x.v === best);
        if (winners.length === 1) winnerIdx = winners[0].i;
    }

    const cells = values.map((v, i) => {
        const cls = i === winnerIdx ? 'is-winner' : '';
        const formatted = v != null && v !== ''
            ? formatPrice(parseFloat(v), products[i]?.currency || userGeo.currency)
            : '—';
        return `<td class="${cls}">${formatted}</td>`;
    }).join('');

    return `<tr><td>${metric.label}</td>${cells}</tr>`;
}

/**
 * Render a boolean cell with green check or red X.
 */
function renderBooleanCell(value) {
    const isTrue = value === true || value === 'Yes' || value === 'yes' || value === 1;
    const statusClass = isTrue ? 'feature-yes' : 'feature-no';
    const icon = isTrue ? 'check' : 'x';
    const text = isTrue ? 'Yes' : 'No';

    return `
        <td class="${statusClass}">
            <div class="compare-spec-value-inner">
                <span class="compare-feature-badge">${renderIcon(icon)}</span>
                <span class="compare-feature-text">${text}</span>
            </div>
        </td>
    `;
}

/**
 * Render feature array as individual rows.
 * Each feature gets its own row with check/X for each product.
 */
function renderFeatureArrayRows(spec) {
    const rows = [];
    const values = products.map(p => getSpec(p, spec.key));

    // Collect all unique features across all products
    const allFeatures = new Set();
    values.forEach(v => {
        if (Array.isArray(v)) {
            v.forEach(f => allFeatures.add(f));
        }
    });

    if (allFeatures.size === 0) return rows;

    // Render a row for each feature
    for (const feature of allFeatures) {
        const featureStatuses = products.map((p, i) => {
            const productFeatures = values[i];
            return Array.isArray(productFeatures) && productFeatures.includes(feature);
        });

        // Check if all products have the same status for this feature
        const allSame = featureStatuses.every(s => s === featureStatuses[0]);
        const sameAttr = allSame ? ' data-same-values' : '';

        const cells = featureStatuses.map(hasFeature => renderBooleanCell(hasFeature)).join('');

        rows.push(`
            <tr${sameAttr}>
                <td>
                    <div class="compare-spec-label">${escapeHtml(feature)}</div>
                </td>
                ${cells}
            </tr>
        `);
    }

    return rows;
}

/**
 * Check if all values in an array are the same (for diff toggle).
 */
function areValuesSame(values) {
    const validValues = values.filter(v => v != null && v !== '');
    if (validValues.length <= 1) return true;

    // Normalize values for comparison
    const normalize = (v) => {
        if (Array.isArray(v)) return JSON.stringify(v.sort());
        if (typeof v === 'boolean') return v.toString();
        return String(v).toLowerCase().trim();
    };

    const first = normalize(validValues[0]);
    return validValues.every(v => normalize(v) === first);
}

/**
 * Render spec table rows.
 * Uses click-activated tooltips with info icon.
 * Handles geo-aware specs by resolving {geo} and {symbol} placeholders.
 */
function renderSpecRows(specs) {
    const rows = [];

    for (const rawSpec of specs) {
        // Handle feature arrays specially - expand into individual rows
        if (rawSpec.format === 'featureArray') {
            rows.push(...renderFeatureArrayRows(rawSpec));
            continue;
        }

        // Resolve geo placeholders for geoAware specs
        const spec = resolveGeoSpec(rawSpec);

        const values = products.map(p => getSpec(p, spec.key));
        if (values.every(v => v == null || v === '')) continue;

        // Check if all values are the same (for diff toggle)
        const isSame = areValuesSame(values);
        const sameAttr = isSame ? ' data-same-values' : '';

        // Handle boolean specs with green/red badges
        if (spec.format === 'boolean') {
            const cells = values.map(v => {
                if (v == null || v === '') return '<td>—</td>';
                return renderBooleanCell(v);
            }).join('');

            const tooltipHtml = spec.tooltip ? `
                <span class="info-trigger" data-tooltip="${escapeHtml(spec.tooltip)}" data-tooltip-trigger="click">
                    ${renderIcon('info', 14, 14)}
                </span>
            ` : '';

            rows.push(`
                <tr${sameAttr}>
                    <td>
                        <div class="compare-spec-label">${spec.label}${tooltipHtml}</div>
                    </td>
                    ${cells}
                </tr>
            `);
            continue;
        }

        // Standard specs with winner highlighting
        const winners = findWinners(values, spec);

        const cells = values.map((v, i) => {
            const isWinner = winners.includes(i);
            return renderSpecCell(v, spec, isWinner);
        }).join('');

        // Click-activated tooltip with info circle icon
        const tooltipHtml = spec.tooltip ? `
            <span class="info-trigger" data-tooltip="${escapeHtml(spec.tooltip)}" data-tooltip-trigger="click">
                ${renderIcon('info', 14, 14)}
            </span>
        ` : '';

        rows.push(`
            <tr${sameAttr}>
                <td>
                    <div class="compare-spec-label">${spec.label}${tooltipHtml}</div>
                </td>
                ${cells}
            </tr>
        `);
    }

    return rows;
}

// Note: Verdict section is rendered via PHP in single-comparison.php for curated comparisons.
// No JS rendering needed.

// =============================================================================
// Scoring - Uses PHP Pre-computed Scores
// =============================================================================
// All scores are pre-calculated by PHP (ProductScorer) during cache rebuild
// and stored in specs.scores. No client-side calculation needed.

/**
 * Map JS category names to PHP score keys.
 */
const CATEGORY_SCORE_KEYS = {
    'Motor Performance': 'motor_performance',
    'Range & Battery': 'range_battery',
    'Ride Quality': 'ride_quality',
    'Portability & Fit': 'portability',
    'Safety': 'safety',
    'Features': 'features',
    'Maintenance': 'maintenance',
};

/**
 * Get overall product score from PHP pre-computed scores.
 * @param {Object} product - Product data with specs.scores
 * @returns {number} Overall score 0-100, or 0 if not available
 */
function calculateProductScore(product) {
    const specs = product.specs || product;
    return specs.scores?.overall ?? 0;
}

/**
 * Get category score from PHP pre-computed scores.
 * @param {Object} product - Product data with specs.scores
 * @param {string} categoryName - Category display name (e.g., 'Motor Performance')
 * @returns {number} Category score 0-100, or 0 if not available
 */
function calculateCategoryScore(product, categoryName) {
    const specs = product.specs || product;
    const scoreKey = CATEGORY_SCORE_KEYS[categoryName];
    return scoreKey ? (specs.scores?.[scoreKey] ?? 0) : 0;
}

// =============================================================================
// Navigation
// =============================================================================

/**
 * Populate nav with spec category links.
 * Called after renderSpecs to add category navigation items.
 */
function populateNav(categoryNav) {
    const navContainer = document.querySelector(SELECTORS.navLinks);
    if (!navContainer) return;

    // Remove existing category links (keep Overview)
    navContainer.querySelectorAll('[data-nav-link]:not([data-nav-link="overview"])').forEach(el => el.remove());

    // Add category links
    const config = window.erhData?.compareConfig;
    const linksHtml = categoryNav.map(({ name, slug }) =>
        `<a href="#${slug}" class="compare-nav-link" data-nav-link="${slug}">${name}</a>`
    ).join('');

    // Add verdict link if curated
    const verdictHtml = config?.isCurated
        ? '<a href="#verdict" class="compare-nav-link" data-nav-link="verdict">Verdict</a>'
        : '';

    navContainer.insertAdjacentHTML('beforeend', linksHtml + verdictHtml);

    // Re-initialize scroll spy with new sections
    setupScrollSpy();
}

// =============================================================================
// Interactions
// =============================================================================

/**
 * Set up scroll spy for section nav.
 * Uses event delegation for click handling to support dynamic nav links.
 */
function setupScrollSpy() {
    const nav = document.querySelector(SELECTORS.nav);
    if (!nav) return;

    // Use event delegation for click handling (supports dynamic links)
    nav.removeEventListener('click', handleNavClick);
    nav.addEventListener('click', handleNavClick);

    // Set up scroll spy observer
    setupScrollSpyObserver();
}

/**
 * Handle nav link clicks with smooth scroll.
 * Temporarily disables scroll spy to prevent choppy intermediate updates.
 */
function handleNavClick(e) {
    const link = e.target.closest(SELECTORS.navLink);
    if (!link) return;

    e.preventDefault();
    const id = link.getAttribute('href')?.slice(1);
    const section = document.getElementById(id);
    if (!section) return;

    // Disable scroll spy during programmatic scroll
    isNavScrolling = true;

    // Immediately update active state to clicked link
    const nav = document.querySelector(SELECTORS.nav);
    const navLinksContainer = document.querySelector(SELECTORS.navLinks);
    nav?.querySelectorAll(SELECTORS.navLink).forEach(l => {
        l.classList.toggle('is-active', l === link);
    });

    // Scroll nav to the clicked link
    scrollNavToActive(navLinksContainer);

    // Scroll page to section
    const y = section.getBoundingClientRect().top + window.scrollY - SCROLL_OFFSET;
    window.scrollTo({ top: y, behavior: 'smooth' });

    // Re-enable scroll spy when scroll stops (detects actual completion)
    waitForScrollEnd(() => {
        isNavScrolling = false;
    });
}

/**
 * Wait for scroll to stop, then call callback.
 * More reliable than fixed timeout for varying scroll distances.
 */
function waitForScrollEnd(callback) {
    let lastScrollY = window.scrollY;
    let checkCount = 0;
    const maxChecks = 100; // Safety limit ~2.5s

    clearInterval(waitForScrollEnd.interval);
    waitForScrollEnd.interval = setInterval(() => {
        checkCount++;
        if (window.scrollY === lastScrollY || checkCount >= maxChecks) {
            clearInterval(waitForScrollEnd.interval);
            callback();
        }
        lastScrollY = window.scrollY;
    }, 25);
}

/**
 * Set up scroll spy using scroll listener.
 * Updates active nav link based on scroll position.
 */
function setupScrollSpyObserver() {
    const nav = document.querySelector(SELECTORS.nav);
    const navLinksContainer = document.querySelector(SELECTORS.navLinks);
    const sections = document.querySelectorAll(SELECTORS.section);
    if (!nav || !sections.length) return;

    let lastActive = null;

    const updateActive = () => {
        // Skip during programmatic navigation
        if (isNavScrolling) return;

        const links = nav.querySelectorAll(SELECTORS.navLink);
        let current = '';

        // Find current section based on scroll position
        sections.forEach(s => {
            const rect = s.getBoundingClientRect();
            if (rect.top <= SCROLL_OFFSET + 50) {
                current = s.dataset.section;
            }
        });

        // Update active states
        links.forEach(link => {
            link.classList.toggle('is-active', link.dataset.navLink === current);
        });

        // Auto-scroll nav when active changes and nav is overflowing
        if (current && current !== lastActive) {
            lastActive = current;
            scrollNavToActive(navLinksContainer);
        }
    };

    // Remove existing listener before adding (prevents duplicates on re-init)
    window.removeEventListener('scroll', scrollSpyHandler);
    scrollSpyHandler = throttle(updateActive, 50);
    window.addEventListener('scroll', scrollSpyHandler, { passive: true });
    updateActive();
}

/**
 * Scroll nav container to center the active link.
 * Only scrolls if nav is overflowing horizontally.
 */
function scrollNavToActive(container) {
    if (!container) return;

    const isOverflowing = container.scrollWidth > container.clientWidth;
    if (!isOverflowing) return;

    const activeLink = container.querySelector('.compare-nav-link.is-active');
    if (!activeLink) return;

    // Calculate scroll position to center the active link
    const containerWidth = container.clientWidth;
    const linkLeft = activeLink.offsetLeft;
    const linkWidth = activeLink.offsetWidth;
    const targetScroll = linkLeft - (containerWidth / 2) + (linkWidth / 2);

    container.scrollTo({
        left: Math.max(0, targetScroll),
        behavior: 'smooth'
    });
}

/**
 * Set up nav stuck state detection.
 * Adds 'is-stuck' class when nav is stuck at top for shadow effect.
 */
function setupNavStuckState() {
    const nav = document.querySelector(SELECTORS.nav);
    if (!nav) return;

    // Insert a sentinel element right before the nav
    const sentinel = document.createElement('div');
    sentinel.style.cssText = 'height: 1px; margin-bottom: -1px; pointer-events: none;';
    nav.parentNode.insertBefore(sentinel, nav);

    const observer = new IntersectionObserver(
        ([entry]) => {
            // When sentinel scrolls out of view, nav is stuck
            nav.classList.toggle('is-stuck', !entry.isIntersecting);
        },
        { threshold: 0, rootMargin: '0px' }
    );

    observer.observe(sentinel);
}

/**
 * Set up differences toggle.
 * Hides rows where values are the same across all products.
 */
function setupDiffToggle() {
    const toggle = document.querySelector('[data-diff-toggle]');
    if (!toggle) return;

    toggle.addEventListener('change', () => {
        const specsContainer = document.querySelector(SELECTORS.specs);
        if (!specsContainer) return;

        specsContainer.classList.toggle('show-diff-only', toggle.checked);
    });
}

/**
 * Set up scroll sync between mini-header and specs scroll wrapper.
 * Keeps both in horizontal sync for 4+ product comparisons.
 */
function setupScrollSync() {
    const miniHeader = document.querySelector('.compare-mini-header');
    const specsScroll = document.querySelector('.compare-specs-scroll');
    if (!miniHeader || !specsScroll) return;

    let isSyncing = false;

    // Sync from mini-header to specs
    miniHeader.addEventListener('scroll', () => {
        if (isSyncing) return;
        isSyncing = true;
        specsScroll.scrollLeft = miniHeader.scrollLeft;
        requestAnimationFrame(() => { isSyncing = false; });
    }, { passive: true });

    // Sync from specs to mini-header
    specsScroll.addEventListener('scroll', () => {
        if (isSyncing) return;
        isSyncing = true;
        miniHeader.scrollLeft = specsScroll.scrollLeft;
        requestAnimationFrame(() => { isSyncing = false; });
    }, { passive: true });

    // Enable drag-to-scroll on both elements
    setupDragToScroll(specsScroll);
    setupDragToScroll(miniHeader);
}

/**
 * Enable mouse drag to scroll horizontally on an element.
 * Skipped on mobile/touch devices where native touch scroll works.
 * @param {HTMLElement} element - Scrollable element to enable drag on.
 */
function setupDragToScroll(element) {
    if (!element) return;

    // Skip on mobile - touch scrolling is native
    if (window.innerWidth <= 768) return;

    let isDown = false;
    let startX = 0;
    let scrollLeft = 0;

    element.style.cursor = 'grab';

    element.addEventListener('mousedown', (e) => {
        // Don't interfere with clicks on links/buttons
        if (e.target.closest('a, button, input, label')) return;

        isDown = true;
        element.style.cursor = 'grabbing';
        element.style.userSelect = 'none';
        startX = e.pageX - element.offsetLeft;
        scrollLeft = element.scrollLeft;
    });

    element.addEventListener('mouseleave', () => {
        if (!isDown) return;
        isDown = false;
        element.style.cursor = 'grab';
        element.style.userSelect = '';
    });

    element.addEventListener('mouseup', () => {
        isDown = false;
        element.style.cursor = 'grab';
        element.style.userSelect = '';
    });

    element.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        const x = e.pageX - element.offsetLeft;
        const walk = (x - startX) * 1.5; // Scroll speed multiplier
        element.scrollLeft = scrollLeft - walk;
    });
}

/**
 * Set up add product modal.
 */
function setupAddProduct() {
    const input = document.querySelector(SELECTORS.searchInput);
    const results = document.querySelector(SELECTORS.searchResults);
    if (!input || !results) return;

    let debounce = null;

    input.addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            const q = input.value.trim().toLowerCase();
            if (q.length < 2) {
                results.innerHTML = '<p class="compare-search-hint">Type to search...</p>';
                return;
            }
            renderSearchResults(q, results, products, (product) => {
                addProduct(product);
                closeModal();
                input.value = '';
                results.innerHTML = '';
            });
        }, 150);
    });
}

/**
 * Render search results.
 */
function renderSearchResults(query, container, exclude, onSelect) {
    const excludeIds = exclude.map(p => p.id);
    const matches = allProducts
        .filter(p => !excludeIds.includes(p.id) && p.name.toLowerCase().includes(query))
        .slice(0, 8);

    if (!matches.length) {
        container.innerHTML = '<p class="compare-search-empty">No products found</p>';
        return;
    }

    container.innerHTML = matches.map(p => {
        const price = p.pricing?.[userGeo.geo]?.current_price || p.pricing?.US?.current_price;
        const priceHtml = price ? formatPrice(price, userGeo.currency) : '';
        return `
            <button type="button" class="compare-search-result" data-id="${p.id}">
                <img src="${p.thumbnail || ''}" alt="" class="compare-search-result-img">
                <div class="compare-search-result-info">
                    <span class="compare-search-result-name">${escapeHtml(p.name)}</span>
                    ${priceHtml ? `<span class="compare-search-result-price">${priceHtml}</span>` : ''}
                </div>
            </button>
        `;
    }).join('');

    container.querySelectorAll('[data-id]').forEach(btn => {
        btn.addEventListener('click', () => {
            const product = allProducts.find(p => p.id === parseInt(btn.dataset.id, 10));
            if (product) onSelect(product);
        });
    });
}

/**
 * Add product to comparison.
 */
function addProduct(product) {
    const enrichedProduct = enrichProduct(product);
    products.push(enrichedProduct);

    // Update URL first, then render, then apply layout mode
    // (render replaces DOM, so layout mode must come after)
    updateUrl();
    updateDocumentTitle();
    render();
    updateLayoutMode();
}

/**
 * Remove product from comparison.
 */
function removeProduct(id) {
    if (products.length <= 2) return;
    products = products.filter(p => p.id !== id);

    // Update URL first, then render, then apply layout mode
    // (render replaces DOM, so layout mode must come after)
    updateUrl();
    updateDocumentTitle();
    render();
    updateLayoutMode();
}

/**
 * Update URL with current products.
 */
function updateUrl() {
    const ids = products.map(p => p.id);
    const url = buildCompareUrl(ids, allProducts);
    window.history.replaceState({}, '', url);
}

/**
 * Update document title dynamically based on current products.
 *
 * Title formats:
 * - 2-3 products: "Product A vs Product B [vs Product C]"
 * - 4+ products: "Comparing X electric scooters"
 */
function updateDocumentTitle() {
    const names = products.map(p => p.name);
    const count = names.length;

    if (count === 0) return;

    const categoryName = window.erhData?.compareConfig?.categoryName || 'Electric Rides';
    let title;

    if (count <= 3) {
        // "Product A vs Product B [vs Product C]"
        title = names.join(' vs ');
    } else {
        // "Comparing X electric scooters"
        title = `Comparing ${count} ${categoryName}`;
    }

    // Update document title (browser tab)
    document.title = title;

    // Update RankMath title tag if present
    const titleTag = document.querySelector('title');
    if (titleTag) {
        titleTag.textContent = title;
    }

    // Update OG title meta if present
    const ogTitle = document.querySelector('meta[property="og:title"]');
    if (ogTitle) {
        ogTitle.setAttribute('content', title);
    }
}

// =============================================================================
// Helpers - Imported from ./compare/utils.js
// =============================================================================
// getSpec, findWinners, buildCompareUrl, closeModal, showError, throttle,
// debounce, trackComparisonView are imported at the top of this file.

// =============================================================================
// Related Comparisons
// =============================================================================

/**
 * Load related comparisons for curated pages.
 *
 * @param {number[]} productIds - Current product IDs.
 * @param {string} categoryKey - Category key.
 */
async function loadRelatedComparisons(productIds, categoryKey) {
    const container = document.querySelector('[data-related-comparisons]');
    if (!container) return;

    try {
        const restUrl = window.erhData?.restUrl || '/wp-json/erh/v1/';
        const params = new URLSearchParams({
            products: productIds.join(','),
            category: categoryKey,
            limit: '4',
        });

        const res = await fetch(`${restUrl}compare/related?${params}`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const json = await res.json();
        if (!json.success || !json.data?.length) {
            container.innerHTML = '<p class="compare-related-empty">No related comparisons found.</p>';
            return;
        }

        container.innerHTML = json.data.map(item => {
            const p1 = item.product_1;
            const p2 = item.product_2;

            return `
                <a href="${item.url}" class="compare-popular-card">
                    <div class="compare-popular-card-products">
                        <div class="compare-popular-card-product">
                            ${p1.thumbnail ? `<img src="${p1.thumbnail}" alt="" class="compare-popular-card-thumb">` : ''}
                            <span class="compare-popular-card-name">${escapeHtml(p1.name)}</span>
                        </div>
                        <span class="compare-popular-card-vs">vs</span>
                        <div class="compare-popular-card-product">
                            ${p2.thumbnail ? `<img src="${p2.thumbnail}" alt="" class="compare-popular-card-thumb">` : ''}
                            <span class="compare-popular-card-name">${escapeHtml(p2.name)}</span>
                        </div>
                    </div>
                    ${item.view_count ? `
                        <div class="compare-popular-card-meta">
                            <span class="compare-popular-card-views">${item.view_count.toLocaleString()} views</span>
                        </div>
                    ` : ''}
                </a>
            `;
        }).join('');
    } catch (e) {
        console.warn('Failed to load related comparisons:', e);
        container.innerHTML = '';
    }
}

export default { init };

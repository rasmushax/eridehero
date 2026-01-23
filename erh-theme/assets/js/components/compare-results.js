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
import { REGIONS, isValidRegion } from '../services/geo-config.js';
import { PriceAlertModal } from './price-alert.js';
import { Modal } from './modal.js';
import { RadarChart } from './radar-chart.js';
import { escapeHtml, ensureAbsoluteUrl } from '../utils/dom.js';
import {
    formatSpecValue,
    compareValues,
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
    renderAdvantagesGrid,
    renderMultiAdvantagesGrid,
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

// NOTE: getAdvantageThreshold() and getMaxAdvantages() removed.
// Advantages now computed server-side via REST API.

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
let navStuckObserver = null;
let navStuckSentinel = null;
let isRendering = false; // Guard to prevent double re-renders
let ssrHydrated = false; // Tracks if initial SSR hydration is complete
let dragToScrollCleanups = []; // Cleanup functions for drag-to-scroll handlers

// =============================================================================
// Product Normalization
// =============================================================================

/**
 * Normalize a product from SSR (snake_case) to JS format (camelCase).
 * Ensures consistent property names across SSR-hydrated and JS-enriched products.
 *
 * @param {Object} p - Product object (from PHP JSON or enrichProduct)
 * @returns {Object} - Normalized product with camelCase keys
 */
function normalizeProduct(p) {
    // If already has camelCase keys, assume it's normalized
    if (p._normalized) return p;

    return {
        ...p,
        // Pricing keys (snake_case from PHP → camelCase)
        currentPrice: p.currentPrice ?? p.current_price ?? null,
        buyLink: p.buyLink ?? p.buy_link ?? p.tracked_url ?? null,
        avg6m: p.avg6m ?? p.avg_6m ?? null,
        priceIndicator: p.priceIndicator ?? p.price_indicator ?? null,
        inStock: p.inStock ?? p.in_stock ?? true,
        // Ensure critical fields exist
        retailer: p.retailer ?? null,
        currency: p.currency ?? 'USD',
        // Mark as normalized to avoid double-processing
        _normalized: true,
    };
}

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
            const rawProducts = JSON.parse(productsJson.textContent || '[]');
            // Normalize snake_case keys from PHP to camelCase for consistency
            products = rawProducts.map(normalizeProduct);
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

    // Hydrate verdict section products with pricing (curated only)
    hydrateVerdictProducts();

    // Hydrate Value Analysis section with geo-aware metrics
    hydrateValueAnalysis();

    // Hydrate mini-header prices with geo-aware data
    hydrateMiniHeader();

    // Hydrate buy row at end of specs (all comparisons)
    hydrateBuyRow();

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

        // Calculate price indicator from 6-month average
        const avg6m = p.avg6m || p.avg_6m || p.priceData?.avg_6m;
        const indicator = calculatePriceIndicator(currentPrice, avg6m);
        const indicatorHtml = renderPriceIndicator(indicator);

        // 1. Inject price overlay into image
        const imageContainer = card.querySelector('.compare-product-image');
        if (imageContainer && price) {
            const priceRow = document.createElement('div');
            priceRow.className = 'compare-product-price-row';
            priceRow.innerHTML = `<span class="compare-product-price">${price}</span>${indicatorHtml}`;
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
                // Replace with actual CTA (affiliate link - needs proper rel for SEO)
                const cta = document.createElement('a');
                cta.href = ensureAbsoluteUrl(buyLink);
                cta.className = 'compare-product-cta btn btn-primary btn-sm';
                cta.target = '_blank';
                cta.rel = 'sponsored noopener';
                cta.innerHTML = `Buy at ${escapeHtml(p.retailer)} <svg class="icon" width="14" height="14"><use href="#icon-external-link"></use></svg>`;
                ctaPlaceholder.replaceWith(cta);
            } else {
                // No price - hide placeholder
                ctaPlaceholder.remove();
            }
        }
    });
}

/**
 * Hydrate verdict section product cards with geo-aware pricing.
 * Curated comparisons have a verdict section with product cards.
 *
 * - Shows price, tracker button, and CTA when price is available
 * - Hides price row and CTA entirely when no price (keeps review/video links)
 */
function hydrateVerdictProducts() {
    const verdictProducts = document.querySelectorAll('[data-verdict-product]');
    if (!verdictProducts.length) return;

    products.forEach(p => {
        const card = document.querySelector(`[data-verdict-product="${p.id}"]`);
        if (!card) return;

        const currentPrice = p.currentPrice || p.current_price;
        const buyLink = p.buyLink || p.buy_link;
        const hasPrice = currentPrice && p.retailer;
        const price = hasPrice ? formatPrice(currentPrice, p.currency) : null;

        // Calculate price indicator from 6-month average
        const avg6m = p.avg6m || p.avg_6m || p.priceData?.avg_6m;
        const indicator = calculatePriceIndicator(currentPrice, avg6m);
        const indicatorHtml = renderPriceIndicator(indicator);

        // Get elements
        const priceRowEl = card.querySelector('[data-verdict-price-row]');
        const priceEl = card.querySelector('[data-verdict-price]');
        const ctaEl = card.querySelector('[data-verdict-cta]');
        const ctaMobileEl = card.querySelector('[data-verdict-cta-mobile]');

        if (hasPrice) {
            // Show price with indicator
            if (priceEl) {
                priceEl.innerHTML = `${price}${indicatorHtml}`;
            }

            // Add tracker button into the image area (top-right)
            const imageEl = card.querySelector('.compare-verdict-product-image');
            if (imageEl) {
                const trackBtn = document.createElement('button');
                trackBtn.type = 'button';
                trackBtn.className = 'compare-verdict-product-track';
                trackBtn.dataset.track = p.id;
                trackBtn.dataset.name = p.name;
                trackBtn.dataset.image = p.thumbnail || '';
                trackBtn.dataset.price = currentPrice;
                trackBtn.dataset.currency = p.currency || 'USD';
                trackBtn.setAttribute('aria-label', 'Track price');
                trackBtn.innerHTML = `<svg class="icon" width="14" height="14"><use href="#icon-bell"></use></svg>`;
                imageEl.appendChild(trackBtn);

                // Attach click handler
                trackBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    PriceAlertModal.open({
                        productId: parseInt(trackBtn.dataset.track, 10),
                        productName: trackBtn.dataset.name,
                        productImage: trackBtn.dataset.image,
                        currentPrice: parseFloat(trackBtn.dataset.price) || 0,
                        currency: trackBtn.dataset.currency || 'USD',
                    });
                });
            }

            // Show CTA with retailer link (both desktop and mobile)
            if (buyLink) {
                const createCtaLink = () => {
                    const link = document.createElement('a');
                    link.href = ensureAbsoluteUrl(buyLink);
                    link.className = 'btn btn-sm btn-primary';
                    link.target = '_blank';
                    link.rel = 'sponsored noopener';
                    link.innerHTML = `Buy at ${escapeHtml(p.retailer)} <svg class="icon" width="14" height="14"><use href="#icon-external-link"></use></svg>`;
                    return link;
                };

                if (ctaEl) {
                    ctaEl.replaceChildren(createCtaLink());
                }
                if (ctaMobileEl) {
                    ctaMobileEl.replaceChildren(createCtaLink());
                }
            }
        } else {
            // No price - hide price row and CTA entirely
            if (priceRowEl) {
                priceRowEl.style.display = 'none';
            }
            if (ctaEl) {
                ctaEl.style.display = 'none';
            }
            if (ctaMobileEl) {
                ctaMobileEl.style.display = 'none';
            }
        }
    });
}

/**
 * Hydrate Value Analysis section with geo-aware pricing metrics.
 * PHP renders empty cells, JS fills them based on user's geo.
 * Includes winner detection (lower value wins for value metrics).
 */
function hydrateValueAnalysis() {
    const container = document.querySelector('[data-value-analysis]');
    if (!container) return;

    const geo = userGeo.geo || 'US';
    // Use symbol from getUserGeo() or derive from currency code
    const symbol = userGeo.symbol || getCurrencySymbol(userGeo.currency || 'USD');

    // Helper to format value based on metric type
    const formatValue = (value, isCurrencyMetric, unitSuffix) => {
        if (value === null || value === undefined) return '—';
        if (isCurrencyMetric) {
            return symbol + value.toFixed(2) + unitSuffix;
        }
        return value.toFixed(2) + ' ' + unitSuffix;
    };

    // Value metrics spec definition (lower is better for all value metrics)
    const valueSpec = { higherBetter: false };

    // Process each desktop table row
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

        // Update label with correct currency symbol (preserve tooltip span)
        if (labelEl) {
            const newText = template.replace('{symbol}', symbol);
            const textNode = Array.from(labelEl.childNodes).find(n => n.nodeType === Node.TEXT_NODE);
            if (textNode) {
                textNode.textContent = newText;
            } else {
                labelEl.prepend(newText);
            }
        }

        // Collect values for winner detection
        const cells = row.querySelectorAll('td[data-product-id]');
        const values = [];
        const cellData = [];

        cells.forEach(cell => {
            const productId = parseInt(cell.dataset.productId, 10);
            const product = products.find(p => p.id === productId);
            const value = product ? getNestedValue(product.specs, resolvedKey) : null;
            values.push(value);
            cellData.push({ cell, value, isCurrencyMetric, unitSuffix });
        });

        // Find winners (lower is better for value metrics)
        const winners = findWinners(values, valueSpec);

        // Update each product cell with value and winner badge
        cellData.forEach(({ cell, value, isCurrencyMetric, unitSuffix }, idx) => {
            const isWinner = winners.includes(idx);
            const formatted = formatValue(value, isCurrencyMetric, unitSuffix);

            if (isWinner && value !== null && value !== undefined) {
                cell.classList.add('is-winner');
                cell.innerHTML = `
                    <div class="compare-spec-value-inner">
                        ${renderWinnerBadge()}
                        <span class="compare-spec-value-text">${formatted}</span>
                    </div>
                `;
            } else {
                cell.classList.remove('is-winner');
                cell.textContent = formatted;
            }
        });
    });

    // Process mobile cards
    const mobileContainer = container.querySelector('[data-value-analysis-mobile]');
    if (mobileContainer) {
        const cards = mobileContainer.querySelectorAll('.compare-spec-card[data-spec-key]');
        cards.forEach(card => {
            const specKey = card.dataset.specKey;
            const resolvedKey = specKey.replace('{geo}', geo);

            // Check if this is a currency metric
            const labelEl = card.querySelector('[data-label-template]');
            const template = labelEl?.dataset.labelTemplate || '';
            const isCurrencyMetric = template.includes('{symbol}');
            const unitSuffix = isCurrencyMetric
                ? template.replace('{symbol}', '')
                : template;

            // Update label with correct currency symbol (preserve tooltip span)
            if (labelEl) {
                const newText = template.replace('{symbol}', symbol);
                const textNode = Array.from(labelEl.childNodes).find(n => n.nodeType === Node.TEXT_NODE);
                if (textNode) {
                    textNode.textContent = newText;
                } else {
                    labelEl.prepend(newText);
                }
            }

            // Collect values for winner detection
            const valueEls = card.querySelectorAll('.compare-spec-card-value[data-product-id]');
            const values = [];
            const elData = [];

            valueEls.forEach(valueEl => {
                const productId = parseInt(valueEl.dataset.productId, 10);
                const product = products.find(p => p.id === productId);
                const value = product ? getNestedValue(product.specs, resolvedKey) : null;
                values.push(value);
                elData.push({ valueEl, value, isCurrencyMetric, unitSuffix });
            });

            // Find winners (lower is better for value metrics)
            const winners = findWinners(values, valueSpec);

            // Update each product value with winner indicator
            elData.forEach(({ valueEl, value, isCurrencyMetric, unitSuffix }, idx) => {
                const textEl = valueEl.querySelector('.compare-spec-card-text');
                if (!textEl) return;

                const isWinner = winners.includes(idx);
                const formatted = formatValue(value, isCurrencyMetric, unitSuffix);

                if (isWinner && value !== null && value !== undefined) {
                    valueEl.classList.add('is-winner');
                    // Add winner badge before text
                    const dataEl = valueEl.querySelector('.compare-spec-card-data');
                    if (dataEl) {
                        dataEl.innerHTML = `
                            ${renderWinnerBadge()}
                            <span class="compare-spec-card-text">${formatted}</span>
                        `;
                    }
                } else {
                    valueEl.classList.remove('is-winner');
                    textEl.textContent = formatted;
                }
            });
        });
    }
}

/**
 * Hydrate mini-header prices with geo-aware data.
 * PHP renders placeholders, JS fills with correct currency.
 */
function hydrateMiniHeader() {
    const placeholders = document.querySelectorAll('[data-mini-price-placeholder]');

    placeholders.forEach(el => {
        const productId = parseInt(el.dataset.productId, 10);
        const buyLink = el.dataset.buyLink;
        const product = products.find(p => p.id === productId);

        if (!product) {
            el.style.display = 'none';
            return;
        }

        const currentPrice = product.currentPrice || product.current_price;
        const hasPrice = currentPrice && product.retailer && buyLink;

        if (hasPrice) {
            const price = formatPrice(currentPrice, product.currency);

            // Create link element safely (affiliate link - needs proper rel for SEO).
            const link = document.createElement('a');
            link.href = ensureAbsoluteUrl(buyLink);
            link.className = 'compare-mini-price-link';
            link.target = '_blank';
            link.rel = 'sponsored noopener';
            link.textContent = price + ' ';

            // Add icon via SVG use (safe).
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('class', 'icon');
            svg.setAttribute('width', '12');
            svg.setAttribute('height', '12');
            const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
            use.setAttribute('href', '#icon-external-link');
            svg.appendChild(use);
            link.appendChild(svg);

            el.appendChild(link);
        } else {
            el.style.display = 'none';
        }
    });
}

/**
 * Hydrate buy row at end of specs with geo-aware pricing.
 * All comparisons (not just curated) get buy buttons when pricing is available.
 */
function hydrateBuyRow() {
    const buyRow = document.querySelector('[data-compare-buy-row]');
    if (!buyRow) return;

    const cells = buyRow.querySelectorAll('[data-buy-cell]');
    let hasAnyPrice = false;

    cells.forEach(cell => {
        const productId = parseInt(cell.dataset.buyCell, 10);
        const product = products.find(p => p.id === productId);

        if (!product) {
            cell.innerHTML = '';
            return;
        }

        const currentPrice = product.currentPrice || product.current_price;
        const buyLink = product.buyLink || product.buy_link;
        const hasPrice = currentPrice && product.retailer && buyLink;

        if (hasPrice) {
            hasAnyPrice = true;
            const price = formatPrice(currentPrice, product.currency);

            // Create buy button with price and retailer
            const link = document.createElement('a');
            link.href = ensureAbsoluteUrl(buyLink);
            link.className = 'btn btn-sm btn-primary';
            link.target = '_blank';
            link.rel = 'sponsored noopener';
            link.innerHTML = `
                <span class="compare-buy-price">${price}</span>
                <span class="compare-buy-retailer">at ${escapeHtml(product.retailer)}</span>
                <svg class="icon" width="14" height="14"><use href="#icon-external-link"></use></svg>
            `;
            cell.replaceChildren(link);
        } else {
            // No price - hide the cell content
            cell.innerHTML = '';
        }
    });

    // If no products have pricing, hide the entire table
    if (!hasAnyPrice) {
        buyRow.dataset.hidden = 'true';
    }
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

    // Toggle page-level full-width class (matches PHP behavior)
    if (needsFullWidth) {
        page.classList.add('compare-page--full-width');
    } else {
        page.classList.remove('compare-page--full-width');
    }

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
 *
 * Business rule: No currency mixing!
 * - User in supported geo (US/GB/EU/CA/AU): Show price ONLY if that geo has pricing. No fallback.
 * - User outside supported geos: Fall back to US as reference.
 */
function enrichProduct(product) {
    const geo = userGeo.geo || 'US';
    const pricing = product.pricing || {};

    // Check if user's geo has valid pricing
    const geoPricing = pricing[geo];
    const hasGeoPrice = geoPricing?.current_price != null;

    // Only fall back to US if user is OUTSIDE all supported geos (use centralized config)
    const isInSupportedGeo = isValidRegion(geo);
    const shouldFallbackToUS = !isInSupportedGeo && !hasGeoPrice;

    let regionPricing, currency;
    if (hasGeoPrice) {
        // User's geo has pricing - use it
        regionPricing = geoPricing;
        currency = userGeo.currency;
    } else if (shouldFallbackToUS) {
        // User outside supported geos - fall back to US as reference
        regionPricing = pricing.US || {};
        currency = 'USD';
    } else {
        // User in supported geo but no pricing for that geo - NO price shown
        regionPricing = {};
        currency = userGeo.currency;
    }

    const currentPrice = regionPricing.current_price || null;
    const avg6m = regionPricing.avg_6m || null;

    return {
        ...product,
        currentPrice,
        currency,
        priceData: regionPricing,
        inStock: regionPricing.instock !== false,
        buyLink: currentPrice ? (regionPricing.tracked_url || null) : null, // No link without price
        retailer: currentPrice ? (regionPricing.retailer || null) : null,   // No retailer without price
        priceIndicator: calculatePriceIndicator(currentPrice, avg6m),
        avg6m,
        _normalized: true,
    };
}

/**
 * Calculate price indicator (% vs 6-month average).
 * @param {number|null} currentPrice - Current price.
 * @param {number|null} avg6m - 6-month average price.
 * @returns {number|null} Percentage difference (negative = below avg).
 */
function calculatePriceIndicator(currentPrice, avg6m) {
    if (!currentPrice || !avg6m || avg6m <= 0) return null;
    return Math.round(((currentPrice - avg6m) / avg6m) * 100);
}

// =============================================================================
// Rendering
// =============================================================================

/**
 * Render all sections.
 * Note: Verdict section is rendered via PHP in single-comparison.php for curated comparisons.
 * Uses isRendering guard to prevent double re-renders from concurrent triggers.
 */
function render() {
    if (isRendering) return;
    isRendering = true;

    try {
        // Render each section independently so one failure doesn't stop others
        try {
            renderProducts();
        } catch (err) {
            console.error('Failed to render products:', err);
        }

        try {
            renderOverview();
        } catch (err) {
            console.error('Failed to render overview:', err);
        }

        try {
            renderSpecs();
        } catch (err) {
            console.error('Failed to render specs:', err);
        }

        try {
            // Re-hydrate Value Analysis with correct currency symbols
            hydrateValueAnalysis();
        } catch (err) {
            console.error('Failed to hydrate value analysis:', err);
        }
    } finally {
        isRendering = false;
    }
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
        try {
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
                        <a href="${ensureAbsoluteUrl(p.buyLink)}" class="compare-product-cta btn btn-primary btn-sm" target="_blank" rel="sponsored noopener">
                            Buy at ${p.retailer}
                            <svg class="icon" width="14" height="14"><use href="#icon-external-link"></use></svg>
                        </a>
                    ` : ''}
                </div>
            </article>
        `;
        } catch (err) {
            console.error(`Failed to render product ${p?.id}:`, err);
            return `<article class="compare-product compare-product--error" data-product-id="${p?.id || 'unknown'}">
                <div class="compare-product-error">Failed to load product</div>
            </article>`;
        }
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
    const icon = isBelow ? 'arrow-down' : 'arrow-up';
    const absPercent = Math.abs(indicator);

    return `
        <span class="compare-product-indicator ${cls}">
            <svg class="icon compare-product-indicator-icon" aria-hidden="true"><use href="#icon-${icon}"></use></svg>${absPercent}%
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

    // Check if SSR content exists and we haven't hydrated yet
    const ssrRadar = container.querySelector('[data-radar-container]');
    const ssrAdvantages = container.querySelector('.compare-advantages');

    if (ssrRadar && ssrAdvantages && !ssrHydrated) {
        // SSR hydration mode: Just render radar chart, advantages already rendered by PHP
        // CSS will auto-hide skeleton via .compare-radar-chart:not(:empty) + .compare-radar-loading
        ssrHydrated = true;
        renderRadarChart();
        return;
    }

    // Client-side mode: Render structure first (with loading state for advantages)
    container.innerHTML = `
        <div class="compare-overview-grid">
            <div class="compare-radar">
                <h3 class="compare-radar-title">Category Scores</h3>
                <div class="compare-radar-chart" data-radar-chart></div>
            </div>
            <div class="compare-advantages compare-advantages--loading">
                ${products.map(p => `
                    <div class="compare-advantage">
                        <div class="skeleton skeleton--text" style="width: 60%; height: 1.2em;"></div>
                        <div class="skeleton skeleton--text" style="width: 90%; height: 0.9em; margin-top: 0.5em;"></div>
                        <div class="skeleton skeleton--text" style="width: 85%; height: 0.9em; margin-top: 0.5em;"></div>
                    </div>
                `).join('')}
            </div>
        </div>
    `;

    // Initialize radar chart
    renderRadarChart();

    // Fetch and render advantages from API (single source of truth)
    fetchAndRenderAdvantages();
}

/**
 * Fetch advantages from REST API and render them.
 * Uses PHP's erh_calculate_spec_advantages() as single source of truth.
 *
 * Supports three modes:
 * - single: 1 product (for product pages) - TODO
 * - head_to_head: 2 products (current implementation)
 * - multi: 3+ products (for multi-compare) - TODO
 */
async function fetchAndRenderAdvantages() {
    const advantagesContainer = document.querySelector('.compare-advantages');
    if (!advantagesContainer || products.length < 1) return;

    const productIds = products.map(p => p.id).join(',');
    const { geo } = await getUserGeo();
    const { restUrl, nonce } = window.erhData || {};

    if (!restUrl) {
        console.error('erhData.restUrl not available');
        advantagesContainer.classList.remove('compare-advantages--loading');
        return;
    }

    try {
        const response = await fetch(`${restUrl}compare/advantages?products=${productIds}&geo=${geo}`, {
            headers: {
                'X-WP-Nonce': nonce || '',
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();

        if (!data.success) {
            console.error('Advantages API error:', data.message);
            return;
        }

        // Handle different modes
        if (data.mode === 'head_to_head' && data.advantages) {
            // 2-product comparison - render advantages
            advantagesContainer.innerHTML = renderAdvantagesGrid(products, data.advantages);
        } else if (data.mode === 'multi' && data.advantages) {
            // 3+ products - render "best at" advantages per product
            advantagesContainer.innerHTML = renderMultiAdvantagesGrid(products, data.advantages);
        } else if (data.mode === 'single') {
            // Single product - TODO: implement single product advantages
            // For now, hide the section
            advantagesContainer.innerHTML = '';
        }
    } catch (err) {
        console.error('Failed to fetch advantages:', err);
    } finally {
        advantagesContainer.classList.remove('compare-advantages--loading');
    }
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

// NOTE: generateAdvantages() and renderAdvantageCard() removed.
// Advantages now fetched from REST API (single source of truth with PHP).
// See fetchAndRenderAdvantages() above.

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
                                            <a href="${ensureAbsoluteUrl(p.buyLink)}" class="compare-mini-price" target="_blank" rel="sponsored noopener">
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

    // Build final HTML: mini-header + scroll wrapper with categories.
    // Always render scroll wrapper for consistency with SSR (CSS only enables overflow in full-width mode).
    const miniHeader = renderMiniHeader();
    const categoriesHtml = categories.join('');

    container.innerHTML = `
        ${miniHeader}
        <div class="compare-specs-scroll">${categoriesHtml}</div>
    `;

    // Sync horizontal scroll between mini-header and tables (safe to call even when not in full-width mode)
    setupScrollSync();

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
 * Includes mappings for both e-scooters and e-bikes.
 */
const CATEGORY_SCORE_KEYS = {
    // Shared categories (same name, same key)
    'Motor Performance': 'motor_performance',
    'Ride Quality': 'ride_quality',

    // E-scooter specific
    'Range & Battery': 'range_battery',
    'Portability & Fit': 'portability',
    'Safety': 'safety',
    'Features': 'features',
    'Maintenance': 'maintenance',

    // E-bike specific
    'Battery & Range': 'range_battery',
    'Drivetrain & Components': 'drivetrain',
    'Weight & Portability': 'portability',
    'Features & Tech': 'features',
    'Safety & Compliance': 'safety',
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

    // Cleanup previous observer and sentinel to prevent memory leaks
    if (navStuckObserver) {
        navStuckObserver.disconnect();
        navStuckObserver = null;
    }
    if (navStuckSentinel && navStuckSentinel.parentNode) {
        navStuckSentinel.remove();
        navStuckSentinel = null;
    }

    // Insert a sentinel element right before the nav
    navStuckSentinel = document.createElement('div');
    navStuckSentinel.style.cssText = 'height: 1px; margin-bottom: -1px; pointer-events: none;';
    nav.parentNode.insertBefore(navStuckSentinel, nav);

    navStuckObserver = new IntersectionObserver(
        ([entry]) => {
            // When sentinel scrolls out of view, nav is stuck
            nav.classList.toggle('is-stuck', !entry.isIntersecting);
        },
        { threshold: 0, rootMargin: '0px' }
    );

    navStuckObserver.observe(navStuckSentinel);
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
    // Clean up any existing drag-to-scroll handlers first
    cleanupDragToScroll();

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

    // Enable drag-to-scroll on both elements (only if overflow exists)
    setupDragToScroll(specsScroll);
    setupDragToScroll(miniHeader);
}

/**
 * Clean up all drag-to-scroll handlers and reset cursor styles.
 */
function cleanupDragToScroll() {
    dragToScrollCleanups.forEach(cleanup => cleanup());
    dragToScrollCleanups = [];
}

/**
 * Enable mouse drag to scroll horizontally on an element.
 * Only enables if element has actual horizontal overflow.
 * Skipped on mobile/touch devices where native touch scroll works.
 * @param {HTMLElement} element - Scrollable element to enable drag on.
 */
function setupDragToScroll(element) {
    if (!element) return;

    // Skip on mobile - touch scrolling is native
    if (window.innerWidth <= 768) {
        element.style.cursor = '';
        return;
    }

    // Only enable if there's actual horizontal overflow
    if (element.scrollWidth <= element.clientWidth) {
        element.style.cursor = '';
        return;
    }

    let isDown = false;
    let startX = 0;
    let scrollLeft = 0;

    element.style.cursor = 'grab';

    const onMouseDown = (e) => {
        // Don't interfere with clicks on links/buttons
        if (e.target.closest('a, button, input, label')) return;

        isDown = true;
        element.style.cursor = 'grabbing';
        element.style.userSelect = 'none';
        startX = e.pageX - element.offsetLeft;
        scrollLeft = element.scrollLeft;
    };

    const onMouseLeave = () => {
        if (!isDown) return;
        isDown = false;
        element.style.cursor = 'grab';
        element.style.userSelect = '';
    };

    const onMouseUp = () => {
        isDown = false;
        element.style.cursor = 'grab';
        element.style.userSelect = '';
    };

    const onMouseMove = (e) => {
        if (!isDown) return;
        e.preventDefault();
        const x = e.pageX - element.offsetLeft;
        const walk = (x - startX) * 1.5; // Scroll speed multiplier
        element.scrollLeft = scrollLeft - walk;
    };

    element.addEventListener('mousedown', onMouseDown);
    element.addEventListener('mouseleave', onMouseLeave);
    element.addEventListener('mouseup', onMouseUp);
    element.addEventListener('mousemove', onMouseMove);

    // Store cleanup function
    dragToScrollCleanups.push(() => {
        element.removeEventListener('mousedown', onMouseDown);
        element.removeEventListener('mouseleave', onMouseLeave);
        element.removeEventListener('mouseup', onMouseUp);
        element.removeEventListener('mousemove', onMouseMove);
        element.style.cursor = '';
        element.style.userSelect = '';
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
        // Same geo pricing logic as enrichProduct: no fallback for supported geos
        const geo = userGeo.geo || 'US';
        const geoPricing = p.pricing?.[geo];
        const hasGeoPrice = geoPricing?.current_price != null;
        const isInSupportedGeo = isValidRegion(geo);

        // Only show price if: geo has price, OR user outside supported geos (fallback to US)
        let price = null, currency = userGeo.currency;
        if (hasGeoPrice) {
            price = geoPricing.current_price;
        } else if (!isInSupportedGeo && p.pricing?.US?.current_price) {
            price = p.pricing.US.current_price;
            currency = 'USD';
        }

        const priceHtml = price ? formatPrice(price, currency) : '';
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
 * Hide curated-only sections when products change.
 *
 * Once products are added/removed, the comparison is no longer "curated"
 * so we remove the intro, verdict, and related sections permanently.
 */
function hideCuratedSections() {
    const config = window.erhData?.compareConfig;
    if (!config?.isCurated) return;

    // Remove curated sections from DOM
    const sectionsToRemove = [
        '.compare-intro',           // H1 and intro text
        '.compare-section--verdict', // Verdict section
    ];

    sectionsToRemove.forEach(selector => {
        const el = document.querySelector(selector);
        if (el) el.remove();
    });

    // Remove verdict nav link
    const verdictNavLink = document.querySelector('[data-nav-link="verdict"]');
    if (verdictNavLink) verdictNavLink.remove();

    // Mark as no longer curated (prevents future renders from treating as curated)
    config.isCurated = false;
}

/**
 * Add product to comparison.
 */
function addProduct(product) {
    // Hide curated sections when products change
    hideCuratedSections();

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

    // Hide curated sections when products change
    hideCuratedSections();

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
export default { init };

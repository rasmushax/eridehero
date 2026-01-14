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
    SPEC_GROUPS,
    CATEGORY_WEIGHTS,
    ADVANTAGE_THRESHOLD,
    MAX_ADVANTAGES,
    formatSpecValue,
    compareValues,
    calculatePercentDiff,
    calculateAbsoluteCategoryScore,
} from '../config/compare-config.js';

// =============================================================================
// Constants
// =============================================================================

const SELECTORS = {
    page: '[data-compare-page]',
    header: '[data-compare-header]',
    products: '[data-compare-products]',
    nav: '[data-compare-nav]',
    navLink: '[data-nav-link]',
    section: '[data-section]',
    overview: '[data-compare-overview]',
    specs: '[data-compare-specs]',
    pricing: '[data-compare-pricing]',
    addModal: '#compare-add-modal',
    searchInput: '[data-compare-search]',
    searchResults: '[data-compare-results]',
    addBtn: '[data-compare-add]',
    inputs: '[data-compare-inputs]',
};

const HEADER_HEIGHT = 72;
const NAV_HEIGHT = 48;
const SCROLL_OFFSET = HEADER_HEIGHT + NAV_HEIGHT + 24;

// =============================================================================
// State
// =============================================================================

let products = [];
let allProducts = [];
let category = 'escooter';
let userGeo = { geo: 'US', currency: 'USD' };
let radarChart = null;

// =============================================================================
// Initialization
// =============================================================================

/**
 * Initialize compare page.
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
    }

    // Load products
    await loadProducts(config.productIds);

    if (products.length < 2) {
        showError('Could not load product data.');
        return;
    }

    // Render all sections
    render();

    // Set up interactions
    setupScrollSpy();
    setupAddProduct();
    setupNavStuckState();
    setupDiffToggle();

    // Track comparison view (fire and forget).
    trackComparisonView(config.productIds);

    // Load related comparisons if curated.
    if (config.isCurated) {
        loadRelatedComparisons(config.productIds, category);
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
        window.location.href = buildCompareUrl(selected.map(p => p.id));
    };
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
    renderPricing();
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

        // Score ring calculation (0-100 scale, circumference = 2 * PI * radius)
        const radius = 15;
        const circumference = 2 * Math.PI * radius;
        const scorePercent = Math.min(100, Math.max(0, score)); // Clamp 0-100
        const offset = circumference - (scorePercent / 100) * circumference;

        return `
            <article class="compare-product" data-product-id="${p.id}">
                <!-- Score ring (top-left) -->
                <div class="compare-product-score" title="${score} points">
                    <svg class="compare-product-score-ring" viewBox="0 0 36 36">
                        <circle class="compare-product-score-track" cx="18" cy="18" r="${radius}" />
                        <circle class="compare-product-score-progress" cx="18" cy="18" r="${radius}"
                                style="stroke-dasharray: ${circumference}; stroke-dashoffset: ${offset};" />
                    </svg>
                    <span class="compare-product-score-value">${score}</span>
                </div>

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
 */
function renderOverview() {
    const container = document.querySelector(SELECTORS.overview);
    if (!container || products.length < 2) return;

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
    const weights = CATEGORY_WEIGHTS[category] || {};
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
    const groups = SPEC_GROUPS[category] || {};

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

            if (winsAll && bestDiff >= ADVANTAGE_THRESHOLD) {
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

    return advantages.sort((a, b) => b.diff - a.diff).slice(0, MAX_ADVANTAGES);
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

    return `
        <div class="compare-mini-header">
            <table class="compare-mini-table">
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
                                                ${price} at ${p.retailer}
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

    const groups = SPEC_GROUPS[category] || {};
    const sections = [];

    // Add sticky mini-header at top of specs
    sections.push(renderMiniHeader());

    for (const [name, group] of Object.entries(groups)) {
        const specsWithValues = (group.specs || []).filter(spec => {
            const values = products.map(p => getSpec(p, spec.key));
            return !values.every(v => v == null || v === '');
        });

        if (!specsWithValues.length) continue;

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

        sections.push(`
            <div class="compare-spec-category">
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

    container.innerHTML = sections.join('');
}

/**
 * Render mobile stacked cards for specs.
 * Each spec becomes a card showing all products' values.
 */
function renderMobileSpecCards(specs) {
    return specs.map(spec => {
        const values = products.map(p => getSpec(p, spec.key));
        const winners = findWinners(values, spec);

        const tooltipHtml = spec.tooltip ? `
            <span class="info-trigger" data-tooltip="${escapeHtml(spec.tooltip)}" data-tooltip-trigger="click">
                <svg class="icon" width="14" height="14"><use href="#icon-info"></use></svg>
            </span>
        ` : '';

        const productValues = products.map((p, i) => {
            const formatted = formatSpecValue(values[i], spec);
            const isWinner = winners.includes(i);
            return `
                <div class="compare-spec-card-value${isWinner ? ' is-winner' : ''}">
                    <span class="compare-spec-card-product">
                        <img src="${p.thumbnail || ''}" alt="">
                        ${escapeHtml(p.name)}
                    </span>
                    <span class="compare-spec-card-data">${formatted}</span>
                </div>
            `;
        }).join('');

        return `
            <div class="compare-spec-card">
                <div class="compare-spec-card-label">
                    ${spec.label}${tooltipHtml}
                </div>
                <div class="compare-spec-card-values">
                    ${productValues}
                </div>
            </div>
        `;
    }).join('');
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
                <span class="compare-feature-badge">
                    <svg class="icon" aria-hidden="true"><use href="#icon-${icon}"></use></svg>
                </span>
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
 */
function renderSpecRows(specs) {
    const rows = [];

    for (const spec of specs) {
        // Handle feature arrays specially - expand into individual rows
        if (spec.format === 'featureArray') {
            rows.push(...renderFeatureArrayRows(spec));
            continue;
        }

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
                    <svg class="icon" width="14" height="14"><use href="#icon-info"></use></svg>
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
            const formatted = formatSpecValue(v, spec);
            const isWinner = winners.includes(i);
            if (isWinner) {
                return `
                    <td class="is-winner">
                        <div class="compare-spec-value-inner">
                            <span class="compare-spec-badge">
                                <svg class="icon" aria-hidden="true"><use href="#icon-check"></use></svg>
                            </span>
                            <span class="compare-spec-value-text">${formatted}</span>
                        </div>
                    </td>
                `;
            }
            return `<td>${formatted}</td>`;
        }).join('');

        // Click-activated tooltip with info circle icon
        const tooltipHtml = spec.tooltip ? `
            <span class="info-trigger" data-tooltip="${escapeHtml(spec.tooltip)}" data-tooltip-trigger="click">
                <svg class="icon" width="14" height="14"><use href="#icon-info"></use></svg>
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

/**
 * Render pricing section.
 */
function renderPricing() {
    const container = document.querySelector(SELECTORS.pricing);
    if (!container) return;

    const rows = [
        pricingRow('Current Price', products.map(p => ({
            html: p.currentPrice ? formatPrice(p.currentPrice, p.currency) : '—',
            raw: p.currentPrice || Infinity,
        })), true),
        pricingRow('vs 3-Month Avg', products.map(p => {
            const curr = p.currentPrice;
            const avg = p.priceData?.avg_3m;
            if (!curr || !avg) return { html: '—', raw: 0 };
            const diff = ((curr - avg) / avg) * 100;
            const cls = diff < -3 ? 'is-good' : diff > 3 ? 'is-bad' : '';
            const icon = diff < 0 ? 'arrow-down' : diff > 0 ? 'arrow-up' : '';
            const iconHtml = icon ? `<svg class="icon compare-price-icon" aria-hidden="true"><use href="#icon-${icon}"></use></svg>` : '';
            return { html: `<span class="${cls}">${iconHtml}${Math.abs(Math.round(diff))}%</span>`, raw: diff };
        }), false, true),
        pricingRow('All-Time Low', products.map(p => {
            const low = p.priceData?.low_all;
            return { html: low ? formatPrice(low, p.currency) : '—', raw: low || 0 };
        })),
        pricingRow('Value/mi', products.map(p => {
            const price = p.currentPrice;
            const range = getSpec(p, 'tested_range_regular') || getSpec(p, 'manufacturer_range');
            if (!price || !range) return { html: '—', raw: Infinity };
            const perMile = price / range;
            const symbol = getCurrencySymbol(p.currency);
            return { html: `${symbol}${perMile.toFixed(0)}/mi`, raw: perMile };
        }), true),
    ];

    const actions = `
        <tr class="compare-pricing-actions">
            <td></td>
            ${products.map(p => `
                <td>
                    <div class="compare-pricing-btns">
                        ${p.currentPrice ? `<button class="btn btn--ghost btn--sm" data-track="${p.id}" data-name="${escapeHtml(p.name)}"
                            data-image="${p.thumbnail || ''}" data-price="${p.currentPrice}" data-currency="${p.currency}">
                            <svg class="icon" width="14" height="14"><use href="#icon-bell"></use></svg>
                            Track
                        </button>` : ''}
                        ${p.buyLink ? `<a href="${p.buyLink}" class="btn btn--primary btn--sm" target="_blank" rel="noopener">Buy</a>` : ''}
                    </div>
                </td>
            `).join('')}
        </tr>
    `;

    container.innerHTML = `
        <table class="compare-pricing-table">
            <thead>
                <tr>
                    <th></th>
                    ${products.map(p => `<th>${escapeHtml(p.name)}</th>`).join('')}
                </tr>
            </thead>
            <tbody>${rows.join('')}${actions}</tbody>
        </table>
    `;

    // Track buttons
    container.querySelectorAll('[data-track]').forEach(btn => {
        btn.addEventListener('click', () => {
            PriceAlertModal.open({
                productId: parseInt(btn.dataset.track, 10),
                productName: btn.dataset.name,
                productImage: btn.dataset.image,
                currentPrice: parseFloat(btn.dataset.price) || 0,
                currency: btn.dataset.currency || 'USD',
            });
        });
    });
}

/**
 * Create pricing table row.
 */
function pricingRow(label, values, lowerWins = true, invertWinner = false) {
    const valid = values.filter(v => v.raw !== Infinity && v.raw !== 0);
    let winnerIdx = -1;

    if (valid.length >= 2) {
        const target = invertWinner || lowerWins
            ? Math.min(...valid.map(v => v.raw))
            : Math.max(...valid.map(v => v.raw));
        winnerIdx = values.findIndex(v => v.raw === target);
    }

    const cells = values.map((v, i) => {
        const cls = i === winnerIdx ? 'is-winner' : '';
        return `<td class="${cls}">${v.html}</td>`;
    }).join('');

    return `<tr><td>${label}</td>${cells}</tr>`;
}

// Note: Verdict section is rendered via PHP in single-comparison.php for curated comparisons.
// No JS rendering needed.

// =============================================================================
// Scoring
// =============================================================================

/**
 * Calculate overall product score.
 * Uses pre-calculated backend score when available, falls back to JS calculation.
 */
function calculateProductScore(product) {
    const specs = product.specs || product;

    // Try pre-calculated overall score first (from backend CacheRebuildJob).
    if (specs.scores && typeof specs.scores.overall === 'number') {
        return specs.scores.overall;
    }

    // Fallback to JS calculation using category scores.
    const weights = CATEGORY_WEIGHTS[category] || {};
    let total = 0, weightSum = 0;

    for (const [name, weight] of Object.entries(weights)) {
        total += calculateCategoryScore(product, name) * weight;
        weightSum += weight;
    }

    return weightSum > 0 ? Math.round(total / weightSum) : 0;
}

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
 * Calculate category score using absolute scoring.
 * Uses pre-calculated backend scores when available, falls back to JS calculation.
 */
function calculateCategoryScore(product, categoryName) {
    const specs = product.specs || product;

    // Try pre-calculated scores first (from backend CacheRebuildJob).
    const scoreKey = CATEGORY_SCORE_KEYS[categoryName];
    if (scoreKey && specs.scores && typeof specs.scores[scoreKey] === 'number') {
        return specs.scores[scoreKey];
    }

    // Fallback to JS calculation.
    return calculateAbsoluteCategoryScore(specs, categoryName, category);
}

// =============================================================================
// Interactions
// =============================================================================

/**
 * Set up scroll spy for section nav.
 */
function setupScrollSpy() {
    const nav = document.querySelector(SELECTORS.nav);
    const sections = document.querySelectorAll(SELECTORS.section);
    if (!nav || !sections.length) return;

    const links = nav.querySelectorAll(SELECTORS.navLink);

    // Smooth scroll on click
    links.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const id = link.getAttribute('href')?.slice(1);
            const section = document.getElementById(id);
            if (section) {
                const y = section.offsetTop - SCROLL_OFFSET;
                window.scrollTo({ top: y, behavior: 'smooth' });
            }
        });
    });

    // Scroll spy
    const updateActive = () => {
        let current = '';
        sections.forEach(s => {
            if (window.scrollY >= s.offsetTop - SCROLL_OFFSET - 50) {
                current = s.dataset.section;
            }
        });

        links.forEach(link => {
            link.classList.toggle('is-active', link.dataset.navLink === current);
        });
    };

    window.addEventListener('scroll', throttle(updateActive, 100), { passive: true });
    updateActive();
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
    products.push(enrichProduct(product));
    updateUrl();
    render();
}

/**
 * Remove product from comparison.
 */
function removeProduct(id) {
    if (products.length <= 2) return;
    products = products.filter(p => p.id !== id);
    updateUrl();
    render();
}

/**
 * Update URL with current products.
 */
function updateUrl() {
    const ids = products.map(p => p.id);
    const url = buildCompareUrl(ids);
    window.history.replaceState({}, '', url);
}

// =============================================================================
// Helpers
// =============================================================================

/**
 * Get nested spec value.
 */
function getSpec(product, key) {
    const specs = product.specs || product;
    return key.split('.').reduce((obj, k) => obj?.[k], specs) ?? null;
}

/**
 * Find winner indices.
 */
function findWinners(values, spec) {
    const valid = values.map((v, i) => ({ v, i })).filter(x => x.v != null && x.v !== '');
    if (valid.length < 2) return [];

    let best = valid[0];
    for (const item of valid.slice(1)) {
        if (compareValues(item.v, best.v, spec) < 0) best = item;
    }

    const winners = valid.filter(x => compareValues(x.v, best.v, spec) === 0).map(x => x.i);
    return winners.length === valid.length ? [] : winners;
}

/**
 * Build compare URL.
 */
function buildCompareUrl(ids) {
    const base = window.erhData?.siteUrl || '';
    if (ids.length <= 4) {
        const slugs = ids.map(id => {
            const p = allProducts.find(x => x.id === id);
            if (p?.slug) return p.slug;
            const match = p?.url?.match(/\/([^/]+)\/?$/);
            return match?.[1] || null;
        }).filter(Boolean);

        if (slugs.length === ids.length) {
            return `${base}/compare/${slugs.join('-vs-')}/`;
        }
    }
    return `${base}/compare/?products=${ids.join(',')}`;
}

/**
 * Close modal.
 */
function closeModal() {
    const modal = document.querySelector(SELECTORS.addModal);
    modal?.querySelector('[data-modal-close]')?.click();
}

/**
 * Show error.
 */
function showError(msg) {
    const container = document.querySelector(SELECTORS.overview);
    if (container) {
        container.innerHTML = `<div class="compare-error"><p>${msg}</p></div>`;
    }
}

// escapeHtml imported from utils/dom.js

/**
 * Throttle function.
 */
function throttle(fn, wait) {
    let last = 0;
    return function(...args) {
        const now = Date.now();
        if (now - last >= wait) {
            last = now;
            fn.apply(this, args);
        }
    };
}

// =============================================================================
// View Tracking
// =============================================================================

/**
 * Track comparison view via REST API.
 * Uses sessionStorage to deduplicate - only tracks once per session per unique pair.
 *
 * @param {number[]} productIds - Array of product IDs being compared.
 */
async function trackComparisonView(productIds) {
    if (!productIds || productIds.length < 2) return;

    // Generate all unique pairs from the product IDs.
    const pairs = generatePairs(productIds);
    if (!pairs.length) return;

    // Filter to pairs not yet tracked this session.
    const untracked = pairs.filter(pair => !isTrackedThisSession(pair));
    if (!untracked.length) return;

    try {
        const restUrl = window.erhData?.restUrl || '/wp-json/erh/v1/';
        const nonce = window.erhData?.nonce || '';

        await fetch(`${restUrl}compare/track`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
            },
            body: JSON.stringify({ product_ids: productIds }),
        });

        // Mark all pairs as tracked for this session.
        untracked.forEach(pair => markTrackedThisSession(pair));
    } catch (e) {
        // Silent fail - tracking is non-critical.
        console.debug('Comparison tracking failed:', e);
    }
}

/**
 * Generate all unique pairs from an array of product IDs.
 * Each pair is normalized with lower ID first.
 *
 * @param {number[]} ids - Array of product IDs.
 * @returns {string[]} Array of pair keys like "123-456".
 */
function generatePairs(ids) {
    const pairs = [];
    for (let i = 0; i < ids.length; i++) {
        for (let j = i + 1; j < ids.length; j++) {
            // Canonical order: lower ID first.
            const a = Math.min(ids[i], ids[j]);
            const b = Math.max(ids[i], ids[j]);
            pairs.push(`${a}-${b}`);
        }
    }
    return pairs;
}

/**
 * Check if a pair has been tracked this session.
 *
 * @param {string} pairKey - Pair key like "123-456".
 * @returns {boolean} True if already tracked.
 */
function isTrackedThisSession(pairKey) {
    try {
        return sessionStorage.getItem(`erh_compared_${pairKey}`) === '1';
    } catch {
        // sessionStorage may be unavailable (private mode, etc.).
        return false;
    }
}

/**
 * Mark a pair as tracked for this session.
 *
 * @param {string} pairKey - Pair key like "123-456".
 */
function markTrackedThisSession(pairKey) {
    try {
        sessionStorage.setItem(`erh_compared_${pairKey}`, '1');
    } catch {
        // Silent fail if sessionStorage unavailable.
    }
}

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

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

import { getUserGeo, formatPrice } from '../services/geo-price.js';
import { PriceAlertModal } from './price-alert.js';
import { Modal } from './modal.js';
import { RadarChart } from './radar-chart.js';
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
    setupStickyBehavior();
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

    return {
        ...product,
        currentPrice: regionPricing.current_price || null,
        currency: pricing[geo] ? userGeo.currency : 'USD',
        priceData: regionPricing,
        inStock: regionPricing.instock !== false,
        buyLink: regionPricing.bestlink || product.url,
    };
}

// =============================================================================
// Rendering
// =============================================================================

/**
 * Render all sections.
 */
function render() {
    renderProducts();
    renderOverview();
    renderSpecs();
    renderPricing();
}

/**
 * Render product header cards.
 */
function renderProducts() {
    const container = document.querySelector(SELECTORS.products);
    if (!container) return;

    const cards = products.map(p => {
        const score = calculateProductScore(p);
        const price = p.currentPrice ? formatPrice(p.currentPrice, p.currency) : '—';

        return `
            <div class="compare-product" data-product-id="${p.id}">
                ${products.length > 2 ? `
                    <button class="compare-product-remove" data-remove-product="${p.id}" aria-label="Remove">
                        <svg class="icon" width="14" height="14"><use href="#icon-x"></use></svg>
                    </button>
                ` : ''}
                <img src="${p.thumbnail || ''}" alt="" class="compare-product-img">
                <h3 class="compare-product-name">${escapeHtml(p.name)}</h3>
                <div class="compare-product-score">${score}<span>pts</span></div>
                <div class="compare-product-price">${price}</div>
                <div class="compare-product-actions">
                    <a href="${p.url}" class="btn btn--ghost btn--xs">View</a>
                    <a href="${p.buyLink}" class="btn btn--primary btn--xs" target="_blank" rel="noopener">Buy</a>
                </div>
            </div>
        `;
    }).join('');

    const addCard = `
        <button class="compare-product compare-product--add" data-open-add-modal>
            <svg class="icon" width="24" height="24"><use href="#icon-plus"></use></svg>
            <span>Add</span>
        </button>
    `;

    container.innerHTML = cards + addCard;

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
 * Render specs section.
 */
function renderSpecs() {
    const container = document.querySelector(SELECTORS.specs);
    if (!container) return;

    const groups = SPEC_GROUPS[category] || {};
    const sections = [];

    for (const [name, group] of Object.entries(groups)) {
        const rows = renderSpecRows(group.specs || []);
        if (!rows.length) continue;

        sections.push(`
            <div class="compare-spec-group" data-group>
                <button class="compare-spec-group-header" data-toggle-group>
                    <span>${name}</span>
                    <svg class="icon" width="16" height="16"><use href="#icon-chevron-down"></use></svg>
                </button>
                <div class="compare-spec-group-body">
                    <table class="compare-spec-table">
                        <thead>
                            <tr>
                                <th></th>
                                ${products.map(p => `<th>${escapeHtml(p.name)}</th>`).join('')}
                            </tr>
                        </thead>
                        <tbody>${rows.join('')}</tbody>
                    </table>
                </div>
            </div>
        `);
    }

    container.innerHTML = sections.join('');

    // Collapsible groups
    container.querySelectorAll('[data-toggle-group]').forEach(btn => {
        btn.addEventListener('click', () => {
            btn.closest('[data-group]')?.classList.toggle('is-collapsed');
        });
    });
}

/**
 * Render spec table rows.
 */
function renderSpecRows(specs) {
    const rows = [];

    for (const spec of specs) {
        const values = products.map(p => getSpec(p, spec.key));
        if (values.every(v => v == null || v === '')) continue;

        const winners = findWinners(values, spec);

        const cells = values.map((v, i) => {
            const formatted = formatSpecValue(v, spec);
            const cls = winners.includes(i) ? 'is-winner' : '';
            return `<td class="${cls}">${formatted}</td>`;
        }).join('');

        const tooltip = spec.tooltip ? `<span class="compare-spec-tip" title="${escapeHtml(spec.tooltip)}">?</span>` : '';

        rows.push(`<tr><td>${spec.label}${tooltip}</td>${cells}</tr>`);
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
            const arrow = diff < 0 ? '↓' : diff > 0 ? '↑' : '';
            return { html: `<span class="${cls}">${arrow}${Math.abs(Math.round(diff))}%</span>`, raw: diff };
        }), false, true),
        pricingRow('All-Time Low', products.map(p => {
            const low = p.priceData?.low_all;
            return { html: low ? formatPrice(low, p.currency) : '—', raw: low || 0 };
        })),
        pricingRow('Value ($/mi)', products.map(p => {
            const price = p.currentPrice;
            const range = getSpec(p, 'tested_range_regular') || getSpec(p, 'manufacturer_range');
            if (!price || !range) return { html: '—', raw: Infinity };
            const perMile = price / range;
            return { html: `$${perMile.toFixed(0)}/mi`, raw: perMile };
        }), true),
    ];

    const actions = `
        <tr class="compare-pricing-actions">
            <td></td>
            ${products.map(p => `
                <td>
                    <div class="compare-pricing-btns">
                        <button class="btn btn--ghost btn--sm" data-track="${p.id}" data-name="${escapeHtml(p.name)}"
                            data-image="${p.thumbnail || ''}" data-price="${p.currentPrice || ''}" data-currency="${p.currency}">
                            <svg class="icon" width="14" height="14"><use href="#icon-bell"></use></svg>
                            Track
                        </button>
                        <a href="${p.buyLink}" class="btn btn--primary btn--sm" target="_blank" rel="noopener">Buy</a>
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

// =============================================================================
// Scoring
// =============================================================================

/**
 * Calculate overall product score.
 */
function calculateProductScore(product) {
    const weights = CATEGORY_WEIGHTS[category] || {};
    let total = 0, weightSum = 0;

    for (const [name, weight] of Object.entries(weights)) {
        total += calculateCategoryScore(product, name) * weight;
        weightSum += weight;
    }

    return weightSum > 0 ? Math.round(total / weightSum) : 0;
}

/**
 * Calculate category score using absolute scoring.
 * Returns fixed scores based on thresholds, not relative comparison.
 */
function calculateCategoryScore(product, categoryName) {
    const specs = product.specs || product;
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
 * Set up sticky header/nav behavior.
 */
function setupStickyBehavior() {
    const header = document.querySelector(SELECTORS.header);
    const nav = document.querySelector(SELECTORS.nav);
    if (!header || !nav) return;

    const updateSticky = () => {
        const scrollY = window.scrollY;
        header.classList.toggle('is-sticky', scrollY > 100);
        nav.classList.toggle('is-sticky', scrollY > 200);
    };

    window.addEventListener('scroll', throttle(updateSticky, 50), { passive: true });
    updateSticky();
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

/**
 * Escape HTML.
 */
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[c]);
}

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

export default { init };

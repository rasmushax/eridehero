/**
 * Finder Table View
 * Handles table rendering, column/filter management, sorting, and row selection
 * Works in conjunction with the main Finder class
 *
 * Key concept: Each "column" is also a "filter" - adding a column adds both
 * the table column AND a filter card with actual controls above the table.
 */

import { Modal } from './modal.js';

export class FinderTable {
    constructor(finder) {
        // Reference to main Finder instance for shared state
        this.finder = finder;
        this.container = finder.container;

        // DOM elements
        this.tableView = this.container.querySelector('[data-finder-table-view]');
        this.tableEl = this.container.querySelector('[data-finder-table]');
        this.tableHead = this.container.querySelector('[data-table-head]');
        this.tableBody = this.container.querySelector('[data-table-body]');
        this.tableContainer = this.container.querySelector('.finder-table-container');
        this.filterCardsEl = this.container.querySelector('[data-active-columns]');
        this.loadMoreBtn = this.container.querySelector('[data-table-load-more]');
        this.tableFooter = this.container.querySelector('[data-table-footer]');

        // Column modal (uses standard modal system)
        this.columnModalEl = document.getElementById('columns-modal');
        this.columnModal = this.columnModalEl ? new Modal(this.columnModalEl) : null;
        this.columnSearch = document.querySelector('[data-columns-search]');
        this.columnGroupsEl = document.querySelector('[data-columns-groups]');

        // Get configs from PHP
        this.columnGroups = finder.filterConfig.columnGroups || {};
        this.columnConfig = finder.filterConfig.columnConfig || {};
        this.rangeConfig = finder.filterConfig.ranges || {};
        this.setConfig = finder.filterConfig.sets || {};
        this.tristateConfig = finder.filterConfig.tristates || {};
        this.defaultColumns = finder.filterConfig.defaultTableColumns || ['price', 'top_speed', 'motor_power', 'battery', 'weight', 'weight_limit'];

        // Get ranges data (min/max values) from page config
        this.rangesData = finder.pageConfig.ranges || {};

        // State
        this.visibleColumns = new Set();
        this.sortColumn = null;
        this.sortDirection = 'desc';
        this.displayLimit = 50;
        this.displayStep = 50;
        this.currentLimit = this.displayLimit;
        this.initialized = false;

        // Initialize visible columns from URL or defaults
        this.initColumnsFromURL();
    }

    /**
     * Initialize the table view
     */
    init() {
        if (this.initialized) return;

        this.bindEvents();
        this.buildColumnModal();
        this.buildTable();
        this.initialized = true;
    }

    /**
     * Initialize visible columns from URL or use defaults
     */
    initColumnsFromURL() {
        const params = new URLSearchParams(window.location.search);
        const colsParam = params.get('cols');

        if (colsParam) {
            const cols = colsParam.split(',').filter(c => this.columnConfig[c]);
            if (cols.length > 0) {
                this.visibleColumns = new Set(cols);
                return;
            }
        }

        // Use defaults
        this.visibleColumns = new Set(this.defaultColumns);
    }

    /**
     * Bind all event handlers
     */
    bindEvents() {
        // Column modal search
        this.columnSearch?.addEventListener('input', (e) => this.handleColumnSearch(e.target.value));

        // Load more
        this.loadMoreBtn?.addEventListener('click', () => this.loadMore());

        // Table container scroll (for sticky shadow)
        this.tableContainer?.addEventListener('scroll', () => {
            this.tableContainer.classList.toggle('is-scrolled', this.tableContainer.scrollLeft > 0);
        });

        // Add Column button click - use event delegation since button is rendered dynamically
        this.filterCardsEl?.addEventListener('click', (e) => {
            if (e.target.closest('[data-add-column]')) {
                this.openColumnModal();
            }
        });
    }

    /**
     * Build the table structure
     */
    buildTable() {
        this.buildFilterCards();
        this.buildHeader();
        this.renderRows();
        this.updateLoadMoreButton();
    }

    // =========================================
    // FILTER CARDS - Renders same HTML as PHP templates
    // Uses finder.js event delegation, no separate binding needed
    // =========================================

    /**
     * Column key to set filter key mapping
     */
    static SET_MAPPINGS = {
        'brand': 'brands',
        'motor_position': 'motor_positions',
        'brake_type': 'brake_types',
        'tire_type': 'tire_types',
        'suspension_type': 'suspension_types',
        'terrain': 'terrains',
        'ip_rating': 'ip_ratings',
        'throttle_type': 'throttle_types'
    };

    /**
     * Column key to range filter key mapping
     * Maps table column keys to the actual range filter config keys
     */
    static RANGE_MAPPINGS = {
        'top_speed': 'speed',
        'tested_speed': 'tested_speed',
        'range': 'range',
        'tested_range': 'tested_range',
        'battery': 'battery',
        'motor_power': 'motor_power',
        'weight': 'weight',
        'weight_limit': 'weight_limit',
        'price': 'price',
        'max_incline': 'max_incline',
        'accel_0_15': 'accel_0_15',
        'accel_0_20': 'accel_0_20',
        'brake_distance': 'brake_distance',
        'hill_climb': 'hill_climb',
        'charge_time': 'charge_time',
        'voltage': 'voltage',
        'price_per_lb': 'price_per_lb',
        'price_per_mph': 'price_per_mph',
        'price_per_mile': 'price_per_mile',
        'price_per_wh': 'price_per_wh',
        'speed_per_lb': 'speed_per_lb',
        'range_per_lb': 'range_per_lb'
    };

    /**
     * Build filter cards for each visible column
     * Renders HTML matching PHP templates so finder.js delegation works
     */
    buildFilterCards() {
        if (!this.filterCardsEl) return;

        let html = '';

        this.visibleColumns.forEach(colKey => {
            const card = this.renderFilterCard(colKey);
            if (card) {
                html += card;
            }
        });

        // Add the "Add Column" button at the end (inline with filter cards)
        html += `
            <button type="button" class="finder-add-column-btn" data-add-column>
                <svg class="icon"><use href="#icon-plus"></use></svg>
                Add Column
            </button>
        `;

        this.filterCardsEl.innerHTML = html;

        // Bind remove button events (these aren't part of finder.js delegation)
        this.filterCardsEl.querySelectorAll('[data-remove-column]').forEach(btn => {
            btn.addEventListener('click', () => {
                const colKey = btn.dataset.removeColumn;
                this.toggleColumn(colKey, false);
            });
        });

        // Initialize range sliders (for drag functionality)
        this.filterCardsEl.querySelectorAll('[data-range-filter]').forEach(container => {
            this.finder.initRangeSlider(container);
        });
    }

    /**
     * Render a single filter card based on the column type
     */
    renderFilterCard(colKey) {
        const colConfig = this.columnConfig[colKey];
        if (!colConfig) return '';

        const label = colConfig.label || colKey;
        const filterType = this.getFilterType(colKey);

        let controlHtml = '';

        switch (filterType) {
            case 'range':
                controlHtml = this.renderRangeFilter(colKey);
                break;
            case 'set':
                controlHtml = this.renderSetFilter(colKey);
                break;
            case 'tristate':
                controlHtml = this.renderTristateFilter(colKey);
                break;
            default:
                // Display-only column with no filter control
                controlHtml = '<span class="filter-card-display-only">Display only</span>';
        }

        return `
            <div class="filter-card" data-filter-card="${colKey}">
                <div class="filter-card-header">
                    <span class="filter-card-label">${this.escapeHtml(label)}</span>
                    <button type="button" class="filter-card-remove" data-remove-column="${colKey}" aria-label="Remove ${this.escapeHtml(label)}">
                        <svg class="icon"><use href="#icon-x"></use></svg>
                    </button>
                </div>
                <div class="filter-card-body">
                    ${controlHtml}
                </div>
            </div>
        `;
    }

    /**
     * Get the range filter key for a column (using mapping or direct match)
     */
    getRangeFilterKey(colKey) {
        // Check mapping first
        const mappedKey = FinderTable.RANGE_MAPPINGS[colKey];
        if (mappedKey && this.rangeConfig[mappedKey]) {
            return mappedKey;
        }
        // Direct match
        if (this.rangeConfig[colKey]) {
            return colKey;
        }
        return null;
    }

    /**
     * Determine the filter type for a column
     */
    getFilterType(colKey) {
        // Range filters (check mapping)
        if (this.getRangeFilterKey(colKey)) {
            return 'range';
        }

        // Set filters
        if (FinderTable.SET_MAPPINGS[colKey] && this.setConfig[FinderTable.SET_MAPPINGS[colKey]]) {
            return 'set';
        }

        // Tristate filters
        if (this.tristateConfig[colKey]) {
            return 'tristate';
        }

        return null;
    }

    /**
     * Compute distribution histogram for a range filter
     * Matches PHP erh_calc_distribution() logic
     */
    computeDistribution(productKey, min, max, numBuckets = 10) {
        const distribution = new Array(numBuckets).fill(0);
        const range = max - min;
        if (range <= 0) return distribution.map(() => 0);

        this.finder.products.forEach(product => {
            const value = product[productKey];
            if (value == null) return;

            const bucketIndex = Math.min(
                numBuckets - 1,
                Math.floor(((value - min) / range) * numBuckets)
            );
            distribution[bucketIndex]++;
        });

        // Normalize to 0-100
        const maxCount = Math.max(...distribution) || 1;
        return distribution.map(count => Math.round((count / maxCount) * 100));
    }

    /**
     * Compute preset counts for a range filter
     */
    computePresetCounts(productKey, presets, min, max) {
        return presets.map(preset => {
            const presetMin = preset.min ?? min;
            const presetMax = preset.max ?? max;

            return this.finder.products.filter(product => {
                const value = product[productKey];
                if (value == null) return false;
                return value >= presetMin && value <= presetMax;
            }).length;
        });
    }

    /**
     * Render range filter matching PHP range-filter.php structure
     */
    renderRangeFilter(colKey) {
        // Get the actual filter key (may differ from column key)
        const filterKey = this.getRangeFilterKey(colKey);
        if (!filterKey) {
            return '<span class="filter-card-no-data">No filter config</span>';
        }

        // Use filter key for config and data lookups
        const rangeData = this.rangesData[filterKey];
        const rangeCfg = this.rangeConfig[filterKey] || {};

        if (!rangeData) {
            return '<span class="filter-card-no-data">No data available</span>';
        }

        const min = rangeData.min || 0;
        const max = rangeData.max || 100;
        const prefix = rangeCfg.prefix || '';
        const suffix = rangeCfg.suffix || '';
        const step = rangeCfg.round_factor || 1;
        const presets = rangeCfg.presets || [];
        const productKey = rangeCfg.productKey || filterKey;

        // Get current filter values (use filter key for state)
        const currentFilter = this.finder.filters[filterKey] || {};
        const currentMin = currentFilter.min ?? min;
        const currentMax = currentFilter.max ?? max;

        // Compute distribution and preset counts
        const distribution = this.computeDistribution(productKey, min, max);
        const presetCounts = presets.length > 0 ? this.computePresetCounts(productKey, presets, min, max) : [];

        // Calculate slider positions
        const minPos = (currentMin - min) / (max - min);
        const maxPos = (currentMax - min) / (max - min);

        // Build distribution bars HTML
        const barsHtml = distribution.map(height =>
            `<div class="filter-range-bar is-selected" style="--height: ${height}"></div>`
        ).join('');

        // Build presets HTML
        let presetsHtml = '';
        if (presets.length > 0) {
            const presetItems = presets.map((preset, index) => {
                const presetValue = `${preset.min ?? ''}-${preset.max ?? ''}`;
                const count = presetCounts[index] ?? 0;
                return `
                    <label class="filter-preset">
                        <input type="radio" name="${filterKey}_preset" value="${presetValue}" data-preset
                            ${preset.min != null ? `data-preset-min="${preset.min}"` : ''}
                            ${preset.max != null ? `data-preset-max="${preset.max}"` : ''}>
                        <span class="filter-preset-radio"></span>
                        <span class="filter-preset-label">${this.escapeHtml(preset.label)}</span>
                        <span class="filter-preset-count">${count}</span>
                    </label>
                `;
            }).join('');

            presetsHtml = `
                <div class="filter-presets" role="radiogroup" aria-label="${this.escapeHtml(rangeCfg.label || filterKey)} presets">
                    ${presetItems}
                </div>
            `;
        }

        return `
            <div class="filter-range" data-range-filter="${filterKey}" data-min="${min}" data-max="${max}" data-step="${step}">
                <div class="filter-range-inputs">
                    <div class="filter-range-input-group">
                        ${prefix ? `<span class="filter-range-prefix">${prefix}</span>` : ''}
                        <input type="number" class="filter-range-input" data-range-min
                               value="${currentMin}" min="${min}" max="${max}" step="${step}">
                        ${suffix ? `<span class="filter-range-suffix">${suffix}</span>` : ''}
                    </div>
                    <span class="filter-range-separator">–</span>
                    <div class="filter-range-input-group">
                        ${prefix ? `<span class="filter-range-prefix">${prefix}</span>` : ''}
                        <input type="number" class="filter-range-input" data-range-max
                               value="${currentMax}" min="${min}" max="${max}" step="${step}">
                        ${suffix ? `<span class="filter-range-suffix">${suffix}</span>` : ''}
                    </div>
                </div>
                <div class="filter-range-distribution" aria-hidden="true">
                    ${barsHtml}
                </div>
                <div class="filter-range-slider" data-range-slider>
                    <div class="filter-range-track"></div>
                    <div class="filter-range-fill" style="--min: ${minPos}; --max: ${maxPos};"></div>
                    <div class="filter-range-handle" data-handle="min" style="--pos: ${minPos};"></div>
                    <div class="filter-range-handle" data-handle="max" style="--pos: ${maxPos};"></div>
                </div>
                ${presetsHtml}
            </div>
        `;
    }

    /**
     * Render set filter matching PHP checkbox-filter.php structure
     */
    renderSetFilter(colKey) {
        const setKey = FinderTable.SET_MAPPINGS[colKey];
        if (!setKey) return '';

        const setCfg = this.setConfig[setKey] || {};
        const productKey = setCfg.productKey || colKey;
        const selector = setCfg.selector || setKey; // Use selector for data-filter to match sidebar

        // Get current selections
        const currentSet = this.finder.filters[setKey] || new Set();

        // Get available options with counts
        const options = this.getSetOptions(productKey);
        if (options.length === 0) {
            return '<span class="filter-card-no-data">No options available</span>';
        }

        const visibleLimit = 6;
        const hasOverflow = options.length > visibleLimit;

        // Build checkbox list HTML
        const checkboxItems = options.map((opt, index) => {
            const isChecked = currentSet.has(opt.value);
            const isHidden = hasOverflow && index >= visibleLimit;

            return `
                <label class="filter-checkbox${isHidden ? ' is-hidden-by-limit' : ''}">
                    <input type="checkbox" name="${selector}" value="${this.escapeHtml(opt.value)}"
                           data-filter="${selector}" ${isChecked ? 'checked' : ''}>
                    <span class="filter-checkbox-box">
                        <svg class="icon"><use href="#icon-check"></use></svg>
                    </span>
                    <span class="filter-checkbox-label">${this.escapeHtml(opt.value)}</span>
                    <span class="filter-checkbox-count">${opt.count}</span>
                </label>
            `;
        }).join('');

        // Show all button if overflow
        const showAllHtml = hasOverflow ? `
            <button type="button" class="filter-show-all" data-filter-show-all="${setKey}">
                <span data-show-text>Show all ${options.length}</span>
                <span data-hide-text hidden>Show less</span>
                <svg class="icon"><use href="#icon-chevron-down"></use></svg>
            </button>
        ` : '';

        return `
            <div class="filter-checkbox-list" data-filter-list="${setKey}" data-limit="${visibleLimit}">
                ${checkboxItems}
            </div>
            ${showAllHtml}
        `;
    }

    /**
     * Render tristate filter matching PHP tristate-filter.php structure
     */
    renderTristateFilter(colKey) {
        const tristateCfg = this.tristateConfig[colKey] || {};
        const productKey = tristateCfg.productKey || colKey;

        // Get current value
        const currentValue = this.finder.filters[colKey] || 'any';

        // Compute yes/no counts
        let yesCount = 0;
        let noCount = 0;
        this.finder.products.forEach(product => {
            const value = product[productKey];
            if (value === true || value === 'Yes' || value === 'yes' || value === 1) {
                yesCount++;
            } else if (value === false || value === 'No' || value === 'no' || value === 0) {
                noCount++;
            }
        });

        return `
            <div class="filter-tristate" data-tristate-filter="${colKey}">
                <div class="filter-tristate-control" role="radiogroup" aria-label="${this.escapeHtml(tristateCfg.label || colKey)}">
                    <button type="button" class="filter-tristate-btn ${currentValue === 'any' ? 'is-active' : ''}"
                            data-value="any" role="radio" aria-checked="${currentValue === 'any'}">
                        Any
                    </button>
                    <button type="button" class="filter-tristate-btn ${currentValue === 'yes' ? 'is-active' : ''}"
                            data-value="yes" role="radio" aria-checked="${currentValue === 'yes'}" title="${yesCount} products">
                        Yes
                    </button>
                    <button type="button" class="filter-tristate-btn ${currentValue === 'no' ? 'is-active' : ''}"
                            data-value="no" role="radio" aria-checked="${currentValue === 'no'}" title="${noCount} products">
                        No
                    </button>
                </div>
                <input type="hidden" name="${colKey}" value="${currentValue}" data-tristate-input>
            </div>
        `;
    }

    /**
     * Get set options with counts from products
     */
    getSetOptions(productKey) {
        const counts = {};

        this.finder.products.forEach(product => {
            const value = product[productKey];
            if (value) {
                if (Array.isArray(value)) {
                    value.forEach(v => {
                        counts[v] = (counts[v] || 0) + 1;
                    });
                } else {
                    counts[value] = (counts[value] || 0) + 1;
                }
            }
        });

        // Sort by count descending
        return Object.entries(counts)
            .map(([value, count]) => ({ value, count }))
            .sort((a, b) => b.count - a.count);
    }

    // =========================================
    // TABLE HEADER & ROWS
    // =========================================

    /**
     * Build table header with sortable columns
     */
    buildHeader() {
        if (!this.tableHead) return;

        const columns = this.getColumnDefinitions();

        let html = '<tr>';

        // Fixed first column: product
        html += `<th>Product</th>`;

        // Dynamic columns
        columns.forEach(col => {
            const sortable = col.sortable !== false;
            const isActive = this.sortColumn === col.columnKey;
            const sortAttr = isActive ? `data-sort-active="${this.sortDirection}"` : '';

            html += `<th ${sortable ? 'data-sortable' : ''} data-column="${col.columnKey}" ${sortAttr}>
                ${col.label}
                ${sortable ? `<span class="finder-table-sort-icon"><svg class="icon"><use href="#icon-arrow-up"></use></svg></span>` : ''}
            </th>`;
        });

        html += '</tr>';
        this.tableHead.innerHTML = html;

        // Bind sort click events
        this.tableHead.querySelectorAll('th[data-sortable]').forEach(th => {
            th.addEventListener('click', () => this.handleSort(th.dataset.column));
        });
    }

    /**
     * Get column definitions for visible columns
     */
    getColumnDefinitions() {
        return Array.from(this.visibleColumns).map(key => {
            const config = this.columnConfig[key] || {};
            return {
                columnKey: key,
                key: config.key || key,
                label: config.label || key,
                type: config.type || 'text',
                prefix: config.prefix || '',
                suffix: config.suffix || '',
                round: config.round,
                sortable: config.sortable !== false,
                sortDir: config.sort_dir || 'desc'
            };
        });
    }

    /**
     * Render table rows for filtered products
     */
    renderRows() {
        if (!this.tableBody) return;

        const products = this.finder.filteredProducts.slice(0, this.currentLimit);
        const columns = this.getColumnDefinitions();

        if (products.length === 0) {
            this.tableBody.innerHTML = `<tr><td colspan="${columns.length + 1}" class="finder-table-cell--empty" style="text-align: center; padding: 48px;">No products match your filters</td></tr>`;
            return;
        }

        let html = '';

        products.forEach(product => {
            const isSelected = this.finder.selectedProducts.has(String(product.id));
            const rowClass = isSelected ? 'is-selected' : '';

            html += `<tr class="${rowClass}" data-product-id="${product.id}">`;

            // Fixed first column: product with checkbox, image, name
            html += `<td>${this.renderProductCell(product)}</td>`;

            // Dynamic columns
            columns.forEach(col => {
                html += `<td>${this.renderCell(col, product)}</td>`;
            });

            html += '</tr>';
        });

        this.tableBody.innerHTML = html;

        // Bind checkbox events using event delegation on tbody
        this.tableBody.addEventListener('change', (e) => {
            const checkbox = e.target.closest('[data-table-compare]');
            if (!checkbox) return;

            const productId = checkbox.value;
            if (checkbox.checked) {
                if (this.finder.selectedProducts.size >= this.finder.maxCompare) {
                    checkbox.checked = false;
                    return;
                }
                this.finder.selectedProducts.add(productId);
            } else {
                this.finder.selectedProducts.delete(productId);
            }

            // Update row styling
            const row = checkbox.closest('tr');
            row?.classList.toggle('is-selected', checkbox.checked);

            // Update comparison bar and sync with grid
            this.finder.updateComparisonBar();
            this.syncSelectionWithGrid();
        });
    }

    /**
     * Render the product cell (checkbox + image + name)
     */
    renderProductCell(product) {
        const isSelected = this.finder.selectedProducts.has(String(product.id));
        const imgSrc = product.thumbnail || '';

        return `
            <div class="finder-table-product-cell">
                <label class="finder-table-product-checkbox">
                    <input type="checkbox" value="${product.id}" data-table-compare ${isSelected ? 'checked' : ''}>
                    <span class="finder-table-product-checkbox-box">
                        <svg class="icon"><use href="#icon-check"></use></svg>
                    </span>
                </label>
                ${imgSrc ? `
                    <div class="finder-table-product-img-wrap">
                        <img src="${imgSrc}" alt="" class="finder-table-product-img" loading="lazy">
                    </div>
                ` : ''}
                <a href="${product.url}" class="finder-table-product-name">${this.escapeHtml(product.name)}</a>
            </div>
        `;
    }

    /**
     * Render a cell value based on column type
     */
    renderCell(col, product) {
        const value = product[col.key];

        // Special handling for price - uses geo-aware pricing with trend
        if (col.type === 'currency' && col.key === 'price') {
            return this.renderPriceCell(product);
        }

        // Handle null/undefined
        if (value == null || value === '') {
            return '<span class="finder-table-cell--empty">-</span>';
        }

        switch (col.type) {
            case 'currency':
                return this.renderCurrencyCell(value);

            case 'rating':
                return this.renderRatingCell(value);

            case 'boolean':
                return this.renderBooleanCell(value);

            case 'array':
                return this.renderArrayCell(value);

            case 'number':
                return this.renderNumberCell(value, col);

            case 'text':
            default:
                // Check for suffix/prefix
                if (col.suffix || col.prefix) {
                    return this.renderNumberCell(value, col);
                }
                return this.escapeHtml(String(value));
        }
    }

    /**
     * Render price cell with geo-aware pricing and trend indicator
     */
    renderPriceCell(product) {
        const price = product.price;

        if (price == null) {
            return '<span class="finder-table-cell--empty">-</span>';
        }

        const formatted = this.finder.formatProductPrice({ price });
        const indicator = this.getPriceIndicator(product);

        return `<span class="finder-table-cell--price">${formatted}${indicator}</span>`;
    }

    /**
     * Get price trend indicator (% vs 3-month average)
     */
    getPriceIndicator(product) {
        const indicator = product.price_indicator;
        if (indicator == null) return '';

        if (indicator < -5) {
            return `<span class="finder-table-indicator finder-table-indicator--below">
                <span class="finder-table-indicator-arrow">↓</span>${Math.abs(indicator)}%
            </span>`;
        } else if (indicator > 10) {
            return `<span class="finder-table-indicator finder-table-indicator--above">
                <span class="finder-table-indicator-arrow">↑</span>${indicator}%
            </span>`;
        }
        return '';
    }

    /**
     * Render a simple currency value (not the main price column)
     */
    renderCurrencyCell(value) {
        const formatted = this.finder.formatProductPrice({ price: value });
        return `<span class="finder-table-cell--price">${formatted}</span>`;
    }

    renderRatingCell(value) {
        const num = parseFloat(value);
        if (isNaN(num)) return this.escapeHtml(String(value));

        let ratingClass = 'average';
        if (num >= 9) ratingClass = 'excellent';
        else if (num >= 8) ratingClass = 'good';
        else if (num < 6) ratingClass = 'poor';

        return `<span class="finder-table-rating finder-table-rating--${ratingClass}">${num.toFixed(1)}</span>`;
    }

    renderBooleanCell(value) {
        const isTrue = value === true || value === 'Yes' || value === 'yes' || value === '1' || value === 1;
        const icon = isTrue ? 'check' : 'x';
        const cls = isTrue ? 'yes' : 'no';

        return `<span class="finder-table-cell--boolean">
            <span class="finder-table-bool finder-table-bool--${cls}">
                <svg class="icon"><use href="#icon-${icon}"></use></svg>
            </span>
        </span>`;
    }

    renderArrayCell(value) {
        if (Array.isArray(value)) {
            return `<span class="finder-table-cell--array">${this.escapeHtml(value.join(', '))}</span>`;
        }
        return this.escapeHtml(String(value));
    }

    renderNumberCell(value, col) {
        let num = parseFloat(value);
        if (isNaN(num)) {
            return `${col.prefix || ''}${this.escapeHtml(String(value))}${col.suffix || ''}`;
        }

        if (col.round !== undefined) {
            num = col.round === 0 ? Math.round(num) : parseFloat(num.toFixed(col.round));
        }

        return `${col.prefix || ''}${num}${col.suffix || ''}`;
    }

    // =========================================
    // SORTING
    // =========================================

    handleSort(columnKey) {
        const config = this.columnConfig[columnKey] || {};
        const productKey = config.key || columnKey;
        const defaultDir = config.sort_dir || 'desc';

        if (this.sortColumn === columnKey) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortColumn = columnKey;
            this.sortDirection = defaultDir;
        }

        this.sortProducts(productKey);
        this.buildHeader();
        this.renderRows();
        this.updateLoadMoreButton();
    }

    sortProducts(productKey) {
        const dir = this.sortDirection;

        this.finder.filteredProducts.sort((a, b) => {
            let aVal = a[productKey];
            let bVal = b[productKey];

            if (aVal == null && bVal == null) return 0;
            if (aVal == null) return dir === 'asc' ? 1 : -1;
            if (bVal == null) return dir === 'asc' ? -1 : 1;

            const aNum = parseFloat(aVal);
            const bNum = parseFloat(bVal);

            if (!isNaN(aNum) && !isNaN(bNum)) {
                return dir === 'asc' ? aNum - bNum : bNum - aNum;
            }

            const aStr = String(aVal).toLowerCase();
            const bStr = String(bVal).toLowerCase();
            return dir === 'asc' ? aStr.localeCompare(bStr) : bStr.localeCompare(aStr);
        });
    }

    // =========================================
    // COLUMN MANAGEMENT
    // =========================================

    toggleColumn(key, visible) {
        if (visible) {
            this.visibleColumns.add(key);
        } else {
            this.visibleColumns.delete(key);

            // Clear filter when removing column
            this.clearFilterForColumn(key);
        }

        if (!visible && this.sortColumn === key) {
            this.sortColumn = null;
            this.sortDirection = 'desc';
        }

        this.buildTable();
        this.updateURL();
        this.updateModalCheckboxes();
    }

    /**
     * Clear the filter state for a column when it's removed
     */
    clearFilterForColumn(colKey) {
        // Range filters (use mapping)
        const rangeFilterKey = this.getRangeFilterKey(colKey);
        if (rangeFilterKey && this.finder.filters[rangeFilterKey]) {
            const rangeCfg = this.rangeConfig[rangeFilterKey] || {};
            if (rangeCfg.filterMode === 'contains') {
                this.finder.filters[rangeFilterKey] = { value: null };
            } else {
                this.finder.filters[rangeFilterKey] = { min: null, max: null };
            }
        }

        // Tristate filters
        if (this.tristateConfig[colKey]) {
            this.finder.filters[colKey] = 'any';
        }

        // Set filters - use static mapping
        const setKey = FinderTable.SET_MAPPINGS[colKey];
        if (setKey && this.finder.filters[setKey]) {
            this.finder.filters[setKey].clear();
        }

        // Apply the filter change
        this.finder.applyFilters();
    }

    updateURL() {
        const url = new URL(window.location.href);
        const cols = Array.from(this.visibleColumns).join(',');

        if (cols === this.defaultColumns.join(',')) {
            url.searchParams.delete('cols');
        } else {
            url.searchParams.set('cols', cols);
        }

        window.history.replaceState({}, '', url);
    }

    // =========================================
    // COLUMN MODAL
    // =========================================

    buildColumnModal() {
        if (!this.columnGroupsEl) return;

        let html = '';

        Object.entries(this.columnGroups).forEach(([groupKey, group]) => {
            html += `
                <div class="finder-columns-group" data-column-group="${groupKey}">
                    <h4>${group.label}</h4>
                    <div class="finder-columns-options">
                        ${group.columns.map(colKey => {
                            const config = this.columnConfig[colKey] || {};
                            const checked = this.visibleColumns.has(colKey) ? 'checked' : '';
                            return `
                                <label class="finder-columns-option" data-column-option="${colKey}">
                                    <input type="checkbox" value="${colKey}" data-column-toggle ${checked}>
                                    <span>${config.label || colKey}</span>
                                </label>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        });

        html += '<p class="finder-columns-empty" hidden>No specs match your search</p>';

        this.columnGroupsEl.innerHTML = html;

        this.columnGroupsEl.addEventListener('change', (e) => {
            if (e.target.matches('[data-column-toggle]')) {
                const key = e.target.value;
                this.toggleColumn(key, e.target.checked);
            }
        });
    }

    updateModalCheckboxes() {
        if (!this.columnGroupsEl) return;

        this.columnGroupsEl.querySelectorAll('[data-column-toggle]').forEach(checkbox => {
            checkbox.checked = this.visibleColumns.has(checkbox.value);
        });
    }

    openColumnModal() {
        if (!this.columnModal) return;

        this.columnModal.open();
        // Focus search input after modal animation
        setTimeout(() => this.columnSearch?.focus(), 200);
    }

    closeColumnModal() {
        if (!this.columnModal) return;

        this.columnModal.close();

        if (this.columnSearch) {
            this.columnSearch.value = '';
            this.handleColumnSearch('');
        }
    }

    handleColumnSearch(query) {
        const searchTerm = query.toLowerCase().trim();
        const groups = this.columnGroupsEl?.querySelectorAll('[data-column-group]') || [];
        const emptyMsg = this.columnGroupsEl?.querySelector('.finder-columns-empty');
        let hasResults = false;

        groups.forEach(group => {
            const options = group.querySelectorAll('[data-column-option]');
            let groupHasVisible = false;

            options.forEach(option => {
                const label = option.querySelector('span')?.textContent?.toLowerCase() || '';
                const matches = !searchTerm || label.includes(searchTerm);
                option.hidden = !matches;
                if (matches) groupHasVisible = true;
            });

            group.hidden = !groupHasVisible;
            if (groupHasVisible) hasResults = true;
        });

        if (emptyMsg) {
            emptyMsg.hidden = hasResults;
        }
    }

    // =========================================
    // PAGINATION & VISIBILITY
    // =========================================

    loadMore() {
        this.currentLimit += this.displayStep;
        this.renderRows();
        this.updateLoadMoreButton();
    }

    updateLoadMoreButton() {
        if (!this.loadMoreBtn) return;

        const hasMore = this.currentLimit < this.finder.filteredProducts.length;
        this.loadMoreBtn.hidden = !hasMore;
    }

    updateData() {
        if (!this.initialized) return;

        this.currentLimit = this.displayLimit;

        if (this.sortColumn) {
            const config = this.columnConfig[this.sortColumn] || {};
            const productKey = config.key || this.sortColumn;
            this.sortProducts(productKey);
        }

        // Rebuild filter cards to reflect current filter state
        this.buildFilterCards();
        this.renderRows();
        this.updateLoadMoreButton();
    }

    show() {
        if (!this.initialized) {
            this.init();
        } else {
            this.updateData();
        }
        this.tableView.hidden = false;
    }

    hide() {
        this.tableView.hidden = true;
        this.closeColumnModal();
    }

    syncSelectionWithGrid() {
        this.finder.grid?.querySelectorAll('[data-compare-select]').forEach(checkbox => {
            checkbox.checked = this.finder.selectedProducts.has(checkbox.value);
        });
    }

    syncSelectionFromGrid() {
        if (!this.tableBody) return;

        this.tableBody.querySelectorAll('[data-table-compare]').forEach(checkbox => {
            const productId = checkbox.value;
            const isSelected = this.finder.selectedProducts.has(productId);
            checkbox.checked = isSelected;

            const row = checkbox.closest('tr');
            row?.classList.toggle('is-selected', isSelected);
        });
    }

    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

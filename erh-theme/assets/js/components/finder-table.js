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

        // Portal system: track filters moved from sidebar to cards
        // Maps colKey -> { source: parentElement, children: array of moved elements }
        this.portaledFilters = new Map();

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

    // =========================================
    // PORTAL SYSTEM
    // Moves actual filter elements from sidebar to cards
    // Single source of truth - no duplicate rendering
    // =========================================

    /**
     * Find the sidebar filter element for a column key
     * Uses filterKey and filterType from column config (single source of truth from PHP)
     * Returns the inner control element (not the wrapper)
     */
    getSidebarFilterElement(colKey) {
        const colConfig = this.columnConfig[colKey];
        if (!colConfig?.filterKey || !colConfig?.filterType) return null;

        const { filterKey, filterType } = colConfig;
        const sidebar = this.finder.sidebar;
        if (!sidebar) return null;

        switch (filterType) {
            case 'range': {
                // Range filters: use filterKey from config
                return sidebar.querySelector(`[data-range-filter="${filterKey}"]`);
            }
            case 'set': {
                // Set filters: find the checkbox list by filterKey
                const checkboxList = sidebar.querySelector(`[data-filter-list="${filterKey}"]`);
                if (!checkboxList) return null;

                // Check if nested in filter-item (collapsible) or standalone
                const filterItem = checkboxList.closest('[data-filter-item]');
                if (filterItem) {
                    // Nested: return the content container
                    return filterItem.querySelector('.filter-item-content');
                }

                // Standalone (like Brand): return the filter-group-content wrapper
                const filterGroup = checkboxList.closest('[data-filter-group]');
                if (filterGroup) {
                    return filterGroup.querySelector('.filter-group-content');
                }

                return null;
            }
            case 'tristate': {
                // Tristate filters: use filterKey from config
                return sidebar.querySelector(`[data-tristate-filter="${filterKey}"]`);
            }
            default:
                return null;
        }
    }

    /**
     * Portal a filter from sidebar to a filter card
     * Stores original parent for restoration
     */
    portalFilterToCard(colKey, cardBodyEl) {
        const filterEl = this.getSidebarFilterElement(colKey);
        if (!filterEl) return false;

        const originalParent = filterEl.parentNode;

        // Store original parent and next sibling for restoration
        this.portaledFilters.set(colKey, {
            element: filterEl,
            originalParent: originalParent,
            nextSibling: filterEl.nextSibling
        });

        // Move the element to the card body
        cardBodyEl.appendChild(filterEl);

        // Add a class to indicate it's portaled (for styling adjustments)
        filterEl.classList.add('is-portaled');

        // Mark parent filter-group as having portaled content (hides empty shell)
        const filterGroup = originalParent.closest('[data-filter-group]');
        if (filterGroup) {
            filterGroup.classList.add('has-portaled-content');
        }

        return true;
    }

    /**
     * Return a portaled filter back to its original sidebar location
     */
    returnFilterToSidebar(colKey) {
        const portalData = this.portaledFilters.get(colKey);
        if (!portalData) return;

        const { element, originalParent, nextSibling } = portalData;

        // Remove portaled class
        element.classList.remove('is-portaled');

        // Return to original position
        if (nextSibling) {
            originalParent.insertBefore(element, nextSibling);
        } else {
            originalParent.appendChild(element);
        }

        // Remove portaled marker from parent filter-group
        const filterGroup = originalParent.closest('[data-filter-group]');
        if (filterGroup) {
            filterGroup.classList.remove('has-portaled-content');
        }

        this.portaledFilters.delete(colKey);
    }

    /**
     * Return all portaled filters to sidebar
     * Called when hiding table view
     */
    returnAllFiltersToSidebar() {
        for (const colKey of this.portaledFilters.keys()) {
            this.returnFilterToSidebar(colKey);
        }
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

        // Handle resize across mobile breakpoint
        // Mobile: return filters to sidebar (table is CSS-hidden, sidebar drawer needs filters)
        // Desktop: re-portal filters if table view is active
        const mobileQuery = window.matchMedia('(max-width: 900px)');
        mobileQuery.addEventListener('change', (e) => {
            if (e.matches) {
                // Crossed into mobile - return filters to sidebar
                if (this.portaledFilters.size > 0) {
                    this.returnAllFiltersToSidebar();
                }
            } else {
                // Crossed into desktop - rebuild filter cards if in table view
                const isTableView = this.container.dataset.view === 'table';
                if (isTableView && this.initialized) {
                    this.buildFilterCards();
                }
            }
        });

        // Note: Filter search/show-all events are handled by finder.js delegation
        // since we portal actual sidebar elements (which have their event handlers attached)
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
    // FILTER CARDS - Portal system
    // Moves actual filter elements from sidebar to cards
    // Uses finder.js event delegation (no duplicate binding needed)
    // =========================================


    /**
     * Build filter cards for each visible column
     * Uses portal system to move actual filter elements from sidebar
     */
    buildFilterCards() {
        if (!this.filterCardsEl) return;

        // First, return any previously portaled filters
        this.returnAllFiltersToSidebar();

        // Clear existing cards
        this.filterCardsEl.innerHTML = '';

        // Build cards for each visible column
        this.visibleColumns.forEach(colKey => {
            const card = this.createFilterCard(colKey);
            if (card) {
                this.filterCardsEl.appendChild(card);
            }
        });

        // Add the "Add Column" button at the end
        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'finder-add-column-btn';
        addBtn.setAttribute('data-add-column', '');
        addBtn.innerHTML = `
            <svg class="icon"><use href="#icon-plus"></use></svg>
            Add Column
        `;
        this.filterCardsEl.appendChild(addBtn);
    }

    /**
     * Create a single filter card and portal the filter into it
     * Only creates cards for columns that have actual filters (not display-only)
     */
    createFilterCard(colKey) {
        const colConfig = this.columnConfig[colKey];
        if (!colConfig) return null;

        // Only create cards for columns with filters
        const filterType = this.getFilterType(colKey);
        if (!filterType) return null;

        const label = colConfig.label || colKey;

        // Create card wrapper
        const card = document.createElement('div');
        card.className = 'filter-card';
        card.dataset.filterCard = colKey;

        // Create header
        const header = document.createElement('div');
        header.className = 'filter-card-header';
        header.innerHTML = `
            <span class="filter-card-label">${this.escapeHtml(label)}</span>
            <button type="button" class="filter-card-remove" data-remove-column="${colKey}" aria-label="Remove ${this.escapeHtml(label)}">
                <svg class="icon"><use href="#icon-x"></use></svg>
            </button>
        `;

        // Bind remove button
        header.querySelector('[data-remove-column]').addEventListener('click', () => {
            this.toggleColumn(colKey, false);
        });

        // Create body
        const body = document.createElement('div');
        body.className = 'filter-card-body';

        // Portal the filter from sidebar
        if (!this.portalFilterToCard(colKey, body)) {
            // Portal failed - filter element not found in sidebar
            return null;
        }

        card.appendChild(header);
        card.appendChild(body);

        return card;
    }

    /**
     * Get filter info from column config (single source of truth)
     * Returns { filterKey, filterType } or null if column has no filter
     */
    getFilterInfo(colKey) {
        const colConfig = this.columnConfig[colKey];
        if (!colConfig?.filterKey || !colConfig?.filterType) return null;
        return { filterKey: colConfig.filterKey, filterType: colConfig.filterType };
    }

    /**
     * Determine the filter type for a column
     * Uses filterType from column config
     */
    getFilterType(colKey) {
        return this.columnConfig[colKey]?.filterType || null;
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
                ${sortable ? `<span class="finder-table-sort-icon">
                    <svg class="icon icon--neutral"><use href="#icon-sort"></use></svg>
                    <svg class="icon icon--asc"><use href="#icon-sort-up"></use></svg>
                    <svg class="icon icon--desc"><use href="#icon-sort-up"></use></svg>
                </span>` : ''}
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
                <svg class="icon finder-table-indicator-icon" aria-hidden="true"><use href="#icon-arrow-down"></use></svg>${Math.abs(indicator)}%
            </span>`;
        } else if (indicator > 10) {
            return `<span class="finder-table-indicator finder-table-indicator--above">
                <svg class="icon finder-table-indicator-icon" aria-hidden="true"><use href="#icon-arrow-up"></use></svg>${indicator}%
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
        const oppositeDir = defaultDir === 'desc' ? 'asc' : 'desc';

        if (this.sortColumn === columnKey) {
            // 3-click cycle: defaultDir → opposite → none
            if (this.sortDirection === defaultDir) {
                this.sortDirection = oppositeDir;
            } else {
                // Reset to unsorted
                this.sortColumn = null;
                this.sortDirection = 'desc';
            }
        } else {
            this.sortColumn = columnKey;
            this.sortDirection = defaultDir;
        }

        if (this.sortColumn) {
            this.sortProducts(productKey);
        } else {
            // Reset to finder's default sort order
            this.finder.sortFilteredProducts();
        }

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
     * Uses filterKey and filterType from column config
     */
    clearFilterForColumn(colKey) {
        const filterInfo = this.getFilterInfo(colKey);
        if (!filterInfo) return;

        const { filterKey, filterType } = filterInfo;

        switch (filterType) {
            case 'range': {
                const rangeCfg = this.rangeConfig[filterKey] || {};
                if (rangeCfg.filterMode === 'contains') {
                    this.finder.filters[filterKey] = { value: null };
                } else {
                    this.finder.filters[filterKey] = { min: null, max: null };
                }
                break;
            }
            case 'tristate': {
                this.finder.filters[filterKey] = 'any';
                break;
            }
            case 'set': {
                // Set filters use pluralized key in JS (brand → brands)
                // Look up the actual filter key from set config
                const setKey = Object.keys(this.setConfig).find(k =>
                    this.setConfig[k].selector === filterKey || k === filterKey + 's' || k === filterKey
                );
                if (setKey && this.finder.filters[setKey]) {
                    this.finder.filters[setKey].clear();
                }
                break;
            }
        }

        // Apply the filter change
        this.finder.applyFilters();
    }

    updateURL() {
        // Delegate to finder.js which handles all URL state
        // This ensures a single source of truth for URL management
        this.finder.updateURL();
    }

    // =========================================
    // COLUMN MODAL
    // =========================================

    buildColumnModal() {
        if (!this.columnGroupsEl) return;

        let html = '';

        Object.entries(this.columnGroups).forEach(([groupKey, group]) => {
            // Only include columns that have actual filters (filterKey defined)
            const filterableColumns = group.columns.filter(colKey => {
                const config = this.columnConfig[colKey];
                return config?.filterKey;
            });

            // Skip empty groups
            if (filterableColumns.length === 0) return;

            html += `
                <div class="finder-columns-group" data-column-group="${groupKey}">
                    <h4>${group.label}</h4>
                    <div class="finder-columns-options">
                        ${filterableColumns.map(colKey => {
                            const config = this.columnConfig[colKey] || {};
                            const checked = this.visibleColumns.has(colKey) ? 'checked' : '';
                            return `
                                <label class="finder-columns-option" data-column-option="${colKey}">
                                    <input type="checkbox" value="${colKey}" data-column-toggle ${checked}>
                                    <span class="finder-columns-option-box">
                                        <svg class="icon"><use href="#icon-check"></use></svg>
                                    </span>
                                    <span class="finder-columns-option-label">${config.label || colKey}</span>
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
        const totalProducts = this.finder.filteredProducts.length;
        const hasMore = this.currentLimit < totalProducts;

        // Hide button when no more products
        if (this.loadMoreBtn) {
            this.loadMoreBtn.hidden = !hasMore;
        }

        // Hide entire footer when no products or all shown
        if (this.tableFooter) {
            this.tableFooter.hidden = totalProducts === 0 || !hasMore;
        }
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
        // Return all portaled filters back to sidebar
        this.returnAllFiltersToSidebar();

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

/**
 * Spec Editor - Table Rendering
 *
 * Renders the spreadsheet table with sorting and group headers.
 * Uses event delegation for memory efficiency.
 *
 * @package ERH\Admin
 */

import { store } from './state.js';
import { startEditing, renderCellValue, cancelCurrentEdit } from './cell-editors.js';
import { updateColumnVisibility } from './column-picker.js';
import { sortProducts, filterProducts } from './utils.js';

const tableContainer = document.getElementById('erh-se-table-container');
const loadingEl = document.getElementById('erh-se-loading');
let tableEl = null;
let delegationInitialized = false;

/**
 * Initialize table rendering.
 */
export function initTable() {
    // Subscribe to state changes.
    store.subscribe(handleStateChange);

    // Set up event delegation once (not per render).
    if (!delegationInitialized && tableContainer) {
        initEventDelegation();
        delegationInitialized = true;
    }
}

/**
 * Initialize event delegation on the table container.
 * This replaces per-cell event listeners for better memory efficiency.
 */
function initEventDelegation() {
    // Delegate cell clicks for editing.
    tableContainer.addEventListener('click', (e) => {
        // Check if clicked on a header (for sorting).
        const th = e.target.closest('th[data-column]');
        if (th) {
            const columnKey = th.dataset.column;
            if (columnKey) {
                handleColumnSort(columnKey);
            }
            return;
        }

        // Check if clicked on a cell (for editing).
        const cell = e.target.closest('.erh-se-cell');
        if (!cell) return;

        const td = cell.closest('td');
        if (!td) return;

        const productId = parseInt(td.dataset.productId, 10);
        const fieldKey = td.dataset.field;

        if (!productId || !fieldKey) return;

        // Skip product name column (handled separately with link).
        if (fieldKey === 'post_title') return;

        // Get column schema.
        const state = store.getState();
        const column = state.schema.find(c => c.key === fieldKey);

        if (!column || column.readonly) return;

        // Get product (fresh from store).
        const product = store.getProductById(productId);
        if (!product) return;

        startEditing(cell, product, column);
    });
}

/**
 * Handle state changes and re-render as needed.
 */
function handleStateChange(state) {
    // This will be called on every state change.
    // We'll do selective re-rendering based on what changed.
}

/**
 * Show loading state.
 */
export function showLoading() {
    if (loadingEl) {
        loadingEl.style.display = 'flex';
    }
    if (tableEl) {
        tableEl.style.display = 'none';
    }
}

/**
 * Hide loading state.
 */
export function hideLoading() {
    if (loadingEl) {
        loadingEl.style.display = 'none';
    }
    if (tableEl) {
        tableEl.style.display = 'table';
    }
}

/**
 * Clear the empty state message if present.
 */
function clearEmptyState() {
    const existingEmpty = tableContainer.querySelector('.erh-se-empty');
    if (existingEmpty) {
        existingEmpty.remove();
    }
}

/**
 * Render the entire table.
 */
export function renderTable() {
    const state = store.getState();

    // Always clear empty state first.
    clearEmptyState();

    // Remove existing table.
    if (tableEl) {
        tableEl.remove();
        tableEl = null;
    }

    if (state.schema.length === 0 || state.products.length === 0) {
        renderEmptyState();
        return;
    }

    // Apply search filter and sorting.
    let displayProducts = filterProducts(state.products, state.searchQuery);
    displayProducts = sortProducts(displayProducts, state.sortColumn, state.sortDirection);

    // Check if filtered results are empty.
    if (displayProducts.length === 0) {
        renderEmptyState();
        return;
    }

    // Create table.
    tableEl = document.createElement('table');
    tableEl.className = 'erh-se-table';

    // Render header.
    const thead = renderTableHeader(state.schema);
    tableEl.appendChild(thead);

    // Render body.
    const tbody = renderTableBody(displayProducts, state.schema);
    tableEl.appendChild(tbody);

    tableContainer.appendChild(tableEl);
    hideLoading();

    // Apply column visibility.
    updateColumnVisibility();
}

/**
 * Render empty state message.
 */
function renderEmptyState() {
    const state = store.getState();
    const { i18n } = window.erhSpecEditor;

    // Clear any existing empty state.
    clearEmptyState();

    // Remove table if present.
    if (tableEl) {
        tableEl.remove();
        tableEl = null;
    }

    const empty = document.createElement('div');
    empty.className = 'erh-se-empty';
    empty.textContent = state.searchQuery
        ? `No products match "${state.searchQuery}"`
        : i18n.noProducts;

    tableContainer.appendChild(empty);
    hideLoading();
}

/**
 * Render table header.
 * @param {Array} schema - Column schema.
 * @returns {HTMLElement} thead element.
 */
function renderTableHeader(schema) {
    const state = store.getState();
    const thead = document.createElement('thead');

    // Main header row with column names.
    const headerRow = document.createElement('tr');

    schema.forEach(column => {
        const th = document.createElement('th');
        th.dataset.column = column.key;

        if (column.pinned) {
            th.classList.add('is-pinned');
        }

        // Sort indicator.
        const isSorted = state.sortColumn === column.key;
        if (isSorted) {
            th.classList.add('is-sorted');
        }

        // Header content.
        const content = document.createElement('div');
        content.className = 'erh-se-th-content';

        const label = document.createElement('span');
        label.textContent = column.label;
        content.appendChild(label);

        // Unit suffix.
        if (column.append) {
            const unit = document.createElement('span');
            unit.className = 'erh-se-th-unit';
            unit.textContent = `(${column.append})`;
            content.appendChild(unit);
        }

        // Sort icon.
        const sortIcon = document.createElement('span');
        sortIcon.className = 'erh-se-sort-icon';
        if (isSorted) {
            sortIcon.textContent = state.sortDirection === 'asc' ? '▲' : '▼';
        } else {
            sortIcon.textContent = '⇅';
        }
        content.appendChild(sortIcon);

        th.appendChild(content);

        // No click listener here - handled by event delegation.

        headerRow.appendChild(th);
    });

    thead.appendChild(headerRow);
    return thead;
}

/**
 * Handle column header click for sorting.
 * @param {string} columnKey - Column key.
 */
function handleColumnSort(columnKey) {
    const state = store.getState();

    let newDirection = 'asc';
    if (state.sortColumn === columnKey) {
        newDirection = state.sortDirection === 'asc' ? 'desc' : 'asc';
    }

    store.setSort(columnKey, newDirection);
    renderTable();
}

/**
 * Render table body.
 * @param {Array} products - Products to display.
 * @param {Array} schema - Column schema.
 * @returns {HTMLElement} tbody element.
 */
function renderTableBody(products, schema) {
    const tbody = document.createElement('tbody');

    products.forEach(product => {
        const row = renderProductRow(product, schema);
        tbody.appendChild(row);
    });

    return tbody;
}

/**
 * Render a product row.
 * No event listeners attached - handled by delegation.
 * @param {Object} product - Product data.
 * @param {Array} schema - Column schema.
 * @returns {HTMLElement} tr element.
 */
function renderProductRow(product, schema) {
    const { adminUrl } = window.erhSpecEditor;
    const row = document.createElement('tr');
    row.dataset.productId = product.id;

    schema.forEach(column => {
        const td = document.createElement('td');
        td.dataset.column = column.key;
        td.dataset.productId = product.id;
        td.dataset.field = column.key;

        if (column.pinned) {
            td.classList.add('is-pinned');
        }

        // Cell wrapper.
        const cell = document.createElement('div');
        cell.className = 'erh-se-cell';

        // Special handling for product name (pinned column).
        if (column.key === 'post_title') {
            const productName = document.createElement('div');
            productName.className = 'erh-se-product-name';

            const link = document.createElement('a');
            link.href = `${adminUrl}post.php?post=${product.id}&action=edit`;
            link.target = '_blank';
            link.textContent = product.title;

            productName.appendChild(link);

            // Status badge.
            if (product.status !== 'publish') {
                const status = document.createElement('span');
                status.className = `erh-se-product-status is-${product.status}`;
                status.textContent = product.status;
                productName.appendChild(status);
            }

            cell.appendChild(productName);

            // Product name is not editable from here.
            td.appendChild(cell);
            row.appendChild(td);
            return;
        }

        // Regular cell with value.
        const value = product.specs[column.key];
        renderCellValue(cell, column, value);

        // No click listener here - handled by event delegation.

        td.appendChild(cell);
        row.appendChild(td);
    });

    return row;
}

/**
 * Update products display when search or sort changes.
 */
export function updateProductsDisplay() {
    const state = store.getState();

    // Always clear empty state first.
    clearEmptyState();

    // Apply search filter and sorting.
    let displayProducts = filterProducts(state.products, state.searchQuery);
    displayProducts = sortProducts(displayProducts, state.sortColumn, state.sortDirection);

    // Check if we need to show empty state.
    if (displayProducts.length === 0) {
        renderEmptyState();
        return;
    }

    // Re-render the table body only.
    if (tableEl) {
        // Make sure table is visible.
        tableEl.style.display = 'table';

        const tbody = tableEl.querySelector('tbody');
        if (tbody) {
            const newTbody = renderTableBody(displayProducts, state.schema);
            tbody.replaceWith(newTbody);
            updateColumnVisibility();
        }
    } else {
        renderTable();
    }
}

/**
 * Update table header to reflect sort state.
 */
export function updateSortIndicators() {
    const state = store.getState();

    if (!tableEl) return;

    const headers = tableEl.querySelectorAll('thead th');
    headers.forEach(th => {
        const columnKey = th.dataset.column;
        const isSorted = state.sortColumn === columnKey;

        th.classList.toggle('is-sorted', isSorted);

        const sortIcon = th.querySelector('.erh-se-sort-icon');
        if (sortIcon) {
            if (isSorted) {
                sortIcon.textContent = state.sortDirection === 'asc' ? '▲' : '▼';
            } else {
                sortIcon.textContent = '⇅';
            }
        }
    });
}

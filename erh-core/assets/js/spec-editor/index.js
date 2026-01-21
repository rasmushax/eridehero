/**
 * Spec Editor - Main Entry Point
 *
 * Excel-like admin dashboard for bulk editing product specifications.
 * Loads all products at once for full client-side sorting and filtering.
 *
 * @package ERH\Admin
 */

import { store } from './state.js';
import { initTable, renderTable, showLoading, updateProductsDisplay } from './table.js';
import { initHistoryUI } from './history.js';
import { initColumnPicker } from './column-picker.js';
import { cancelCurrentEdit } from './cell-editors.js';
import { apiRequest, debounce } from './utils.js';

/**
 * Initialize the spec editor application.
 */
async function init() {
    const config = window.erhSpecEditor;
    if (!config) {
        console.error('Spec Editor: Configuration not found');
        return;
    }

    // Initialize UI components.
    initTable();
    initHistoryUI();
    initColumnPicker();
    initTabs();
    initSearch();
    initStatusBar();

    // Hide pagination element if it exists.
    hidePagination();

    // Load initial data.
    await loadProductType(config.defaultType);
}

/**
 * Hide pagination UI (we load all products now).
 */
function hidePagination() {
    const paginationEl = document.getElementById('erh-se-pagination');
    if (paginationEl) {
        paginationEl.style.display = 'none';
    }
}

/**
 * Initialize product type tabs.
 */
function initTabs() {
    const tabsContainer = document.querySelector('.erh-se-tabs');
    if (!tabsContainer) return;

    const { productTypes } = window.erhSpecEditor;

    // Clear existing tabs.
    tabsContainer.replaceChildren();

    // Create tabs for each product type.
    Object.entries(productTypes).forEach(([key, type]) => {
        const tab = document.createElement('button');
        tab.type = 'button';
        tab.className = 'erh-se-tab';
        tab.dataset.type = key;
        tab.textContent = type.label;

        // Add count badge (will be updated after loading).
        const count = document.createElement('span');
        count.className = 'erh-se-tab-count';
        count.textContent = '—';
        tab.appendChild(count);

        tab.addEventListener('click', () => handleTabClick(key));
        tabsContainer.appendChild(tab);
    });

    // Fetch counts for all types.
    fetchTypeCounts();
}

/**
 * Fetch product counts for all types.
 */
async function fetchTypeCounts() {
    try {
        const response = await apiRequest('types');
        response.types.forEach(type => {
            const tab = document.querySelector(`.erh-se-tab[data-type="${type.key}"]`);
            if (tab) {
                const count = tab.querySelector('.erh-se-tab-count');
                if (count) {
                    count.textContent = type.count;
                }
            }
        });
    } catch (error) {
        console.error('Failed to fetch type counts:', error);
    }
}

/**
 * Handle tab click.
 * @param {string} productType - Product type key.
 */
async function handleTabClick(productType) {
    const state = store.getState();
    if (state.productType === productType) return;

    // Cancel any editing.
    cancelCurrentEdit();

    // Update active tab.
    document.querySelectorAll('.erh-se-tab').forEach(tab => {
        tab.classList.toggle('is-active', tab.dataset.type === productType);
    });

    // Load new product type.
    await loadProductType(productType);
}

/**
 * Load data for a product type.
 * @param {string} productType - Product type key.
 */
async function loadProductType(productType) {
    showLoading();
    store.setProductType(productType);

    // Update active tab.
    document.querySelectorAll('.erh-se-tab').forEach(tab => {
        tab.classList.toggle('is-active', tab.dataset.type === productType);
    });

    try {
        // Load schema.
        store.setLoading({ isLoadingSchema: true });
        const schemaResponse = await apiRequest(`schema/${productType}`);
        store.setSchema(schemaResponse);
        store.setLoading({ isLoadingSchema: false });

        // Load all products (no pagination).
        await loadProducts();
    } catch (error) {
        console.error('Failed to load product type:', error);
        store.showStatus('error', `Failed to load: ${error.message}`, 5000);
        store.setLoading({ isLoadingSchema: false, isLoadingProducts: false });
    }
}

/**
 * Load all products for current type (no pagination).
 */
async function loadProducts() {
    const state = store.getState();

    store.setLoading({ isLoadingProducts: true });
    showLoading();

    try {
        // Load all products (per_page=-1 means all).
        const params = new URLSearchParams({
            per_page: -1,
        });

        const response = await apiRequest(`products/${state.productType}?${params}`);
        store.setProducts(response);
        store.setLoading({ isLoadingProducts: false });

        // Render table.
        renderTable();

        // Update product count in status.
        updateProductCount(response.total);
    } catch (error) {
        console.error('Failed to load products:', error);
        store.showStatus('error', `Failed to load products: ${error.message}`, 5000);
        store.setLoading({ isLoadingProducts: false });
    }
}

/**
 * Update product count display.
 * @param {number} total - Total product count.
 */
function updateProductCount(total) {
    const countEl = document.getElementById('erh-se-product-count');
    if (countEl) {
        const { i18n } = window.erhSpecEditor;
        countEl.textContent = `${total} ${i18n.products}`;
    }
}

/**
 * Initialize search functionality.
 * All filtering is client-side since we load all products.
 */
function initSearch() {
    const searchInput = document.getElementById('erh-se-search');
    if (!searchInput) return;

    // Debounced search handler (client-side filtering only).
    const handleSearch = debounce((query) => {
        store.setSearchQuery(query);
        updateProductsDisplay();
    }, 150);

    searchInput.addEventListener('input', (e) => {
        handleSearch(e.target.value);
    });

    // Ctrl+F to focus search.
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            searchInput.focus();
            searchInput.select();
        }
    });
}

/**
 * Initialize status bar.
 */
function initStatusBar() {
    const statusBar = document.getElementById('erh-se-status-bar');
    if (!statusBar) return;

    // Subscribe to status changes.
    store.subscribe((state) => {
        if (state.status) {
            showStatusBar(state.status.type, state.status.message);
        } else {
            hideStatusBar();
        }
    });
}

/**
 * Show status bar.
 * @param {string} type - Status type.
 * @param {string} message - Status message.
 */
function showStatusBar(type, message) {
    const statusBar = document.getElementById('erh-se-status-bar');
    const statusText = statusBar.querySelector('.erh-se-status-text');
    const statusIcon = statusBar.querySelector('.erh-se-status-icon');

    if (!statusBar) return;

    // Remove existing type classes.
    statusBar.classList.remove('is-saving', 'is-success', 'is-error');
    statusBar.classList.add(`is-${type}`);

    // Update icon.
    statusIcon.replaceChildren();
    if (type === 'saving') {
        const spinner = document.createElement('span');
        spinner.className = 'spinner is-active';
        statusIcon.appendChild(spinner);
    } else if (type === 'success') {
        statusIcon.textContent = '✓';
    } else if (type === 'error') {
        statusIcon.textContent = '✕';
    }

    // Update message.
    statusText.textContent = message;

    // Show bar.
    statusBar.style.display = 'flex';
    statusBar.classList.add('is-visible');
}

/**
 * Hide status bar.
 */
function hideStatusBar() {
    const statusBar = document.getElementById('erh-se-status-bar');
    if (!statusBar) return;

    statusBar.classList.remove('is-visible');
    setTimeout(() => {
        if (!statusBar.classList.contains('is-visible')) {
            statusBar.style.display = 'none';
        }
    }, 200);
}

// Initialize on DOM ready.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

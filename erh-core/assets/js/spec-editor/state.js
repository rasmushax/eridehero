/**
 * Spec Editor - State Management
 *
 * Central state store with event-based updates.
 *
 * @package ERH\Admin
 */

import { getColumnStorageKey, isColumnVisibleByDefault } from './utils.js';

/**
 * Create the spec editor state store.
 * @returns {Object} State store with subscribe/dispatch.
 */
export function createStore() {
    const state = {
        // Current product type tab.
        productType: window.erhSpecEditor?.defaultType || 'escooter',

        // Schema data.
        schema: [],
        groups: {},

        // Products data.
        products: [],
        filteredProducts: [],
        total: 0,
        totalPages: 0,

        // Pagination.
        page: 1,
        perPage: 100,

        // Search.
        searchQuery: '',

        // Sorting.
        sortColumn: 'post_title',
        sortDirection: 'asc',

        // Column visibility.
        columnVisibility: {},

        // Editing state.
        editingCell: null, // { productId, fieldPath }

        // Loading states.
        isLoadingSchema: false,
        isLoadingProducts: false,
        isSaving: false,

        // Status message.
        status: null, // { type: 'saving'|'success'|'error', message: '' }
    };

    // Subscribers.
    const listeners = new Set();

    /**
     * Get current state.
     * @returns {Object} Current state.
     */
    function getState() {
        return { ...state };
    }

    /**
     * Update state and notify listeners.
     * @param {Object} updates - State updates.
     */
    function setState(updates) {
        Object.assign(state, updates);
        listeners.forEach(listener => listener(state));
    }

    /**
     * Subscribe to state changes.
     * @param {Function} listener - Listener function.
     * @returns {Function} Unsubscribe function.
     */
    function subscribe(listener) {
        listeners.add(listener);
        return () => listeners.delete(listener);
    }

    /**
     * Load column visibility from localStorage.
     * @param {string} productType - Product type.
     * @param {Array} columns - Column schema.
     * @returns {Object} Column visibility map.
     */
    function loadColumnVisibility(productType, columns) {
        const storageKey = getColumnStorageKey(productType);
        const stored = localStorage.getItem(storageKey);

        if (stored) {
            try {
                return JSON.parse(stored);
            } catch (e) {
                console.warn('Failed to parse stored column visibility:', e);
            }
        }

        // Default visibility.
        const visibility = {};
        columns.forEach(col => {
            visibility[col.key] = isColumnVisibleByDefault(col);
        });
        return visibility;
    }

    /**
     * Save column visibility to localStorage.
     */
    function saveColumnVisibility() {
        const storageKey = getColumnStorageKey(state.productType);
        localStorage.setItem(storageKey, JSON.stringify(state.columnVisibility));
    }

    /**
     * Toggle column visibility.
     * @param {string} columnKey - Column key.
     */
    function toggleColumnVisibility(columnKey) {
        const newVisibility = {
            ...state.columnVisibility,
            [columnKey]: !state.columnVisibility[columnKey],
        };
        setState({ columnVisibility: newVisibility });
        saveColumnVisibility();
    }

    /**
     * Set all columns visibility.
     * @param {boolean} visible - Visibility state.
     */
    function setAllColumnsVisible(visible) {
        const newVisibility = {};
        state.schema.forEach(col => {
            // Always keep pinned columns visible.
            newVisibility[col.key] = col.pinned ? true : visible;
        });
        setState({ columnVisibility: newVisibility });
        saveColumnVisibility();
    }

    /**
     * Reset column visibility to defaults.
     */
    function resetColumnVisibility() {
        const visibility = {};
        state.schema.forEach(col => {
            visibility[col.key] = isColumnVisibleByDefault(col);
        });
        setState({ columnVisibility: visibility });
        saveColumnVisibility();
    }

    /**
     * Set product type and reset related state.
     * @param {string} productType - Product type key.
     */
    function setProductType(productType) {
        setState({
            productType,
            schema: [],
            groups: {},
            products: [],
            filteredProducts: [],
            total: 0,
            totalPages: 0,
            page: 1,
            searchQuery: '',
            sortColumn: 'post_title',
            sortDirection: 'asc',
            editingCell: null,
        });
    }

    /**
     * Set schema data.
     * @param {Object} data - Schema response data.
     */
    function setSchema(data) {
        const visibility = loadColumnVisibility(state.productType, data.columns);
        setState({
            schema: data.columns,
            groups: data.groups,
            columnVisibility: visibility,
        });
    }

    /**
     * Set products data.
     * @param {Object} data - Products response data.
     */
    function setProducts(data) {
        setState({
            products: data.products,
            filteredProducts: data.products,
            total: data.total,
            totalPages: data.total_pages,
            page: data.page,
        });
    }

    /**
     * Update a product's spec value in state.
     * @param {number} productId - Product ID.
     * @param {string} fieldPath - Field path.
     * @param {*} newValue - New value.
     */
    function updateProductSpec(productId, fieldPath, newValue) {
        const products = state.products.map(product => {
            if (product.id === productId) {
                return {
                    ...product,
                    specs: {
                        ...product.specs,
                        [fieldPath]: newValue,
                    },
                };
            }
            return product;
        });

        const filteredProducts = state.filteredProducts.map(product => {
            if (product.id === productId) {
                return {
                    ...product,
                    specs: {
                        ...product.specs,
                        [fieldPath]: newValue,
                    },
                };
            }
            return product;
        });

        setState({ products, filteredProducts });
    }

    /**
     * Set editing cell.
     * @param {Object|null} cell - { productId, fieldPath } or null.
     */
    function setEditingCell(cell) {
        setState({ editingCell: cell });
    }

    /**
     * Set search query.
     * @param {string} query - Search query.
     */
    function setSearchQuery(query) {
        setState({
            searchQuery: query,
            page: 1,
        });
    }

    /**
     * Set sort column and direction.
     * @param {string} column - Column key.
     * @param {string} direction - 'asc' or 'desc'.
     */
    function setSort(column, direction) {
        setState({
            sortColumn: column,
            sortDirection: direction,
        });
    }

    /**
     * Set page number.
     * @param {number} page - Page number.
     */
    function setPage(page) {
        setState({ page });
    }

    /**
     * Set loading states.
     * @param {Object} loading - Loading state updates.
     */
    function setLoading(loading) {
        setState(loading);
    }

    /**
     * Show status message.
     * @param {string} type - 'saving', 'success', or 'error'.
     * @param {string} message - Status message.
     * @param {number} duration - Auto-hide duration in ms (0 = no auto-hide).
     */
    function showStatus(type, message, duration = 3000) {
        setState({ status: { type, message } });

        if (duration > 0) {
            setTimeout(() => {
                setState({ status: null });
            }, duration);
        }
    }

    /**
     * Clear status message.
     */
    function clearStatus() {
        setState({ status: null });
    }

    /**
     * Get a product by ID.
     * @param {number} productId - Product ID.
     * @returns {Object|null} Product or null.
     */
    function getProductById(productId) {
        return state.products.find(p => p.id === productId) || null;
    }

    /**
     * Check if a column is visible.
     * @param {string} columnKey - Column key.
     * @returns {boolean} True if visible.
     */
    function isColumnVisible(columnKey) {
        return state.columnVisibility[columnKey] !== false;
    }

    return {
        getState,
        subscribe,
        setProductType,
        setSchema,
        setProducts,
        updateProductSpec,
        setEditingCell,
        setSearchQuery,
        setSort,
        setPage,
        setLoading,
        showStatus,
        clearStatus,
        getProductById,
        isColumnVisible,
        toggleColumnVisibility,
        setAllColumnsVisible,
        resetColumnVisibility,
    };
}

// Create singleton store.
export const store = createStore();

/**
 * Comparison Component
 * Advanced product comparison selector with:
 * - Smart search with keyboard navigation
 * - Category filtering with visual indicator
 * - Dynamic row management
 * - Full accessibility support
 * - Geo-aware pricing
 *
 * Supports multiple instances with different configurations.
 */

import { getUserGeo, formatPrice } from '../services/geo-price.js';
import { escapeHtml } from '../utils/dom.js';

export async function initComparison(options = {}) {
    // Default configuration
    const config = {
        containerId: 'comparison-container',
        containerSelector: null, // Alternative to containerId - use CSS selector
        inputsContainerId: null, // If null, uses rightColumnId for dynamic inputs
        inputsContainerSelector: null, // Alternative selector for inputs container
        rightColumnId: 'comparison-right-column',
        submitBtnId: 'comparison-submit',
        submitBtnSelector: null, // Alternative to submitBtnId - use CSS selector
        categoryPillId: 'comparison-category-pill',
        categoryTextId: 'comparison-category-text',
        categoryClearId: 'comparison-category-clear',
        announcerId: 'comparison-announcer',
        announcerSelector: null, // Alternative to announcerId - use CSS selector
        categoryFilter: null, // e.g., 'escooter' to filter products (single category)
        allowedCategories: null, // e.g., ['escooter', 'ebike'] to allow multiple categories initially
        wrapperClass: 'comparison-input-wrapper', // Additional classes for new wrappers
        showCategoryInResults: true,
        lockedProduct: null, // Pre-selected product that can't be removed (e.g., current review page product)
        allowDynamicInputs: true, // Set to false for fixed 2-product comparisons (like sidebar)
        ...options
    };

    // DOM Elements - support both ID and selector options
    const container = config.containerSelector
        ? document.querySelector(config.containerSelector)
        : (config.containerId ? document.getElementById(config.containerId) : null);

    const inputsContainer = config.inputsContainerSelector
        ? document.querySelector(config.inputsContainerSelector)
        : (config.inputsContainerId
            ? document.getElementById(config.inputsContainerId)
            : document.getElementById(config.rightColumnId));

    const rightColumn = document.getElementById(config.rightColumnId);

    const submitBtn = config.submitBtnSelector
        ? (container ? container.querySelector(config.submitBtnSelector) : document.querySelector(config.submitBtnSelector))
        : document.getElementById(config.submitBtnId);

    const categoryPill = document.getElementById(config.categoryPillId);
    const categoryText = document.getElementById(config.categoryTextId);
    const categoryClear = document.getElementById(config.categoryClearId);

    const announcer = config.announcerSelector
        ? (container ? container.querySelector(config.announcerSelector) : document.querySelector(config.announcerSelector))
        : document.getElementById(config.announcerId);

    // For stacked layout (hub), inputsContainer is where all inputs live
    // For side-by-side layout (homepage), rightColumn is where dynamic inputs go
    // For simple layout (sidebar), we use the container itself
    const dynamicInputsContainer = config.inputsContainerId
        ? inputsContainer
        : (rightColumn || container);

    if (!container) return null;

    // Get user's geo for pricing
    const { geo: userGeo, currency: userCurrency } = await getUserGeo();

    // State
    let products = [];
    const selectedProducts = new Map();
    let nextSlot = 2;
    let activeCategory = config.categoryFilter; // Pre-set if filtered (locks to single category)

    // Read allowedCategories from data attribute if not in config
    let allowedCategories = config.allowedCategories;
    if (!allowedCategories && container?.dataset.allowedCategories) {
        allowedCategories = container.dataset.allowedCategories.split(',').map(c => c.trim()).filter(Boolean);
    }
    let highlightedIndex = -1;
    let currentResults = [];
    let activeDropdown = null;
    let activeInput = null;

    // Check for locked product from data attributes on container
    let lockedProduct = config.lockedProduct;
    if (!lockedProduct && container?.dataset.lockedId) {
        lockedProduct = {
            id: container.dataset.lockedId,
            name: container.dataset.lockedName || '',
            image: container.dataset.lockedImage || '',
            category: container.dataset.lockedCategory || config.categoryFilter
        };
    }

    // Pre-populate slot 0 with locked product
    if (lockedProduct) {
        selectedProducts.set(0, {
            id: lockedProduct.id,
            name: lockedProduct.name,
            image: lockedProduct.image,
            category: lockedProduct.category,
            categoryLabel: lockedProduct.categoryLabel || lockedProduct.category
        });

        // Set active category from locked product (filters search to same category)
        if (lockedProduct.category && !config.categoryFilter) {
            activeCategory = lockedProduct.category;
        }
    }

    // Category labels for display
    const categoryLabels = {
        'escooter': 'E-Scooters',
        'ebike': 'E-Bikes',
        'eskateboard': 'E-Skateboards',
        'euc': 'Electric Unicycles',
        'hoverboard': 'Hoverboards'
    };

    // Load products from JSON URL (from data attribute or default path)
    try {
        const jsonUrl = container.dataset.jsonUrl || '/wp-content/uploads/comparison_products.json';
        const response = await fetch(jsonUrl);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const data = await response.json();

        // Handle both formats: direct array (cron) or {products: []} (static)
        const rawProducts = Array.isArray(data) ? data : (data.products || []);

        // Map to expected format with category labels and geo-aware pricing
        // NO fallback to US - all prices must be in user's currency for fair comparison
        // (Users from unmapped countries already default to US at geo-detection level)
        products = rawProducts.map(p => {
            const prices = p.prices || {};
            const geoPrice = prices[userGeo] ?? null;

            return {
                id: String(p.id),
                name: p.name,
                category: p.category,
                categoryLabel: categoryLabels[p.category] || p.categoryLabel || p.category,
                image: p.thumbnail || p.image,
                price: geoPrice,
                currency: userCurrency,
                url: p.url,
                popularity: p.popularity || 0
            };
        });

        // Sort by popularity (highest first)
        products.sort((a, b) => b.popularity - a.popularity);

        // Pre-filter if category specified
        if (config.categoryFilter) {
            products = products.filter(p => p.category === config.categoryFilter);
        }
    } catch (error) {
        console.error('Failed to load products:', error);
        showError('Unable to load products. Please refresh the page.');
        return null;
    }

    // Initialize existing inputs
    container.querySelectorAll('.comparison-input-wrapper').forEach(initInputWrapper);

    // Initial UI state (important for locked product scenarios)
    updateUI();

    // Category clear button
    if (categoryClear) {
        categoryClear.addEventListener('click', clearAllSelections);
    }

    /**
     * Initialize an input wrapper with all event handlers
     */
    function initInputWrapper(wrapper) {
        const input = wrapper.querySelector('.comparison-input');
        const clearBtn = wrapper.querySelector('.comparison-input-clear');
        const dropdown = wrapper.querySelector('.comparison-results');
        const thumb = wrapper.querySelector('.comparison-input-thumb');
        const slot = parseInt(input?.dataset.slot, 10);

        if (!input || isNaN(slot)) return;

        // Track highest slot for nextSlot
        if (slot >= nextSlot) {
            nextSlot = slot + 1;
        }

        // Set up ARIA attributes
        const listboxId = `${config.containerId}-listbox-${slot}`;
        dropdown.id = listboxId;
        dropdown.setAttribute('role', 'listbox');
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', listboxId);
        input.setAttribute('aria-haspopup', 'listbox');

        let debounceTimer = null;
        let previousValue = '';

        // Store reference to wrapper data
        wrapper._comparisonData = { input, clearBtn, dropdown, thumb, slot };

        // Focus handler - show all products immediately
        input.addEventListener('focus', () => {
            previousValue = input.value;
            activeInput = input;
            activeDropdown = dropdown;

            // If has value, select all text for easy replacement
            if (input.value && selectedProducts.has(slot)) {
                input.select();
            }

            showResults(getFilteredProducts(input.value), dropdown, input, slot, thumb);
        });

        // Input handler with debounce
        input.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const query = input.value.trim().toLowerCase();
                showResults(getFilteredProducts(query), dropdown, input, slot, thumb);
            }, 100);
        });

        // Keyboard navigation
        input.addEventListener('keydown', (e) => {
            handleKeydown(e, dropdown, input, slot, thumb, previousValue);
        });

        // Clear button
        if (clearBtn) {
            clearBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                clearSlot(slot, wrapper);
                input.focus();
            });
        }

        // Image error handler
        if (thumb) {
            thumb.addEventListener('error', () => {
                thumb.classList.add('error');
                thumb.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="28" height="28"%3E%3Crect fill="%23f0f0f0" width="28" height="28"/%3E%3C/svg%3E';
            });
        }

        // Click outside to close
        document.addEventListener('click', (e) => {
            if (!wrapper.contains(e.target)) {
                hideResults(dropdown, input);
            }
        });

        // Blur handler for row cleanup
        input.addEventListener('blur', () => {
            // Delay to allow click events to fire first
            setTimeout(() => {
                if (document.activeElement !== input) {
                    cleanupEmptyRows();
                }
            }, 200);
        });
    }

    /**
     * Handle keyboard navigation
     */
    function handleKeydown(e, dropdown, input, slot, thumb, previousValue) {
        const isOpen = dropdown.classList.contains('active');

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                if (!isOpen) {
                    showResults(getFilteredProducts(input.value), dropdown, input, slot, thumb);
                } else {
                    highlightedIndex = Math.min(highlightedIndex + 1, currentResults.length - 1);
                    updateHighlight(dropdown);
                }
                break;

            case 'ArrowUp':
                e.preventDefault();
                if (isOpen) {
                    highlightedIndex = Math.max(highlightedIndex - 1, 0);
                    updateHighlight(dropdown);
                }
                break;

            case 'Enter':
                e.preventDefault();
                if (isOpen && highlightedIndex >= 0 && currentResults[highlightedIndex]) {
                    const product = currentResults[highlightedIndex];
                    if (!isProductSelected(product.id)) {
                        selectProduct(product, input, slot, thumb);
                        hideResults(dropdown, input);
                    }
                }
                break;

            case 'Escape':
                e.preventDefault();
                if (isOpen) {
                    // Restore previous value if we had a selection
                    if (selectedProducts.has(slot)) {
                        input.value = selectedProducts.get(slot).name;
                    } else {
                        input.value = '';
                    }
                    hideResults(dropdown, input);
                }
                break;

            case 'Tab':
                hideResults(dropdown, input);
                break;
        }
    }

    /**
     * Update highlight in dropdown
     */
    function updateHighlight(dropdown) {
        const items = dropdown.querySelectorAll('.comparison-result:not(.already-selected)');
        items.forEach((item, idx) => {
            item.classList.toggle('highlighted', idx === highlightedIndex);
            if (idx === highlightedIndex) {
                item.scrollIntoView({ block: 'nearest' });
                activeInput?.setAttribute('aria-activedescendant', item.id);
            }
        });
    }

    /**
     * Get filtered products based on query and category
     */
    function getFilteredProducts(query = '') {
        query = query.toLowerCase().trim();

        return products.filter(product => {
            // If activeCategory is set (after first selection), filter strictly to that category
            if (activeCategory && product.category !== activeCategory) {
                return false;
            }

            // If no activeCategory but we have allowedCategories, filter to those
            if (!activeCategory && allowedCategories && allowedCategories.length > 0) {
                if (!allowedCategories.includes(product.category)) {
                    return false;
                }
            }

            // Filter by query if provided
            if (query.length > 0) {
                const nameMatch = product.name.toLowerCase().includes(query);
                const categoryMatch = product.categoryLabel.toLowerCase().includes(query);
                if (!nameMatch && !categoryMatch) return false;
            }

            return true;
        });
    }

    /**
     * Check if a product is already selected (includes locked product)
     */
    function isProductSelected(productId) {
        // Check locked product
        if (lockedProduct && lockedProduct.id === productId) return true;
        // Check selected products
        for (const data of selectedProducts.values()) {
            if (data.id === productId) return true;
        }
        return false;
    }

    /**
     * Highlight matching text in product name
     */
    function highlightMatch(text, query) {
        if (!query || query.length < 1) return escapeHtml(text);

        const regex = new RegExp(`(${escapeRegex(query)})`, 'gi');
        return escapeHtml(text).replace(regex, '<mark>$1</mark>');
    }

    // escapeHtml imported from utils/dom.js

    /**
     * Escape regex special characters
     */
    function escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    /**
     * Show search results dropdown
     */
    function showResults(results, dropdown, input, slot, thumb) {
        if (!dropdown) return;

        currentResults = results;
        highlightedIndex = -1;
        const query = input.value.trim().toLowerCase();

        if (results.length === 0) {
            const categoryHint = activeCategory && !config.categoryFilter
                ? `Try searching within ${getCategoryLabel(activeCategory)} or clear the filter.`
                : 'Try a different search term.';

            dropdown.innerHTML = `
                <div class="comparison-no-results">
                    <p class="comparison-no-results-text">No products found</p>
                    <p class="comparison-no-results-hint">${categoryHint}</p>
                </div>
            `;
        } else {
            // Find first non-selected item for initial highlight
            const firstAvailableIdx = results.findIndex(p => !isProductSelected(p.id));
            highlightedIndex = firstAvailableIdx >= 0 ? firstAvailableIdx : -1;

            const header = activeCategory && !config.categoryFilter
                ? `<div class="comparison-results-header">${getCategoryLabel(activeCategory)}</div>`
                : '';

            dropdown.innerHTML = header + results.slice(0, 10).map((product, idx) => {
                const isSelected = isProductSelected(product.id);
                const isHighlighted = idx === highlightedIndex;

                return `
                    <div class="comparison-result ${isSelected ? 'already-selected' : ''} ${isHighlighted ? 'highlighted' : ''}"
                         data-product-id="${product.id}"
                         data-index="${idx}"
                         id="${config.containerId}-option-${slot}-${idx}"
                         role="option"
                         aria-selected="${isHighlighted}"
                         ${isSelected ? 'aria-disabled="true"' : ''}>
                        <img class="comparison-result-thumb"
                             src="${product.image}"
                             alt=""
                             onerror="this.classList.add('error'); this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect fill=%22%23f0f0f0%22 width=%2240%22 height=%2240%22/%3E%3C/svg%3E'">
                        <div class="comparison-result-info">
                            <span class="comparison-result-name">${highlightMatch(product.name, query)}</span>
                            <div class="comparison-result-meta">
                                ${isSelected
                                    ? '<span class="comparison-result-selected">Already selected</span>'
                                    : `${config.showCategoryInResults ? `<span class="comparison-result-category">${product.categoryLabel}</span>` : ''}
                                       ${product.price > 0 ? `<span class="comparison-result-price">${formatPrice(product.price, product.currency)}</span>` : ''}`
                                }
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            // Add click handlers
            dropdown.querySelectorAll('.comparison-result:not(.already-selected)').forEach(result => {
                result.addEventListener('click', () => {
                    const productId = result.dataset.productId;
                    const product = products.find(p => p.id === productId);
                    if (product) {
                        selectProduct(product, input, slot, thumb);
                        hideResults(dropdown, input);
                    }
                });

                // Hover to highlight
                result.addEventListener('mouseenter', () => {
                    highlightedIndex = parseInt(result.dataset.index, 10);
                    updateHighlight(dropdown);
                });
            });
        }

        dropdown.classList.add('active');
        input.setAttribute('aria-expanded', 'true');
    }

    /**
     * Hide search results dropdown
     */
    function hideResults(dropdown, input) {
        if (dropdown) {
            dropdown.classList.remove('active');
            input?.setAttribute('aria-expanded', 'false');
            input?.removeAttribute('aria-activedescendant');
        }
        highlightedIndex = -1;
        currentResults = [];
        activeDropdown = null;
    }

    /**
     * Select a product
     */
    function selectProduct(product, input, slot, thumb) {
        // Store selection data
        selectedProducts.set(slot, {
            id: product.id,
            name: product.name,
            image: product.image,
            category: product.category,
            categoryLabel: product.categoryLabel
        });

        // Update input
        input.value = product.name;
        input.classList.add('has-value');

        // Selection pulse animation
        input.classList.add('just-selected');
        setTimeout(() => input.classList.remove('just-selected'), 400);

        // Update thumbnail
        if (thumb) {
            thumb.src = product.image;
            thumb.alt = product.name;
            thumb.classList.remove('error');
        }

        // Set category if this is first selection (and not pre-filtered)
        if (!activeCategory && !config.categoryFilter) {
            setActiveCategory(product.category, product.categoryLabel);
        }

        // Announce for screen readers
        announce(`Selected ${product.name}`);

        updateUI();

        // Add new input if this was the last one in dynamic container (skip for fixed comparisons)
        if (config.allowDynamicInputs) {
            const allInputs = dynamicInputsContainer.querySelectorAll('.comparison-input');
            const lastInput = allInputs[allInputs.length - 1];
            if (lastInput && parseInt(lastInput.dataset.slot, 10) === slot) {
                addNewInput();
            }
        }
    }

    /**
     * Clear a slot
     */
    function clearSlot(slot, wrapper) {
        const data = selectedProducts.get(slot);
        const productName = data?.name || 'selection';

        selectedProducts.delete(slot);

        const input = wrapper.querySelector('.comparison-input');
        const thumb = wrapper.querySelector('.comparison-input-thumb');

        if (input) {
            input.value = '';
            input.classList.remove('has-value');
        }

        if (thumb) {
            thumb.src = '';
            thumb.alt = '';
            thumb.classList.remove('error');
        }

        // Check if we should reset category (only if not pre-filtered)
        if (selectedProducts.size === 0 && !config.categoryFilter) {
            clearActiveCategory();
        }

        announce(`Removed ${productName}`);
        updateUI();

        // Schedule row cleanup
        setTimeout(cleanupEmptyRows, 100);
    }

    /**
     * Clear all selections
     */
    function clearAllSelections() {
        // Clear all selected products
        selectedProducts.forEach((data, slot) => {
            const wrapper = container.querySelector(`[data-slot="${slot}"]`)?.closest('.comparison-input-wrapper');
            if (wrapper) {
                const input = wrapper.querySelector('.comparison-input');
                const thumb = wrapper.querySelector('.comparison-input-thumb');

                if (input) {
                    input.value = '';
                    input.classList.remove('has-value');
                }
                if (thumb) {
                    thumb.src = '';
                    thumb.alt = '';
                }
            }
        });

        selectedProducts.clear();
        if (!config.categoryFilter) {
            clearActiveCategory();
        }
        announce('All selections cleared');
        updateUI();

        // Clean up extra rows
        setTimeout(() => {
            cleanupEmptyRows(true);
        }, 100);
    }

    /**
     * Set active category filter
     */
    function setActiveCategory(category, label) {
        activeCategory = category;
        if (categoryText) {
            categoryText.textContent = `Showing ${label}`;
        }
        if (categoryPill) {
            categoryPill.classList.add('visible');
        }
    }

    /**
     * Clear active category filter
     */
    function clearActiveCategory() {
        activeCategory = null;
        if (categoryPill) {
            categoryPill.classList.remove('visible');
        }
    }

    /**
     * Get category label from category key
     */
    function getCategoryLabel(category) {
        const product = products.find(p => p.category === category);
        return product?.categoryLabel || category;
    }

    /**
     * Cleanup empty rows in dynamic inputs container
     */
    function cleanupEmptyRows(keepMinimum = false) {
        // Skip for fixed comparisons (sidebar)
        if (!config.allowDynamicInputs) return;

        const wrappers = dynamicInputsContainer.querySelectorAll('.comparison-input-wrapper');
        const emptyWrappers = [];

        // Find empty wrappers (only dynamic slots >= 2)
        wrappers.forEach((wrapper) => {
            const input = wrapper.querySelector('.comparison-input');
            const slot = parseInt(input?.dataset.slot, 10);

            if (slot >= 2 && !selectedProducts.has(slot)) {
                emptyWrappers.push(wrapper);
            }
        });

        // If slot 1 is empty, remove ALL dynamic rows
        // Otherwise keep one empty wrapper for adding more (unless clearing all)
        const slot1HasValue = selectedProducts.has(1);
        const toRemove = keepMinimum || !slot1HasValue ? emptyWrappers : emptyWrappers.slice(0, -1);

        toRemove.forEach(wrapper => {
            wrapper.classList.add('removing');
            setTimeout(() => wrapper.remove(), 150);
        });

        // Renumber slots after removal (only for side-by-side layout with rightColumn)
        if (toRemove.length > 0 && rightColumn && !config.inputsContainerId) {
            setTimeout(renumberSlots, 200);
        }
    }

    /**
     * Renumber slots after removing rows (for side-by-side layout)
     */
    function renumberSlots() {
        if (!rightColumn) return;

        const rightInputs = rightColumn.querySelectorAll('.comparison-input');
        const newSelectedProducts = new Map();

        // Keep slot 0 (left column)
        if (selectedProducts.has(0)) {
            newSelectedProducts.set(0, selectedProducts.get(0));
        }

        // Renumber right column starting from 1
        rightInputs.forEach((input, index) => {
            const oldSlot = parseInt(input.dataset.slot, 10);
            const newSlot = index + 1;
            input.dataset.slot = newSlot;

            // Update listbox ID
            const wrapper = input.closest('.comparison-input-wrapper');
            const dropdown = wrapper?.querySelector('.comparison-results');
            if (dropdown) {
                dropdown.id = `${config.containerId}-listbox-${newSlot}`;
                input.setAttribute('aria-controls', dropdown.id);
            }

            if (selectedProducts.has(oldSlot)) {
                newSelectedProducts.set(newSlot, selectedProducts.get(oldSlot));
            }
        });

        selectedProducts.clear();
        newSelectedProducts.forEach((value, key) => selectedProducts.set(key, value));

        nextSlot = rightInputs.length + 1;
    }

    /**
     * Add a new input to the dynamic inputs container
     */
    function addNewInput() {
        const wrapper = document.createElement('div');
        wrapper.className = config.wrapperClass;
        wrapper.innerHTML = `
            <input type="text"
                   class="comparison-input"
                   placeholder="Add another..."
                   autocomplete="off"
                   data-slot="${nextSlot}">
            <button type="button" class="comparison-input-clear" aria-label="Clear selection">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            <img class="comparison-input-thumb" src="" alt="" aria-hidden="true">
            <div class="comparison-results"></div>
        `;

        dynamicInputsContainer.appendChild(wrapper);
        initInputWrapper(wrapper);
        nextSlot++;
    }

    /**
     * Update UI state
     */
    function updateUI() {
        // Update button state
        if (submitBtn) {
            submitBtn.disabled = selectedProducts.size < 2;
        }
    }

    /**
     * Announce message for screen readers
     */
    function announce(message) {
        if (announcer) {
            announcer.textContent = message;
            // Clear after a moment to allow repeated announcements
            setTimeout(() => {
                announcer.textContent = '';
            }, 1000);
        }
    }

    /**
     * Show error message
     */
    function showError(message) {
        console.error(message);
        // Could show a visual error state here
    }

    // Submit handler
    if (submitBtn) {
        submitBtn.addEventListener('click', () => {
            if (selectedProducts.size >= 2) {
                const ids = Array.from(selectedProducts.values()).map(d => d.id);
                const base = window.erhData?.siteUrl || '';
                const url = `${base}/compare/?products=${ids.join(',')}`;
                window.location.href = url;
            }
        });
    }

    return {
        getSelectedProducts: () => Array.from(selectedProducts.values()),
        clearAll: clearAllSelections
    };
}

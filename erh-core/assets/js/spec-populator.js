/**
 * Spec Populator - Bulk Wizard JavaScript
 *
 * 4-step wizard for AI-powered bulk spec population.
 *
 * @package ERH\Admin
 */

(function() {
    'use strict';

    const config = window.erhSpecPopulator || {};
    const { ajaxUrl, nonce, isConfigured, productTypes } = config;

    // State
    let currentType = '';
    let products = [];
    let results = {};
    let isFetching = false;

    // DOM Elements
    const typeTabs = document.querySelectorAll('.erh-sp-type-tab');
    const searchInput = document.getElementById('erh-sp-search');
    const brandFilter = document.getElementById('erh-sp-brand');
    const statusFilter = document.getElementById('erh-sp-status');
    const productsContainer = document.getElementById('erh-sp-products');
    const checkAllBtn = document.getElementById('erh-sp-check-all');
    const uncheckAllBtn = document.getElementById('erh-sp-uncheck-all');
    const overwriteToggle = document.getElementById('erh-sp-overwrite');
    const fetchBtn = document.getElementById('erh-sp-fetch');
    const saveBtn = document.getElementById('erh-sp-save');
    const resultsContainer = document.getElementById('erh-sp-results');

    /**
     * Initialize the module.
     */
    function init() {
        if (!typeTabs.length) return;
        if (!isConfigured) return;

        bindEvents();
    }

    /**
     * Bind event listeners.
     */
    function bindEvents() {
        typeTabs.forEach(tab => {
            tab.addEventListener('click', () => selectType(tab.dataset.type));
        });

        if (searchInput) {
            searchInput.addEventListener('input', debounce(filterProducts, 300));
        }
        if (brandFilter) {
            brandFilter.addEventListener('change', filterProducts);
        }
        if (statusFilter) {
            statusFilter.addEventListener('change', filterProducts);
        }
        if (checkAllBtn) {
            checkAllBtn.addEventListener('click', checkAllVisible);
        }
        if (uncheckAllBtn) {
            uncheckAllBtn.addEventListener('click', uncheckAll);
        }
        if (fetchBtn) {
            fetchBtn.addEventListener('click', fetchSpecs);
        }
        if (saveBtn) {
            saveBtn.addEventListener('click', saveSpecs);
        }
    }

    /**
     * Select a product type tab.
     */
    function selectType(type) {
        currentType = type;
        results = {};

        // Update tab active state.
        typeTabs.forEach(tab => {
            tab.classList.toggle('is-active', tab.dataset.type === type);
        });

        // Show step 2.
        showStep(2);
        hideStep(3);
        hideStep(4);

        // Load products.
        loadProducts(type);
    }

    /**
     * Load products for the selected type.
     */
    function loadProducts(type) {
        productsContainer.innerHTML = '<p class="erh-sp-loading">Loading products...</p>';

        const formData = new FormData();
        formData.append('action', 'erh_sp_get_products');
        formData.append('nonce', nonce);
        formData.append('product_type', type);

        fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(response => {
                if (!response.success) {
                    productsContainer.innerHTML =
                        '<p class="erh-sp-error">' + escHtml(response.data?.message || 'Error loading products.') + '</p>';
                    return;
                }

                products = response.data.products;
                populateBrandFilter(response.data.brands);
                renderProducts();
                showStep(3);
            })
            .catch(() => {
                productsContainer.innerHTML = '<p class="erh-sp-error">Network error loading products.</p>';
            });
    }

    /**
     * Populate the brand dropdown.
     */
    function populateBrandFilter(brands) {
        if (!brandFilter) return;
        brandFilter.innerHTML = '<option value="">All Brands</option>';
        brands.forEach(brand => {
            const opt = document.createElement('option');
            opt.value = brand;
            opt.textContent = brand;
            brandFilter.appendChild(opt);
        });
    }

    /**
     * Render the product list.
     */
    function renderProducts() {
        const search = (searchInput?.value || '').toLowerCase();
        const brand = brandFilter?.value || '';
        const status = statusFilter?.value || 'needs-specs';

        const filtered = products.filter(p => {
            if (search && !p.name.toLowerCase().includes(search) && !p.brand.toLowerCase().includes(search)) {
                return false;
            }
            if (brand && p.brand !== brand) {
                return false;
            }
            if (status === 'needs-specs' && p.empty_fields === 0) {
                return false;
            }
            if (status === 'has-specs' && p.empty_fields === p.total_fields) {
                return false;
            }
            return true;
        });

        if (!filtered.length) {
            productsContainer.innerHTML = '<p class="erh-sp-empty">No products match the current filters.</p>';
            updateSelectedCount();
            return;
        }

        productsContainer.innerHTML = filtered.map(p => {
            const filledCount = p.total_fields - p.empty_fields;
            const statusText = p.empty_fields + '/' + p.total_fields + ' fields empty';
            const hasSpecs = p.empty_fields < p.total_fields;
            const statusClass = p.empty_fields === 0 ? 'complete' : (hasSpecs ? 'partial' : 'empty');

            return '<label class="erh-sp-product" data-id="' + p.id + '">'
                + '<input type="checkbox" value="' + p.id + '">'
                + '<span class="erh-sp-product-name">' + escHtml(p.name) + '</span>'
                + '<span class="erh-sp-product-brand">' + escHtml(p.brand) + '</span>'
                + '<span class="erh-sp-product-status ' + statusClass + '">' + statusText + '</span>'
                + '</label>';
        }).join('');

        // Bind checkbox changes.
        productsContainer.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });

        updateSelectedCount();
    }

    /**
     * Filter products (called by search/brand/status changes).
     */
    function filterProducts() {
        renderProducts();
    }

    /**
     * Check all visible checkboxes.
     */
    function checkAllVisible() {
        productsContainer.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            cb.checked = true;
        });
        updateSelectedCount();
    }

    /**
     * Uncheck all checkboxes.
     */
    function uncheckAll() {
        productsContainer.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            cb.checked = false;
        });
        updateSelectedCount();
    }

    /**
     * Update the selected count display.
     */
    function updateSelectedCount() {
        const count = productsContainer.querySelectorAll('input[type="checkbox"]:checked').length;
        const countEl = document.querySelector('.erh-sp-selected-count strong');
        if (countEl) {
            countEl.textContent = count;
        }
    }

    /**
     * Get selected product IDs.
     */
    function getSelectedProducts() {
        const ids = [];
        productsContainer.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => {
            ids.push(parseInt(cb.value, 10));
        });
        return ids;
    }

    /**
     * Fetch specs for all selected products.
     */
    async function fetchSpecs() {
        const selectedIds = getSelectedProducts();
        if (!selectedIds.length) {
            alert('No products selected.');
            return;
        }

        if (isFetching) return;
        isFetching = true;

        const overwrite = overwriteToggle?.checked || false;
        results = {};

        // Show progress.
        const progressWrap = document.querySelector('.erh-sp-progress');
        const progressFill = document.querySelector('.erh-sp-progress-fill');
        const progressText = document.querySelector('.erh-sp-progress-text');
        if (progressWrap) progressWrap.style.display = 'block';

        fetchBtn.disabled = true;

        for (let i = 0; i < selectedIds.length; i++) {
            const productId = selectedIds[i];
            const product = products.find(p => p.id === productId);
            const productName = product ? product.name : 'Product #' + productId;

            // Update progress.
            const pct = Math.round(((i + 1) / selectedIds.length) * 100);
            if (progressFill) progressFill.style.width = pct + '%';
            if (progressText) progressText.textContent = 'Processing ' + (i + 1) + ' of ' + selectedIds.length + ': ' + productName;

            try {
                const formData = new FormData();
                formData.append('action', 'erh_sp_fetch_specs');
                formData.append('nonce', nonce);
                formData.append('product_id', productId);
                formData.append('product_type', currentType);
                if (overwrite) {
                    formData.append('overwrite_existing', '1');
                }

                const response = await fetch(ajaxUrl, { method: 'POST', body: formData });
                const data = await response.json();

                if (data.success) {
                    results[productId] = {
                        name: productName,
                        ...data.data,
                    };
                } else {
                    results[productId] = {
                        name: productName,
                        success: false,
                        error: data.data?.message || 'Unknown error',
                    };

                    // Retry once on rate limit.
                    if (data.data?.message && data.data.message.includes('Rate limit')) {
                        if (progressText) progressText.textContent = 'Rate limited, retrying in 5s: ' + productName;
                        await sleep(5000);

                        const retryResponse = await fetch(ajaxUrl, { method: 'POST', body: formData });
                        const retryData = await retryResponse.json();
                        if (retryData.success) {
                            results[productId] = {
                                name: productName,
                                ...retryData.data,
                            };
                        }
                    }
                }
            } catch (err) {
                results[productId] = {
                    name: productName,
                    success: false,
                    error: 'Network error',
                };
            }

            // Delay between requests.
            if (i < selectedIds.length - 1) {
                await sleep(500);
            }
        }

        isFetching = false;
        fetchBtn.disabled = false;
        if (progressText) progressText.textContent = 'Done! Processed ' + selectedIds.length + ' product(s).';

        // Render results.
        renderResults();
        showStep(4);
    }

    /**
     * Render the review results.
     */
    function renderResults() {
        if (!resultsContainer) return;

        const productIds = Object.keys(results);
        if (!productIds.length) {
            resultsContainer.innerHTML = '<p class="erh-sp-empty">No results to display.</p>';
            return;
        }

        let html = '';

        productIds.forEach(productId => {
            const result = results[productId];
            const productName = result.name || 'Product #' + productId;

            // Error state.
            if (!result.success || result.error) {
                html += '<div class="erh-sp-result-product">'
                    + '<div class="erh-sp-result-header is-error">'
                    + '<span class="erh-sp-result-toggle">&#9654;</span> '
                    + '<strong>' + escHtml(productName) + '</strong>'
                    + '<span class="erh-sp-badge erh-sp-badge-error">Error</span>'
                    + '<span class="erh-sp-result-error">' + escHtml(result.error || 'Unknown error') + '</span>'
                    + '</div></div>';
                return;
            }

            // Empty state.
            if (result.empty) {
                html += '<div class="erh-sp-result-product">'
                    + '<div class="erh-sp-result-header is-empty">'
                    + '<span class="erh-sp-result-toggle">&#9654;</span> '
                    + '<strong>' + escHtml(productName) + '</strong>'
                    + '<span class="erh-sp-badge erh-sp-badge-info">All fields populated</span>'
                    + '</div></div>';
                return;
            }

            // Build field rows.
            const suggestions = result.suggestions || {};
            const currentValues = result.current_values || {};
            const schema = result.schema || {};
            const suggestionKeys = Object.keys(suggestions);

            if (!suggestionKeys.length) {
                html += '<div class="erh-sp-result-product">'
                    + '<div class="erh-sp-result-header">'
                    + '<span class="erh-sp-result-toggle">&#9654;</span> '
                    + '<strong>' + escHtml(productName) + '</strong>'
                    + '<span class="erh-sp-badge erh-sp-badge-info">No suggestions</span>'
                    + '</div></div>';
                return;
            }

            let fieldRows = '';
            let validCount = 0;

            suggestionKeys.forEach(fieldPath => {
                const suggestion = suggestions[fieldPath];
                const currentVal = currentValues[fieldPath];
                const fieldSchema = schema[fieldPath] || {};
                const isValid = suggestion.valid;
                const suggestedVal = suggestion.value;

                // Determine status.
                let status, statusClass;
                if (!isValid) {
                    status = 'Invalid';
                    statusClass = 'erh-sp-badge-error';
                } else if (currentVal === null || currentVal === '' || (Array.isArray(currentVal) && !currentVal.length)) {
                    status = 'New';
                    statusClass = 'erh-sp-badge-new';
                } else if (String(currentVal) !== String(suggestedVal)) {
                    status = 'Changed';
                    statusClass = 'erh-sp-badge-changed';
                } else {
                    status = 'Same';
                    statusClass = 'erh-sp-badge-same';
                }

                if (isValid) validCount++;

                fieldRows += '<tr class="erh-sp-field-row" data-product="' + productId + '" data-field="' + escAttr(fieldPath) + '">'
                    + '<td class="erh-sp-check-col">'
                    + (isValid ? '<input type="checkbox" checked data-value=\'' + escAttr(JSON.stringify(suggestedVal)) + '\'>' : '')
                    + '</td>'
                    + '<td class="erh-sp-field-col">' + escHtml(fieldSchema.label || fieldPath) + '</td>'
                    + '<td class="erh-sp-group-col">' + escHtml(fieldSchema.group || '') + '</td>'
                    + '<td class="erh-sp-current-col">' + formatValue(currentVal, fieldSchema) + '</td>'
                    + '<td class="erh-sp-suggested-col">' + formatValue(suggestedVal, fieldSchema)
                    + (suggestion.message ? '<span class="erh-sp-validation-msg">' + escHtml(suggestion.message) + '</span>' : '')
                    + '</td>'
                    + '<td class="erh-sp-status-col"><span class="erh-sp-badge ' + statusClass + '">' + status + '</span></td>'
                    + '</tr>';
            });

            html += '<div class="erh-sp-result-product" data-product="' + productId + '">'
                + '<div class="erh-sp-result-header is-collapsible" onclick="this.parentElement.classList.toggle(\'is-open\')">'
                + '<span class="erh-sp-result-toggle">&#9654;</span> '
                + '<strong>' + escHtml(productName) + '</strong>'
                + '<span class="erh-sp-badge erh-sp-badge-count">' + validCount + '/' + suggestionKeys.length + ' valid</span>'
                + '</div>'
                + '<div class="erh-sp-result-body">'
                + '<table class="wp-list-table widefat fixed striped erh-sp-fields-table">'
                + '<thead><tr>'
                + '<th class="erh-sp-check-col"><input type="checkbox" class="erh-sp-check-all-fields" data-product="' + productId + '" checked></th>'
                + '<th>Field</th>'
                + '<th>Group</th>'
                + '<th>Current</th>'
                + '<th>Suggested</th>'
                + '<th>Status</th>'
                + '</tr></thead>'
                + '<tbody>' + fieldRows + '</tbody>'
                + '</table>'
                + '</div>'
                + '</div>';
        });

        resultsContainer.innerHTML = html;

        // Auto-expand first product.
        const first = resultsContainer.querySelector('.erh-sp-result-product');
        if (first) first.classList.add('is-open');

        // Bind check-all per product.
        resultsContainer.querySelectorAll('.erh-sp-check-all-fields').forEach(cb => {
            cb.addEventListener('change', function() {
                const pid = this.dataset.product;
                const rows = resultsContainer.querySelectorAll('tr[data-product="' + pid + '"] input[type="checkbox"]');
                rows.forEach(r => { r.checked = cb.checked; });
            });
        });

        // Enable save button.
        if (saveBtn) saveBtn.disabled = false;
    }

    /**
     * Save selected specs.
     */
    async function saveSpecs() {
        if (!resultsContainer) return;

        saveBtn.disabled = true;
        const statusEl = document.querySelector('.erh-sp-save-status');

        // Collect checked specs per product.
        const productSpecs = {};
        resultsContainer.querySelectorAll('tr.erh-sp-field-row').forEach(row => {
            const cb = row.querySelector('input[type="checkbox"]');
            if (!cb || !cb.checked) return;

            const productId = row.dataset.product;
            const fieldPath = row.dataset.field;
            const value = JSON.parse(cb.dataset.value);

            if (!productSpecs[productId]) {
                productSpecs[productId] = {};
            }
            productSpecs[productId][fieldPath] = value;
        });

        const productIds = Object.keys(productSpecs);
        if (!productIds.length) {
            if (statusEl) statusEl.textContent = 'No specs selected to save.';
            saveBtn.disabled = false;
            return;
        }

        let totalSaved = 0;
        let totalErrors = 0;

        for (let i = 0; i < productIds.length; i++) {
            const productId = productIds[i];
            const specs = productSpecs[productId];

            if (statusEl) {
                statusEl.textContent = 'Saving product ' + (i + 1) + ' of ' + productIds.length + '...';
            }

            try {
                const formData = new FormData();
                formData.append('action', 'erh_sp_save_specs');
                formData.append('nonce', nonce);
                formData.append('product_id', productId);
                formData.append('product_type', currentType);
                formData.append('specs', JSON.stringify(specs));

                const response = await fetch(ajaxUrl, { method: 'POST', body: formData });
                const data = await response.json();

                if (data.success) {
                    totalSaved += data.data.saved || 0;
                    totalErrors += (data.data.errors || []).length;
                } else {
                    totalErrors++;
                }
            } catch (err) {
                totalErrors++;
            }
        }

        if (statusEl) {
            statusEl.textContent = 'Saved ' + totalSaved + ' field(s) across ' + productIds.length + ' product(s).'
                + (totalErrors > 0 ? ' ' + totalErrors + ' error(s).' : '');
        }

        saveBtn.disabled = false;
    }

    /**
     * Format a value for display.
     */
    function formatValue(value, schema) {
        if (value === null || value === undefined || value === '') {
            return '<span class="erh-sp-empty-val">-</span>';
        }

        if (Array.isArray(value)) {
            return escHtml(value.join(', '));
        }

        if (typeof value === 'boolean') {
            return value ? 'Yes' : 'No';
        }

        let display = escHtml(String(value));
        if (schema && schema.append) {
            display += ' <span class="erh-sp-unit">' + escHtml(schema.append) + '</span>';
        }

        return display;
    }

    /**
     * Show a step.
     */
    function showStep(n) {
        const step = document.querySelector('.erh-sp-step[data-step="' + n + '"]');
        if (step) step.style.display = 'block';
    }

    /**
     * Hide a step.
     */
    function hideStep(n) {
        const step = document.querySelector('.erh-sp-step[data-step="' + n + '"]');
        if (step) step.style.display = 'none';
    }

    /**
     * HTML-escape a string.
     */
    function escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Attribute-escape a string.
     */
    function escAttr(str) {
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    /**
     * Sleep for a given number of milliseconds.
     */
    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Debounce a function.
     */
    function debounce(fn, delay) {
        let timer;
        return function(...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    // Initialize on DOM ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

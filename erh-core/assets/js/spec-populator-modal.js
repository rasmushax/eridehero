/**
 * Spec Populator Modal - Single-product spec population on edit screens.
 *
 * Injects a floating button and modal overlay on product edit pages.
 *
 * @package ERH\Admin
 */

(function() {
    'use strict';

    const config = window.erhSpecPopulatorModal || {};
    const { ajaxUrl, nonce, productId, productName, productType, isConfigured } = config;

    // Don't initialize if not configured or no product type.
    if (!isConfigured || !productId || !productType) return;

    let modalEl = null;
    let isFetching = false;

    /**
     * Initialize: inject the floating button.
     */
    function init() {
        // Create floating button.
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'erh-spm-trigger';
        btn.innerHTML = '<span class="erh-spm-trigger-icon">&#9881;</span> AI Specs';
        btn.title = 'Populate specs with AI';
        btn.addEventListener('click', openModal);
        document.body.appendChild(btn);
    }

    /**
     * Open the modal overlay.
     */
    function openModal() {
        if (modalEl) {
            modalEl.style.display = 'flex';
            return;
        }

        // Create modal.
        modalEl = document.createElement('div');
        modalEl.className = 'erh-spm-overlay';
        modalEl.innerHTML = buildModalHtml();
        document.body.appendChild(modalEl);

        // Bind events.
        modalEl.querySelector('.erh-spm-close').addEventListener('click', closeModal);
        modalEl.querySelector('.erh-spm-fetch').addEventListener('click', fetchSpecs);
        modalEl.querySelector('.erh-spm-save').addEventListener('click', saveSpecs);
        modalEl.addEventListener('click', function(e) {
            if (e.target === modalEl) closeModal();
        });
    }

    /**
     * Close the modal.
     */
    function closeModal() {
        if (modalEl) {
            modalEl.style.display = 'none';
        }
    }

    /**
     * Build the modal HTML.
     */
    function buildModalHtml() {
        return '<div class="erh-spm-modal">'
            + '<div class="erh-spm-header">'
            + '<h2>AI Spec Populator</h2>'
            + '<button type="button" class="erh-spm-close">&times;</button>'
            + '</div>'
            + '<div class="erh-spm-body">'
            + '<p class="erh-spm-product-name">' + escHtml(productName) + '</p>'
            + '<div class="erh-spm-options">'
            + '<label>'
            + '<input type="checkbox" class="erh-spm-overwrite">'
            + ' Include fields with existing values'
            + '</label>'
            + '</div>'
            + '<button type="button" class="button button-primary erh-spm-fetch">Fetch Specs with AI</button>'
            + '<div class="erh-spm-status" style="display: none;"></div>'
            + '<div class="erh-spm-results"></div>'
            + '</div>'
            + '<div class="erh-spm-footer" style="display: none;">'
            + '<button type="button" class="button button-primary erh-spm-save">Save Selected Specs</button>'
            + '<span class="erh-spm-save-status"></span>'
            + '</div>'
            + '</div>';
    }

    /**
     * Fetch specs via AJAX.
     */
    async function fetchSpecs() {
        if (isFetching) return;
        isFetching = true;

        const overwrite = modalEl.querySelector('.erh-spm-overwrite')?.checked || false;
        const statusEl = modalEl.querySelector('.erh-spm-status');
        const resultsEl = modalEl.querySelector('.erh-spm-results');
        const fetchBtnEl = modalEl.querySelector('.erh-spm-fetch');
        const footerEl = modalEl.querySelector('.erh-spm-footer');

        fetchBtnEl.disabled = true;
        statusEl.style.display = 'block';
        statusEl.textContent = 'Fetching specs from AI...';
        statusEl.className = 'erh-spm-status';
        resultsEl.innerHTML = '';
        footerEl.style.display = 'none';

        try {
            const formData = new FormData();
            formData.append('action', 'erh_sp_fetch_specs');
            formData.append('nonce', nonce);
            formData.append('product_id', productId);
            formData.append('product_type', productType);
            if (overwrite) {
                formData.append('overwrite_existing', '1');
            }

            const response = await fetch(ajaxUrl, { method: 'POST', body: formData });
            const data = await response.json();

            if (!data.success) {
                statusEl.textContent = data.data?.message || 'Error fetching specs.';
                statusEl.className = 'erh-spm-status is-error';
                fetchBtnEl.disabled = false;
                isFetching = false;
                return;
            }

            if (data.data.empty) {
                statusEl.textContent = data.data.message || 'All fields already have values.';
                statusEl.className = 'erh-spm-status is-info';
                fetchBtnEl.disabled = false;
                isFetching = false;
                return;
            }

            // Render results table.
            renderModalResults(data.data, resultsEl);
            statusEl.textContent = 'Review the suggestions below.';
            statusEl.className = 'erh-spm-status is-success';
            footerEl.style.display = 'flex';
        } catch (err) {
            statusEl.textContent = 'Network error.';
            statusEl.className = 'erh-spm-status is-error';
        }

        fetchBtnEl.disabled = false;
        isFetching = false;
    }

    /**
     * Render results inside the modal.
     */
    function renderModalResults(data, container) {
        const suggestions = data.suggestions || {};
        const currentValues = data.current_values || {};
        const schema = data.schema || {};
        const keys = Object.keys(suggestions);

        if (!keys.length) {
            container.innerHTML = '<p>No suggestions returned by AI.</p>';
            return;
        }

        let rows = '';
        keys.forEach(fieldPath => {
            const suggestion = suggestions[fieldPath];
            const currentVal = currentValues[fieldPath];
            const fieldSchema = schema[fieldPath] || {};
            const isValid = suggestion.valid;
            const isNoData = suggestion.no_data === true;
            const suggestedVal = suggestion.value;

            let status, statusClass;
            if (isNoData) {
                status = 'No data';
                statusClass = 'erh-sp-badge-nodata';
            } else if (!isValid) {
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

            const isChecked = (isValid && !isNoData) ? ' checked' : '';
            const fieldType = fieldSchema.type || 'text';

            rows += '<tr data-field="' + escAttr(fieldPath) + '" data-type="' + escAttr(fieldType) + '"'
                + (isNoData ? ' data-nodata="1"' : '') + '>'
                + '<td class="erh-sp-check-col">'
                + '<input type="checkbox"' + isChecked + '>'
                + '</td>'
                + '<td>' + escHtml(fieldSchema.label || fieldPath) + '</td>'
                + '<td>' + escHtml(fieldSchema.group || '') + '</td>'
                + '<td>' + formatValue(currentVal, fieldSchema) + '</td>'
                + '<td>' + buildEditInput(isNoData ? '' : suggestedVal, fieldSchema)
                      + (suggestion.message && !isNoData ? '<span class="erh-sp-validation-msg">' + escHtml(suggestion.message) + '</span>' : '')
                + '</td>'
                + '<td><span class="erh-sp-badge ' + statusClass + '">' + status + '</span></td>'
                + '</tr>';
        });

        container.innerHTML = '<table class="wp-list-table widefat fixed striped erh-spm-table">'
            + '<thead><tr>'
            + '<th class="erh-sp-check-col"><input type="checkbox" class="erh-spm-check-all" checked></th>'
            + '<th>Field</th>'
            + '<th>Group</th>'
            + '<th>Current</th>'
            + '<th>Suggested</th>'
            + '<th>Status</th>'
            + '</tr></thead>'
            + '<tbody>' + rows + '</tbody>'
            + '</table>';

        // Bind check-all (skip no-data rows).
        container.querySelector('.erh-spm-check-all').addEventListener('change', function() {
            container.querySelectorAll('tbody tr:not([data-nodata]) input[type="checkbox"]').forEach(cb => {
                cb.checked = this.checked;
            });
        });
    }

    /**
     * Save selected specs.
     */
    async function saveSpecs() {
        const resultsEl = modalEl.querySelector('.erh-spm-results');
        const saveBtnEl = modalEl.querySelector('.erh-spm-save');
        const saveStatusEl = modalEl.querySelector('.erh-spm-save-status');

        // Collect checked specs.
        const specs = {};
        resultsEl.querySelectorAll('tr[data-field]').forEach(row => {
            const cb = row.querySelector('input[type="checkbox"]');
            if (!cb || !cb.checked) return;
            const value = getEditValue(row);
            if (value !== null) {
                specs[row.dataset.field] = value;
            }
        });

        if (!Object.keys(specs).length) {
            saveStatusEl.textContent = 'No specs selected.';
            return;
        }

        saveBtnEl.disabled = true;
        saveStatusEl.textContent = 'Saving...';

        try {
            const formData = new FormData();
            formData.append('action', 'erh_sp_save_specs');
            formData.append('nonce', nonce);
            formData.append('product_id', productId);
            formData.append('product_type', productType);
            formData.append('specs', JSON.stringify(specs));

            const response = await fetch(ajaxUrl, { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                saveStatusEl.textContent = 'Saved ' + (data.data.saved || 0) + ' field(s). Reloading...';
                // Reload page so ACF fields reflect changes.
                setTimeout(() => window.location.reload(), 1500);
            } else {
                saveStatusEl.textContent = data.data?.message || 'Error saving specs.';
                saveBtnEl.disabled = false;
            }
        } catch (err) {
            saveStatusEl.textContent = 'Network error.';
            saveBtnEl.disabled = false;
        }
    }

    /**
     * Format a value for display (read-only).
     */
    function formatValue(value, schema) {
        if (value === null || value === undefined || value === '') {
            return '<span class="erh-sp-empty-val">-</span>';
        }
        if (Array.isArray(value)) {
            return escHtml(value.join(', '));
        }
        // Boolean/true_false fields: ACF stores as 1/0, show as Yes/No.
        if (schema && schema.type === 'boolean') {
            return (value === true || value === 1 || value === '1') ? 'Yes' : 'No';
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
     * Build an editable input for the suggested value column.
     */
    function buildEditInput(suggestedVal, fieldSchema) {
        const type = fieldSchema.type || 'text';
        const val = (suggestedVal !== null && suggestedVal !== undefined) ? suggestedVal : '';

        if (type === 'boolean') {
            const isTrue = val === 1 || val === '1' || val === true || val === 'true';
            const isFalse = val === 0 || val === '0' || val === false || val === 'false';
            return '<select class="erh-sp-edit-value">'
                + '<option value=""' + (!isTrue && !isFalse ? ' selected' : '') + '>\u2014</option>'
                + '<option value="1"' + (isTrue ? ' selected' : '') + '>Yes</option>'
                + '<option value="0"' + (isFalse ? ' selected' : '') + '>No</option>'
                + '</select>';
        }

        if (type === 'select' && fieldSchema.choices && Object.keys(fieldSchema.choices).length) {
            let html = '<select class="erh-sp-edit-value">'
                + '<option value="">\u2014</option>';
            for (const [optVal, optLabel] of Object.entries(fieldSchema.choices)) {
                const selected = String(val) === String(optVal) ? ' selected' : '';
                html += '<option value="' + escAttr(optVal) + '"' + selected + '>' + escHtml(String(optLabel)) + '</option>';
            }
            html += '</select>';
            return html;
        }

        if (type === 'textarea') {
            return '<textarea class="erh-sp-edit-value" rows="2">' + escHtml(String(val)) + '</textarea>';
        }

        const displayVal = Array.isArray(val) ? val.join(', ') : String(val);
        return '<input type="text" class="erh-sp-edit-value" value="' + escAttr(displayVal) + '">';
    }

    /**
     * Read the edited value from a result row's input.
     */
    function getEditValue(row) {
        const type = row.dataset.type || 'text';
        const input = row.querySelector('.erh-sp-edit-value');
        if (!input) return null;

        const raw = (input.tagName === 'TEXTAREA' ? input.value : input.value).trim();
        if (raw === '' || raw === '\u2014') return null;

        if (type === 'number') {
            const num = parseFloat(raw);
            return isNaN(num) ? raw : num;
        }

        if (type === 'boolean') {
            return raw === '1' ? 1 : (raw === '0' ? 0 : null);
        }

        if (type === 'checkbox') {
            return raw.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
        }

        return raw;
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

    // Initialize on DOM ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

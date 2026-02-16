/**
 * Price History Editor - Admin JavaScript
 *
 * Handles table rendering, geo filtering, inline price editing,
 * and bulk delete operations for the price history meta box.
 *
 * @package ERH\Admin
 */

(function() {
    'use strict';

    const config = window.erhPriceHistoryEditor || {};
    const { ajaxUrl, nonce, productId, availableGeos } = config;

    // State
    let rows = [];
    let selectedIds = new Set();
    let editingRowId = null;

    // DOM Elements
    const wrap = document.getElementById('erh-phe-wrap');
    if (!wrap) return;

    const geoFilter = document.getElementById('erh-phe-geo-filter');
    const recordCount = document.getElementById('erh-phe-record-count');
    const tableBody = document.getElementById('erh-phe-body');
    const checkAll = document.getElementById('erh-phe-check-all');
    const deleteSelectedBtn = document.getElementById('erh-phe-delete-selected');
    const selectedCountEl = document.getElementById('erh-phe-selected-count');
    const toggleRangeBtn = document.getElementById('erh-phe-toggle-range');
    const clearAllBtn = document.getElementById('erh-phe-clear-all');
    const rangePanel = document.getElementById('erh-phe-range-panel');
    const rangeFrom = document.getElementById('erh-phe-range-from');
    const rangeTo = document.getElementById('erh-phe-range-to');
    const rangeGeo = document.getElementById('erh-phe-range-geo');
    const deleteRangeBtn = document.getElementById('erh-phe-delete-range');
    const cancelRangeBtn = document.getElementById('erh-phe-cancel-range');
    const statusEl = document.getElementById('erh-phe-status');

    /**
     * Initialize the module.
     */
    function init() {
        populateGeoDropdowns();
        bindEvents();
        loadHistory();
    }

    /**
     * Populate geo dropdowns from available geos.
     */
    function populateGeoDropdowns() {
        if (!availableGeos || !availableGeos.length) return;

        availableGeos.forEach(function(geo) {
            var label = geo || 'US (legacy)';

            var opt1 = document.createElement('option');
            opt1.value = geo;
            opt1.textContent = label;
            geoFilter.appendChild(opt1);

            var opt2 = document.createElement('option');
            opt2.value = geo;
            opt2.textContent = label;
            rangeGeo.appendChild(opt2);
        });
    }

    /**
     * Bind event listeners.
     */
    function bindEvents() {
        geoFilter.addEventListener('change', function() {
            loadHistory();
        });

        checkAll.addEventListener('change', function() {
            var checked = checkAll.checked;
            rows.forEach(function(row) {
                if (checked) {
                    selectedIds.add(row.id);
                } else {
                    selectedIds.delete(row.id);
                }
            });
            updateCheckboxes();
            updateSelectionUI();
        });

        deleteSelectedBtn.addEventListener('click', onDeleteSelected);
        toggleRangeBtn.addEventListener('click', function() {
            rangePanel.style.display = rangePanel.style.display === 'none' ? '' : 'none';
        });
        cancelRangeBtn.addEventListener('click', function() {
            rangePanel.style.display = 'none';
        });
        deleteRangeBtn.addEventListener('click', onDeleteRange);
        clearAllBtn.addEventListener('click', onClearAll);
    }

    /**
     * Load history via AJAX.
     */
    function loadHistory() {
        tableBody.innerHTML = '<tr><td colspan="7" class="erh-phe-loading">Loading...</td></tr>';
        selectedIds.clear();
        checkAll.checked = false;
        updateSelectionUI();

        var data = new FormData();
        data.append('action', 'erh_phe_get_history');
        data.append('nonce', nonce);
        data.append('product_id', productId);
        data.append('geo', geoFilter.value);

        fetch(ajaxUrl, { method: 'POST', body: data })
            .then(function(r) { return r.json(); })
            .then(function(response) {
                if (!response.success) {
                    showStatus(response.data.message, 'error');
                    tableBody.innerHTML = '<tr><td colspan="7">Error loading data.</td></tr>';
                    return;
                }
                rows = response.data.rows;
                recordCount.textContent = response.data.count + ' records';
                renderTable();
            })
            .catch(function() {
                tableBody.innerHTML = '<tr><td colspan="7">Network error.</td></tr>';
            });
    }

    /**
     * Render table rows.
     */
    function renderTable() {
        if (!rows.length) {
            tableBody.innerHTML = '<tr><td colspan="7" class="erh-phe-empty">No price history found.</td></tr>';
            return;
        }

        var html = '';
        rows.forEach(function(row) {
            var checked = selectedIds.has(row.id) ? ' checked' : '';
            var isEditing = editingRowId === row.id;

            html += '<tr data-id="' + row.id + '">';
            html += '<td><input type="checkbox" class="erh-phe-row-check" value="' + row.id + '"' + checked + '></td>';
            html += '<td>' + escHtml(row.date) + '</td>';

            if (isEditing) {
                html += '<td class="erh-phe-edit-cell">';
                html += '<input type="number" step="0.01" min="0" class="erh-phe-price-input" value="' + row.price.toFixed(2) + '">';
                html += '<button type="button" class="button button-small erh-phe-save-price" data-id="' + row.id + '">Save</button>';
                html += '<button type="button" class="button button-small erh-phe-cancel-edit">Cancel</button>';
                html += '</td>';
            } else {
                html += '<td>' + formatPrice(row.price) + '</td>';
            }

            html += '<td>' + escHtml(row.currency || '') + '</td>';
            html += '<td>' + escHtml(row.domain || '') + '</td>';
            html += '<td>' + escHtml(row.geo || '') + '</td>';

            if (!isEditing) {
                html += '<td><button type="button" class="button button-small erh-phe-edit-btn" data-id="' + row.id + '" title="Edit price">&#9998;</button></td>';
            } else {
                html += '<td></td>';
            }

            html += '</tr>';
        });

        tableBody.innerHTML = html;
        bindRowEvents();
    }

    /**
     * Bind events on rendered rows.
     */
    function bindRowEvents() {
        // Checkboxes
        tableBody.querySelectorAll('.erh-phe-row-check').forEach(function(cb) {
            cb.addEventListener('change', function() {
                var id = parseInt(cb.value, 10);
                if (cb.checked) {
                    selectedIds.add(id);
                } else {
                    selectedIds.delete(id);
                }
                updateSelectionUI();
            });
        });

        // Edit buttons
        tableBody.querySelectorAll('.erh-phe-edit-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                editingRowId = parseInt(btn.dataset.id, 10);
                renderTable();
                // Focus the input
                var input = tableBody.querySelector('.erh-phe-price-input');
                if (input) input.focus();
            });
        });

        // Save price buttons
        tableBody.querySelectorAll('.erh-phe-save-price').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var input = tableBody.querySelector('.erh-phe-price-input');
                if (!input) return;
                var newPrice = parseFloat(input.value);
                if (isNaN(newPrice) || newPrice < 0) {
                    showStatus('Please enter a valid price.', 'error');
                    return;
                }
                savePrice(parseInt(btn.dataset.id, 10), newPrice);
            });
        });

        // Cancel edit buttons
        tableBody.querySelectorAll('.erh-phe-cancel-edit').forEach(function(btn) {
            btn.addEventListener('click', function() {
                editingRowId = null;
                renderTable();
            });
        });

        // Handle Enter key in price input
        var priceInput = tableBody.querySelector('.erh-phe-price-input');
        if (priceInput) {
            priceInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    var saveBtn = tableBody.querySelector('.erh-phe-save-price');
                    if (saveBtn) saveBtn.click();
                } else if (e.key === 'Escape') {
                    editingRowId = null;
                    renderTable();
                }
            });
        }
    }

    /**
     * Update checkboxes to reflect selectedIds state.
     */
    function updateCheckboxes() {
        tableBody.querySelectorAll('.erh-phe-row-check').forEach(function(cb) {
            cb.checked = selectedIds.has(parseInt(cb.value, 10));
        });
    }

    /**
     * Update the selection count and button state.
     */
    function updateSelectionUI() {
        var count = selectedIds.size;
        deleteSelectedBtn.disabled = count === 0;
        selectedCountEl.textContent = count > 0 ? count + ' selected' : '';
    }

    /**
     * Delete selected rows.
     */
    function onDeleteSelected() {
        var count = selectedIds.size;
        if (!count) return;

        if (!confirm('Delete ' + count + ' selected row(s)? This cannot be undone.')) {
            return;
        }

        var data = new FormData();
        data.append('action', 'erh_phe_delete_rows');
        data.append('nonce', nonce);
        data.append('product_id', productId);

        var idsArray = Array.from(selectedIds);
        idsArray.forEach(function(id) {
            data.append('ids[]', id);
        });

        setLoading(deleteSelectedBtn, true);

        fetch(ajaxUrl, { method: 'POST', body: data })
            .then(function(r) { return r.json(); })
            .then(function(response) {
                setLoading(deleteSelectedBtn, false);
                if (response.success) {
                    showStatus(response.data.message, 'success');
                    editingRowId = null;
                    loadHistory();
                } else {
                    showStatus(response.data.message, 'error');
                }
            })
            .catch(function() {
                setLoading(deleteSelectedBtn, false);
                showStatus('Network error.', 'error');
            });
    }

    /**
     * Delete rows in date range.
     */
    function onDeleteRange() {
        var from = rangeFrom.value;
        var to = rangeTo.value;
        var geo = rangeGeo.value;

        if (!from || !to) {
            showStatus('Please select both From and To dates.', 'error');
            return;
        }

        var msg = 'Delete all rows from ' + from + ' to ' + to;
        if (geo) msg += ' for geo ' + geo;
        msg += '? This cannot be undone.';

        if (!confirm(msg)) return;

        var data = new FormData();
        data.append('action', 'erh_phe_delete_range');
        data.append('nonce', nonce);
        data.append('product_id', productId);
        data.append('date_from', from);
        data.append('date_to', to);
        data.append('geo', geo);

        setLoading(deleteRangeBtn, true);

        fetch(ajaxUrl, { method: 'POST', body: data })
            .then(function(r) { return r.json(); })
            .then(function(response) {
                setLoading(deleteRangeBtn, false);
                if (response.success) {
                    showStatus(response.data.message, 'success');
                    rangePanel.style.display = 'none';
                    editingRowId = null;
                    loadHistory();
                } else {
                    showStatus(response.data.message, 'error');
                }
            })
            .catch(function() {
                setLoading(deleteRangeBtn, false);
                showStatus('Network error.', 'error');
            });
    }

    /**
     * Clear all history for this product.
     */
    function onClearAll() {
        if (!confirm('Delete ALL price history for this product? This cannot be undone.')) {
            return;
        }
        if (!confirm('Are you absolutely sure? This will remove all price data permanently.')) {
            return;
        }

        var data = new FormData();
        data.append('action', 'erh_phe_clear_all');
        data.append('nonce', nonce);
        data.append('product_id', productId);

        setLoading(clearAllBtn, true);

        fetch(ajaxUrl, { method: 'POST', body: data })
            .then(function(r) { return r.json(); })
            .then(function(response) {
                setLoading(clearAllBtn, false);
                if (response.success) {
                    showStatus(response.data.message, 'success');
                    editingRowId = null;
                    loadHistory();
                } else {
                    showStatus(response.data.message, 'error');
                }
            })
            .catch(function() {
                setLoading(clearAllBtn, false);
                showStatus('Network error.', 'error');
            });
    }

    /**
     * Save an edited price via AJAX.
     */
    function savePrice(rowId, price) {
        var data = new FormData();
        data.append('action', 'erh_phe_update_price');
        data.append('nonce', nonce);
        data.append('product_id', productId);
        data.append('row_id', rowId);
        data.append('price', price);

        var saveBtn = tableBody.querySelector('.erh-phe-save-price');
        if (saveBtn) setLoading(saveBtn, true);

        fetch(ajaxUrl, { method: 'POST', body: data })
            .then(function(r) { return r.json(); })
            .then(function(response) {
                if (response.success) {
                    // Update the row in local state
                    rows.forEach(function(row) {
                        if (row.id === rowId) {
                            row.price = price;
                        }
                    });
                    editingRowId = null;
                    renderTable();
                    showStatus(response.data.message, 'success');
                } else {
                    if (saveBtn) setLoading(saveBtn, false);
                    showStatus(response.data.message, 'error');
                }
            })
            .catch(function() {
                if (saveBtn) setLoading(saveBtn, false);
                showStatus('Network error.', 'error');
            });
    }

    /**
     * Show a status message.
     */
    function showStatus(message, type) {
        statusEl.textContent = message;
        statusEl.className = 'erh-phe-status erh-phe-status--' + type;
        statusEl.style.display = '';

        clearTimeout(showStatus._timer);
        showStatus._timer = setTimeout(function() {
            statusEl.style.display = 'none';
        }, 5000);
    }

    /**
     * Set loading state on a button.
     */
    function setLoading(btn, loading) {
        btn.disabled = loading;
        if (loading) {
            btn.dataset.origText = btn.textContent;
            btn.textContent = 'Working...';
        } else if (btn.dataset.origText) {
            btn.textContent = btn.dataset.origText;
        }
    }

    /**
     * Format a price number.
     */
    function formatPrice(price) {
        return price.toFixed(2);
    }

    /**
     * Escape HTML entities.
     */
    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // Boot
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

/**
 * Spec Editor - Cell Editors
 *
 * Type-specific inline editors for spreadsheet cells.
 *
 * @package ERH\Admin
 */

import { store } from './state.js';
import { historyManager } from './history.js';
import { apiRequest, parseValue, valuesEqual, escapeHtml } from './utils.js';

/**
 * Create an editor for a cell based on column type.
 * @param {Object} column - Column schema.
 * @param {*} currentValue - Current value.
 * @param {Function} onSave - Callback when saving.
 * @param {Function} onCancel - Callback when canceling.
 * @returns {HTMLElement} Editor element.
 */
export function createEditor(column, currentValue, onSave, onCancel) {
    const type = column.type || 'text';

    switch (type) {
        case 'number':
            return createNumberEditor(column, currentValue, onSave, onCancel);

        case 'select':
            return createSelectEditor(column, currentValue, onSave, onCancel);

        case 'checkbox':
            return createCheckboxEditor(column, currentValue, onSave, onCancel);

        case 'boolean':
            return createBooleanEditor(column, currentValue, onSave, onCancel);

        case 'textarea':
            return createTextareaEditor(column, currentValue, onSave, onCancel);

        case 'text':
        default:
            return createTextEditor(column, currentValue, onSave, onCancel);
    }
}

/**
 * Create a text input editor.
 */
function createTextEditor(column, currentValue, onSave, onCancel) {
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'erh-se-editor';
    input.value = currentValue ?? '';

    attachCommonHandlers(input, column, currentValue, onSave, onCancel);

    return input;
}

/**
 * Create a number input editor.
 */
function createNumberEditor(column, currentValue, onSave, onCancel) {
    const input = document.createElement('input');
    input.type = 'number';
    input.className = 'erh-se-editor';
    input.value = currentValue ?? '';

    if (column.min !== null && column.min !== undefined) {
        input.min = column.min;
    }
    if (column.max !== null && column.max !== undefined) {
        input.max = column.max;
    }
    if (column.step) {
        input.step = column.step;
    }

    attachCommonHandlers(input, column, currentValue, onSave, onCancel);

    return input;
}

/**
 * Create a select dropdown editor.
 */
function createSelectEditor(column, currentValue, onSave, onCancel) {
    const select = document.createElement('select');
    select.className = 'erh-se-editor erh-se-editor-select';

    // Add empty option.
    const emptyOption = document.createElement('option');
    emptyOption.value = '';
    emptyOption.textContent = '—';
    select.appendChild(emptyOption);

    // Add choices.
    if (column.choices) {
        Object.entries(column.choices).forEach(([value, label]) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            if (value === currentValue) {
                option.selected = true;
            }
            select.appendChild(option);
        });
    }

    // Save on change.
    select.addEventListener('change', () => {
        const newValue = select.value;
        if (!valuesEqual(newValue, currentValue)) {
            onSave(newValue);
        } else {
            onCancel();
        }
    });

    // Cancel on escape.
    select.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            onCancel();
        }
    });

    // Cancel on blur.
    select.addEventListener('blur', () => {
        // Small delay to allow change event to fire first.
        setTimeout(() => {
            if (document.activeElement !== select) {
                onCancel();
            }
        }, 100);
    });

    return select;
}

/**
 * Create a checkbox (multi-select) editor.
 */
function createCheckboxEditor(column, currentValue, onSave, onCancel) {
    const container = document.createElement('div');
    container.className = 'erh-se-editor-checkbox';

    const values = Array.isArray(currentValue) ? currentValue : [];

    if (column.choices) {
        Object.entries(column.choices).forEach(([value, label]) => {
            const labelEl = document.createElement('label');

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.value = value;
            checkbox.checked = values.includes(value);

            const text = document.createTextNode(label);

            labelEl.appendChild(checkbox);
            labelEl.appendChild(text);
            container.appendChild(labelEl);

            checkbox.addEventListener('change', () => {
                const newValues = [];
                container.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                    if (cb.checked) {
                        newValues.push(cb.value);
                    }
                });

                if (!valuesEqual(newValues, currentValue)) {
                    onSave(newValues);
                }
            });
        });
    }

    // Cancel on escape.
    container.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            onCancel();
        }
    });

    return container;
}

/**
 * Create a boolean (true/false) toggle editor.
 */
function createBooleanEditor(column, currentValue, onSave, onCancel) {
    const container = document.createElement('div');
    container.className = 'erh-se-editor-checkbox';

    const { i18n } = window.erhSpecEditor;

    const label = document.createElement('label');

    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.checked = !!currentValue;

    const text = document.createTextNode(checkbox.checked ? i18n.true : i18n.false);

    label.appendChild(checkbox);
    label.appendChild(text);
    container.appendChild(label);

    // Save on change.
    checkbox.addEventListener('change', () => {
        const newValue = checkbox.checked;
        text.textContent = newValue ? i18n.true : i18n.false;
        onSave(newValue);
    });

    // Cancel on escape.
    container.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            onCancel();
        }
    });

    return container;
}

/**
 * Create a textarea editor.
 */
function createTextareaEditor(column, currentValue, onSave, onCancel) {
    const textarea = document.createElement('textarea');
    textarea.className = 'erh-se-editor';
    textarea.value = currentValue ?? '';
    textarea.rows = 3;

    attachCommonHandlers(textarea, column, currentValue, onSave, onCancel, true);

    return textarea;
}

/**
 * Attach common event handlers to an input element.
 */
function attachCommonHandlers(input, column, currentValue, onSave, onCancel, isTextarea = false) {
    // Save on blur.
    input.addEventListener('blur', () => {
        const newValue = parseValue(input.value, column);
        if (!valuesEqual(newValue, currentValue)) {
            onSave(newValue);
        } else {
            onCancel();
        }
    });

    // Handle keyboard.
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            input.value = currentValue ?? '';
            onCancel();
        }

        // Enter saves (but not for textarea unless Ctrl is held).
        if (e.key === 'Enter' && (!isTextarea || e.ctrlKey)) {
            e.preventDefault();
            input.blur();
        }

        // Tab saves and moves to next cell.
        if (e.key === 'Tab') {
            // Let the blur handler save.
        }
    });
}

/**
 * Handle cell click to start editing.
 * @param {HTMLElement} cell - Cell element.
 * @param {Object} product - Product data (may be stale, used for ID only).
 * @param {Object} column - Column schema.
 */
export function startEditing(cell, product, column) {
    // Don't edit readonly columns.
    if (column.readonly) {
        return;
    }

    const state = store.getState();

    // If already editing this cell, do nothing.
    if (state.editingCell?.productId === product.id &&
        state.editingCell?.fieldPath === column.key) {
        return;
    }

    // Cancel any existing edit.
    cancelCurrentEdit();

    // Get fresh product data from store (not the stale closure).
    const freshProduct = store.getProductById(product.id);
    if (!freshProduct) {
        return;
    }

    // Mark cell as editing.
    cell.classList.add('is-editing');
    store.setEditingCell({ productId: freshProduct.id, fieldPath: column.key });

    // Get current value from fresh product data.
    const currentValue = column.key === 'post_title'
        ? freshProduct.title
        : freshProduct.specs[column.key];

    // Create editor.
    const editor = createEditor(
        column,
        currentValue,
        (newValue) => saveCell(cell, freshProduct, column, currentValue, newValue),
        () => cancelEdit(cell)
    );

    // Replace cell content with editor.
    cell.replaceChildren(editor);

    // Focus the editor.
    if (editor.focus) {
        editor.focus();
        if (editor.select) {
            editor.select();
        }
    }
}

/**
 * Save a cell value.
 */
async function saveCell(cell, product, column, oldValue, newValue) {
    const state = store.getState();

    // Mark as saving.
    cell.classList.remove('is-editing');
    cell.classList.add('is-saving');
    store.setEditingCell(null);

    // Show saving status.
    store.showStatus('saving', window.erhSpecEditor.i18n.saving, 0);

    try {
        // Make API call.
        const response = await apiRequest('update', {
            method: 'POST',
            body: JSON.stringify({
                product_id: product.id,
                field_path: column.key,
                value: newValue,
                type: state.productType,
            }),
        });

        // Update local state.
        store.updateProductSpec(product.id, column.key, newValue);

        // Add to history.
        historyManager.push({
            productId: product.id,
            productName: product.title,
            productType: state.productType,
            fieldPath: column.key,
            fieldLabel: column.label,
            oldValue: oldValue,
            newValue: newValue,
        });

        // Visual feedback.
        cell.classList.remove('is-saving');
        cell.classList.add('is-saved');
        store.showStatus('success', window.erhSpecEditor.i18n.saved, 2000);

        setTimeout(() => {
            cell.classList.remove('is-saved');
        }, 1000);

        // Re-render cell content.
        renderCellValue(cell, column, newValue);

    } catch (error) {
        console.error('Save error:', error);

        // Visual feedback.
        cell.classList.remove('is-saving');
        cell.classList.add('is-error');
        store.showStatus('error', error.message || window.erhSpecEditor.i18n.error, 3000);

        setTimeout(() => {
            cell.classList.remove('is-error');
        }, 1000);

        // Revert to old value display.
        renderCellValue(cell, column, oldValue);
    }
}

/**
 * Cancel editing a cell.
 */
function cancelEdit(cell) {
    const state = store.getState();
    if (!state.editingCell) return;

    cell.classList.remove('is-editing');
    store.setEditingCell(null);

    // Get the product and column to restore display.
    const product = store.getProductById(state.editingCell.productId);
    const column = state.schema.find(c => c.key === state.editingCell.fieldPath);

    if (product && column) {
        const value = column.key === 'post_title'
            ? product.title
            : product.specs[column.key];
        renderCellValue(cell, column, value);
    }
}

/**
 * Cancel any currently editing cell.
 */
export function cancelCurrentEdit() {
    const state = store.getState();
    if (!state.editingCell) return;

    const cell = document.querySelector(
        `[data-product-id="${state.editingCell.productId}"][data-field="${state.editingCell.fieldPath}"]`
    );

    if (cell) {
        cancelEdit(cell);
    } else {
        store.setEditingCell(null);
    }
}

/**
 * Render a cell's value display.
 * @param {HTMLElement} cell - Cell element.
 * @param {Object} column - Column schema.
 * @param {*} value - Value to display.
 */
export function renderCellValue(cell, column, value) {
    const { i18n } = window.erhSpecEditor;
    const type = column.type || 'text';

    // Clear existing content.
    cell.replaceChildren();

    const valueEl = document.createElement('span');
    valueEl.className = 'erh-se-cell-value';

    if (value === null || value === undefined || value === '') {
        valueEl.classList.add('is-empty');
        valueEl.textContent = '—';
        cell.appendChild(valueEl);
        return;
    }

    switch (type) {
        case 'boolean':
            valueEl.classList.add('is-boolean');
            const icon = document.createElement('span');
            icon.className = `dashicons dashicons-${value ? 'yes-alt' : 'no-alt'}`;
            valueEl.appendChild(icon);
            valueEl.appendChild(document.createTextNode(value ? i18n.true : i18n.false));
            break;

        case 'select':
            valueEl.textContent = column.choices?.[value] || value;
            break;

        case 'checkbox':
            if (Array.isArray(value) && value.length > 0) {
                const labels = value.map(v => column.choices?.[v] || v);
                valueEl.textContent = labels.join(', ');
            } else {
                valueEl.classList.add('is-empty');
                valueEl.textContent = '—';
            }
            break;

        case 'number':
            const num = parseFloat(value);
            if (!isNaN(num)) {
                const suffix = column.append ? ` ${column.append}` : '';
                valueEl.textContent = num.toLocaleString() + suffix;
            } else {
                valueEl.textContent = value;
            }
            break;

        default:
            valueEl.textContent = String(value);
    }

    cell.appendChild(valueEl);
}

/**
 * Spec Editor - History (Undo/Redo) System
 *
 * Tracks changes with local storage persistence.
 *
 * @package ERH\Admin
 */

import { store } from './state.js';
import { getHistoryStorageKey, generateId, formatRelativeTime, apiRequest, escapeHtml } from './utils.js';

const MAX_HISTORY_ITEMS = 50;

/**
 * Create the history manager.
 * @returns {Object} History manager.
 */
export function createHistoryManager() {
    let history = [];
    let undoStack = [];
    let listeners = new Set();
    let isOperating = false; // Lock to prevent concurrent undo/redo operations.

    /**
     * Load history from localStorage.
     */
    function load() {
        const stored = localStorage.getItem(getHistoryStorageKey());
        if (stored) {
            try {
                history = JSON.parse(stored);
                // Clear undo stack on load.
                undoStack = [];
                notifyListeners();
            } catch (e) {
                console.warn('Failed to parse stored history:', e);
                history = [];
            }
        }
    }

    /**
     * Save history to localStorage.
     */
    function save() {
        localStorage.setItem(getHistoryStorageKey(), JSON.stringify(history));
    }

    /**
     * Subscribe to history changes.
     * @param {Function} listener - Listener function.
     * @returns {Function} Unsubscribe function.
     */
    function subscribe(listener) {
        listeners.add(listener);
        return () => listeners.delete(listener);
    }

    /**
     * Notify all listeners.
     */
    function notifyListeners() {
        const state = getState();
        listeners.forEach(listener => listener(state));
    }

    /**
     * Get history state.
     * @returns {Object} History state.
     */
    function getState() {
        return {
            history: [...history],
            canUndo: history.length > 0 && !isOperating,
            canRedo: undoStack.length > 0 && !isOperating,
            isOperating,
            count: history.length,
        };
    }

    /**
     * Push a change to history.
     * @param {Object} change - Change object.
     */
    function push(change) {
        const entry = {
            id: generateId(),
            timestamp: new Date().toISOString(),
            ...change,
        };

        // Add to beginning.
        history.unshift(entry);

        // Trim to max items.
        if (history.length > MAX_HISTORY_ITEMS) {
            history = history.slice(0, MAX_HISTORY_ITEMS);
        }

        // Clear redo stack when new change is made.
        undoStack = [];

        save();
        notifyListeners();
    }

    /**
     * Undo the last change.
     * @returns {Promise<boolean>} Success.
     */
    async function undo() {
        if (history.length === 0) return false;

        // Prevent concurrent operations.
        if (isOperating) {
            return false;
        }
        isOperating = true;

        const change = history.shift();

        try {
            // Make API call to revert.
            await apiRequest('update', {
                method: 'POST',
                body: JSON.stringify({
                    product_id: change.productId,
                    field_path: change.fieldPath,
                    value: change.oldValue,
                    type: change.productType,
                }),
            });

            // Update state.
            store.updateProductSpec(change.productId, change.fieldPath, change.oldValue);

            // Add to redo stack.
            undoStack.unshift(change);

            save();
            notifyListeners();

            store.showStatus('success', `Undid change to ${change.fieldLabel}`, 2000);
            return true;
        } catch (error) {
            // Put change back.
            history.unshift(change);
            store.showStatus('error', `Failed to undo: ${error.message}`, 3000);
            return false;
        } finally {
            isOperating = false;
        }
    }

    /**
     * Redo the last undone change.
     * @returns {Promise<boolean>} Success.
     */
    async function redo() {
        if (undoStack.length === 0) return false;

        // Prevent concurrent operations.
        if (isOperating) {
            return false;
        }
        isOperating = true;

        const change = undoStack.shift();

        try {
            // Make API call to apply.
            await apiRequest('update', {
                method: 'POST',
                body: JSON.stringify({
                    product_id: change.productId,
                    field_path: change.fieldPath,
                    value: change.newValue,
                    type: change.productType,
                }),
            });

            // Update state.
            store.updateProductSpec(change.productId, change.fieldPath, change.newValue);

            // Add back to history.
            history.unshift(change);

            save();
            notifyListeners();

            store.showStatus('success', `Redid change to ${change.fieldLabel}`, 2000);
            return true;
        } catch (error) {
            // Put change back on redo stack.
            undoStack.unshift(change);
            store.showStatus('error', `Failed to redo: ${error.message}`, 3000);
            return false;
        } finally {
            isOperating = false;
        }
    }

    /**
     * Clear all history.
     */
    function clear() {
        history = [];
        undoStack = [];
        save();
        notifyListeners();
    }

    /**
     * Get formatted history items for display.
     * @returns {Array} Formatted history items.
     */
    function getFormattedHistory() {
        return history.map(item => ({
            ...item,
            relativeTime: formatRelativeTime(item.timestamp),
            oldValueFormatted: formatChangeValue(item.oldValue),
            newValueFormatted: formatChangeValue(item.newValue),
        }));
    }

    /**
     * Format a value for display in history.
     * @param {*} value - Value to format.
     * @returns {string} Formatted value.
     */
    function formatChangeValue(value) {
        if (value === null || value === undefined || value === '') {
            return '(empty)';
        }
        if (typeof value === 'boolean') {
            return value ? 'Yes' : 'No';
        }
        if (Array.isArray(value)) {
            return value.join(', ') || '(empty)';
        }
        return String(value);
    }

    // Load on init.
    load();

    return {
        subscribe,
        getState,
        push,
        undo,
        redo,
        clear,
        getFormattedHistory,
    };
}

// Create singleton history manager.
export const historyManager = createHistoryManager();

/**
 * Create a history item element using DOM methods.
 * @param {Object} item - History item.
 * @returns {HTMLElement} History item element.
 */
function createHistoryItemElement(item) {
    const div = document.createElement('div');
    div.className = 'erh-se-history-item';
    div.dataset.id = item.id;

    const header = document.createElement('div');
    header.className = 'erh-se-history-item-header';

    const productName = document.createElement('span');
    productName.className = 'erh-se-history-product';
    productName.textContent = item.productName;

    const time = document.createElement('span');
    time.className = 'erh-se-history-time';
    time.textContent = item.relativeTime;

    header.appendChild(productName);
    header.appendChild(time);

    const field = document.createElement('div');
    field.className = 'erh-se-history-field';
    field.textContent = item.fieldLabel;

    const values = document.createElement('div');
    values.className = 'erh-se-history-values';

    const oldVal = document.createElement('span');
    oldVal.className = 'erh-se-history-old';
    oldVal.textContent = item.oldValueFormatted;

    const arrow = document.createElement('span');
    arrow.className = 'erh-se-history-arrow';
    arrow.textContent = '→';

    const newVal = document.createElement('span');
    newVal.className = 'erh-se-history-new';
    newVal.textContent = item.newValueFormatted;

    values.appendChild(oldVal);
    values.appendChild(arrow);
    values.appendChild(newVal);

    div.appendChild(header);
    div.appendChild(field);
    div.appendChild(values);

    return div;
}

/**
 * Initialize history UI.
 */
export function initHistoryUI() {
    const undoBtn = document.getElementById('erh-se-undo');
    const redoBtn = document.getElementById('erh-se-redo');
    const historyBtn = document.getElementById('erh-se-history-btn');
    const historyCount = document.getElementById('erh-se-history-count');
    const historyPanel = document.getElementById('erh-se-history-panel');
    const historyClose = document.getElementById('erh-se-history-close');
    const historyList = document.getElementById('erh-se-history-list');
    const historyClear = document.getElementById('erh-se-history-clear');

    const { i18n } = window.erhSpecEditor;

    /**
     * Update button states.
     */
    function updateButtons() {
        const state = historyManager.getState();

        undoBtn.disabled = !state.canUndo;
        redoBtn.disabled = !state.canRedo;

        // Update count badge.
        historyCount.textContent = state.count;
        historyCount.classList.toggle('has-changes', state.count > 0);
    }

    /**
     * Render history list using DOM methods.
     */
    function renderHistoryList() {
        const items = historyManager.getFormattedHistory();

        // Clear existing content.
        historyList.replaceChildren();

        if (items.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'erh-se-history-empty';
            empty.textContent = i18n.noChanges;
            historyList.appendChild(empty);
            return;
        }

        items.forEach(item => {
            historyList.appendChild(createHistoryItemElement(item));
        });
    }

    /**
     * Toggle history panel.
     */
    function toggleHistoryPanel() {
        const isOpen = historyPanel.classList.toggle('is-open');
        historyPanel.style.display = isOpen ? 'block' : 'none';

        if (isOpen) {
            renderHistoryList();
        }
    }

    // Event listeners.
    undoBtn.addEventListener('click', () => historyManager.undo());
    redoBtn.addEventListener('click', () => historyManager.redo());
    historyBtn.addEventListener('click', toggleHistoryPanel);
    historyClose.addEventListener('click', toggleHistoryPanel);

    historyClear.addEventListener('click', () => {
        if (confirm(i18n.confirmClear)) {
            historyManager.clear();
            renderHistoryList();
        }
    });

    // Keyboard shortcuts.
    document.addEventListener('keydown', (e) => {
        // Ctrl+Z = Undo.
        if (e.ctrlKey && !e.shiftKey && e.key === 'z') {
            e.preventDefault();
            historyManager.undo();
        }

        // Ctrl+Shift+Z or Ctrl+Y = Redo.
        if ((e.ctrlKey && e.shiftKey && e.key === 'z') || (e.ctrlKey && e.key === 'y')) {
            e.preventDefault();
            historyManager.redo();
        }
    });

    // Subscribe to history changes.
    historyManager.subscribe(() => {
        updateButtons();
        if (historyPanel.classList.contains('is-open')) {
            renderHistoryList();
        }
    });

    // Initial render.
    updateButtons();
}

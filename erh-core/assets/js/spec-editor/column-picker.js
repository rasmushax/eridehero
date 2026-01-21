/**
 * Spec Editor - Column Picker
 *
 * Dropdown UI for showing/hiding table columns.
 *
 * @package ERH\Admin
 */

import { store } from './state.js';

let isDropdownOpen = false;

/**
 * Initialize the column picker UI.
 */
export function initColumnPicker() {
    const btn = document.getElementById('erh-se-columns-btn');
    const dropdown = document.getElementById('erh-se-columns-dropdown');

    if (!btn || !dropdown) return;

    const { i18n } = window.erhSpecEditor;

    // Toggle dropdown on button click.
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleDropdown();
    });

    // Close dropdown when clicking outside.
    document.addEventListener('click', (e) => {
        if (isDropdownOpen && !dropdown.contains(e.target) && !btn.contains(e.target)) {
            closeDropdown();
        }
    });

    // Close on escape.
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && isDropdownOpen) {
            closeDropdown();
        }
    });

    // Subscribe to state changes to re-render.
    store.subscribe((state) => {
        if (isDropdownOpen) {
            renderDropdown();
        }
        updateColumnVisibility();
    });

    /**
     * Toggle dropdown open/closed.
     */
    function toggleDropdown() {
        if (isDropdownOpen) {
            closeDropdown();
        } else {
            openDropdown();
        }
    }

    /**
     * Open dropdown.
     */
    function openDropdown() {
        isDropdownOpen = true;
        dropdown.style.display = 'block';
        renderDropdown();
    }

    /**
     * Close dropdown.
     */
    function closeDropdown() {
        isDropdownOpen = false;
        dropdown.style.display = 'none';
    }

    /**
     * Render the dropdown content using DOM methods.
     */
    function renderDropdown() {
        const state = store.getState();

        // Clear existing content.
        dropdown.replaceChildren();

        // Header with show all/hide all buttons.
        const header = document.createElement('div');
        header.className = 'erh-se-columns-header';

        const title = document.createElement('h4');
        title.textContent = i18n.columns;

        const actions = document.createElement('div');
        actions.className = 'erh-se-columns-actions';

        const showAllBtn = document.createElement('button');
        showAllBtn.type = 'button';
        showAllBtn.className = 'button-link';
        showAllBtn.textContent = i18n.showAll;
        showAllBtn.addEventListener('click', () => store.setAllColumnsVisible(true));

        const hideAllBtn = document.createElement('button');
        hideAllBtn.type = 'button';
        hideAllBtn.className = 'button-link';
        hideAllBtn.textContent = i18n.hideAll;
        hideAllBtn.addEventListener('click', () => store.setAllColumnsVisible(false));

        const resetBtn = document.createElement('button');
        resetBtn.type = 'button';
        resetBtn.className = 'button-link';
        resetBtn.textContent = i18n.reset;
        resetBtn.addEventListener('click', () => store.resetColumnVisibility());

        actions.appendChild(showAllBtn);
        actions.appendChild(hideAllBtn);
        actions.appendChild(resetBtn);
        header.appendChild(title);
        header.appendChild(actions);
        dropdown.appendChild(header);

        // Group columns by their group.
        const groups = {};
        state.schema.forEach(column => {
            const group = column.group || 'Other';
            if (!groups[group]) {
                groups[group] = [];
            }
            groups[group].push(column);
        });

        // Render each group.
        Object.entries(groups).forEach(([groupName, columns]) => {
            const groupEl = document.createElement('div');
            groupEl.className = 'erh-se-column-group';

            const groupHeader = document.createElement('div');
            groupHeader.className = 'erh-se-column-group-header';
            groupHeader.textContent = groupName;
            groupEl.appendChild(groupHeader);

            columns.forEach(column => {
                const item = document.createElement('div');
                item.className = 'erh-se-column-item';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.id = `col-${column.key}`;
                checkbox.checked = store.isColumnVisible(column.key);
                checkbox.disabled = column.pinned; // Can't hide pinned columns.

                checkbox.addEventListener('change', () => {
                    store.toggleColumnVisibility(column.key);
                });

                const label = document.createElement('label');
                label.htmlFor = `col-${column.key}`;
                label.textContent = column.label;

                if (column.pinned) {
                    const pinnedBadge = document.createElement('span');
                    pinnedBadge.style.cssText = 'margin-left: 5px; font-size: 10px; color: #646970;';
                    pinnedBadge.textContent = '(pinned)';
                    label.appendChild(pinnedBadge);
                }

                item.appendChild(checkbox);
                item.appendChild(label);
                groupEl.appendChild(item);
            });

            dropdown.appendChild(groupEl);
        });
    }
}

/**
 * Update column visibility in the table.
 */
export function updateColumnVisibility() {
    const state = store.getState();

    // Get all column header cells and body cells.
    state.schema.forEach((column, index) => {
        const isVisible = store.isColumnVisible(column.key);

        // Update header.
        const th = document.querySelector(`th[data-column="${column.key}"]`);
        if (th) {
            th.classList.toggle('is-hidden', !isVisible);
        }

        // Update all body cells in this column.
        const cells = document.querySelectorAll(`td[data-column="${column.key}"]`);
        cells.forEach(cell => {
            cell.classList.toggle('is-hidden', !isVisible);
        });
    });
}

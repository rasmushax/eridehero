/**
 * Custom Select Component
 *
 * A fully accessible, keyboard-navigable custom select dropdown.
 * Replaces native <select> elements while maintaining form compatibility.
 *
 * Features:
 * - Full keyboard navigation (Arrow keys, Enter, Escape, Home, End, Type-ahead)
 * - ARIA attributes for screen reader support
 * - Form submission support via hidden native select
 * - Supports disabled options and option groups
 * - Click outside to close
 * - Focus management
 * - Mobile drawer mode (bottom sheet on small screens)
 *
 * Usage:
 * 1. Add data-custom-select to a <select> element
 * 2. Call initCustomSelects() or let app.js handle it
 *
 * Example HTML:
 * <select data-custom-select data-placeholder="Choose...">
 *   <option value="">Choose an option</option>
 *   <option value="1">Option 1</option>
 *   <option value="2">Option 2</option>
 * </select>
 *
 * Mobile drawer opt-out:
 * <select data-custom-select data-mobile-drawer="false">
 */

import { SelectDrawer } from './select-drawer.js';

export class CustomSelect {
    constructor(selectElement, options = {}) {
        this.select = selectElement;
        this.options = {
            placeholder: selectElement.dataset.placeholder || 'Select...',
            searchable: selectElement.dataset.searchable === 'true',
            ...options
        };

        this.isOpen = false;
        this.focusedIndex = -1;
        this.searchString = '';
        this.searchTimeout = null;

        // Mobile drawer mode (opt-out via data-mobile-drawer="false")
        this.useDrawerOnMobile = selectElement.dataset.mobileDrawer !== 'false';
        this.drawer = null;

        this.init();
    }

    init() {
        this.createCustomSelect();
        this.bindEvents();
        this.setInitialValue();
    }

    createCustomSelect() {
        // Create wrapper
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'custom-select';
        if (this.select.disabled) {
            this.wrapper.classList.add('is-disabled');
        }

        // Transfer variant classes from select to wrapper
        if (this.select.classList.contains('custom-select-sm')) {
            this.wrapper.classList.add('custom-select-sm');
        }
        if (this.select.classList.contains('custom-select-lg')) {
            this.wrapper.classList.add('custom-select-lg');
        }
        if (this.select.classList.contains('custom-select--inline')) {
            this.wrapper.classList.add('custom-select--inline');
        }
        if (this.select.classList.contains('custom-select--align-right')) {
            this.wrapper.classList.add('custom-select--align-right');
        }

        // Get selected option
        const selectedOption = this.select.options[this.select.selectedIndex];
        const hasValue = selectedOption && selectedOption.value !== '';

        // Create trigger button
        this.trigger = document.createElement('button');
        this.trigger.type = 'button';
        this.trigger.className = 'custom-select-trigger';
        this.trigger.setAttribute('aria-haspopup', 'listbox');
        this.trigger.setAttribute('aria-expanded', 'false');
        this.trigger.disabled = this.select.disabled;

        // Generate unique ID for listbox
        const listboxId = `custom-select-listbox-${Math.random().toString(36).substr(2, 9)}`;
        this.trigger.setAttribute('aria-controls', listboxId);

        // Add label association if exists
        const label = document.querySelector(`label[for="${this.select.id}"]`);
        if (label) {
            const labelId = label.id || `label-${listboxId}`;
            label.id = labelId;
            this.trigger.setAttribute('aria-labelledby', labelId);
        }

        this.trigger.innerHTML = `
            <span class="custom-select-value ${!hasValue ? 'is-placeholder' : ''}">${hasValue ? selectedOption.text : this.options.placeholder}</span>
            <svg class="icon" aria-hidden="true"><use href="#icon-chevron-down"></use></svg>
        `;

        // Create dropdown
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'custom-select-dropdown';
        this.dropdown.id = listboxId;
        this.dropdown.setAttribute('role', 'listbox');
        this.dropdown.setAttribute('tabindex', '-1');

        if (label) {
            this.dropdown.setAttribute('aria-labelledby', label.id);
        }

        // Create options list
        this.optionsList = document.createElement('ul');
        this.optionsList.className = 'custom-select-options';
        this.optionsList.setAttribute('role', 'presentation');

        this.renderOptions();

        this.dropdown.appendChild(this.optionsList);

        // Assemble and insert
        this.wrapper.appendChild(this.trigger);
        this.wrapper.appendChild(this.dropdown);

        // Hide original select but keep it in DOM for form submission
        this.select.parentNode.insertBefore(this.wrapper, this.select);
        this.wrapper.appendChild(this.select);
    }

    renderOptions() {
        this.optionsList.innerHTML = '';
        this.optionElements = [];

        const options = Array.from(this.select.options);

        options.forEach((option, index) => {
            // Skip empty placeholder options
            if (option.value === '' && index === 0) {
                return;
            }

            const li = document.createElement('li');
            li.className = 'custom-select-option';
            li.setAttribute('role', 'option');
            li.setAttribute('data-value', option.value);
            li.setAttribute('aria-selected', option.selected ? 'true' : 'false');
            li.id = `${this.dropdown.id}-option-${index}`;
            li.textContent = option.text;

            if (option.disabled) {
                li.classList.add('is-disabled');
                li.setAttribute('aria-disabled', 'true');
            }

            if (option.selected && option.value !== '') {
                li.classList.add('is-selected');
            }

            this.optionsList.appendChild(li);
            this.optionElements.push(li);
        });
    }

    bindEvents() {
        // Trigger click
        this.trigger.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggle();
        });

        // Keyboard navigation on trigger
        this.trigger.addEventListener('keydown', (e) => this.handleTriggerKeydown(e));

        // Option click
        this.optionsList.addEventListener('click', (e) => {
            const option = e.target.closest('.custom-select-option');
            if (option && !option.classList.contains('is-disabled')) {
                this.selectOption(option);
            }
        });

        // Mouse over options
        this.optionsList.addEventListener('mouseover', (e) => {
            const option = e.target.closest('.custom-select-option');
            if (option && !option.classList.contains('is-disabled')) {
                this.setFocusedOption(this.optionElements.indexOf(option));
            }
        });

        // Close on click outside
        document.addEventListener('click', (e) => {
            if (!this.wrapper.contains(e.target) && this.isOpen) {
                this.close();
            }
        });

        // Close on escape (document level)
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
                this.trigger.focus();
            }
        });

        // Sync with native select changes
        this.select.addEventListener('change', () => {
            this.syncFromNative();
        });
    }

    handleTriggerKeydown(e) {
        switch (e.key) {
            case 'Enter':
            case ' ':
                e.preventDefault();
                if (this.isOpen) {
                    if (this.focusedIndex >= 0) {
                        this.selectOption(this.optionElements[this.focusedIndex]);
                    }
                } else {
                    this.open();
                }
                break;

            case 'ArrowDown':
                e.preventDefault();
                if (this.isOpen) {
                    this.focusNextOption();
                } else {
                    this.open();
                }
                break;

            case 'ArrowUp':
                e.preventDefault();
                if (this.isOpen) {
                    this.focusPreviousOption();
                } else {
                    this.open();
                }
                break;

            case 'Home':
                e.preventDefault();
                if (this.isOpen) {
                    this.setFocusedOption(0);
                }
                break;

            case 'End':
                e.preventDefault();
                if (this.isOpen) {
                    this.setFocusedOption(this.optionElements.length - 1);
                }
                break;

            case 'Tab':
                if (this.isOpen) {
                    this.close();
                }
                break;

            default:
                // Type-ahead search
                if (e.key.length === 1 && !e.ctrlKey && !e.metaKey) {
                    this.handleTypeAhead(e.key);
                }
                break;
        }
    }

    handleTypeAhead(char) {
        // Clear previous timeout
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }

        this.searchString += char.toLowerCase();

        // Find matching option
        const matchIndex = this.optionElements.findIndex(option =>
            !option.classList.contains('is-disabled') &&
            option.textContent.toLowerCase().startsWith(this.searchString)
        );

        if (matchIndex >= 0) {
            if (!this.isOpen) {
                this.open();
            }
            this.setFocusedOption(matchIndex);
        }

        // Clear search string after delay
        this.searchTimeout = setTimeout(() => {
            this.searchString = '';
        }, 500);
    }

    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }

    open() {
        if (this.wrapper.classList.contains('is-disabled')) return;

        // Check for mobile drawer mode
        if (this.useDrawerOnMobile && SelectDrawer.shouldUseDrawer()) {
            // Lazy-create drawer instance
            if (!this.drawer) {
                this.drawer = new SelectDrawer(this);
            }
            this.drawer.open();
            return;
        }

        // Desktop dropdown mode
        this.isOpen = true;
        this.wrapper.classList.add('is-open');
        this.trigger.setAttribute('aria-expanded', 'true');

        // Focus currently selected option or first option
        const selectedIndex = this.optionElements.findIndex(el => el.classList.contains('is-selected'));
        this.setFocusedOption(selectedIndex >= 0 ? selectedIndex : 0);

        // Scroll selected option into view
        if (selectedIndex >= 0) {
            this.scrollOptionIntoView(this.optionElements[selectedIndex]);
        }
    }

    close() {
        // If drawer is open, close it instead
        if (this.drawer?.isOpen) {
            this.drawer.close();
            return;
        }

        this.isOpen = false;
        this.wrapper.classList.remove('is-open');
        this.trigger.setAttribute('aria-expanded', 'false');
        this.clearFocusedOption();
    }

    setFocusedOption(index) {
        // Clear previous focus
        this.clearFocusedOption();

        // Skip disabled options
        while (index >= 0 && index < this.optionElements.length &&
               this.optionElements[index].classList.contains('is-disabled')) {
            index++;
        }

        if (index >= 0 && index < this.optionElements.length) {
            this.focusedIndex = index;
            const option = this.optionElements[index];
            option.classList.add('is-focused');
            this.trigger.setAttribute('aria-activedescendant', option.id);
            this.scrollOptionIntoView(option);
        }
    }

    clearFocusedOption() {
        this.optionElements.forEach(el => el.classList.remove('is-focused'));
        this.focusedIndex = -1;
        this.trigger.removeAttribute('aria-activedescendant');
    }

    focusNextOption() {
        let nextIndex = this.focusedIndex + 1;

        // Skip disabled options
        while (nextIndex < this.optionElements.length &&
               this.optionElements[nextIndex].classList.contains('is-disabled')) {
            nextIndex++;
        }

        if (nextIndex < this.optionElements.length) {
            this.setFocusedOption(nextIndex);
        }
    }

    focusPreviousOption() {
        let prevIndex = this.focusedIndex - 1;

        // Skip disabled options
        while (prevIndex >= 0 &&
               this.optionElements[prevIndex].classList.contains('is-disabled')) {
            prevIndex--;
        }

        if (prevIndex >= 0) {
            this.setFocusedOption(prevIndex);
        }
    }

    scrollOptionIntoView(option) {
        const dropdown = this.dropdown;
        const optionTop = option.offsetTop;
        const optionBottom = optionTop + option.offsetHeight;
        const dropdownTop = dropdown.scrollTop;
        const dropdownBottom = dropdownTop + dropdown.clientHeight;

        if (optionTop < dropdownTop) {
            dropdown.scrollTop = optionTop;
        } else if (optionBottom > dropdownBottom) {
            dropdown.scrollTop = optionBottom - dropdown.clientHeight;
        }
    }

    selectOption(optionElement) {
        const value = optionElement.dataset.value;
        const text = optionElement.textContent;

        // Update native select
        this.select.value = value;

        // Trigger change event on native select
        const event = new Event('change', { bubbles: true });
        this.select.dispatchEvent(event);

        // Update custom select UI
        this.updateDisplay(text, value !== '');

        // Update selected state
        this.optionElements.forEach(el => {
            el.classList.remove('is-selected');
            el.setAttribute('aria-selected', 'false');
        });
        optionElement.classList.add('is-selected');
        optionElement.setAttribute('aria-selected', 'true');

        this.close();
        this.trigger.focus();
    }

    updateDisplay(text, hasValue) {
        const valueElement = this.trigger.querySelector('.custom-select-value');
        valueElement.textContent = text;
        valueElement.classList.toggle('is-placeholder', !hasValue);
    }

    setInitialValue() {
        const selectedOption = this.select.options[this.select.selectedIndex];
        if (selectedOption && selectedOption.value !== '') {
            this.updateDisplay(selectedOption.text, true);
        }
    }

    syncFromNative() {
        const selectedOption = this.select.options[this.select.selectedIndex];
        const hasValue = selectedOption && selectedOption.value !== '';

        // Update display
        this.updateDisplay(
            hasValue ? selectedOption.text : this.options.placeholder,
            hasValue
        );

        // Update selected state in options
        this.optionElements.forEach(el => {
            const isSelected = el.dataset.value === this.select.value;
            el.classList.toggle('is-selected', isSelected);
            el.setAttribute('aria-selected', isSelected ? 'true' : 'false');
        });
    }

    // Public API
    getValue() {
        return this.select.value;
    }

    setValue(value) {
        this.select.value = value;
        this.syncFromNative();
    }

    disable() {
        this.select.disabled = true;
        this.trigger.disabled = true;
        this.wrapper.classList.add('is-disabled');
    }

    enable() {
        this.select.disabled = false;
        this.trigger.disabled = false;
        this.wrapper.classList.remove('is-disabled');
    }

    destroy() {
        // Move select back out and remove wrapper
        this.wrapper.parentNode.insertBefore(this.select, this.wrapper);
        this.wrapper.remove();
    }
}

/**
 * Initialize all custom selects on the page or within a container
 * @param {string|HTMLElement} selectorOrContainer - CSS selector or container element
 * @returns {CustomSelect[]} Array of CustomSelect instances
 */
export function initCustomSelects(selectorOrContainer = '[data-custom-select]') {
    let selects;

    if (selectorOrContainer instanceof HTMLElement) {
        // Container element passed - find selects within it
        selects = selectorOrContainer.querySelectorAll('[data-custom-select]');
    } else {
        // Selector string passed
        selects = document.querySelectorAll(selectorOrContainer);
    }

    const instances = [];

    selects.forEach(select => {
        // Skip if already initialized
        if (select.closest('.custom-select')) return;

        instances.push(new CustomSelect(select));
    });

    return instances;
}

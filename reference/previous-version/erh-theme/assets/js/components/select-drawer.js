/**
 * Select Drawer - Mobile Bottom Sheet for Selects
 *
 * On mobile devices (<600px), intercepts CustomSelect open() to show a
 * bottom-sheet drawer instead of the inline dropdown. Provides a better
 * touch experience with larger tap targets.
 *
 * Features:
 * - Automatic mobile detection (breakpoint-based)
 * - Search for long option lists (>8 items)
 * - Keyboard navigation (Arrow keys, Enter, Escape)
 * - Focus trap and management
 * - Swipe-down to close gesture
 * - ARIA attributes for accessibility
 */

export class SelectDrawer {
    // Singleton backdrop shared across all drawers
    static backdrop = null;

    // Only one drawer can be open at a time
    static activeDrawer = null;

    // Mobile breakpoint (matches modal)
    static MOBILE_BREAKPOINT = 600;

    // Search threshold (show search if options > this)
    static SEARCH_THRESHOLD = 8;

    /**
     * Check if drawer mode should be used
     */
    static shouldUseDrawer() {
        return window.innerWidth < SelectDrawer.MOBILE_BREAKPOINT;
    }

    /**
     * Get or create the shared backdrop
     */
    static getBackdrop() {
        if (!SelectDrawer.backdrop) {
            SelectDrawer.backdrop = document.createElement('div');
            SelectDrawer.backdrop.className = 'select-drawer-backdrop';
            SelectDrawer.backdrop.addEventListener('click', () => {
                if (SelectDrawer.activeDrawer) {
                    SelectDrawer.activeDrawer.close();
                }
            });
        }
        return SelectDrawer.backdrop;
    }

    /**
     * Show the backdrop
     */
    static showBackdrop() {
        const backdrop = SelectDrawer.getBackdrop();
        if (!backdrop.parentNode) {
            document.body.appendChild(backdrop);
        }
        // Force reflow
        backdrop.offsetHeight;
        backdrop.classList.add('is-visible');
    }

    /**
     * Hide the backdrop
     */
    static hideBackdrop() {
        const backdrop = SelectDrawer.getBackdrop();
        backdrop.classList.remove('is-visible');
    }

    constructor(customSelect, options = {}) {
        this.customSelect = customSelect;
        this.select = customSelect.select;
        this.options = {
            searchThreshold: SelectDrawer.SEARCH_THRESHOLD,
            searchPlaceholder: 'Search...',
            ...options
        };

        this.isOpen = false;
        this.drawerElement = null;
        this.optionsContainer = null;
        this.searchInput = null;
        this.emptyState = null;
        this.focusedIndex = -1;
        this.optionButtons = [];

        // Bound handlers for cleanup
        this.boundHandleKeydown = this.handleKeydown.bind(this);
        this.boundHandleResize = this.handleResize.bind(this);

        // Swipe gesture state
        this.touchStartY = 0;
        this.touchCurrentY = 0;
        this.isSwiping = false;
    }

    /**
     * Open the drawer
     */
    open() {
        if (this.isOpen) return;

        // Close any other active drawer
        if (SelectDrawer.activeDrawer && SelectDrawer.activeDrawer !== this) {
            SelectDrawer.activeDrawer.close();
        }

        // Create drawer DOM on first open
        if (!this.drawerElement) {
            this.createDrawer();
        }

        // Render current options
        this.renderOptions();

        this.isOpen = true;
        SelectDrawer.activeDrawer = this;

        // Show backdrop
        SelectDrawer.showBackdrop();

        // Add drawer to DOM
        document.body.appendChild(this.drawerElement);

        // Clear any lingering inline transform from swipe gesture
        this.drawerElement.style.transform = '';

        // Force reflow for animation
        this.drawerElement.offsetHeight;
        this.drawerElement.classList.add('is-visible');

        // Lock body scroll
        this.lockBodyScroll();

        // Bind events
        document.addEventListener('keydown', this.boundHandleKeydown);
        window.addEventListener('resize', this.boundHandleResize);

        // Set initial focus
        requestAnimationFrame(() => {
            this.setInitialFocus();
        });
    }

    /**
     * Close the drawer
     */
    close() {
        if (!this.isOpen) return;

        this.isOpen = false;
        SelectDrawer.activeDrawer = null;

        // Hide drawer
        this.drawerElement.classList.remove('is-visible');

        // Hide backdrop
        SelectDrawer.hideBackdrop();

        // Unlock body scroll
        this.unlockBodyScroll();

        // Unbind events
        document.removeEventListener('keydown', this.boundHandleKeydown);
        window.removeEventListener('resize', this.boundHandleResize);

        // Clear search
        if (this.searchInput) {
            this.searchInput.value = '';
            this.filterOptions('');
        }

        // Remove from DOM after animation
        setTimeout(() => {
            if (this.drawerElement?.parentNode) {
                this.drawerElement.parentNode.removeChild(this.drawerElement);
            }
        }, 300);

        // Return focus to trigger
        this.customSelect.trigger.focus();
    }

    /**
     * Create the drawer DOM structure
     */
    createDrawer() {
        const drawer = document.createElement('div');
        drawer.className = 'select-drawer';
        drawer.setAttribute('role', 'dialog');
        drawer.setAttribute('aria-modal', 'true');

        const optionCount = this.select.options.length;
        const hasSearch = optionCount > this.options.searchThreshold;
        if (hasSearch) {
            drawer.classList.add('has-search');
        }

        // Get title from data attribute, associated label, or default
        const title = this.getDrawerTitle();
        const titleId = `select-drawer-title-${Math.random().toString(36).substr(2, 9)}`;
        drawer.setAttribute('aria-labelledby', titleId);

        drawer.innerHTML = `
            <div class="select-drawer-handle" data-drawer-handle></div>
            <div class="select-drawer-header">
                <h2 id="${titleId}" class="select-drawer-title">${title}</h2>
                <button type="button" class="select-drawer-close" data-drawer-close aria-label="Close">
                    <svg class="icon" aria-hidden="true"><use href="#icon-x"></use></svg>
                </button>
            </div>
            <div class="select-drawer-search">
                <svg class="icon" aria-hidden="true"><use href="#icon-search"></use></svg>
                <input type="text" placeholder="${this.options.searchPlaceholder}" data-drawer-search autocomplete="off">
            </div>
            <div class="select-drawer-options" role="listbox" data-drawer-options></div>
            <div class="select-drawer-empty" data-drawer-empty>No options found</div>
        `;

        // Store references
        this.drawerElement = drawer;
        this.optionsContainer = drawer.querySelector('[data-drawer-options]');
        this.searchInput = drawer.querySelector('[data-drawer-search]');
        this.emptyState = drawer.querySelector('[data-drawer-empty]');

        // Bind drawer events
        this.bindDrawerEvents();
    }

    /**
     * Get the drawer title
     */
    getDrawerTitle() {
        // Check data attribute first
        const dataTitle = this.select.dataset.drawerTitle;
        if (dataTitle) return dataTitle;

        // Check for associated label
        const labelId = this.select.id;
        if (labelId) {
            const label = document.querySelector(`label[for="${labelId}"]`);
            if (label) return label.textContent.trim();
        }

        // Default
        return 'Select option';
    }

    /**
     * Render options from the native select
     */
    renderOptions() {
        this.optionsContainer.innerHTML = '';
        this.optionButtons = [];
        this.focusedIndex = -1;

        const options = Array.from(this.select.options);
        let selectableIndex = 0;

        options.forEach((option, index) => {
            // Skip empty placeholder options
            if (option.value === '' && index === 0) return;

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'select-drawer-option';
            button.setAttribute('role', 'option');
            button.setAttribute('data-value', option.value);
            button.setAttribute('aria-selected', option.selected ? 'true' : 'false');
            button.textContent = option.text;

            if (option.disabled) {
                button.classList.add('is-disabled');
                button.setAttribute('aria-disabled', 'true');
            }

            if (option.selected && option.value !== '') {
                button.classList.add('is-selected');
                this.focusedIndex = selectableIndex;
            }

            // Click handler
            button.addEventListener('click', () => {
                if (!option.disabled) {
                    this.selectOption(option.value, option.text);
                }
            });

            this.optionsContainer.appendChild(button);
            this.optionButtons.push(button);
            selectableIndex++;
        });

        // Update search visibility based on actual option count
        const hasSearch = this.optionButtons.length > this.options.searchThreshold;
        this.drawerElement.classList.toggle('has-search', hasSearch);
    }

    /**
     * Bind drawer events
     */
    bindDrawerEvents() {
        // Close button
        const closeBtn = this.drawerElement.querySelector('[data-drawer-close]');
        closeBtn.addEventListener('click', () => this.close());

        // Search input
        if (this.searchInput) {
            this.searchInput.addEventListener('input', (e) => {
                this.filterOptions(e.target.value);
            });
        }

        // Swipe gesture on handle
        const handle = this.drawerElement.querySelector('[data-drawer-handle]');
        if (handle) {
            handle.addEventListener('touchstart', (e) => this.handleTouchStart(e), { passive: true });
            handle.addEventListener('touchmove', (e) => this.handleTouchMove(e), { passive: false });
            handle.addEventListener('touchend', () => this.handleTouchEnd());
        }
    }

    /**
     * Filter options based on search term
     */
    filterOptions(term) {
        const searchTerm = term.toLowerCase().trim();
        let visibleCount = 0;

        this.optionButtons.forEach((button) => {
            const text = button.textContent.toLowerCase();
            const matches = searchTerm === '' || text.includes(searchTerm);

            button.hidden = !matches;
            if (matches) visibleCount++;
        });

        // Show/hide empty state
        this.emptyState.classList.toggle('is-visible', visibleCount === 0);

        // Reset focus index
        this.focusedIndex = -1;
        this.optionButtons.forEach(btn => btn.classList.remove('is-focused'));
    }

    /**
     * Select an option
     */
    selectOption(value, text) {
        // Update native select
        this.select.value = value;

        // Dispatch change event
        const event = new Event('change', { bubbles: true });
        this.select.dispatchEvent(event);

        // Close drawer
        this.close();
    }

    /**
     * Handle keydown events
     */
    handleKeydown(e) {
        switch (e.key) {
            case 'Escape':
                e.preventDefault();
                this.close();
                break;

            case 'ArrowDown':
                e.preventDefault();
                this.focusNextOption();
                break;

            case 'ArrowUp':
                e.preventDefault();
                this.focusPreviousOption();
                break;

            case 'Enter':
            case ' ':
                if (document.activeElement === this.searchInput) {
                    // Don't prevent typing in search
                    if (e.key === ' ') return;
                }
                if (this.focusedIndex >= 0) {
                    e.preventDefault();
                    const button = this.getVisibleOptions()[this.focusedIndex];
                    if (button && !button.classList.contains('is-disabled')) {
                        button.click();
                    }
                }
                break;

            case 'Tab':
                // Trap focus within drawer
                e.preventDefault();
                if (this.searchInput && document.activeElement !== this.searchInput) {
                    this.searchInput.focus();
                } else {
                    const closeBtn = this.drawerElement.querySelector('[data-drawer-close]');
                    closeBtn.focus();
                }
                break;
        }
    }

    /**
     * Get visible (non-hidden) options
     */
    getVisibleOptions() {
        return this.optionButtons.filter(btn => !btn.hidden);
    }

    /**
     * Focus next option
     */
    focusNextOption() {
        const visible = this.getVisibleOptions();
        if (visible.length === 0) return;

        // Clear current focus
        visible.forEach(btn => btn.classList.remove('is-focused'));

        // Find next non-disabled option
        let nextIndex = this.focusedIndex + 1;
        while (nextIndex < visible.length && visible[nextIndex].classList.contains('is-disabled')) {
            nextIndex++;
        }

        if (nextIndex < visible.length) {
            this.focusedIndex = nextIndex;
            visible[nextIndex].classList.add('is-focused');
            visible[nextIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    /**
     * Focus previous option
     */
    focusPreviousOption() {
        const visible = this.getVisibleOptions();
        if (visible.length === 0) return;

        // Clear current focus
        visible.forEach(btn => btn.classList.remove('is-focused'));

        // Find previous non-disabled option
        let prevIndex = this.focusedIndex - 1;
        while (prevIndex >= 0 && visible[prevIndex].classList.contains('is-disabled')) {
            prevIndex--;
        }

        if (prevIndex >= 0) {
            this.focusedIndex = prevIndex;
            visible[prevIndex].classList.add('is-focused');
            visible[prevIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    /**
     * Set initial focus
     */
    setInitialFocus() {
        // If searchable, focus search input
        if (this.searchInput && this.drawerElement.classList.contains('has-search')) {
            this.searchInput.focus();
            return;
        }

        // Otherwise focus first visible option or selected option
        const visible = this.getVisibleOptions();
        if (visible.length > 0) {
            const selectedIndex = visible.findIndex(btn => btn.classList.contains('is-selected'));
            this.focusedIndex = selectedIndex >= 0 ? selectedIndex : 0;
            visible[this.focusedIndex].classList.add('is-focused');
            visible[this.focusedIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    /**
     * Lock body scroll
     */
    lockBodyScroll() {
        this.scrollY = window.scrollY;
        document.body.style.position = 'fixed';
        document.body.style.top = `-${this.scrollY}px`;
        document.body.style.left = '0';
        document.body.style.right = '0';
        document.body.style.overflow = 'hidden';
    }

    /**
     * Unlock body scroll
     */
    unlockBodyScroll() {
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.left = '';
        document.body.style.right = '';
        document.body.style.overflow = '';
        window.scrollTo(0, this.scrollY);
    }

    /**
     * Handle resize (close if viewport becomes desktop)
     */
    handleResize() {
        if (!SelectDrawer.shouldUseDrawer() && this.isOpen) {
            this.close();
        }
    }

    /**
     * Handle touch start for swipe gesture
     */
    handleTouchStart(e) {
        this.touchStartY = e.touches[0].clientY;
        this.touchCurrentY = this.touchStartY;
        this.isSwiping = true;
    }

    /**
     * Handle touch move for swipe gesture
     */
    handleTouchMove(e) {
        if (!this.isSwiping) return;

        this.touchCurrentY = e.touches[0].clientY;
        const delta = this.touchCurrentY - this.touchStartY;

        // Only allow swiping down
        if (delta > 0) {
            e.preventDefault();
            // Apply transform with resistance
            const resistance = 0.5;
            this.drawerElement.style.transform = `translateY(${delta * resistance}px)`;
        }
    }

    /**
     * Handle touch end for swipe gesture
     */
    handleTouchEnd() {
        if (!this.isSwiping) return;

        this.isSwiping = false;
        const delta = this.touchCurrentY - this.touchStartY;

        // Reset inline transform first
        this.drawerElement.style.transform = '';

        // If swiped down enough, close
        if (delta > 80) {
            this.close();
        }
    }

    /**
     * Destroy the drawer instance
     */
    destroy() {
        if (this.isOpen) {
            this.close();
        }
        this.drawerElement = null;
        this.customSelect = null;
    }
}

export default SelectDrawer;

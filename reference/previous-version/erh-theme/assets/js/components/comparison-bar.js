/**
 * Comparison Bar Module
 *
 * Shared module for managing the comparison bar across pages.
 * Used by: finder.js, deals-page.js
 */

const CONFIG = {
    maxCompare: 4,
    placeholderImage: '/wp-content/themes/erh-theme/assets/images/placeholder.svg',
};

/**
 * ComparisonBar class
 * Manages the selection state and UI updates for the comparison bar
 */
export class ComparisonBar {
    /**
     * @param {Object} options
     * @param {HTMLElement} options.container - Parent container (e.g., [data-finder-page])
     * @param {HTMLElement} options.grid - The product grid element
     * @param {Function} options.getProductById - Function to get product data by ID
     * @param {number} [options.maxCompare=4] - Maximum products to compare
     */
    constructor(options) {
        this.container = options.container;
        this.grid = options.grid;
        this.getProductById = options.getProductById;
        this.maxCompare = options.maxCompare || CONFIG.maxCompare;

        // Find bar elements
        this.bar = this.container.querySelector('[data-comparison-bar]');
        if (!this.bar) return;

        this.productsEl = this.bar.querySelector('[data-comparison-products]');
        this.countEl = this.bar.querySelector('[data-comparison-count]');
        this.clearBtn = this.bar.querySelector('[data-comparison-clear]');
        this.compareLink = this.bar.querySelector('[data-comparison-link]');

        // State
        this.selectedProducts = new Set();

        // Bind events
        this.bindEvents();
    }

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Clear button
        this.clearBtn?.addEventListener('click', () => {
            this.clear();
        });

        // Compare link - update href before navigation
        this.compareLink?.addEventListener('click', (e) => {
            if (this.selectedProducts.size < 2) {
                e.preventDefault();
                return;
            }
            const ids = Array.from(this.selectedProducts).join(',');
            const base = window.erhData?.siteUrl || '';
            this.compareLink.href = `${base}/compare/?products=${ids}`;
        });
    }

    /**
     * Handle checkbox change event
     * Call this from your grid's change event listener
     * @param {HTMLInputElement} checkbox
     * @returns {boolean} Whether the selection was accepted
     */
    handleCheckboxChange(checkbox) {
        const productId = checkbox.value;

        if (checkbox.checked) {
            if (this.selectedProducts.size >= this.maxCompare) {
                checkbox.checked = false;
                return false;
            }
            this.selectedProducts.add(productId);
        } else {
            this.selectedProducts.delete(productId);
        }

        this.update();
        return true;
    }

    /**
     * Toggle product selection by ID
     * @param {string|number} productId
     * @returns {boolean} Whether the product is now selected
     */
    toggleProduct(productId) {
        const id = String(productId);

        if (this.selectedProducts.has(id)) {
            this.selectedProducts.delete(id);
            return false;
        }

        if (this.selectedProducts.size >= this.maxCompare) {
            return false;
        }

        this.selectedProducts.add(id);
        return true;
    }

    /**
     * Check if product is selected
     * @param {string|number} productId
     * @returns {boolean}
     */
    isSelected(productId) {
        return this.selectedProducts.has(String(productId));
    }

    /**
     * Get count of selected products
     * @returns {number}
     */
    getCount() {
        return this.selectedProducts.size;
    }

    /**
     * Get selected product IDs
     * @returns {string[]}
     */
    getSelectedIds() {
        return Array.from(this.selectedProducts);
    }

    /**
     * Update the comparison bar UI
     */
    update() {
        if (!this.bar) return;

        const count = this.selectedProducts.size;

        // Update count
        if (this.countEl) {
            this.countEl.textContent = count;
        }

        // Show/hide bar with animation
        if (count > 0) {
            this.bar.classList.add('is-visible');
            this.bar.hidden = false;
        } else {
            this.bar.classList.remove('is-visible');
            // Don't set hidden immediately - let animation complete
            setTimeout(() => {
                if (this.selectedProducts.size === 0) {
                    this.bar.hidden = true;
                }
            }, 300);
        }

        // Update compare link state
        if (this.compareLink) {
            this.compareLink.classList.toggle('btn-disabled', count < 2);
        }

        // Update product thumbnails
        this.renderThumbnails();
    }

    /**
     * Render product thumbnails in the bar
     */
    renderThumbnails() {
        if (!this.productsEl) return;

        this.productsEl.innerHTML = '';

        this.selectedProducts.forEach(id => {
            const product = this.getProductById(id);
            if (!product) return;

            const thumb = document.createElement('div');
            thumb.className = 'comparison-bar-thumb-wrapper';
            thumb.innerHTML = `
                <img
                    src="${product.thumbnail || CONFIG.placeholderImage}"
                    alt="${product.name || ''}"
                    class="comparison-bar-thumb"
                >
                <button
                    type="button"
                    class="comparison-bar-remove"
                    data-remove-product="${id}"
                    aria-label="Remove ${product.name || 'product'}"
                >
                    <svg class="icon"><use href="#icon-x"></use></svg>
                </button>
            `;
            this.productsEl.appendChild(thumb);
        });

        // Bind remove buttons
        this.productsEl.querySelectorAll('[data-remove-product]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.removeProduct;
                this.removeProduct(id);
            });
        });
    }

    /**
     * Remove a product from selection
     * @param {string|number} productId
     */
    removeProduct(productId) {
        const id = String(productId);
        this.selectedProducts.delete(id);

        // Uncheck the corresponding checkbox in the grid
        const checkbox = this.grid?.querySelector(`[data-compare-select][value="${id}"]`);
        if (checkbox) {
            checkbox.checked = false;
        }

        this.update();
    }

    /**
     * Clear all selections
     */
    clear() {
        this.selectedProducts.clear();

        // Uncheck all checkboxes in the grid
        this.grid?.querySelectorAll('[data-compare-select]').forEach(checkbox => {
            checkbox.checked = false;
        });

        this.update();
    }

    /**
     * Sync checkbox states with current selection
     * Call after re-rendering the grid
     */
    syncCheckboxes() {
        this.grid?.querySelectorAll('[data-compare-select]').forEach(checkbox => {
            checkbox.checked = this.selectedProducts.has(checkbox.value);
        });
    }
}

export default ComparisonBar;

/**
 * ERideHero Price Alert Modal
 *
 * Allows users to create/edit/delete price alerts for products.
 * Requires authentication - opens auth modal if not logged in.
 *
 * Usage:
 *   import { PriceAlertModal } from './components/price-alert.js';
 *
 *   // Open for a product (checks auth, fetches existing tracker)
 *   PriceAlertModal.open({
 *       productId: 123,
 *       productName: 'Segway Ninebot Max G2',
 *       productImage: 'https://...',
 *       currentPrice: 749
 *   });
 */

import { Modal } from './modal.js';
import { Toast } from './toast.js';
import { AuthModal } from './auth-modal.js';
import { getUserGeo, formatPrice, getCurrencySymbol } from '../services/geo-price.js';

const REST_URL = window.erhData?.restUrl || '/wp-json/erh/v1/';

class PriceAlertModalManager {
    constructor() {
        this.modal = null;
        this.productData = null;
        this.existingTracker = null;
        this.isSubmitting = false;
        this.isDeleting = false;
        this.alertType = 'target'; // 'target' or 'drop'
        this.userGeo = null; // { geo, currency, symbol }
    }

    /**
     * Open the price alert modal
     * @param {Object} options
     * @param {number} options.productId - Product ID
     * @param {string} options.productName - Product name for display
     * @param {string} options.productImage - Product thumbnail URL
     * @param {number} options.currentPrice - Current price
     * @param {string} [options.currency] - Currency code (defaults to user's region currency)
     */
    async open(options = {}) {
        const { productId, productName, productImage, currentPrice, currency } = options;

        if (!productId) {
            console.error('[PriceAlert] Product ID is required');
            return;
        }

        // Check if user is logged in
        if (!window.erhData?.isLoggedIn) {
            // Store intent and open auth modal
            AuthModal.openForAction('price-alert', { productId, productName, productImage, currentPrice, currency });
            return;
        }

        // Get user's geo (this is fast - uses cached value from localStorage)
        this.userGeo = await getUserGeo();
        const displayCurrency = currency || this.userGeo.currency;

        this.productData = {
            productId,
            productName,
            productImage,
            currentPrice,
            currency: displayCurrency,
            symbol: getCurrencySymbol(displayCurrency)
        };

        // Create modal if needed
        if (!this.modal) {
            this.createModal();
        }

        // Show loading state
        this.renderLoading();
        this.modal.open();

        // Fetch existing tracker
        try {
            const response = await fetch(`${REST_URL}products/${productId}/tracker`, {
                headers: {
                    'X-WP-Nonce': window.erhData?.nonce || ''
                }
            });

            if (response.ok) {
                const result = await response.json();
                this.existingTracker = result.tracker || null;
            } else if (response.status === 404) {
                this.existingTracker = null;
            }
        } catch (error) {
            console.error('[PriceAlert] Failed to fetch tracker:', error);
            this.existingTracker = null;
        }

        // Render the form
        this.renderForm();
    }

    /**
     * Close the modal
     */
    close() {
        if (this.modal) {
            this.modal.close();
        }
    }

    /**
     * Create the modal element
     */
    createModal() {
        const modalEl = document.createElement('div');
        modalEl.className = 'modal price-alert-modal';
        modalEl.id = 'price-alert-modal';
        modalEl.setAttribute('role', 'dialog');
        modalEl.setAttribute('aria-modal', 'true');
        modalEl.innerHTML = `
            <div class="modal-content modal-content--sm">
                <button class="modal-close" data-modal-close aria-label="Close">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
                <div class="price-alert-modal-body"></div>
            </div>
        `;

        document.body.appendChild(modalEl);
        this.modal = new Modal(modalEl);
        this.bodyEl = modalEl.querySelector('.price-alert-modal-body');

        // Event delegation
        modalEl.addEventListener('click', (e) => {
            // Toggle buttons
            const toggleBtn = e.target.closest('[data-alert-type]');
            if (toggleBtn) {
                this.setAlertType(toggleBtn.dataset.alertType);
            }

            // Suggestion buttons
            const suggestion = e.target.closest('[data-suggestion]');
            if (suggestion) {
                this.applySuggestion(suggestion.dataset.suggestion);
            }

            // Delete button
            const deleteBtn = e.target.closest('[data-delete-tracker]');
            if (deleteBtn) {
                this.confirmDelete();
            }

            // Confirm delete
            const confirmDeleteBtn = e.target.closest('[data-confirm-delete]');
            if (confirmDeleteBtn) {
                this.deleteTracker();
            }

            // Cancel delete
            const cancelDeleteBtn = e.target.closest('[data-cancel-delete]');
            if (cancelDeleteBtn) {
                this.renderForm();
            }
        });

        // Form submission
        modalEl.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleSubmit(e.target);
        });
    }

    /**
     * Render loading state
     */
    renderLoading() {
        this.bodyEl.innerHTML = `
            <div class="price-alert-loading">
                <svg class="spinner" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4" stroke-linecap="round"/>
                </svg>
            </div>
        `;
    }

    /**
     * Render the form (create or edit mode)
     */
    renderForm() {
        const isEdit = !!this.existingTracker;
        const { productName, productImage, currentPrice, symbol } = this.productData;
        const userGeo = this.userGeo?.geo || 'US';

        // For editing, use the tracker's stored currency; for new, use user's current
        let displayCurrency, displaySymbol, displayPrice;
        let geoMismatchNotice = '';

        if (isEdit) {
            const trackerGeo = this.existingTracker.geo || 'US';
            const trackerCurrency = this.existingTracker.currency || 'USD';
            displaySymbol = getCurrencySymbol(trackerCurrency);

            // Use live_price only if its currency matches the tracker's currency
            // (avoids showing wrong price when API returns different geo's price)
            const livePriceValid = this.existingTracker.live_price &&
                (!this.existingTracker.live_currency || this.existingTracker.live_currency === trackerCurrency);

            const rawPrice = livePriceValid
                ? this.existingTracker.live_price
                : (this.existingTracker.current_price || this.existingTracker.start_price);
            displayPrice = rawPrice ? parseFloat(rawPrice) : null;

            // Check for geo mismatch
            if (trackerGeo !== userGeo) {
                const regionNames = { US: 'US', GB: 'UK', EU: 'EU', CA: 'Canada', AU: 'Australia' };
                const trackerRegionName = regionNames[trackerGeo] || trackerGeo;
                geoMismatchNotice = `
                    <div class="price-alert-geo-notice">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <span>You're tracking <strong>${trackerRegionName} prices</strong> for this product. To track prices in your current region, delete this alert and create a new one.</span>
                    </div>
                `;
            }
        } else {
            displaySymbol = symbol || '$';
            displayPrice = currentPrice;
        }

        const currencySymbol = displaySymbol;

        // Determine initial values
        let initialTarget = '';
        let initialDrop = '';

        if (isEdit) {
            if (this.existingTracker.target_price) {
                this.alertType = 'target';
                initialTarget = this.existingTracker.target_price;
            } else if (this.existingTracker.price_drop) {
                this.alertType = 'drop';
                initialDrop = this.existingTracker.price_drop;
            }
        } else {
            this.alertType = 'target';
            // Default suggestion: 50 below current price
            initialTarget = displayPrice ? Math.max(0, displayPrice - 50).toFixed(0) : '';
        }

        // Calculate price change if editing
        let priceChange = '';
        if (isEdit && this.existingTracker.start_price && displayPrice) {
            const diff = this.existingTracker.start_price - displayPrice;
            if (diff !== 0) {
                const sign = diff > 0 ? '↓' : '↑';
                priceChange = `${sign} ${currencySymbol}${Math.abs(diff).toFixed(0)} since you started tracking`;
            }
        }

        this.bodyEl.innerHTML = `
            <div class="price-alert-header">
                <h2 class="price-alert-title">${isEdit ? 'Edit price alert' : 'Set price alert'}</h2>
            </div>

            ${geoMismatchNotice}

            <div class="price-alert-product">
                ${productImage ? `<img src="${productImage}" alt="" class="price-alert-product-img">` : ''}
                <div class="price-alert-product-info">
                    <span class="price-alert-product-name">${this.escapeHtml(productName || 'Product')}</span>
                    <span class="price-alert-product-price">
                        Current price: ${currencySymbol}${this.formatPriceValue(displayPrice)}
                        ${priceChange ? `<span class="price-alert-price-change">${priceChange}</span>` : ''}
                    </span>
                </div>
            </div>

            <form class="price-alert-form">
                <div class="price-alert-type-label">Alert me when price:</div>

                <div class="price-alert-toggle">
                    <button type="button" class="price-alert-toggle-btn ${this.alertType === 'target' ? 'is-active' : ''}" data-alert-type="target">
                        Reaches target
                    </button>
                    <button type="button" class="price-alert-toggle-btn ${this.alertType === 'drop' ? 'is-active' : ''}" data-alert-type="drop">
                        Drops by amount
                    </button>
                </div>

                <div class="price-alert-input-group" data-input-group="target" ${this.alertType !== 'target' ? 'hidden' : ''}>
                    <label class="price-alert-label">Target price</label>
                    <div class="price-alert-input-wrapper">
                        <span class="price-alert-input-prefix">${currencySymbol}</span>
                        <input type="number" name="target_price" class="price-alert-input" placeholder="0" min="1" step="1" value="${initialTarget}">
                    </div>
                    <div class="price-alert-suggestions">
                        ${currentPrice ? `
                            <button type="button" class="price-alert-suggestion" data-suggestion="${Math.max(0, currentPrice - 50)}">-${currencySymbol}50</button>
                            <button type="button" class="price-alert-suggestion" data-suggestion="${Math.max(0, currentPrice - 100)}">-${currencySymbol}100</button>
                            <button type="button" class="price-alert-suggestion" data-suggestion="${Math.max(0, currentPrice - 150)}">-${currencySymbol}150</button>
                        ` : ''}
                    </div>
                </div>

                <div class="price-alert-input-group" data-input-group="drop" ${this.alertType !== 'drop' ? 'hidden' : ''}>
                    <label class="price-alert-label">Drop amount</label>
                    <div class="price-alert-input-wrapper">
                        <span class="price-alert-input-prefix">${currencySymbol}</span>
                        <input type="number" name="price_drop" class="price-alert-input" placeholder="0" min="1" step="1" value="${initialDrop}">
                    </div>
                    <div class="price-alert-suggestions">
                        <button type="button" class="price-alert-suggestion" data-suggestion="50">${currencySymbol}50</button>
                        <button type="button" class="price-alert-suggestion" data-suggestion="100">${currencySymbol}100</button>
                        <button type="button" class="price-alert-suggestion" data-suggestion="150">${currencySymbol}150</button>
                    </div>
                </div>

                <div class="price-alert-error" data-error hidden></div>

                <button type="submit" class="btn btn-primary btn-block price-alert-submit">
                    <span class="price-alert-submit-text">${isEdit ? 'Update alert' : 'Create alert'}</span>
                    <span class="price-alert-submit-loading" hidden>
                        <svg class="spinner" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4" stroke-linecap="round"/></svg>
                    </span>
                </button>

                ${isEdit ? `
                    <button type="button" class="btn btn-error btn-block" data-delete-tracker>
                        Delete this alert
                    </button>
                ` : ''}
            </form>
        `;
    }

    /**
     * Render delete confirmation
     */
    renderDeleteConfirmation() {
        const { productName } = this.productData;

        this.bodyEl.innerHTML = `
            <div class="price-alert-confirm-delete">
                <div class="price-alert-confirm-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 6h18"></path>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                </div>
                <h3 class="price-alert-confirm-title">Delete price alert?</h3>
                <p class="price-alert-confirm-text">
                    You'll no longer receive notifications when the price of <strong>${this.escapeHtml(productName || 'this product')}</strong> drops.
                </p>
                <div class="price-alert-confirm-actions">
                    <button type="button" class="btn btn-secondary" data-cancel-delete>Cancel</button>
                    <button type="button" class="btn btn-error" data-confirm-delete>
                        <span class="price-alert-delete-text">Delete</span>
                        <span class="price-alert-delete-loading" hidden>
                            <svg class="spinner" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4" stroke-linecap="round"/></svg>
                        </span>
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Set the alert type (target or drop)
     */
    setAlertType(type) {
        this.alertType = type;

        // Update toggle buttons
        this.bodyEl.querySelectorAll('.price-alert-toggle-btn').forEach(btn => {
            btn.classList.toggle('is-active', btn.dataset.alertType === type);
        });

        // Show/hide input groups
        this.bodyEl.querySelectorAll('[data-input-group]').forEach(group => {
            group.hidden = group.dataset.inputGroup !== type;
        });

        // Focus the visible input
        const visibleInput = this.bodyEl.querySelector(`[data-input-group="${type}"] input`);
        if (visibleInput) visibleInput.focus();
    }

    /**
     * Apply a suggestion value
     */
    applySuggestion(value) {
        const group = this.bodyEl.querySelector(`[data-input-group="${this.alertType}"]`);
        const input = group?.querySelector('input');
        if (input) {
            input.value = value;
            input.focus();
        }
    }

    /**
     * Handle form submission
     */
    async handleSubmit(form) {
        if (this.isSubmitting) return;

        const formData = new FormData(form);
        const submitBtn = form.querySelector('.price-alert-submit');
        const textEl = form.querySelector('.price-alert-submit-text');
        const loadingEl = form.querySelector('.price-alert-submit-loading');
        const errorEl = form.querySelector('[data-error]');

        // Get value based on alert type
        const rawValue = this.alertType === 'target'
            ? formData.get('target_price')
            : formData.get('price_drop');

        // Validate input: must be a positive number
        const value = parseFloat(rawValue);
        if (!rawValue || rawValue.trim() === '' || isNaN(value) || value <= 0) {
            errorEl.textContent = 'Please enter a valid amount greater than zero.';
            errorEl.hidden = false;
            return;
        }

        // For target price: must be lower than current price
        const currentPrice = this.productData.currentPrice;
        if (this.alertType === 'target' && currentPrice && value >= currentPrice) {
            errorEl.textContent = `Target price must be lower than ${this.productData.symbol}${this.formatPriceValue(currentPrice)}.`;
            errorEl.hidden = false;
            return;
        }

        // For price drop: must be lower than current price (can't drop more than 100%)
        if (this.alertType === 'drop' && currentPrice && value >= currentPrice) {
            errorEl.textContent = `Drop amount must be less than the current price.`;
            errorEl.hidden = false;
            return;
        }

        // Show loading
        this.isSubmitting = true;
        submitBtn.disabled = true;
        textEl.style.visibility = 'hidden';
        loadingEl.hidden = false;
        errorEl.hidden = true;

        try {
            // Map our alert type to API tracker_type
            const trackerType = this.alertType === 'target' ? 'target_price' : 'price_drop';

            const payload = {
                product_id: this.productData.productId,
                tracker_type: trackerType,
                geo: this.userGeo?.geo || 'US',
                currency: this.productData.currency || this.userGeo?.currency || 'USD',
                ...(this.alertType === 'target'
                    ? { target_price: value }
                    : { price_drop: value }
                )
            };

            const isEdit = !!this.existingTracker;
            const endpoint = isEdit
                ? `user/trackers/${this.existingTracker.id}`
                : 'user/trackers';

            const response = await fetch(`${REST_URL}${endpoint}`, {
                method: isEdit ? 'PUT' : 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.erhData?.nonce || ''
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Failed to save alert');
            }

            // Success
            this.close();
            Toast.success(isEdit ? 'Price alert updated' : 'Price alert created');

            // Dispatch event for other components
            window.dispatchEvent(new CustomEvent('priceAlert:saved', {
                detail: { productId: this.productData.productId, tracker: result.tracker }
            }));

        } catch (error) {
            errorEl.textContent = error.message;
            errorEl.hidden = false;
        } finally {
            this.isSubmitting = false;
            submitBtn.disabled = false;
            textEl.style.visibility = '';
            loadingEl.hidden = true;
        }
    }

    /**
     * Show delete confirmation
     */
    confirmDelete() {
        this.renderDeleteConfirmation();
    }

    /**
     * Delete the tracker
     */
    async deleteTracker() {
        if (this.isDeleting || !this.existingTracker) return;

        const deleteBtn = this.bodyEl.querySelector('[data-confirm-delete]');
        const textEl = deleteBtn?.querySelector('.price-alert-delete-text');
        const loadingEl = deleteBtn?.querySelector('.price-alert-delete-loading');

        this.isDeleting = true;
        if (deleteBtn) deleteBtn.disabled = true;
        if (textEl) textEl.style.visibility = 'hidden';
        if (loadingEl) loadingEl.hidden = false;

        try {
            const response = await fetch(`${REST_URL}user/trackers/${this.existingTracker.id}`, {
                method: 'DELETE',
                headers: {
                    'X-WP-Nonce': window.erhData?.nonce || ''
                }
            });

            if (!response.ok) {
                const result = await response.json();
                throw new Error(result.message || 'Failed to delete alert');
            }

            // Success
            this.close();
            Toast.success('Price alert deleted');

            // Dispatch event
            window.dispatchEvent(new CustomEvent('priceAlert:deleted', {
                detail: { productId: this.productData.productId }
            }));

        } catch (error) {
            Toast.error(error.message);
            this.renderForm(); // Go back to form
        } finally {
            this.isDeleting = false;
        }
    }

    /**
     * Format a price value (number only, no symbol)
     * Shows cents only when they exist (e.g., 999.99), otherwise whole number (e.g., 500)
     */
    formatPriceValue(price) {
        if (price == null) return '--';
        const hasCents = (price % 1) >= 0.005;
        if (hasCents) {
            return price.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        return Math.floor(price).toLocaleString();
    }

    /**
     * Escape HTML
     */
    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

// Singleton instance
const PriceAlertModal = new PriceAlertModalManager();

/**
 * Check for pending action on page load (from auth flow)
 */
function checkPendingAction() {
    if (!window.erhData?.isLoggedIn) return;

    const pending = AuthModal.checkPendingAction();
    if (pending?.action === 'price-alert') {
        // Small delay to ensure page is ready
        setTimeout(() => {
            PriceAlertModal.open(pending.data);
        }, 500);
    }
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', checkPendingAction);
} else {
    checkPendingAction();
}

export { PriceAlertModal };

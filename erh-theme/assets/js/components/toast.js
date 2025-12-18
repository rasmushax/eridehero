/**
 * ERideHero Toast Notification System
 *
 * Lightweight toast notifications for user feedback.
 *
 * Usage:
 *   import { Toast } from './components/toast.js';
 *
 *   Toast.success('Price alert created!');
 *   Toast.error('Something went wrong');
 *   Toast.info('Price updated');
 *   Toast.warning('Low stock');
 *
 *   // With options
 *   Toast.show({
 *       message: 'Custom message',
 *       type: 'success',
 *       duration: 5000,
 *       action: { label: 'Undo', onClick: () => {} }
 *   });
 */

const TOAST_DURATION = 4000;
const TOAST_GAP = 12;

class ToastManager {
    constructor() {
        this.container = null;
        this.toasts = [];
        this.counter = 0;
    }

    /**
     * Get or create the toast container
     */
    getContainer() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            this.container.setAttribute('aria-live', 'polite');
            this.container.setAttribute('aria-label', 'Notifications');
            document.body.appendChild(this.container);
        }
        return this.container;
    }

    /**
     * Show a toast notification
     * @param {Object} options Toast options
     * @param {string} options.message - The message to display
     * @param {string} options.type - Type: 'success', 'error', 'warning', 'info'
     * @param {number} options.duration - Duration in ms (0 for persistent)
     * @param {Object} options.action - Optional action button { label, onClick }
     * @returns {Object} Toast instance with dismiss() method
     */
    show(options = {}) {
        const {
            message = '',
            type = 'info',
            duration = TOAST_DURATION,
            action = null
        } = options;

        const container = this.getContainer();
        const id = ++this.counter;

        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast toast--${type}`;
        toast.setAttribute('role', 'status');
        toast.dataset.toastId = id;

        // Icon based on type
        const icons = {
            success: `<svg class="toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>`,
            error: `<svg class="toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>`,
            warning: `<svg class="toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>`,
            info: `<svg class="toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>`
        };

        // Build toast HTML
        toast.innerHTML = `
            ${icons[type] || icons.info}
            <span class="toast-message">${this.escapeHtml(message)}</span>
            ${action ? `<button type="button" class="toast-action">${this.escapeHtml(action.label)}</button>` : ''}
            <button type="button" class="toast-close" aria-label="Dismiss">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        `;

        // Add to container
        container.appendChild(toast);

        // Track toast
        const toastInstance = {
            id,
            element: toast,
            dismiss: () => this.dismiss(id)
        };
        this.toasts.push(toastInstance);

        // Trigger enter animation
        requestAnimationFrame(() => {
            toast.classList.add('is-visible');
        });

        // Bind close button
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => this.dismiss(id));

        // Bind action button if present
        if (action && action.onClick) {
            const actionBtn = toast.querySelector('.toast-action');
            actionBtn.addEventListener('click', () => {
                action.onClick();
                this.dismiss(id);
            });
        }

        // Auto-dismiss after duration
        if (duration > 0) {
            setTimeout(() => this.dismiss(id), duration);
        }

        return toastInstance;
    }

    /**
     * Dismiss a toast by ID
     */
    dismiss(id) {
        const index = this.toasts.findIndex(t => t.id === id);
        if (index === -1) return;

        const toast = this.toasts[index];
        toast.element.classList.remove('is-visible');
        toast.element.classList.add('is-leaving');

        // Remove after animation
        setTimeout(() => {
            toast.element.remove();
            this.toasts.splice(index, 1);

            // Remove container if empty
            if (this.toasts.length === 0 && this.container) {
                this.container.remove();
                this.container = null;
            }
        }, 200);
    }

    /**
     * Dismiss all toasts
     */
    dismissAll() {
        [...this.toasts].forEach(toast => this.dismiss(toast.id));
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Convenience methods
    success(message, options = {}) {
        return this.show({ message, type: 'success', ...options });
    }

    error(message, options = {}) {
        return this.show({ message, type: 'error', ...options });
    }

    warning(message, options = {}) {
        return this.show({ message, type: 'warning', ...options });
    }

    info(message, options = {}) {
        return this.show({ message, type: 'info', ...options });
    }
}

// Singleton instance
const Toast = new ToastManager();

export { Toast };

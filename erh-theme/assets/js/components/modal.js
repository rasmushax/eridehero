/**
 * ERideHero Modal System
 *
 * A production-ready, accessible modal component.
 * Supports both declarative (data attributes) and programmatic usage.
 *
 * Usage (Declarative):
 *   <button data-modal-trigger="my-modal">Open Modal</button>
 *   <div class="modal" id="my-modal" data-modal>
 *     <div class="modal-content">...</div>
 *   </div>
 *
 * Usage (Programmatic):
 *   import { Modal } from './components/modal.js';
 *
 *   const modal = Modal.open({
 *     id: 'my-modal',
 *     title: 'Modal Title',
 *     content: '<p>Modal content</p>',
 *     size: 'md',
 *     onClose: () => console.log('closed')
 *   });
 *
 *   modal.close();
 */

const FOCUSABLE_SELECTORS = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])'
].join(', ');

const TRANSITION_DURATION = 200;

// Track open modals for stacking
const openModals = [];

// Store scroll position for body lock
let scrollPosition = 0;

/**
 * Lock body scroll
 */
function lockBodyScroll() {
    if (openModals.length === 1) {
        scrollPosition = window.scrollY;
        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.top = `-${scrollPosition}px`;
        document.body.style.width = '100%';
    }
}

/**
 * Unlock body scroll
 */
function unlockBodyScroll() {
    if (openModals.length === 0) {
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.width = '';
        window.scrollTo(0, scrollPosition);
    }
}

/**
 * Modal Class
 */
class Modal {
    static instances = new Map();
    static backdrop = null;

    /**
     * Create or get a modal instance
     * @param {HTMLElement|string} element - Modal element or selector
     * @param {Object} options - Configuration options
     */
    constructor(element, options = {}) {
        // Handle string selector
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }

        if (!element) {
            throw new Error('Modal: Element not found');
        }

        // Return existing instance if available
        if (Modal.instances.has(element)) {
            return Modal.instances.get(element);
        }

        this.element = element;
        this.id = element.id;
        this.isOpen = false;
        this.triggerElement = null;
        this.previouslyFocused = null;

        // Merge options with defaults
        this.options = {
            closeOnBackdrop: true,
            closeOnEscape: true,
            trapFocus: true,
            autoFocus: true,
            returnFocus: true,
            beforeOpen: null,
            afterOpen: null,
            beforeClose: null,
            afterClose: null,
            ...options
        };

        this._init();
        Modal.instances.set(element, this);
    }

    /**
     * Initialize the modal
     */
    _init() {
        // Set ARIA attributes
        this.element.setAttribute('role', 'dialog');
        this.element.setAttribute('aria-modal', 'true');

        // Find or set aria-labelledby
        const title = this.element.querySelector('.modal-title');
        if (title) {
            if (!title.id) {
                title.id = `${this.id}-title`;
            }
            this.element.setAttribute('aria-labelledby', title.id);
        }

        // Bound close handler (for proper removal)
        this._boundClose = () => this.close();

        // Find close buttons within modal
        this.closeButtons = this.element.querySelectorAll('[data-modal-close]');
        this.closeButtons.forEach(btn => {
            btn.addEventListener('click', this._boundClose);
        });

        // Cache content wrapper
        this.content = this.element.querySelector('.modal-content');

        // Cache for focusable elements (refreshed on open)
        this._focusableElements = null;
    }

    /**
     * Refresh cached focusable elements
     */
    _refreshFocusableCache() {
        this._focusableElements = this.element.querySelectorAll(FOCUSABLE_SELECTORS);
    }

    /**
     * Create the shared backdrop element
     */
    static _createBackdrop() {
        if (Modal.backdrop) return Modal.backdrop;

        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop';
        backdrop.setAttribute('aria-hidden', 'true');
        document.body.appendChild(backdrop);
        Modal.backdrop = backdrop;

        return backdrop;
    }

    /**
     * Show the backdrop
     */
    static _showBackdrop() {
        const backdrop = Modal._createBackdrop();
        // Force reflow for animation
        backdrop.offsetHeight;
        backdrop.classList.add('is-visible');
    }

    /**
     * Hide the backdrop
     */
    static _hideBackdrop() {
        if (Modal.backdrop && openModals.length === 0) {
            Modal.backdrop.classList.remove('is-visible');
        }
    }

    /**
     * Open the modal
     * @param {HTMLElement} triggerElement - Element that triggered the open
     */
    open(triggerElement = null) {
        if (this.isOpen) return;

        // Store trigger for focus return
        this.triggerElement = triggerElement;
        this.previouslyFocused = document.activeElement;

        // Before open callback
        if (this.options.beforeOpen) {
            const result = this.options.beforeOpen(this);
            if (result === false) return;
        }

        // Dispatch custom event
        const beforeEvent = new CustomEvent('modal:beforeOpen', {
            bubbles: true,
            cancelable: true,
            detail: { modal: this }
        });
        if (!this.element.dispatchEvent(beforeEvent)) return;

        // Show backdrop
        Modal._showBackdrop();

        // Add to stack
        openModals.push(this);
        this.isOpen = true;

        // Lock body scroll
        lockBodyScroll();

        // Show modal
        this.element.classList.add('is-visible');
        this.element.setAttribute('aria-hidden', 'false');

        // Set z-index based on stack position
        this.element.style.zIndex = 1000 + openModals.length;

        // Refresh focusable elements cache
        this._refreshFocusableCache();

        // Focus management
        if (this.options.autoFocus) {
            setTimeout(() => this._setInitialFocus(), TRANSITION_DURATION);
        }

        // Setup event listeners
        this._bindEvents();

        // After open callback
        setTimeout(() => {
            if (this.options.afterOpen) {
                this.options.afterOpen(this);
            }
            this.element.dispatchEvent(new CustomEvent('modal:afterOpen', {
                bubbles: true,
                detail: { modal: this }
            }));
        }, TRANSITION_DURATION);
    }

    /**
     * Close the modal
     */
    close() {
        if (!this.isOpen) return;

        // Before close callback
        if (this.options.beforeClose) {
            const result = this.options.beforeClose(this);
            if (result === false) return;
        }

        // Dispatch custom event
        const beforeEvent = new CustomEvent('modal:beforeClose', {
            bubbles: true,
            cancelable: true,
            detail: { modal: this }
        });
        if (!this.element.dispatchEvent(beforeEvent)) return;

        // Remove from stack
        const index = openModals.indexOf(this);
        if (index > -1) {
            openModals.splice(index, 1);
        }
        this.isOpen = false;

        // Hide modal
        this.element.classList.remove('is-visible');
        this.element.setAttribute('aria-hidden', 'true');

        // Unbind events
        this._unbindEvents();

        // Hide backdrop if no more modals
        Modal._hideBackdrop();

        // Unlock body scroll
        unlockBodyScroll();

        // Return focus
        if (this.options.returnFocus && this.previouslyFocused) {
            setTimeout(() => {
                this.previouslyFocused.focus();
            }, TRANSITION_DURATION);
        }

        // After close callback
        setTimeout(() => {
            if (this.options.afterClose) {
                this.options.afterClose(this);
            }
            this.element.dispatchEvent(new CustomEvent('modal:afterClose', {
                bubbles: true,
                detail: { modal: this }
            }));
        }, TRANSITION_DURATION);
    }

    /**
     * Toggle the modal
     */
    toggle(triggerElement = null) {
        if (this.isOpen) {
            this.close();
        } else {
            this.open(triggerElement);
        }
    }

    /**
     * Set initial focus within modal
     */
    _setInitialFocus() {
        // First try to focus an element with autofocus
        const autofocusEl = this.element.querySelector('[autofocus]');
        if (autofocusEl) {
            autofocusEl.focus();
            return;
        }

        // Then try to focus the first focusable element (use cache)
        if (this._focusableElements?.length) {
            this._focusableElements[0].focus();
            return;
        }

        // Fallback to the modal content itself
        if (this.content) {
            this.content.setAttribute('tabindex', '-1');
            this.content.focus();
        }
    }

    /**
     * Trap focus within modal (uses cached elements)
     */
    _handleFocusTrap(e) {
        if (!this.options.trapFocus) return;

        const focusable = this._focusableElements;
        if (!focusable?.length) return;

        const firstFocusable = focusable[0];
        const lastFocusable = focusable[focusable.length - 1];

        if (e.shiftKey && document.activeElement === firstFocusable) {
            e.preventDefault();
            lastFocusable.focus();
        } else if (!e.shiftKey && document.activeElement === lastFocusable) {
            e.preventDefault();
            firstFocusable.focus();
        }
    }

    /**
     * Handle keydown events
     */
    _handleKeydown = (e) => {
        // Only handle events for the topmost modal
        if (openModals[openModals.length - 1] !== this) return;

        if (e.key === 'Escape' && this.options.closeOnEscape) {
            e.preventDefault();
            this.close();
        }

        if (e.key === 'Tab') {
            this._handleFocusTrap(e);
        }
    };

    /**
     * Track mousedown origin to prevent closing when dragging from content to backdrop
     */
    _handleMousedown = (e) => {
        this._mousedownTarget = e.target;
    };

    /**
     * Handle backdrop click - only close if both mousedown and mouseup on backdrop
     */
    _handleBackdropClick = (e) => {
        if (!this.options.closeOnBackdrop) return;

        // Only close if BOTH mousedown and click happened on the modal backdrop
        // This prevents closing when user drags from content to backdrop (e.g., selecting text)
        if (e.target === this.element && this._mousedownTarget === this.element) {
            this.close();
        }
    };

    /**
     * Bind event listeners
     */
    _bindEvents() {
        document.addEventListener('keydown', this._handleKeydown);
        this.element.addEventListener('mousedown', this._handleMousedown);
        this.element.addEventListener('click', this._handleBackdropClick);
    }

    /**
     * Unbind event listeners
     */
    _unbindEvents() {
        document.removeEventListener('keydown', this._handleKeydown);
        this.element.removeEventListener('mousedown', this._handleMousedown);
        this.element.removeEventListener('click', this._handleBackdropClick);
    }

    /**
     * Destroy the modal instance
     * @param {boolean} removeElement - Whether to remove the DOM element (for dynamic modals)
     */
    destroy(removeElement = false) {
        if (this.isOpen) {
            this.close();
        }

        // Properly remove close button listeners using stored reference
        this.closeButtons.forEach(btn => {
            btn.removeEventListener('click', this._boundClose);
        });

        // Remove from instances map
        Modal.instances.delete(this.element);

        // Optionally remove from DOM (for dynamically created modals)
        if (removeElement && this.element.parentNode) {
            this.element.parentNode.removeChild(this.element);
        }

        // Clear references
        this._focusableElements = null;
        this._boundClose = null;
    }

    /**
     * Static method to open a modal by ID
     * @param {string} id - Modal ID
     * @param {HTMLElement} triggerElement - Trigger element
     */
    static openById(id, triggerElement = null) {
        const element = document.getElementById(id);
        if (!element) {
            console.warn(`Modal: Element with id "${id}" not found`);
            return null;
        }

        const modal = new Modal(element);
        modal.open(triggerElement);
        return modal;
    }

    /**
     * Static method to close all open modals
     */
    static closeAll() {
        [...openModals].reverse().forEach(modal => modal.close());
    }

    /**
     * Static method to create and open a modal programmatically
     * @param {Object} config - Modal configuration
     */
    static create(config = {}) {
        const {
            id = `modal-${Date.now()}`,
            title = '',
            content = '',
            size = 'md',
            closable = true,
            footerContent = '',
            className = '',
            ...options
        } = config;

        // Check if modal already exists
        let element = document.getElementById(id);

        if (!element) {
            // Create modal HTML
            const modalHTML = `
                <div class="modal ${className}" id="${id}" data-modal aria-hidden="true">
                    <div class="modal-content modal-content--${size}">
                        ${closable ? `
                            <button class="modal-close" data-modal-close aria-label="Close modal">
                                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        ` : ''}
                        ${title ? `<h2 class="modal-title">${title}</h2>` : ''}
                        <div class="modal-body">${content}</div>
                        ${footerContent ? `<div class="modal-footer">${footerContent}</div>` : ''}
                    </div>
                </div>
            `;

            // Insert into DOM
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            element = document.getElementById(id);
        }

        const modal = new Modal(element, options);
        modal.open();

        return modal;
    }

    /**
     * Update modal content dynamically
     * @param {Object} updates - Content updates
     */
    updateContent({ title, body, footer }) {
        if (title !== undefined) {
            const titleEl = this.element.querySelector('.modal-title');
            if (titleEl) titleEl.innerHTML = title;
        }

        if (body !== undefined) {
            const bodyEl = this.element.querySelector('.modal-body');
            if (bodyEl) bodyEl.innerHTML = body;
        }

        if (footer !== undefined) {
            const footerEl = this.element.querySelector('.modal-footer');
            if (footerEl) footerEl.innerHTML = footer;
        }
    }
}

/**
 * Initialize declarative modals
 * Call this on DOMContentLoaded or after dynamic content loads
 */
function initModals() {
    // Set up triggers (skip already initialized)
    document.querySelectorAll('[data-modal-trigger]:not([data-modal-initialized])').forEach(trigger => {
        const modalId = trigger.getAttribute('data-modal-trigger');

        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            Modal.openById(modalId, trigger);
        });

        // Mark as initialized to prevent duplicate listeners
        trigger.setAttribute('data-modal-initialized', 'true');
    });

    // Initialize existing modal elements (Modal constructor handles duplicates via instances Map)
    document.querySelectorAll('[data-modal]').forEach(element => {
        new Modal(element);
    });
}

// Auto-initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initModals);
} else {
    initModals();
}

export { Modal, initModals };

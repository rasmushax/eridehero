/**
 * Account Trackers Component
 * Handles price tracker listing, search, sort, edit, and delete
 * Uses PriceAlertModal for editing (DRY principle)
 */

import { Toast } from './toast.js';
import { Modal } from './modal.js';
import { PriceAlertModal } from './price-alert.js';
import { escapeHtml } from '../utils/dom.js';
import { formatPrice } from '../services/geo-price.js';
import { getRestUrl } from '../utils/api.js';

const getApiBase = () => getRestUrl().replace(/\/$/, '');
const getNonce = () => window.erhData?.nonce || '';

// Geo to flag mapping
const GEO_FLAGS = {
    US: 'united-states.svg',
    GB: 'united-kingdom.svg',
    EU: 'european-union.svg',
    CA: 'canada.svg',
    AU: 'australia.svg'
};

// State
let trackers = [];
let sortColumn = 'name';
let sortDirection = 'asc';
let searchQuery = '';
let deleteModal = null;
let pendingDeleteId = null;

// DOM references
let elements = {};

export function initAccountTrackers() {
    cacheElements();
    if (!elements.container) return;

    bindEvents();
    loadTrackers();

    // Listen for price alert events (from PriceAlertModal)
    window.addEventListener('priceAlert:saved', handleTrackerUpdated);
    window.addEventListener('priceAlert:deleted', handleTrackerDeleted);
}

/**
 * Cache DOM elements
 */
function cacheElements() {
    elements = {
        container: document.querySelector('[data-trackers]'),
        loading: document.querySelector('[data-trackers-loading]'),
        tableWrapper: document.querySelector('[data-trackers-table-wrapper]'),
        tbody: document.querySelector('[data-trackers-body]'),
        empty: document.querySelector('[data-trackers-empty]'),
        error: document.querySelector('[data-trackers-error]'),
        retryBtn: document.querySelector('[data-trackers-retry]'),
        searchInput: document.querySelector('[data-trackers-search]'),
        sortBtns: document.querySelectorAll('.trackers-th[data-sort]')
    };
}

/**
 * Bind event listeners
 */
function bindEvents() {
    // Search
    if (elements.searchInput) {
        elements.searchInput.addEventListener('input', debounce((e) => {
            searchQuery = e.target.value.toLowerCase().trim();
            renderTable();
        }, 200));
    }

    // Sort (3-click cycle: asc → desc → none)
    elements.sortBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const column = btn.dataset.sort;
            if (sortColumn === column) {
                if (sortDirection === 'asc') {
                    sortDirection = 'desc';
                } else if (sortDirection === 'desc') {
                    sortColumn = null;
                    sortDirection = 'asc';
                }
            } else {
                sortColumn = column;
                sortDirection = 'asc';
            }
            updateSortUI();
            renderTable();
        });
    });

    // Retry
    if (elements.retryBtn) {
        elements.retryBtn.addEventListener('click', loadTrackers);
    }

    // Close menus on outside click
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.trackers-actions')) {
            closeAllMenus();
        }
    });

    // Event delegation for table row actions (prevents memory leak from rebinding)
    if (elements.tbody) {
        elements.tbody.addEventListener('click', handleTableClick);
    }
}

/**
 * Handle clicks on table rows via event delegation
 */
function handleTableClick(e) {
    const target = e.target;

    // Action menu toggle
    const toggleBtn = target.closest('[data-action-toggle]');
    if (toggleBtn) {
        e.stopPropagation();
        const menu = toggleBtn.nextElementSibling;
        const isOpen = menu?.classList.contains('is-open');
        closeAllMenus();
        if (!isOpen && menu) {
            menu.classList.add('is-open');
        }
        return;
    }

    // Edit action
    const editBtn = target.closest('[data-action="edit"]');
    if (editBtn) {
        const trackerId = editBtn.dataset.trackerId;
        const tracker = trackers.find(t => String(t.id) === trackerId);
        if (tracker) openEditModal(tracker);
        return;
    }

    // Delete action
    const deleteBtn = target.closest('[data-action="delete"]');
    if (deleteBtn) {
        const trackerId = deleteBtn.dataset.trackerId;
        const tracker = trackers.find(t => String(t.id) === trackerId);
        if (tracker) openDeleteModal(tracker);
        return;
    }
}

/**
 * Load trackers from API
 */
async function loadTrackers() {
    showState('loading');

    try {
        const response = await fetch(`${getApiBase()}/user/trackers`, {
            headers: {
                'X-WP-Nonce': getNonce()
            },
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error('Failed to load trackers');
        }

        const data = await response.json();
        trackers = Array.isArray(data) ? data : (data.trackers || []);

        if (trackers.length === 0) {
            showState('empty');
        } else {
            showState('table');
            updateSortUI();
            renderTable();
        }

    } catch (error) {
        console.error('Error loading trackers:', error);
        showState('error');
    }
}

/**
 * Show specific state
 */
function showState(state) {
    elements.loading.hidden = state !== 'loading';
    elements.tableWrapper.hidden = state !== 'table';
    elements.empty.hidden = state !== 'empty';
    elements.error.hidden = state !== 'error';
}

/**
 * Update sort UI
 */
function updateSortUI() {
    elements.sortBtns.forEach(btn => {
        const column = btn.dataset.sort;
        if (column === sortColumn) {
            btn.dataset.sortActive = sortDirection;
        } else {
            delete btn.dataset.sortActive;
        }
    });
}

/**
 * Render table rows
 */
function renderTable() {
    if (!elements.tbody) return;

    // Filter
    let filtered = trackers;
    if (searchQuery) {
        filtered = trackers.filter(t =>
            t.product_name?.toLowerCase().includes(searchQuery)
        );
    }

    // Sort
    const sorted = [...filtered].sort((a, b) => {
        let aVal, bVal;

        switch (sortColumn) {
            case 'name':
                aVal = a.product_name?.toLowerCase() || '';
                bVal = b.product_name?.toLowerCase() || '';
                break;
            case 'start_price':
                aVal = parseFloat(a.start_price) || 0;
                bVal = parseFloat(b.start_price) || 0;
                break;
            case 'current_price':
                aVal = parseFloat(a.current_price) || 0;
                bVal = parseFloat(b.current_price) || 0;
                break;
            default:
                return 0;
        }

        if (aVal < bVal) return sortDirection === 'asc' ? -1 : 1;
        if (aVal > bVal) return sortDirection === 'asc' ? 1 : -1;
        return 0;
    });

    // Render
    if (sorted.length === 0 && searchQuery) {
        elements.tbody.innerHTML = `
            <tr>
                <td colspan="5" class="trackers-td" style="text-align: center; padding: 40px;">
                    No trackers match "${escapeHtml(searchQuery)}"
                </td>
            </tr>
        `;
    } else {
        elements.tbody.innerHTML = sorted.map(tracker => renderRow(tracker)).join('');
    }
    // Note: Row actions handled via event delegation in handleTableClick()
}

/**
 * Render a single table row
 */
function renderRow(tracker) {
    const currency = tracker.currency || 'USD';
    const geo = tracker.geo || 'US';
    const flagFile = GEO_FLAGS[geo] || GEO_FLAGS.US;
    const flagUrl = (window.erhData?.themeUrl || '') + '/assets/images/countries/' + flagFile;
    const trackerType = tracker.target_price
        ? `Price ${formatPrice(tracker.target_price, currency)}`
        : `Drop ${formatPrice(tracker.price_drop, currency)}`;

    return `
        <tr class="trackers-row" data-tracker-row="${tracker.id}">
            <td class="trackers-td trackers-td--product">
                <div class="trackers-product">
                    <img
                        src="${escapeHtml(tracker.product_thumbnail || '/wp-content/themes/erh-theme/assets/images/placeholder.svg')}"
                        alt=""
                        class="trackers-product-image"
                        loading="lazy"
                    >
                    <div class="trackers-product-info">
                        <a href="${escapeHtml(tracker.product_url || '#')}" class="trackers-product-name">
                            ${escapeHtml(tracker.product_name)}
                        </a>
                        <span class="trackers-product-geo"><img src="${escapeHtml(flagUrl)}" alt="" class="trackers-geo-flag"> Tracking ${escapeHtml(geo)} price</span>
                    </div>
                </div>
            </td>
            <td class="trackers-td trackers-td--start">
                <span class="trackers-price">${formatPrice(tracker.start_price, currency)}</span>
            </td>
            <td class="trackers-td trackers-td--current">
                <span class="trackers-price">${formatPrice(tracker.current_price, currency)}</span>
            </td>
            <td class="trackers-td trackers-td--tracker">
                <span class="trackers-tracker">
                    ${trackerType}
                </span>
            </td>
            <td class="trackers-td trackers-td--actions">
                <div class="trackers-actions">
                    <button type="button" class="trackers-action-btn" data-action-toggle aria-label="Actions">
                        <svg class="icon" aria-hidden="true"><use href="#icon-more-vertical"></use></svg>
                    </button>
                    <div class="trackers-action-menu" data-action-menu>
                        <button type="button" class="trackers-action-menu-item" data-action="edit" data-tracker-id="${tracker.id}">
                            <svg class="icon" aria-hidden="true"><use href="#icon-edit"></use></svg>
                            Edit
                        </button>
                        <button type="button" class="trackers-action-menu-item trackers-action-menu-item--danger" data-action="delete" data-tracker-id="${tracker.id}">
                            <svg class="icon" aria-hidden="true"><use href="#icon-trash"></use></svg>
                            Delete
                        </button>
                    </div>
                </div>
            </td>
        </tr>
    `;
}

/**
 * Close all action menus
 */
function closeAllMenus() {
    document.querySelectorAll('[data-action-menu].is-open').forEach(menu => {
        menu.classList.remove('is-open');
    });
}

/**
 * Open edit modal using PriceAlertModal
 */
function openEditModal(tracker) {
    closeAllMenus();

    // Use the site-wide PriceAlertModal component
    PriceAlertModal.open({
        productId: parseInt(tracker.product_id, 10),
        productName: tracker.product_name,
        productImage: tracker.product_thumbnail,
        currentPrice: parseFloat(tracker.live_price || tracker.current_price),
        currency: tracker.currency || 'USD'
    });
}

/**
 * Open delete confirmation modal using Modal.create()
 */
function openDeleteModal(tracker) {
    closeAllMenus();
    pendingDeleteId = tracker.id;

    // Clean up any existing modal first
    if (deleteModal) {
        deleteModal.destroy(true);
        deleteModal = null;
    }

    // Create delete confirmation modal programmatically
    deleteModal = Modal.create({
        id: 'delete-tracker-confirm',
        title: 'Delete price alert?',
        content: `
            <p class="modal-text">
                Are you sure you want to delete the price alert for <strong>${escapeHtml(tracker.product_name)}</strong>?
                You won't receive any more notifications for this product.
            </p>
        `,
        size: 'sm',
        footerContent: `
            <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
            <button type="button" class="btn btn-danger" data-confirm-delete>
                <span class="btn-text">Delete</span>
                <span class="btn-loading" hidden>
                    <svg class="spinner" viewBox="0 0 24 24" width="20" height="20">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4" stroke-linecap="round"/>
                    </svg>
                </span>
            </button>
        `,
        afterClose: () => {
            // Clean up modal on close
            if (deleteModal) {
                deleteModal.destroy(true);
                deleteModal = null;
            }
            pendingDeleteId = null;
        }
    });

    // Bind delete confirm button
    const confirmBtn = deleteModal.element.querySelector('[data-confirm-delete]');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', handleDeleteConfirm);
    }
}

/**
 * Handle delete confirmation
 */
async function handleDeleteConfirm() {
    if (!pendingDeleteId) return;

    const trackerId = pendingDeleteId;
    const confirmBtn = deleteModal?.element.querySelector('[data-confirm-delete]');
    const btnText = confirmBtn?.querySelector('.btn-text');
    const btnLoading = confirmBtn?.querySelector('.btn-loading');

    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.classList.add('is-loading');
        if (btnText) btnText.style.visibility = 'hidden';
        if (btnLoading) btnLoading.hidden = false;
    }

    try {
        const response = await fetch(`${getApiBase()}/user/trackers/${trackerId}`, {
            method: 'DELETE',
            headers: {
                'X-WP-Nonce': getNonce()
            },
            credentials: 'same-origin'
        });

        if (!response.ok) {
            const result = await response.json();
            throw new Error(result.message || 'Failed to delete tracker');
        }

        // Remove from local state
        trackers = trackers.filter(t => String(t.id) !== String(trackerId));

        // Close modal (afterClose callback handles cleanup)
        deleteModal?.close();

        if (trackers.length === 0) {
            showState('empty');
        } else {
            renderTable();
        }

        Toast.success('Price alert deleted');

    } catch (error) {
        Toast.error(error.message);
        if (confirmBtn) {
            confirmBtn.disabled = false;
            confirmBtn.classList.remove('is-loading');
            if (btnText) btnText.style.visibility = '';
            if (btnLoading) btnLoading.hidden = true;
        }
    }
}

/**
 * Handle tracker updated event (from PriceAlertModal)
 */
function handleTrackerUpdated(event) {
    const { productId, tracker } = event.detail || {};
    if (!productId) return;

    // Find and update the tracker in our list
    const index = trackers.findIndex(t => String(t.product_id) === String(productId));
    if (index !== -1 && tracker) {
        // Merge updated data
        trackers[index] = { ...trackers[index], ...tracker };
        renderTable();
    } else {
        // Tracker might be new or we don't have it - reload
        loadTrackers();
    }
}

/**
 * Handle tracker deleted event (from PriceAlertModal)
 */
function handleTrackerDeleted(event) {
    const { productId } = event.detail || {};
    if (!productId) return;

    // Remove from local state
    trackers = trackers.filter(t => String(t.product_id) !== String(productId));

    if (trackers.length === 0) {
        showState('empty');
    } else {
        renderTable();
    }
}

// formatPrice imported from services/geo-price.js
// escapeHtml imported from utils/dom.js

/**
 * Debounce helper
 */
function debounce(fn, delay) {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn(...args), delay);
    };
}

// Auto-initialize
if (document.querySelector('[data-trackers]')) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAccountTrackers);
    } else {
        initAccountTrackers();
    }
}

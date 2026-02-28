/**
 * Footer Region Picker Component
 *
 * Allows users to change their region/currency preference.
 * - For logged-in users: saves to database via REST API
 * - For guests: saves to localStorage
 * - Shows confirmation modal if user has deals digest enabled
 */

import { getUserGeo, setUserGeoPreference } from '../services/geo-price.js';
import { Modal } from './modal.js';
import { Toast } from './toast.js';

const REGIONS = {
    US: { label: 'United States (USD)', flag: 'united-states.svg' },
    GB: { label: 'United Kingdom (GBP)', flag: 'united-kingdom.svg' },
    EU: { label: 'Europe (EUR)', flag: 'european-union.svg' },
    CA: { label: 'Canada (CAD)', flag: 'canada.svg' },
    AU: { label: 'Australia (AUD)', flag: 'australia.svg' },
};

/**
 * Initialize the footer region picker
 */
export function initRegionPicker() {
    const wrapper = document.querySelector('[data-region-picker-wrapper]');
    const trigger = document.querySelector('[data-region-picker-trigger]');
    const dropdown = document.querySelector('[data-region-picker]');

    if (!wrapper || !trigger || !dropdown) return;

    // Set current region on load
    getUserGeo().then(({ geo }) => {
        updateCurrentRegionDisplay(geo);
    });

    // Toggle dropdown
    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = !dropdown.hidden;
        dropdown.hidden = isOpen;
        trigger.setAttribute('aria-expanded', String(!isOpen));
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
        if (!wrapper.contains(e.target)) {
            dropdown.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
        }
    });

    // Close on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !dropdown.hidden) {
            dropdown.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            trigger.focus();
        }
    });

    // Handle region selection
    dropdown.addEventListener('click', async (e) => {
        const option = e.target.closest('[data-region]');
        if (!option) return;

        const newRegion = option.dataset.region;
        dropdown.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');

        // Check if logged-in user has deals digest enabled
        const needsConfirmation = shouldConfirmRegionChange();

        if (needsConfirmation) {
            showRegionChangeConfirmation(newRegion);
        } else {
            await applyRegionChange(newRegion);
        }
    });
}

/**
 * Check if user has deals digest enabled (needs confirmation)
 * Preferences are injected in erhData.user.preferences by PHP
 * @returns {boolean}
 */
function shouldConfirmRegionChange() {
    if (!window.erhData?.isLoggedIn) return false;
    return window.erhData?.user?.preferences?.sales_roundup_emails === true;
}

/**
 * Show confirmation modal using Modal.create() pattern
 * @param {string} newRegion - Region code to change to
 */
function showRegionChangeConfirmation(newRegion) {
    const regionLabel = REGIONS[newRegion]?.label || newRegion;

    const modal = Modal.create({
        id: 'region-change-confirm',
        title: 'Change your region?',
        content: `
            <p class="modal-text">
                You have deal digest emails enabled. Changing your region to
                <strong>${regionLabel}</strong> will also update
                which deals you receive in your email digest.
            </p>
        `,
        size: 'sm',
        footerContent: `
            <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
            <button type="button" class="btn btn-primary" data-confirm-region="${newRegion}">
                Change Region
            </button>
        `,
    });

    // Bind confirm button
    const confirmBtn = modal.element.querySelector('[data-confirm-region]');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', async () => {
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Updating...';

            await applyRegionChange(newRegion);
            modal.close();
        });
    }
}

/**
 * Apply the region change and reload page
 * @param {string} newRegion - Region code to change to
 */
async function applyRegionChange(newRegion) {
    const result = await setUserGeoPreference(newRegion);

    if (result.success) {
        Toast.success('Region updated');
        // Reload to refresh all pricing on page
        setTimeout(() => {
            window.location.reload();
        }, 500);
    } else {
        Toast.error('Failed to update region. Please try again.');
    }
}

/**
 * Update the trigger display with current region
 * @param {string} geo - Current region code
 */
function updateCurrentRegionDisplay(geo) {
    const labelEl = document.querySelector('[data-current-region]');
    const flagEl = document.querySelector('[data-current-flag]');
    const region = REGIONS[geo] || REGIONS.US;

    if (labelEl) {
        labelEl.textContent = region.label;
    }

    if (flagEl && window.erhData?.themeUrl) {
        const flagUrl = window.erhData.themeUrl + '/assets/images/countries/' + region.flag;
        flagEl.src = flagUrl;
        flagEl.dataset.src = flagUrl;
    }
}

/**
 * Account Settings Component
 * Handles email change, password change, and email preferences forms
 */

import { Toast } from './toast.js';
import { setUserGeoPreference } from '../services/geo-price.js';

// Use erhData.restUrl which includes correct site path (e.g., /eridehero/wp-json/erh/v1/)
const getApiBase = () => (window.erhData?.restUrl || '/wp-json/erh/v1/').replace(/\/$/, '');
const getNonce = () => window.erhData?.nonce || '';
const getSiteUrl = () => window.erhData?.siteUrl || '/';

export function initAccountSettings() {
    initPasswordToggles();
    initEmailForm();
    initPasswordForm();
    initGeoForm();
    initPreferencesForm();
    initConnectedAccounts();
    initLogout();
    loadPreferences();
}

/**
 * Password visibility toggle
 */
function initPasswordToggles() {
    const toggles = document.querySelectorAll('[data-toggle-password]');

    toggles.forEach(toggle => {
        toggle.addEventListener('click', () => {
            const wrapper = toggle.closest('.password-input-wrapper');
            const input = wrapper?.querySelector('input');

            if (!input) return;

            const isVisible = input.type === 'text';
            input.type = isVisible ? 'password' : 'text';
            toggle.classList.toggle('is-visible', !isVisible);
            toggle.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
        });
    });
}

/**
 * Email change form
 */
function initEmailForm() {
    const form = document.querySelector('[data-email-form]');
    if (!form) return;

    const errorEl = form.querySelector('[data-email-error]');
    const submitBtn = form.querySelector('[data-email-submit]');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearError(errorEl);
        setLoading(submitBtn, true);

        const formData = new FormData(form);
        const data = {
            new_email: formData.get('new_email'),
            current_password: formData.get('current_password')
        };

        try {
            const response = await fetch(`${getApiBase()}/user/email`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': getNonce()
                },
                credentials: 'same-origin',
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Failed to update email');
            }

            Toast.success('Email updated successfully');
            form.reset();

            // Update displayed email
            const currentValue = document.querySelector('.settings-current-value');
            if (currentValue) {
                currentValue.textContent = data.new_email;
            }

        } catch (error) {
            showError(errorEl, error.message);
        } finally {
            setLoading(submitBtn, false);
        }
    });
}

/**
 * Password change form
 */
function initPasswordForm() {
    const form = document.querySelector('[data-password-form]');
    if (!form) return;

    const errorEl = form.querySelector('[data-password-error]');
    const submitBtn = form.querySelector('[data-password-submit]');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearError(errorEl);

        const formData = new FormData(form);
        const currentPassword = formData.get('current_password');
        const newPassword = formData.get('new_password');
        const confirmPassword = formData.get('confirm_password');

        // Client-side validation
        if (newPassword !== confirmPassword) {
            showError(errorEl, 'New passwords do not match');
            return;
        }

        if (newPassword.length < 8) {
            showError(errorEl, 'Password must be at least 8 characters');
            return;
        }

        setLoading(submitBtn, true);

        try {
            const response = await fetch(`${getApiBase()}/user/password`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': getNonce()
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    current_password: currentPassword,
                    new_password: newPassword
                })
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Failed to update password');
            }

            Toast.success('Password updated successfully');
            form.reset();

        } catch (error) {
            showError(errorEl, error.message);
        } finally {
            setLoading(submitBtn, false);
        }
    });
}

/**
 * Region/Geo preference form
 */
function initGeoForm() {
    const form = document.querySelector('[data-geo-form]');
    const select = form?.querySelector('[data-geo-select]');
    if (!form || !select) return;

    const errorEl = form.querySelector('[data-geo-error]');
    const submitBtn = form.querySelector('[data-geo-submit]');

    // Set current value from erhData (injected by PHP)
    const currentGeo = window.erhData?.user?.geo || 'US';
    select.value = currentGeo;
    // Trigger change to sync custom select UI
    select.dispatchEvent(new Event('change', { bubbles: true }));

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearError(errorEl);
        setLoading(submitBtn, true);

        const newGeo = select.value;

        try {
            const result = await setUserGeoPreference(newGeo);

            if (result.success) {
                Toast.success('Region updated successfully');
            } else {
                throw new Error('Failed to update region');
            }
        } catch (error) {
            showError(errorEl, error.message);
        } finally {
            setLoading(submitBtn, false);
        }
    });
}

/**
 * Load current preferences
 */
async function loadPreferences() {
    const form = document.querySelector('[data-preferences-form]');
    if (!form) return;

    try {
        const response = await fetch(`${getApiBase()}/user/preferences`, {
            headers: {
                'X-WP-Nonce': getNonce()
            },
            credentials: 'same-origin'
        });

        if (!response.ok) return;

        const data = await response.json();
        // API returns { success, preferences: {...} }
        const prefs = data.preferences || data;

        // Set checkbox states
        const trackerCheckbox = form.querySelector('input[data-preference="price_trackers_emails"]');
        const roundupCheckbox = form.querySelector('input[data-preference="sales_roundup_emails"]');
        const newsletterCheckbox = form.querySelector('input[data-preference="newsletter_subscription"]');
        const frequencySelect = form.querySelector('select[data-preference="sales_roundup_frequency"]');

        if (trackerCheckbox) trackerCheckbox.checked = prefs.price_tracker_emails ?? true;
        if (roundupCheckbox) roundupCheckbox.checked = prefs.sales_roundup_emails ?? true;
        if (newsletterCheckbox) newsletterCheckbox.checked = prefs.newsletter_subscription ?? false;

        // Set frequency select value (works with both native and custom select)
        if (frequencySelect) {
            frequencySelect.value = prefs.sales_roundup_frequency || 'weekly';
            // Trigger change to sync custom select UI
            frequencySelect.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Set product type checkboxes
        const typeCheckboxes = form.querySelectorAll('input[data-roundup-type]');
        const selectedTypes = prefs.sales_roundup_types || [];
        typeCheckboxes.forEach(checkbox => {
            checkbox.checked = selectedTypes.includes(checkbox.value);
        });

        // Show/hide roundup options based on roundup checkbox
        updateRoundupVisibility(roundupCheckbox?.checked);

    } catch (error) {
        console.error('Failed to load preferences:', error);
    }
}

/**
 * Fetch user's active tracker count
 */
async function fetchTrackerCount() {
    try {
        const response = await fetch(`${getApiBase()}/user/trackers`, {
            headers: {
                'X-WP-Nonce': getNonce()
            },
            credentials: 'same-origin'
        });

        if (!response.ok) return 0;

        const data = await response.json();
        return data.trackers?.length || 0;
    } catch {
        return 0;
    }
}

/**
 * Handle tracker emails toggle - show warning if disabling with active trackers
 */
function handleTrackerEmailsToggle(checkbox, trackerCount) {
    // Remove any existing warning
    document.querySelector('[data-tracker-warning]')?.remove();

    // Show warning if unchecking and has active trackers
    if (!checkbox.checked && trackerCount > 0) {
        const warning = document.createElement('div');
        warning.className = 'settings-inline-warning';
        warning.setAttribute('data-tracker-warning', '');
        warning.innerHTML = `
            <svg class="settings-warning-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
            <span>
                You have <strong>${trackerCount} active tracker${trackerCount > 1 ? 's' : ''}</strong>.
                Disabling emails means you won't be notified when prices drop.
                Your trackers will remain visible in your account.
            </span>
        `;

        // Insert warning after the preference container
        const preferenceEl = checkbox.closest('.settings-preference');
        if (preferenceEl) {
            preferenceEl.after(warning);
        }
    }
}

/**
 * Email preferences form
 */
function initPreferencesForm() {
    const form = document.querySelector('[data-preferences-form]');
    if (!form) return;

    const errorEl = form.querySelector('[data-preferences-error]');
    const submitBtn = form.querySelector('[data-preferences-submit]');
    const roundupCheckbox = form.querySelector('input[data-preference="sales_roundup_emails"]');
    const roundupWrapper = form.querySelector('[data-roundup-wrapper]');
    const trackerCheckbox = form.querySelector('input[data-preference="price_trackers_emails"]');

    // Track tracker count for warning display
    let trackerCount = 0;
    fetchTrackerCount().then(count => {
        trackerCount = count;
    });

    // Toggle roundup options visibility
    if (roundupCheckbox && roundupWrapper) {
        roundupCheckbox.addEventListener('change', () => {
            updateRoundupVisibility(roundupCheckbox.checked);
        });
    }

    // Handle tracker emails toggle warning
    if (trackerCheckbox) {
        trackerCheckbox.addEventListener('change', () => {
            handleTrackerEmailsToggle(trackerCheckbox, trackerCount);
        });
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearError(errorEl);
        setLoading(submitBtn, true);

        const formData = new FormData(form);

        // Collect selected product types
        const selectedTypes = [];
        form.querySelectorAll('input[data-roundup-type]:checked').forEach(checkbox => {
            selectedTypes.push(checkbox.value);
        });

        try {
            const response = await fetch(`${getApiBase()}/user/preferences`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': getNonce()
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    // API expects 'price_tracker_emails' (singular), not 'price_trackers_emails'
                    price_tracker_emails: formData.get('price_trackers_emails') === 'on',
                    sales_roundup_emails: formData.get('sales_roundup_emails') === 'on',
                    sales_roundup_frequency: formData.get('sales_roundup_frequency'),
                    sales_roundup_types: selectedTypes,
                    newsletter_subscription: formData.get('newsletter_subscription') === 'on'
                })
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Failed to save preferences');
            }

            Toast.success('Preferences saved');

        } catch (error) {
            showError(errorEl, error.message);
        } finally {
            setLoading(submitBtn, false);
        }
    });
}

/**
 * Update roundup options visibility (types + frequency)
 */
function updateRoundupVisibility(show) {
    const wrapper = document.querySelector('[data-roundup-wrapper]');
    if (wrapper) {
        wrapper.style.display = show ? '' : 'none';
    }
}

/**
 * Connected social accounts
 */
function initConnectedAccounts() {
    const container = document.querySelector('[data-connected-accounts]');
    if (!container) return;

    loadConnectedAccounts(container);
}

/**
 * Load and render connected accounts
 */
async function loadConnectedAccounts(container) {
    const providers = [
        { key: 'google', name: 'Google', icon: 'google' },
        { key: 'facebook', name: 'Facebook', icon: 'facebook' },
        { key: 'reddit', name: 'Reddit', icon: 'reddit' }
    ];

    try {
        const response = await fetch(`${getApiBase()}/user/profile`, {
            headers: {
                'X-WP-Nonce': getNonce()
            },
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error('Failed to load profile');
        }

        const data = await response.json();
        const linkedProviders = data.profile?.linked_providers || [];

        // Only show linked providers (disconnect only, no "Connect" buttons)
        const linkedItems = providers.filter(p => linkedProviders.includes(p.key));

        if (linkedItems.length === 0) {
            container.innerHTML = '<p class="connected-accounts-empty">No social accounts connected.</p>';
            return;
        }

        container.innerHTML = linkedItems.map(p => `
            <div class="connected-account" data-provider="${p.key}">
                <div class="connected-account-info">
                    <svg class="connected-account-icon" width="20" height="20">
                        <use href="#icon-${p.icon}"></use>
                    </svg>
                    <span class="connected-account-name">${p.name}</span>
                    <span class="connected-account-status">Connected</span>
                </div>
                <button
                    type="button"
                    class="btn btn-sm btn-outline connected-account-disconnect"
                    data-disconnect="${p.key}"
                >
                    Disconnect
                </button>
            </div>
        `).join('');

        // Handle disconnect clicks
        container.querySelectorAll('[data-disconnect]').forEach(btn => {
            btn.addEventListener('click', () => handleDisconnect(btn, container));
        });

    } catch (error) {
        console.error('Failed to load connected accounts:', error);
        container.innerHTML = '<p class="connected-accounts-error">Failed to load connected accounts.</p>';
    }
}

/**
 * Handle disconnecting a social account
 */
async function handleDisconnect(btn, container) {
    const provider = btn.dataset.disconnect;
    const providerName = provider.charAt(0).toUpperCase() + provider.slice(1);

    // Confirm before disconnecting
    if (!confirm(`Are you sure you want to disconnect your ${providerName} account?`)) {
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Disconnecting...';

    try {
        const response = await fetch(`${getApiBase()}/user/unlink-social`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': getNonce()
            },
            credentials: 'same-origin',
            body: JSON.stringify({ provider })
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.message || 'Failed to disconnect');
        }

        Toast.success(result.message || `${providerName} account disconnected.`);

        // Re-render the connected accounts
        loadConnectedAccounts(container);

    } catch (error) {
        Toast.error(error.message);
        btn.disabled = false;
        btn.textContent = 'Disconnect';
    }
}

/**
 * Logout button
 */
function initLogout() {
    const logoutBtn = document.querySelector('[data-account-logout]');
    if (!logoutBtn) return;

    logoutBtn.addEventListener('click', async () => {
        logoutBtn.disabled = true;

        try {
            const response = await fetch(`${getApiBase()}/auth/logout`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': getNonce()
                },
                credentials: 'same-origin'
            });

            // Redirect to home regardless of response
            window.location.href = getSiteUrl();

        } catch (error) {
            // Still redirect on error
            window.location.href = getSiteUrl();
        }
    });
}

/**
 * Helper: Show error message
 */
function showError(el, message) {
    if (!el) return;
    el.textContent = message;
    el.hidden = false;
}

/**
 * Helper: Clear error message
 */
function clearError(el) {
    if (!el) return;
    el.textContent = '';
    el.hidden = true;
}

/**
 * Helper: Set loading state on button
 */
function setLoading(btn, loading) {
    if (!btn) return;
    btn.disabled = loading;
    btn.classList.toggle('is-loading', loading);
}

// Auto-initialize
if (document.querySelector('[data-settings]')) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAccountSettings);
    } else {
        initAccountSettings();
    }
}

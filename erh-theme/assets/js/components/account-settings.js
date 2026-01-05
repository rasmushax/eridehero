/**
 * Account Settings Component
 * Handles email change, password change, and email preferences forms
 */

import { Toast } from './toast.js';

// Use erhData.restUrl which includes correct site path (e.g., /eridehero/wp-json/erh/v1/)
const getApiBase = () => (window.erhData?.restUrl || '/wp-json/erh/v1/').replace(/\/$/, '');
const getNonce = () => window.erhData?.nonce || '';

export function initAccountSettings() {
    initPasswordToggles();
    initEmailForm();
    initPasswordForm();
    initPreferencesForm();
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
 * Email preferences form
 */
function initPreferencesForm() {
    const form = document.querySelector('[data-preferences-form]');
    if (!form) return;

    const errorEl = form.querySelector('[data-preferences-error]');
    const submitBtn = form.querySelector('[data-preferences-submit]');
    const roundupCheckbox = form.querySelector('input[data-preference="sales_roundup_emails"]');
    const roundupWrapper = form.querySelector('[data-roundup-wrapper]');

    // Toggle roundup options visibility
    if (roundupCheckbox && roundupWrapper) {
        roundupCheckbox.addEventListener('change', () => {
            updateRoundupVisibility(roundupCheckbox.checked);
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
            window.location.href = '/';

        } catch (error) {
            // Still redirect on error
            window.location.href = '/';
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

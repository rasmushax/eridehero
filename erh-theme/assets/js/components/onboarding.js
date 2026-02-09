/**
 * Email Preferences Onboarding
 *
 * Handles the onboarding form submission after user registration.
 * Saves preferences and redirects to the original URL or home.
 */

import { Toast } from './toast.js';
import { getRestUrl } from '../utils/api.js';

const getApiBase = () => getRestUrl().replace(/\/$/, '');
const getNonce = () => window.erhData?.nonce || '';

export function initOnboarding() {
    const form = document.querySelector('[data-onboarding-form]');
    if (!form) return;

    const errorEl = form.querySelector('[data-onboarding-error]');
    const submitBtn = form.querySelector('[data-onboarding-submit]');
    const skipLink = document.querySelector('[data-skip-preferences]');
    const roundupToggle = form.querySelector('[data-toggle-categories]');
    const categoriesWrapper = form.querySelector('[data-roundup-categories]');

    // Toggle category visibility based on roundup checkbox
    if (roundupToggle && categoriesWrapper) {
        const updateCategoriesVisibility = () => {
            categoriesWrapper.style.display = roundupToggle.checked ? '' : 'none';
        };
        roundupToggle.addEventListener('change', updateCategoriesVisibility);
        // Initial state
        updateCategoriesVisibility();
    }

    // Handle form submission
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(form);
        const redirectUrl = formData.get('redirect') || '/';

        setLoading(submitBtn, true);
        clearError(errorEl);

        // Collect selected roundup types
        const selectedTypes = [];
        form.querySelectorAll('input[data-roundup-type]:checked').forEach(checkbox => {
            selectedTypes.push(checkbox.value);
        });

        try {
            // Build preferences payload
            const preferences = {
                price_tracker_emails: formData.get('price_tracker_emails') === 'on',
                sales_roundup_emails: formData.get('sales_roundup_emails') === 'on',
                newsletter_subscription: formData.get('newsletter_subscription') === 'on',
                sales_roundup_frequency: formData.get('sales_roundup_frequency') || 'weekly',
                sales_roundup_types: selectedTypes.length > 0 ? selectedTypes : ['escooter', 'ebike', 'eskate', 'euc', 'hoverboard']
            };

            const response = await fetch(`${getApiBase()}/user/preferences`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': getNonce()
                },
                credentials: 'same-origin',
                body: JSON.stringify(preferences)
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Failed to save preferences');
            }

            // Success - show toast and redirect
            Toast.success('Preferences saved!');
            setTimeout(() => {
                window.location.href = redirectUrl;
            }, 500);

        } catch (error) {
            showError(errorEl, error.message);
            setLoading(submitBtn, false);
        }
    });

    // Skip link - still marks preferences as "set" to prevent redirect loop
    if (skipLink) {
        skipLink.addEventListener('click', async (e) => {
            e.preventDefault();

            try {
                // Set preferences_set flag without changing defaults
                // An empty update still sets email_preferences_set = '1' in backend
                await fetch(`${getApiBase()}/user/preferences`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': getNonce()
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({})
                });
            } catch {
                // Ignore errors on skip - user can always set later
            }

            // Redirect regardless of API result
            window.location.href = skipLink.href;
        });
    }
}

/**
 * Set loading state on submit button
 */
function setLoading(btn, loading) {
    if (!btn) return;
    btn.disabled = loading;

    const textEl = btn.querySelector('.btn-text');
    const loadingEl = btn.querySelector('.btn-loading');

    if (textEl) textEl.hidden = loading;
    if (loadingEl) loadingEl.hidden = !loading;
}

/**
 * Show error message
 */
function showError(el, message) {
    if (!el) return;
    el.textContent = message;
    el.hidden = false;
}

/**
 * Clear error message
 */
function clearError(el) {
    if (!el) return;
    el.textContent = '';
    el.hidden = true;
}

// Auto-initialize
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initOnboarding);
} else {
    initOnboarding();
}

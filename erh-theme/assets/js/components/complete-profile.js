/**
 * Complete Profile - Email collection for OAuth without email
 *
 * Handles the form on /complete-profile/ page for Reddit users
 * who need to provide an email address to complete signup.
 */

import { Toast } from './toast.js';

const getApiBase = () => (window.erhData?.restUrl || '/wp-json/erh/v1/').replace(/\/$/, '');
const getSiteUrl = () => window.erhData?.siteUrl || '';
const getNonce = () => window.erhData?.nonce || '';

export function initCompleteProfile() {
    const form = document.querySelector('[data-complete-profile-form]');
    if (!form) return;

    const emailInput = form.querySelector('[data-email-input]');
    const passwordField = form.querySelector('[data-password-field]');
    const errorEl = form.querySelector('[data-error]');
    const submitBtn = form.querySelector('[data-submit-btn]');

    let emailExists = false;
    let checkTimeout;

    // Check if email exists as user types (debounced)
    emailInput?.addEventListener('input', () => {
        clearTimeout(checkTimeout);
        clearError();

        const email = emailInput.value.trim();

        // Basic email validation before checking
        if (!email || !email.includes('@') || !email.includes('.')) {
            passwordField.hidden = true;
            emailExists = false;
            return;
        }

        checkTimeout = setTimeout(async () => {
            try {
                const response = await fetch(`${getApiBase()}/auth/check-email`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email })
                });

                const data = await response.json();

                emailExists = data.exists;
                passwordField.hidden = !emailExists;

                // Focus password field if it becomes visible
                if (emailExists) {
                    const passwordInput = passwordField.querySelector('input');
                    passwordInput?.focus();
                }
            } catch {
                // Silently fail - user can still submit
                emailExists = false;
                passwordField.hidden = true;
            }
        }, 500);
    });

    // Handle form submission
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(form);
        const state = formData.get('state');
        const provider = formData.get('provider');
        const email = formData.get('email')?.trim();
        const password = emailExists ? formData.get('password') : null;

        // Basic validation
        if (!email) {
            showError('Please enter your email address.');
            return;
        }

        if (emailExists && !password) {
            showError('Please enter your password to link your account.');
            return;
        }

        setLoading(true);
        clearError();

        try {
            const response = await fetch(`${getApiBase()}/auth/social/complete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': getNonce()
                },
                credentials: 'same-origin',
                body: JSON.stringify({ state, provider, email, password })
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Something went wrong');
            }

            // Success
            Toast.success(result.message || 'Success!');

            // Redirect to onboarding or home
            setTimeout(() => {
                if (result.needsOnboarding) {
                    const returnUrl = encodeURIComponent(result.redirect || getSiteUrl() || '/');
                    window.location.href = `${getSiteUrl()}/email-preferences/?redirect=${returnUrl}`;
                } else {
                    window.location.href = result.redirect || getSiteUrl() || '/';
                }
            }, 500);

        } catch (error) {
            showError(error.message);
            setLoading(false);
        }
    });

    /**
     * Show error message
     */
    function showError(message) {
        if (!errorEl) return;
        errorEl.textContent = message;
        errorEl.hidden = false;
    }

    /**
     * Clear error message
     */
    function clearError() {
        if (!errorEl) return;
        errorEl.textContent = '';
        errorEl.hidden = true;
    }

    /**
     * Set loading state
     */
    function setLoading(loading) {
        if (!submitBtn) return;
        submitBtn.disabled = loading;

        const textEl = submitBtn.querySelector('.btn-text');
        const loadingEl = submitBtn.querySelector('.btn-loading');

        if (textEl) textEl.hidden = loading;
        if (loadingEl) loadingEl.hidden = !loading;
    }
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCompleteProfile);
} else {
    initCompleteProfile();
}

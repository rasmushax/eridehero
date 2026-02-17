/**
 * Shared Auth Core
 *
 * Reusable authentication logic shared between the auth modal and auth page.
 * Handles API calls, social login flows, success handling, and form UI helpers.
 *
 * @module utils/auth-core
 */

import { getRestUrl } from './api.js';

const REST_URL = getRestUrl();

/**
 * Endpoint map for form types.
 */
const FORM_ENDPOINTS = {
    signin: 'auth/login',
    signup: 'auth/register',
    forgot: 'auth/forgot-password',
};

/**
 * Submit an auth form (signin, signup, or forgot password).
 *
 * @param {string} formType - 'signin', 'signup', or 'forgot'
 * @param {Object} formData - { email, password, ... }
 * @returns {Promise<Object>} Parsed response body
 * @throws {Error} On HTTP error with server message
 */
export async function submitAuthForm(formType, formData) {
    const endpoint = FORM_ENDPOINTS[formType];
    if (!endpoint) {
        throw new Error(`Unknown form type: ${formType}`);
    }

    const response = await fetch(`${REST_URL}${endpoint}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': window.erhData?.nonce || '',
        },
        body: JSON.stringify(formData),
    });

    const result = await response.json();

    if (!response.ok) {
        throw new Error(result.message || 'Something went wrong');
    }

    return result;
}

/**
 * Submit a password reset (set new password from email link).
 *
 * @param {Object} data - { key, login, password, password_confirm }
 * @returns {Promise<Object>} Parsed response body
 * @throws {Error} On HTTP error with server message
 */
export async function submitPasswordReset(data) {
    const response = await fetch(`${REST_URL}auth/reset-password`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': window.erhData?.nonce || '',
        },
        body: JSON.stringify(data),
    });

    const result = await response.json();

    if (!response.ok) {
        throw new Error(result.message || 'Something went wrong');
    }

    return result;
}

/**
 * Initiate social login flow.
 *
 * @param {string} provider - 'google', 'facebook', or 'reddit'
 * @param {Object} options
 * @param {boolean} options.popup - true = open popup (modal), false = full-page redirect (page)
 * @param {string} [options.redirect] - Where to redirect after auth (page mode)
 * @returns {Promise<Object>|undefined} Promise resolving to postMessage data (popup mode), or undefined (redirect mode)
 */
export function initSocialLogin(provider, { popup = false, redirect = '' } = {}) {
    const baseUrl = `${REST_URL}auth/social/${provider}`;

    if (!popup) {
        // Full-page redirect mode (auth page)
        const params = new URLSearchParams();
        if (redirect) {
            params.set('redirect', redirect);
        }
        const url = params.toString() ? `${baseUrl}?${params}` : baseUrl;
        window.location.href = url;
        return;
    }

    // Popup mode (modal)
    const width = 500;
    const height = 600;
    const left = (window.innerWidth - width) / 2 + window.screenX;
    const top = (window.innerHeight - height) / 2 + window.screenY;

    const popupWindow = window.open(
        `${baseUrl}?popup=1`,
        'auth-popup',
        `width=${width},height=${height},left=${left},top=${top},popup=1`
    );

    return new Promise((resolve, reject) => {
        const handleMessage = (event) => {
            if (event.origin !== window.location.origin) return;

            if (event.data?.type === 'auth-success') {
                window.removeEventListener('message', handleMessage);
                clearInterval(checkClosed);
                popupWindow?.close();
                resolve(event.data);
            } else if (event.data?.type === 'auth-error') {
                window.removeEventListener('message', handleMessage);
                clearInterval(checkClosed);
                popupWindow?.close();
                reject(new Error(event.data.message || 'Authentication failed'));
            }
        };

        window.addEventListener('message', handleMessage);

        // Fallback: detect popup closed without message
        const checkClosed = setInterval(() => {
            if (popupWindow?.closed) {
                clearInterval(checkClosed);
                window.removeEventListener('message', handleMessage);
                // Silently resolve — user may have closed popup intentionally
                reject(new Error('Popup closed'));
            }
        }, 500);
    });
}

/**
 * Handle successful authentication (post-login/register).
 *
 * @param {string} method - 'signin', 'signup', or 'social'
 * @param {boolean} needsOnboarding - Whether user needs email preferences setup
 * @param {string} [redirectUrl] - Override redirect URL (otherwise reload)
 */
export function handleAuthSuccess(method, needsOnboarding = false, redirectUrl = '') {
    const siteUrl = window.erhData?.siteUrl || '';
    const toastMessage = method === 'signup' ? 'Account created!' : 'Signed in successfully';

    // Dynamically import toast to avoid circular dependency issues
    import('../components/toast.js').then(({ Toast }) => {
        Toast.success(toastMessage);
    });

    setTimeout(() => {
        if (needsOnboarding) {
            const returnUrl = encodeURIComponent(redirectUrl || window.location.href);
            window.location.href = `${siteUrl}/email-preferences/?redirect=${returnUrl}`;
        } else if (redirectUrl) {
            window.location.href = redirectUrl;
        } else {
            window.location.reload();
        }
    }, 500);
}

/**
 * Toggle loading state on a submit button.
 *
 * Expects button structure:
 *   <button class="...submit">
 *     <span class="...submit-text">Text</span>
 *     <span class="...submit-loading" hidden>spinner</span>
 *   </button>
 *
 * @param {HTMLFormElement} form - The form element
 * @param {boolean} loading - Whether to show loading state
 * @param {string} [submitSelector='[type="submit"]'] - Submit button selector
 */
export function setSubmitLoading(form, loading, submitSelector = '[type="submit"]') {
    const submitBtn = form.querySelector(submitSelector);
    if (!submitBtn) return;

    const textEl = submitBtn.querySelector('[class*="submit-text"]');
    const loadingEl = submitBtn.querySelector('[class*="submit-loading"]');

    submitBtn.disabled = loading;
    if (textEl) textEl.hidden = loading;
    if (loadingEl) loadingEl.hidden = !loading;
}

/**
 * Show an error message on a form.
 *
 * @param {HTMLFormElement} form - The form element
 * @param {string} message - Error message to display
 */
export function showFormError(form, message) {
    const errorEl = form.querySelector('[data-auth-error]');
    if (errorEl) {
        errorEl.textContent = message;
        errorEl.hidden = false;
    }
}

/**
 * Clear error message on a form.
 *
 * @param {HTMLFormElement} form - The form element
 */
export function clearFormError(form) {
    const errorEl = form.querySelector('[data-auth-error]');
    if (errorEl) {
        errorEl.textContent = '';
        errorEl.hidden = true;
    }
}

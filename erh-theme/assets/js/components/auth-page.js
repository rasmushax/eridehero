/**
 * Auth Page Controller
 *
 * Handles the full-page login (/login/) and reset-password (/reset-password/) pages.
 * Uses hash-based routing for state switching (signin/signup/forgot).
 * Delegates all auth logic to auth-core.js.
 *
 * @module components/auth-page
 */

import {
    submitAuthForm,
    submitPasswordReset,
    initSocialLogin,
    handleAuthSuccess,
    setSubmitLoading,
    showFormError,
    clearFormError,
} from '../utils/auth-core.js';

/**
 * Map hash values to auth state IDs.
 */
const HASH_STATE_MAP = {
    '#register': 'signup',
    '#signup': 'signup',
    '#forgot': 'forgot',
};

/**
 * State ID to page title suffix.
 */
const STATE_TITLES = {
    signin: 'Sign in',
    signup: 'Create account',
    forgot: 'Reset password',
    'forgot-sent': 'Check your email',
};

/**
 * Initialize the login page controller.
 */
function initLoginPage() {
    const container = document.querySelector('[data-auth-page]');
    if (!container) return;

    const redirect = container.dataset.redirect || '';
    let isSubmitting = false;

    // Determine initial state from hash.
    function getStateFromHash() {
        return HASH_STATE_MAP[window.location.hash] || 'signin';
    }

    // Show a specific auth state.
    function showState(stateId) {
        const states = container.querySelectorAll('.auth-state');
        states.forEach((el) => {
            el.hidden = el.id !== `auth-${stateId}`;
        });

        // Update page title.
        const suffix = STATE_TITLES[stateId] || 'Sign in';
        document.title = `${suffix} – ${document.title.split('–').pop().trim()}`;

        // Focus first input in visible state.
        setTimeout(() => {
            const visibleState = container.querySelector(`.auth-state:not([hidden])`);
            const firstInput = visibleState?.querySelector('input:not([type="hidden"])');
            if (firstInput) firstInput.focus();
        }, 50);
    }

    // Set initial state.
    showState(getStateFromHash());

    // Listen for hash changes.
    window.addEventListener('hashchange', () => {
        showState(getStateFromHash());
    });

    // Handle state switching links (update hash which triggers hashchange).
    container.addEventListener('click', (e) => {
        const switchLink = e.target.closest('[data-auth-switch]');
        if (switchLink) {
            e.preventDefault();
            const target = switchLink.dataset.authSwitch;

            if (target === 'signin') {
                // Remove hash for signin (clean URL).
                window.history.pushState({}, '', window.location.pathname + window.location.search);
                showState('signin');
            } else {
                const hashMap = { signup: '#register', forgot: '#forgot' };
                window.location.hash = hashMap[target] || '#' + target;
            }
        }

        // Handle social login buttons (full-page redirect, not popup).
        const socialBtn = e.target.closest('[data-social-provider]');
        if (socialBtn) {
            const provider = socialBtn.dataset.socialProvider;
            initSocialLogin(provider, {
                popup: false,
                redirect: redirect || window.location.href,
            });
        }
    });

    // Handle form submissions.
    container.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const formType = form.dataset.authForm;
        if (!formType || isSubmitting) return;

        const formData = Object.fromEntries(new FormData(form));

        isSubmitting = true;
        setSubmitLoading(form, true);
        clearFormError(form);

        try {
            const result = await submitAuthForm(formType, formData);

            if (formType === 'forgot') {
                showState('forgot-sent');
            } else {
                handleAuthSuccess(formType, result.needsOnboarding || false, redirect);
            }
        } catch (error) {
            showFormError(form, error.message);
        } finally {
            isSubmitting = false;
            setSubmitLoading(form, false);
        }
    });

    // Show error from URL param (e.g., OAuth error redirect).
    const urlError = new URLSearchParams(window.location.search).get('error');
    if (urlError) {
        const signinForm = container.querySelector('[data-auth-form="signin"]');
        if (signinForm) {
            showFormError(signinForm, decodeURIComponent(urlError));
        }
        // Clean URL.
        const cleanUrl = window.location.pathname + window.location.hash;
        window.history.replaceState({}, '', cleanUrl);
    }
}

/**
 * Initialize the reset-password page controller.
 */
export function initResetPassword() {
    const container = document.querySelector('[data-reset-password-page]');
    if (!container) return;

    const key = container.dataset.key;
    const login = container.dataset.login;
    let isSubmitting = false;

    if (!key || !login) return; // Missing params — PHP already shows error state.

    const form = container.querySelector('[data-auth-form="reset-password"]');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (isSubmitting) return;

        const formData = Object.fromEntries(new FormData(form));

        // Client-side validation: passwords match.
        if (formData.password !== formData.password_confirm) {
            showFormError(form, 'Passwords do not match.');
            return;
        }

        isSubmitting = true;
        setSubmitLoading(form, true);
        clearFormError(form);

        try {
            await submitPasswordReset({
                key: key,
                login: login,
                password: formData.password,
                password_confirm: formData.password_confirm,
            });

            // Show success state.
            const resetForm = document.getElementById('reset-form');
            const resetSuccess = document.getElementById('reset-success');
            if (resetForm) resetForm.hidden = true;
            if (resetSuccess) resetSuccess.hidden = false;
        } catch (error) {
            showFormError(form, error.message);
        } finally {
            isSubmitting = false;
            setSubmitLoading(form, false);
        }
    });
}

// Auto-initialize login page on import.
initLoginPage();

/**
 * ERideHero Auth Modal
 *
 * Authentication modal with sign-in, sign-up, and forgot password states.
 * Supports social login (Google, Facebook, Reddit) via popup windows.
 *
 * Usage:
 *   import { AuthModal } from './components/auth-modal.js';
 *
 *   // Open sign-in modal
 *   AuthModal.open();
 *
 *   // Open with callback after successful auth
 *   AuthModal.open({
 *       initialState: 'signup',
 *       onSuccess: () => { ... }
 *   });
 *
 *   // Open for a specific action (stored in sessionStorage for post-reload)
 *   AuthModal.openForAction('price-alert', { productId: 123 });
 */

import { Modal } from './modal.js';
import { Toast } from './toast.js';

const REST_URL = window.erhData?.restUrl || '/wp-json/erh/v1/';
const STORAGE_KEY = 'erh_pending_action';

class AuthModalManager {
    constructor() {
        this.modal = null;
        this.currentState = 'signin';
        this.onSuccessCallback = null;
        this.isSubmitting = false;
    }

    /**
     * Open the auth modal
     * @param {Object} options
     * @param {string} options.initialState - 'signin', 'signup', or 'forgot'
     * @param {Function} options.onSuccess - Callback after successful auth
     */
    open(options = {}) {
        const { initialState = 'signin', onSuccess = null } = options;

        this.currentState = initialState;
        this.onSuccessCallback = onSuccess;

        if (!this.modal) {
            this.createModal();
        }

        this.renderState(initialState);
        this.modal.open();
    }

    /**
     * Open modal for a specific action (stores in sessionStorage)
     * @param {string} action - Action identifier (e.g., 'price-alert')
     * @param {Object} data - Action data to store
     */
    openForAction(action, data = {}) {
        // Store the pending action
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ action, data }));

        this.open({
            onSuccess: () => {
                // Page will reload, action will be processed on load
            }
        });
    }

    /**
     * Close the modal
     */
    close() {
        if (this.modal) {
            this.modal.close();
        }
    }

    /**
     * Create the modal element
     */
    createModal() {
        const modalEl = document.createElement('div');
        modalEl.className = 'modal auth-modal';
        modalEl.id = 'auth-modal';
        modalEl.setAttribute('role', 'dialog');
        modalEl.setAttribute('aria-modal', 'true');
        modalEl.innerHTML = `
            <div class="modal-content modal-content--sm">
                <button class="modal-close" data-modal-close aria-label="Close">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
                <div class="auth-modal-body"></div>
            </div>
        `;

        document.body.appendChild(modalEl);
        this.modal = new Modal(modalEl);
        this.bodyEl = modalEl.querySelector('.auth-modal-body');

        // Handle state switching via event delegation
        modalEl.addEventListener('click', (e) => {
            const switchLink = e.target.closest('[data-auth-switch]');
            if (switchLink) {
                e.preventDefault();
                this.renderState(switchLink.dataset.authSwitch);
            }

            const socialBtn = e.target.closest('[data-social-provider]');
            if (socialBtn) {
                this.handleSocialLogin(socialBtn.dataset.socialProvider);
            }
        });

        // Handle form submissions
        modalEl.addEventListener('submit', (e) => {
            e.preventDefault();
            const form = e.target;
            if (form.dataset.authForm) {
                this.handleFormSubmit(form.dataset.authForm, form);
            }
        });
    }

    /**
     * Render a specific auth state
     */
    renderState(state) {
        this.currentState = state;

        const states = {
            signin: this.getSignInHTML(),
            signup: this.getSignUpHTML(),
            forgot: this.getForgotHTML(),
            'forgot-sent': this.getForgotSentHTML()
        };

        this.bodyEl.innerHTML = states[state] || states.signin;

        // Update aria-labelledby
        const title = this.bodyEl.querySelector('.auth-modal-title');
        if (title) {
            title.id = 'auth-modal-title';
            this.modal.element.setAttribute('aria-labelledby', 'auth-modal-title');
        }

        // Focus first input
        setTimeout(() => {
            const firstInput = this.bodyEl.querySelector('input:not([type="hidden"])');
            if (firstInput) firstInput.focus();
        }, 100);
    }

    /**
     * Sign In HTML
     */
    getSignInHTML() {
        return `
            <div class="auth-modal-header">
                <h2 class="auth-modal-title">Welcome back</h2>
                <p class="auth-modal-subtitle">Sign in to your account to continue</p>
            </div>

            <div class="auth-modal-social">
                <button type="button" class="auth-social-btn" data-social-provider="google">
                    <svg class="auth-social-icon" viewBox="0 0 24 24">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                    </svg>
                    Continue with Google
                </button>
                <button type="button" class="auth-social-btn" data-social-provider="facebook">
                    <svg class="auth-social-icon" viewBox="0 0 24 24" fill="#1877F2">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                    Continue with Facebook
                </button>
                <button type="button" class="auth-social-btn" data-social-provider="reddit">
                    <svg class="auth-social-icon" viewBox="0 0 24 24" fill="#FF4500">
                        <path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z"/>
                    </svg>
                    Continue with Reddit
                </button>
            </div>

            <div class="auth-modal-divider">
                <span>or continue with email</span>
            </div>

            <form class="auth-modal-form" data-auth-form="signin">
                <div class="auth-modal-field">
                    <label for="signin-email" class="auth-modal-label">Email address</label>
                    <input type="email" id="signin-email" name="email" class="auth-modal-input" placeholder="you@example.com" required>
                </div>

                <div class="auth-modal-field">
                    <div class="auth-modal-label-row">
                        <label for="signin-password" class="auth-modal-label">Password</label>
                        <a href="#" class="auth-modal-forgot" data-auth-switch="forgot">Forgot password?</a>
                    </div>
                    <input type="password" id="signin-password" name="password" class="auth-modal-input" placeholder="Enter your password" required>
                </div>

                <div class="auth-modal-error" data-auth-error hidden></div>

                <button type="submit" class="btn btn-primary btn-block auth-modal-submit">
                    <span class="auth-modal-submit-text">Sign in</span>
                    <span class="auth-modal-submit-loading" hidden>
                        <svg class="spinner" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4" stroke-linecap="round"/></svg>
                    </span>
                </button>
            </form>

            <p class="auth-modal-toggle">
                Don't have an account? <a href="#" data-auth-switch="signup">Create one for free</a>
            </p>
        `;
    }

    /**
     * Sign Up HTML
     */
    getSignUpHTML() {
        return `
            <a href="#" class="auth-modal-back" data-auth-switch="signin">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M12.5 15L7.5 10L12.5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Back to sign in
            </a>

            <div class="auth-modal-header">
                <h2 class="auth-modal-title">Create your account</h2>
                <p class="auth-modal-subtitle">Join thousands of riders tracking prices</p>
            </div>

            <div class="auth-modal-social">
                <button type="button" class="auth-social-btn" data-social-provider="google">
                    <svg class="auth-social-icon" viewBox="0 0 24 24">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                    </svg>
                    Continue with Google
                </button>
                <button type="button" class="auth-social-btn" data-social-provider="facebook">
                    <svg class="auth-social-icon" viewBox="0 0 24 24" fill="#1877F2">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                    Continue with Facebook
                </button>
                <button type="button" class="auth-social-btn" data-social-provider="reddit">
                    <svg class="auth-social-icon" viewBox="0 0 24 24" fill="#FF4500">
                        <path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z"/>
                    </svg>
                    Continue with Reddit
                </button>
            </div>

            <div class="auth-modal-divider">
                <span>or continue with email</span>
            </div>

            <form class="auth-modal-form" data-auth-form="signup">
                <div class="auth-modal-field">
                    <label for="signup-email" class="auth-modal-label">Email address</label>
                    <input type="email" id="signup-email" name="email" class="auth-modal-input" placeholder="you@example.com" required>
                </div>

                <div class="auth-modal-field">
                    <label for="signup-password" class="auth-modal-label">Create password</label>
                    <input type="password" id="signup-password" name="password" class="auth-modal-input" placeholder="At least 8 characters" minlength="8" required>
                </div>

                <div class="auth-modal-error" data-auth-error hidden></div>

                <button type="submit" class="btn btn-primary btn-block auth-modal-submit">
                    <span class="auth-modal-submit-text">Create account</span>
                    <span class="auth-modal-submit-loading" hidden>
                        <svg class="spinner" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4" stroke-linecap="round"/></svg>
                    </span>
                </button>
            </form>

            <p class="auth-modal-legal">
                By creating an account, you agree to our <a href="/terms">Terms</a> and <a href="/privacy">Privacy Policy</a>.
            </p>
        `;
    }

    /**
     * Forgot Password HTML
     */
    getForgotHTML() {
        return `
            <a href="#" class="auth-modal-back" data-auth-switch="signin">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M12.5 15L7.5 10L12.5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Back to sign in
            </a>

            <div class="auth-modal-header">
                <h2 class="auth-modal-title">Reset your password</h2>
                <p class="auth-modal-subtitle">Enter your email and we'll send you a reset link</p>
            </div>

            <form class="auth-modal-form" data-auth-form="forgot">
                <div class="auth-modal-field">
                    <label for="forgot-email" class="auth-modal-label">Email address</label>
                    <input type="email" id="forgot-email" name="email" class="auth-modal-input" placeholder="you@example.com" required>
                </div>

                <div class="auth-modal-error" data-auth-error hidden></div>

                <button type="submit" class="btn btn-primary btn-block auth-modal-submit">
                    <span class="auth-modal-submit-text">Send reset link</span>
                    <span class="auth-modal-submit-loading" hidden>
                        <svg class="spinner" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4" stroke-linecap="round"/></svg>
                    </span>
                </button>
            </form>
        `;
    }

    /**
     * Forgot Password Sent HTML
     */
    getForgotSentHTML() {
        return `
            <div class="auth-modal-success">
                <div class="auth-modal-success-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>
                <h2 class="auth-modal-title">Check your email</h2>
                <p class="auth-modal-subtitle">We've sent a password reset link to your email address. The link will expire in 24 hours.</p>
                <button type="button" class="btn btn-secondary btn-block" data-modal-close>Done</button>
            </div>
        `;
    }

    /**
     * Handle form submission
     */
    async handleFormSubmit(formType, form) {
        if (this.isSubmitting) return;

        const submitBtn = form.querySelector('.auth-modal-submit');
        const textEl = form.querySelector('.auth-modal-submit-text');
        const loadingEl = form.querySelector('.auth-modal-submit-loading');
        const errorEl = form.querySelector('[data-auth-error]');

        // Get form data
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);

        // Show loading state
        this.isSubmitting = true;
        submitBtn.disabled = true;
        textEl.hidden = true;
        loadingEl.hidden = false;
        errorEl.hidden = true;

        try {
            let endpoint;
            switch (formType) {
                case 'signin':
                    endpoint = 'auth/login';
                    break;
                case 'signup':
                    endpoint = 'auth/register';
                    break;
                case 'forgot':
                    endpoint = 'auth/forgot-password';
                    break;
            }

            const response = await fetch(`${REST_URL}${endpoint}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.erhData?.nonce || ''
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Something went wrong');
            }

            // Handle success
            if (formType === 'forgot') {
                this.renderState('forgot-sent');
            } else {
                // Login or register success - reload page
                this.handleAuthSuccess(formType);
            }

        } catch (error) {
            // Show error
            errorEl.textContent = error.message;
            errorEl.hidden = false;
        } finally {
            this.isSubmitting = false;
            submitBtn.disabled = false;
            textEl.hidden = false;
            loadingEl.hidden = true;
        }
    }

    /**
     * Handle social login
     */
    handleSocialLogin(provider) {
        const width = 500;
        const height = 600;
        const left = (window.innerWidth - width) / 2 + window.screenX;
        const top = (window.innerHeight - height) / 2 + window.screenY;

        const popup = window.open(
            `${REST_URL}auth/social/${provider}`,
            'auth-popup',
            `width=${width},height=${height},left=${left},top=${top},popup=1`
        );

        // Listen for completion message from popup
        const handleMessage = (event) => {
            // Verify origin matches our site
            if (event.origin !== window.location.origin) return;

            if (event.data?.type === 'auth-success') {
                window.removeEventListener('message', handleMessage);
                popup?.close();
                this.handleAuthSuccess('social');
            } else if (event.data?.type === 'auth-error') {
                window.removeEventListener('message', handleMessage);
                popup?.close();
                Toast.error(event.data.message || 'Authentication failed');
            }
        };

        window.addEventListener('message', handleMessage);

        // Fallback: Check if popup closed without sending message
        const checkClosed = setInterval(() => {
            if (popup?.closed) {
                clearInterval(checkClosed);
                window.removeEventListener('message', handleMessage);
            }
        }, 500);
    }

    /**
     * Handle successful authentication
     */
    handleAuthSuccess(method) {
        this.close();

        // Show success toast
        Toast.success(method === 'signup' ? 'Account created!' : 'Signed in successfully');

        // Reload page after brief delay to show toast
        setTimeout(() => {
            window.location.reload();
        }, 500);
    }

    /**
     * Check for pending action on page load
     */
    checkPendingAction() {
        const stored = sessionStorage.getItem(STORAGE_KEY);
        if (!stored) return null;

        try {
            const { action, data } = JSON.parse(stored);
            sessionStorage.removeItem(STORAGE_KEY);
            return { action, data };
        } catch {
            sessionStorage.removeItem(STORAGE_KEY);
            return null;
        }
    }
}

// Singleton instance
const AuthModal = new AuthModalManager();

// Auto-bind login/signup buttons on page
function initAuthTriggers() {
    document.addEventListener('click', (e) => {
        const loginBtn = e.target.closest('[data-auth-trigger="login"], .btn-login');
        const signupBtn = e.target.closest('[data-auth-trigger="signup"], .btn-signup');

        if (loginBtn) {
            e.preventDefault();
            AuthModal.open({ initialState: 'signin' });
        }

        if (signupBtn) {
            e.preventDefault();
            AuthModal.open({ initialState: 'signup' });
        }
    });
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAuthTriggers);
} else {
    initAuthTriggers();
}

export { AuthModal };

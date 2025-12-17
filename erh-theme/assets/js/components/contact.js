/**
 * Contact Form Handler
 *
 * Handles form validation and submission via REST API.
 */

export function initContactForm() {
    const form = document.getElementById('erh-contact-form');
    if (!form) return;

    const submitBtn = document.getElementById('contact-submit');
    const btnText = submitBtn?.querySelector('.btn-text');
    const btnLoading = submitBtn?.querySelector('.btn-loading');
    const successMsg = document.getElementById('contact-success');
    const errorMsg = document.getElementById('contact-error');

    // Field references
    const fields = {
        name: form.querySelector('[name="name"]'),
        email: form.querySelector('[name="email"]'),
        topic: form.querySelector('[name="topic"]'),
        message: form.querySelector('[name="message"]'),
    };

    // Validation rules
    const validators = {
        name: (value) => {
            if (!value.trim()) return 'Please enter your name';
            if (value.length > 100) return 'Name is too long';
            return null;
        },
        email: (value) => {
            if (!value.trim()) return 'Please enter your email';
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) return 'Please enter a valid email address';
            return null;
        },
        topic: (value) => {
            if (!value) return 'Please select a topic';
            return null;
        },
        message: (value) => {
            if (!value.trim()) return 'Please enter a message';
            if (value.trim().length < 10) return 'Message is too short (minimum 10 characters)';
            if (value.length > 5000) return 'Message is too long (maximum 5000 characters)';
            return null;
        },
    };

    /**
     * Show field error
     */
    function showError(fieldName, message) {
        const errorEl = form.querySelector(`[data-error="${fieldName}"]`);
        const field = fields[fieldName];

        if (errorEl) {
            errorEl.textContent = message;
            errorEl.hidden = false;
        }

        if (field) {
            field.classList.add('is-invalid');
            field.setAttribute('aria-invalid', 'true');
        }
    }

    /**
     * Clear field error
     */
    function clearError(fieldName) {
        const errorEl = form.querySelector(`[data-error="${fieldName}"]`);
        const field = fields[fieldName];

        if (errorEl) {
            errorEl.textContent = '';
            errorEl.hidden = true;
        }

        if (field) {
            field.classList.remove('is-invalid');
            field.removeAttribute('aria-invalid');
        }
    }

    /**
     * Clear all errors
     */
    function clearAllErrors() {
        Object.keys(fields).forEach(clearError);
        if (errorMsg) {
            errorMsg.hidden = true;
            errorMsg.querySelector('p').textContent = '';
        }
    }

    /**
     * Show global error
     */
    function showGlobalError(message) {
        if (errorMsg) {
            errorMsg.querySelector('p').textContent = message;
            errorMsg.hidden = false;
            errorMsg.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    /**
     * Validate all fields
     */
    function validateForm() {
        let isValid = true;
        clearAllErrors();

        Object.entries(validators).forEach(([fieldName, validate]) => {
            const field = fields[fieldName];
            if (!field) return;

            const error = validate(field.value);
            if (error) {
                showError(fieldName, error);
                isValid = false;
            }
        });

        return isValid;
    }

    /**
     * Set loading state
     */
    function setLoading(loading) {
        if (submitBtn) {
            submitBtn.disabled = loading;
        }
        if (btnText) {
            btnText.hidden = loading;
        }
        if (btnLoading) {
            btnLoading.hidden = !loading;
        }
    }

    /**
     * Show success state
     */
    function showSuccess() {
        form.reset();
        form.querySelectorAll('.form-group, .form-row, button[type="submit"]').forEach(el => {
            el.style.display = 'none';
        });
        if (successMsg) {
            successMsg.hidden = false;
        }
    }

    // Real-time validation on blur
    Object.entries(fields).forEach(([fieldName, field]) => {
        if (!field) return;

        field.addEventListener('blur', () => {
            const validate = validators[fieldName];
            if (!validate) return;

            const error = validate(field.value);
            if (error) {
                showError(fieldName, error);
            } else {
                clearError(fieldName);
            }
        });

        // Clear error on input
        field.addEventListener('input', () => {
            clearError(fieldName);
        });
    });

    // Form submission
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Validate
        if (!validateForm()) {
            // Focus first invalid field
            const firstInvalid = form.querySelector('.is-invalid');
            if (firstInvalid) {
                firstInvalid.focus();
            }
            return;
        }

        setLoading(true);
        clearAllErrors();

        try {
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            // Get nonce from form
            const nonce = form.querySelector('[name="erh_contact_nonce"]')?.value;
            if (nonce) {
                data._wpnonce = nonce;
            }

            const response = await fetch('/wp-json/erh/v1/contact', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Something went wrong. Please try again.');
            }

            showSuccess();

        } catch (error) {
            showGlobalError(error.message || 'Failed to send message. Please try again.');
        } finally {
            setLoading(false);
        }
    });
}

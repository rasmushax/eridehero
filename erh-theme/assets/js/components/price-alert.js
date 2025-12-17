/**
 * ERideHero Price Alert Modal
 *
 * Handles the price alert form interactions:
 * - Toggle between target price and percentage drop
 * - Quick suggestion buttons
 * - Form validation and submission
 */

function initPriceAlert() {
    const modal = document.getElementById('price-alert-modal');
    if (!modal) return;

    const form = modal.querySelector('#price-alert-form');
    const toggleBtns = modal.querySelectorAll('.price-alert-toggle-btn');
    const targetGroup = modal.querySelector('#alert-target-group');
    const dropGroup = modal.querySelector('#alert-drop-group');
    const targetInput = modal.querySelector('#alert-target-price');
    const dropInput = modal.querySelector('#alert-drop-percent');
    const emailInput = modal.querySelector('#alert-email');

    // Handle toggle between target price and drop percentage
    toggleBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const type = btn.dataset.alertType;

            // Update active state
            toggleBtns.forEach(b => b.classList.remove('is-active'));
            btn.classList.add('is-active');

            // Show/hide appropriate input group
            if (type === 'target') {
                targetGroup.hidden = false;
                dropGroup.hidden = true;
                targetInput.focus();
            } else {
                targetGroup.hidden = true;
                dropGroup.hidden = false;
                dropInput.focus();
            }
        });
    });

    // Handle suggestion buttons
    modal.querySelectorAll('.price-alert-suggestion').forEach(suggestion => {
        suggestion.addEventListener('click', () => {
            const value = suggestion.dataset.value;
            const group = suggestion.closest('.price-alert-input-group');
            const input = group.querySelector('.price-alert-input');

            if (input) {
                input.value = value;
                input.focus();

                // Trigger input event for any listeners
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    });

    // Handle form submission
    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();

            // Determine which type is active
            const activeType = modal.querySelector('.price-alert-toggle-btn.is-active')?.dataset.alertType;

            // Get values
            const email = emailInput?.value;
            const targetPrice = activeType === 'target' ? targetInput?.value : null;
            const dropPercent = activeType === 'drop' ? dropInput?.value : null;

            // Basic validation
            if (!email) {
                emailInput?.focus();
                return;
            }

            if (activeType === 'target' && !targetPrice) {
                targetInput?.focus();
                return;
            }

            if (activeType === 'drop' && !dropPercent) {
                dropInput?.focus();
                return;
            }

            // Prepare data
            const alertData = {
                email,
                type: activeType,
                ...(targetPrice && { targetPrice: parseFloat(targetPrice) }),
                ...(dropPercent && { dropPercent: parseFloat(dropPercent) })
            };

            // Dispatch custom event for handling
            const submitEvent = new CustomEvent('priceAlert:submit', {
                bubbles: true,
                detail: alertData
            });
            form.dispatchEvent(submitEvent);

            // Show success state (placeholder - replace with actual API call)
            showSuccessState(modal, alertData);
        });
    }

    // Focus email input when modal opens (if target price is pre-filled)
    modal.addEventListener('modal:afterOpen', () => {
        // Small delay to ensure modal animation completes
        setTimeout(() => {
            if (targetInput?.value) {
                emailInput?.focus();
            } else {
                targetInput?.focus();
            }
        }, 100);
    });

    // Reset form when modal closes
    modal.addEventListener('modal:afterClose', () => {
        resetForm(modal);
    });
}

/**
 * Show success state after form submission
 */
function showSuccessState(modal, data) {
    const body = modal.querySelector('.modal-body');
    if (!body) return;

    // Store original content
    const originalContent = body.innerHTML;

    // Create success message
    const priceText = data.targetPrice
        ? `$${data.targetPrice}`
        : `${data.dropPercent}% drop`;

    body.innerHTML = `
        <div class="price-alert-success">
            <div class="price-alert-success-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
            <h3 class="price-alert-success-title">Alert created!</h3>
            <p class="price-alert-success-message">
                We'll email <strong>${data.email}</strong> when the price hits <strong>${priceText}</strong>.
            </p>
            <button type="button" class="btn btn-secondary" data-modal-close>Done</button>
        </div>
    `;

    // Add click handler to the new close button
    const closeBtn = body.querySelector('[data-modal-close]');
    closeBtn?.addEventListener('click', () => {
        // Restore original content after close
        setTimeout(() => {
            body.innerHTML = originalContent;
            // Re-init event listeners
            initPriceAlert();
        }, 200);
    });
}

/**
 * Reset form to initial state
 */
function resetForm(modal) {
    const form = modal.querySelector('#price-alert-form');
    const toggleBtns = modal.querySelectorAll('.price-alert-toggle-btn');
    const targetGroup = modal.querySelector('#alert-target-group');
    const dropGroup = modal.querySelector('#alert-drop-group');

    // Reset form fields
    form?.reset();

    // Reset toggle to target price
    toggleBtns.forEach(btn => {
        btn.classList.toggle('is-active', btn.dataset.alertType === 'target');
    });

    // Show target group, hide drop group
    if (targetGroup) targetGroup.hidden = false;
    if (dropGroup) dropGroup.hidden = true;
}

// Auto-initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPriceAlert);
} else {
    initPriceAlert();
}

export { initPriceAlert };

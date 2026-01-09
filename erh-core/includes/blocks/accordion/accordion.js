/**
 * ERH Accordion Block
 *
 * Accessible accordion with keyboard navigation.
 */
(function () {
    'use strict';

    /**
     * Initialize all accordions on the page.
     */
    function initAccordions() {
        const accordions = document.querySelectorAll('[data-erh-accordion]');
        accordions.forEach(initAccordion);
    }

    /**
     * Initialize a single accordion.
     *
     * @param {HTMLElement} accordion - The accordion container element.
     */
    function initAccordion(accordion) {
        // Skip if already initialized.
        if (accordion.dataset.initialized === 'true') {
            return;
        }

        const triggers = accordion.querySelectorAll('[data-accordion-trigger]');

        triggers.forEach((trigger) => {
            // Click handler.
            trigger.addEventListener('click', handleTriggerClick);

            // Keyboard handler.
            trigger.addEventListener('keydown', handleTriggerKeydown);
        });

        accordion.dataset.initialized = 'true';
    }

    /**
     * Handle trigger click.
     *
     * @param {Event} event - The click event.
     */
    function handleTriggerClick(event) {
        const trigger = event.currentTarget;
        togglePanel(trigger);
    }

    /**
     * Handle keyboard navigation.
     *
     * @param {KeyboardEvent} event - The keydown event.
     */
    function handleTriggerKeydown(event) {
        const trigger = event.currentTarget;
        const accordion = trigger.closest('[data-erh-accordion]');
        const triggers = Array.from(
            accordion.querySelectorAll('[data-accordion-trigger]')
        );
        const currentIndex = triggers.indexOf(trigger);

        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                focusTrigger(triggers, currentIndex + 1);
                break;

            case 'ArrowUp':
                event.preventDefault();
                focusTrigger(triggers, currentIndex - 1);
                break;

            case 'Home':
                event.preventDefault();
                focusTrigger(triggers, 0);
                break;

            case 'End':
                event.preventDefault();
                focusTrigger(triggers, triggers.length - 1);
                break;
        }
    }

    /**
     * Focus a trigger by index with wrapping.
     *
     * @param {HTMLElement[]} triggers - Array of trigger elements.
     * @param {number} index - Target index.
     */
    function focusTrigger(triggers, index) {
        const length = triggers.length;
        const targetIndex = ((index % length) + length) % length;
        triggers[targetIndex].focus();
    }

    /**
     * Toggle an accordion panel.
     *
     * @param {HTMLElement} trigger - The trigger button element.
     */
    function togglePanel(trigger) {
        const panelId = trigger.getAttribute('aria-controls');
        const panel = document.getElementById(panelId);

        if (!panel) {
            return;
        }

        const isExpanded = trigger.getAttribute('aria-expanded') === 'true';
        const newState = !isExpanded;

        // Update trigger state.
        trigger.setAttribute('aria-expanded', String(newState));
        trigger.classList.toggle('is-active', newState);

        // Update panel state.
        panel.classList.toggle('is-open', newState);
    }

    // Initialize on DOM ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAccordions);
    } else {
        initAccordions();
    }

    // Re-initialize when new content is added (for AJAX/dynamic content).
    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        if (node.matches('[data-erh-accordion]')) {
                            initAccordion(node);
                        }
                        node.querySelectorAll('[data-erh-accordion]').forEach(
                            initAccordion
                        );
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });
    }
})();

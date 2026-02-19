/**
 * Popover Component
 *
 * Click-triggered popovers with viewport-aware positioning.
 * Uses display:none when hidden to prevent overflow issues.
 * Two-step show: is-active (display:block) → is-visible (opacity transition).
 * Uses fixed positioning for reliable viewport constraint.
 */

// Configuration
const CONFIG = {
    offset: 8,             // Distance from trigger element (px)
    viewportPadding: 12    // Min distance from viewport edge (px)
};

// State
let activePopover = null;
let activeTrigger = null;
let hideTransitionHandler = null;

export function initPopovers() {
    const triggers = document.querySelectorAll('[data-popover-trigger]');
    if (!triggers.length) return null;

    // Initialize triggers
    triggers.forEach(trigger => {
        const popoverId = trigger.getAttribute('data-popover-trigger');
        const popover = document.getElementById(popoverId);
        if (!popover) return;

        // Store original position preference
        if (popover.classList.contains('popover--bottom')) {
            popover.dataset.preferredPosition = 'bottom';
        } else {
            popover.dataset.preferredPosition = 'top';
        }

        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();

            if (popover.classList.contains('is-visible')) {
                closePopover();
            } else {
                openPopover(popover, trigger);
            }
        });

        // Close button inside popover
        const closeBtn = popover.querySelector('[class*="popover-close"]');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => closePopover());
        }
    });

    // Global listeners
    document.addEventListener('click', handleClickOutside);
    document.addEventListener('keydown', handleEscape);
    window.addEventListener('resize', handleResize, { passive: true });
    window.addEventListener('scroll', handleScroll, { passive: true, capture: true });

    return {
        close: closePopover
    };
}

function openPopover(popover, trigger) {
    // Close any existing popover immediately (no animation)
    if (activePopover && activePopover !== popover) {
        cancelHideTransition(activePopover);
        activePopover.classList.remove('is-visible', 'is-active');
        activePopover.setAttribute('aria-hidden', 'true');
        resetPopoverStyles(activePopover);
    }

    // Cancel any pending hide transition on this popover.
    cancelHideTransition(popover);

    activePopover = popover;
    activeTrigger = trigger;

    // Step 1: Make visible for measurement (is-active = display:block, no opacity yet).
    popover.classList.add('is-active');
    popover.setAttribute('aria-hidden', 'false');

    // Position (now measurable since display:block).
    positionPopover(popover, trigger);

    // Step 2: Trigger opacity transition on next frame.
    requestAnimationFrame(() => {
        popover.classList.add('is-visible');
    });
}

function closePopover() {
    if (!activePopover) return;

    const popover = activePopover;

    // If not even active, nothing to do.
    if (!popover.classList.contains('is-active')) {
        activePopover = null;
        activeTrigger = null;
        return;
    }

    // Step 1: Remove visible (triggers fade-out transition).
    popover.classList.remove('is-visible');
    popover.setAttribute('aria-hidden', 'true');

    // Step 2: After transition, remove from layout entirely.
    cancelHideTransition(popover);
    const handler = function onEnd(e) {
        if (e.propertyName !== 'opacity') return;
        popover.removeEventListener('transitionend', handler);
        hideTransitionHandler = null;

        // Only remove if still hidden (not re-shown during transition).
        if (!popover.classList.contains('is-visible')) {
            popover.classList.remove('is-active');
            resetPopoverStyles(popover);
        }
    };
    hideTransitionHandler = handler;
    popover.addEventListener('transitionend', handler);

    // Fallback: if transitionend never fires.
    setTimeout(() => {
        if (hideTransitionHandler === handler) {
            popover.removeEventListener('transitionend', handler);
            hideTransitionHandler = null;
            popover.classList.remove('is-active');
            resetPopoverStyles(popover);
        }
    }, 200);

    activePopover = null;
    activeTrigger = null;
}

function cancelHideTransition(popover) {
    if (hideTransitionHandler) {
        popover.removeEventListener('transitionend', hideTransitionHandler);
        hideTransitionHandler = null;
    }
}

function resetPopoverStyles(popover) {
    popover.style.left = '';
    popover.style.top = '';

    // Reset position class to original
    const preferred = popover.dataset.preferredPosition || 'top';
    popover.classList.remove('popover--top', 'popover--bottom');
    popover.classList.add(`popover--${preferred}`);

    // Reset arrow offset
    const arrowEl = popover.querySelector('.popover-arrow');
    if (arrowEl) {
        arrowEl.style.removeProperty('--arrow-offset');
    }
}

function positionPopover(popover, trigger) {
    const triggerRect = trigger.getBoundingClientRect();
    const popoverRect = popover.getBoundingClientRect();

    const viewport = {
        width: window.innerWidth,
        height: window.innerHeight
    };

    const preferredPosition = popover.dataset.preferredPosition || 'top';

    // Calculate positions for top and bottom
    const positions = {
        top: {
            x: triggerRect.left + (triggerRect.width - popoverRect.width) / 2,
            y: triggerRect.top - popoverRect.height - CONFIG.offset
        },
        bottom: {
            x: triggerRect.left + (triggerRect.width - popoverRect.width) / 2,
            y: triggerRect.bottom + CONFIG.offset
        }
    };

    // Check if position fits vertically
    function fitsVertically(pos, placement) {
        if (placement === 'top') {
            return pos.y >= CONFIG.viewportPadding;
        } else {
            return pos.y + popoverRect.height <= viewport.height - CONFIG.viewportPadding;
        }
    }

    // Determine final placement (flip if needed)
    let placement = preferredPosition;
    if (!fitsVertically(positions[preferredPosition], preferredPosition)) {
        const opposite = preferredPosition === 'top' ? 'bottom' : 'top';
        if (fitsVertically(positions[opposite], opposite)) {
            placement = opposite;
        }
    }

    // Get position for chosen placement
    let { x, y } = positions[placement];

    // Constrain horizontally within viewport
    const minX = CONFIG.viewportPadding;
    const maxX = viewport.width - popoverRect.width - CONFIG.viewportPadding;
    const constrainedX = Math.max(minX, Math.min(x, maxX));

    // Calculate arrow offset (how much we shifted from centered)
    const triggerCenterX = triggerRect.left + triggerRect.width / 2;
    const popoverCenterX = constrainedX + popoverRect.width / 2;
    const arrowOffset = triggerCenterX - popoverCenterX;

    // Clamp arrow offset so it stays within popover bounds
    const maxArrowOffset = (popoverRect.width / 2) - 20; // 20px from edge minimum
    const clampedArrowOffset = Math.max(-maxArrowOffset, Math.min(arrowOffset, maxArrowOffset));

    // Apply position class
    popover.classList.remove('popover--top', 'popover--bottom');
    popover.classList.add(`popover--${placement}`);

    // Apply fixed positioning
    popover.style.left = `${constrainedX}px`;
    popover.style.top = `${y}px`;

    // Apply arrow offset
    const arrowEl = popover.querySelector('.popover-arrow');
    if (arrowEl) {
        arrowEl.style.setProperty('--arrow-offset', `${clampedArrowOffset}px`);
    }
}

function handleClickOutside(e) {
    if (!activePopover) return;

    // Check if click is inside popover or trigger
    if (activePopover.contains(e.target) || activeTrigger?.contains(e.target)) {
        return;
    }

    closePopover();
}

function handleEscape(e) {
    if (e.key === 'Escape' && activePopover) {
        closePopover();
    }
}

function handleResize() {
    if (activePopover && activeTrigger) {
        positionPopover(activePopover, activeTrigger);
    }
}

function handleScroll() {
    // Close on scroll for simplicity (popover is contextual)
    if (activePopover) {
        closePopover();
    }
}

// Auto-initialize
document.addEventListener('DOMContentLoaded', initPopovers);

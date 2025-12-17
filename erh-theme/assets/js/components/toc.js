/**
 * Table of Contents - Unified Desktop/Mobile Component
 *
 * A reusable ToC component that:
 * - Desktop: Sticky sidebar with scroll spy and nested sections
 * - Mobile: Fixed bar with dropdown, syncs with header visibility
 *
 * Usage:
 *   import { initToc } from './components/toc.js';
 *
 *   // Basic usage
 *   initToc('.toc');
 *
 *   // With options
 *   initToc('.toc', {
 *     offset: 100,
 *     mobileBreakpoint: 890
 *   });
 */

const MOBILE_BREAKPOINT = 890;
const HEADER_FIXED_CLASS = 'header--fixed';
const HEADER_HIDDEN_CLASS = 'header--hidden';
const TOC_SCROLL_THRESHOLD = 700; // ToC only appears after this scroll distance

const defaultOptions = {
    // Offset from top of viewport to trigger active state
    offset: 100,

    // Mobile breakpoint (matches CSS)
    mobileBreakpoint: MOBILE_BREAKPOINT,

    // Class applied to active links
    activeClass: 'is-active',

    // Class applied to parent li when section has subsections
    parentActiveClass: 'is-active',

    // Whether to expand parent groups when child is active
    expandParent: true,

    // Selector for links within the ToC
    linkSelector: 'a[href^="#"]',

    // Throttle scroll events (ms)
    throttleMs: 50
};

/**
 * Initialize Table of Contents
 */
export function initToc(tocElement, options = {}) {
    const toc = typeof tocElement === 'string'
        ? document.querySelector(tocElement)
        : tocElement;

    if (!toc) return null;

    const config = { ...defaultOptions, ...options };
    const header = document.querySelector('.header');
    const links = toc.querySelectorAll(config.linkSelector);

    if (!links.length) return null;

    // Build section map
    const sections = new Map();

    links.forEach(link => {
        const href = link.getAttribute('href');
        if (!href || !href.startsWith('#')) return;

        const id = href.slice(1);
        const element = document.getElementById(id);

        if (element) {
            const parentLi = link.closest('li');
            const grandparentLi = parentLi?.parentElement?.closest('li');
            const isTopLevel = parentLi?.parentElement?.classList.contains('toc-list');

            sections.set(id, {
                element,
                link,
                parentItem: parentLi,
                parentGroup: grandparentLi?.querySelector(':scope > a[href^="#"]') ? grandparentLi : null,
                text: link.textContent.trim(),
                isTopLevel
            });
        }
    });

    if (!sections.size) return null;

    // State
    let lastActiveId = null;
    let ticking = false;
    let isMobileMode = false;
    let isOpen = false;
    let isAnimatingOut = false;
    let toggleButton = null;
    let headerObserver = null;

    /**
     * Check if we're at mobile breakpoint
     */
    function isMobile() {
        return window.innerWidth <= config.mobileBreakpoint;
    }

    /**
     * Create toggle button for mobile mode
     */
    function createToggleButton() {
        if (toggleButton) return toggleButton;

        toggleButton = document.createElement('button');
        toggleButton.className = 'toc-toggle';
        toggleButton.setAttribute('aria-expanded', 'false');
        toggleButton.setAttribute('aria-controls', 'toc-list');
        toggleButton.innerHTML = `
            <svg class="icon" aria-hidden="true"><use href="#icon-list"></use></svg>
            <span class="toc-toggle-label">On this page</span>
            <svg class="icon toc-toggle-chevron" aria-hidden="true"><use href="#icon-chevron-down"></use></svg>
        `;

        toggleButton.addEventListener('click', toggleDropdown);

        return toggleButton;
    }

    /**
     * Enter mobile mode
     */
    function enterMobileMode() {
        if (isMobileMode) return;
        isMobileMode = true;

        // Check if header is fixed (past scroll threshold)
        const headerIsFixed = header?.classList.contains(HEADER_FIXED_CLASS);

        if (headerIsFixed) {
            toc.classList.add('toc--mobile');

            // Add toggle button if not present
            if (!toggleButton) {
                createToggleButton();
            }
            if (!toc.contains(toggleButton)) {
                toc.insertBefore(toggleButton, toc.firstChild);
            }

            // Give toc-list an ID for aria-controls
            const tocList = toc.querySelector('.toc-list');
            if (tocList) tocList.id = 'toc-list';

            // Sync with header visibility
            syncWithHeader();

            // Update label with current section
            updateToggleLabel();
        }

        // Set up header observer
        setupHeaderObserver();
    }

    /**
     * Exit mobile mode
     */
    function exitMobileMode() {
        if (!isMobileMode) return;
        isMobileMode = false;

        toc.classList.remove('toc--mobile', 'is-hidden', 'is-open');

        if (toggleButton && toc.contains(toggleButton)) {
            toggleButton.remove();
        }

        isOpen = false;

        // Remove header observer
        if (headerObserver) {
            headerObserver.disconnect();
            headerObserver = null;
        }
    }

    /**
     * Check and update mobile mode based on scroll position
     */
    function updateMobileMode() {
        if (!isMobile()) {
            exitMobileMode();
            return;
        }

        const pastThreshold = window.scrollY >= TOC_SCROLL_THRESHOLD;

        if (pastThreshold && !toc.classList.contains('toc--mobile')) {
            // Past threshold - activate mobile toc
            // Check if header is already hidden (user scrolling down)
            const headerHidden = header?.classList.contains(HEADER_HIDDEN_CLASS);

            // If header is hidden, add is-hidden immediately (no transition flash)
            if (headerHidden) {
                toc.style.transition = 'none';
                toc.classList.add('toc--mobile', 'is-hidden');
                // Re-enable transitions after a frame
                requestAnimationFrame(() => {
                    toc.style.transition = '';
                });
            } else {
                toc.classList.add('toc--mobile');
            }

            if (!toggleButton) {
                createToggleButton();
            }
            if (!toc.contains(toggleButton)) {
                toc.insertBefore(toggleButton, toc.firstChild);
            }

            const tocList = toc.querySelector('.toc-list');
            if (tocList) tocList.id = 'toc-list';

            updateToggleLabel();
        } else if (!pastThreshold && toc.classList.contains('toc--mobile') && !isAnimatingOut) {
            // Scrolled back above threshold - animate out then remove
            isAnimatingOut = true;
            toc.classList.add('is-hidden');
            isOpen = false;

            // Remove toc--mobile class after animation completes
            setTimeout(() => {
                if (window.scrollY < TOC_SCROLL_THRESHOLD) {
                    toc.classList.remove('toc--mobile', 'is-hidden', 'is-open');
                }
                isAnimatingOut = false;
            }, 300);
        }

        // Sync with header if in mobile mode (but not while animating out)
        if (toc.classList.contains('toc--mobile') && !isAnimatingOut) {
            syncWithHeader();
        }

        isMobileMode = isMobile();
    }

    /**
     * Set up observer to watch header class changes
     */
    function setupHeaderObserver() {
        if (!header || headerObserver) return;

        headerObserver = new MutationObserver(() => {
            updateMobileMode();
        });

        headerObserver.observe(header, {
            attributes: true,
            attributeFilter: ['class']
        });
    }

    /**
     * Sync visibility with header
     */
    function syncWithHeader() {
        if (!header) return;

        const headerHidden = header.classList.contains(HEADER_HIDDEN_CLASS);
        toc.classList.toggle('is-hidden', headerHidden);

        if (headerHidden && isOpen) {
            closeDropdown();
        }
    }

    /**
     * Toggle dropdown open/closed
     */
    function toggleDropdown() {
        isOpen = !isOpen;
        toc.classList.toggle('is-open', isOpen);

        if (toggleButton) {
            toggleButton.setAttribute('aria-expanded', isOpen);
        }

        if (isOpen) {
            // Focus first link
            const firstLink = toc.querySelector('.toc-list > li > .toc-link');
            firstLink?.focus();
        }
    }

    /**
     * Close dropdown
     */
    function closeDropdown() {
        if (!isOpen) return;
        isOpen = false;
        toc.classList.remove('is-open');

        if (toggleButton) {
            toggleButton.setAttribute('aria-expanded', 'false');
        }
    }

    /**
     * Update toggle button label with current section
     */
    function updateToggleLabel() {
        if (!toggleButton || !lastActiveId) return;

        const section = sections.get(lastActiveId);
        if (section) {
            // For mobile, show top-level section name
            let labelText = section.text;

            // If this is a subsection, show parent section name instead
            if (!section.isTopLevel && section.parentGroup) {
                const parentLink = section.parentGroup.querySelector(':scope > a[href^="#"]');
                if (parentLink) {
                    labelText = parentLink.textContent.trim();
                }
            }

            const label = toggleButton.querySelector('.toc-toggle-label');
            if (label) {
                label.textContent = labelText;
            }
        }
    }

    /**
     * Clear all active states
     */
    function clearActiveStates() {
        links.forEach(link => {
            link.classList.remove(config.activeClass);
        });

        toc.querySelectorAll(`.${config.parentActiveClass}`).forEach(el => {
            el.classList.remove(config.parentActiveClass);
        });
    }

    /**
     * Set active state for a section
     */
    function setActive(id) {
        if (id === lastActiveId) return;

        clearActiveStates();

        const section = sections.get(id);
        if (!section) return;

        section.link.classList.add(config.activeClass);

        if (config.expandParent && section.parentGroup) {
            section.parentGroup.classList.add(config.parentActiveClass);
            const parentLink = section.parentGroup.querySelector(':scope > a[href^="#"]');
            if (parentLink) {
                parentLink.classList.add(config.activeClass);
            }
        }

        if (config.expandParent && section.parentItem) {
            const hasSublist = section.parentItem.querySelector('ul');
            if (hasSublist) {
                section.parentItem.classList.add(config.parentActiveClass);
            }
        }

        lastActiveId = id;

        // Update mobile toggle label
        if (isMobileMode) {
            updateToggleLabel();
        }
    }

    /**
     * Determine which section is currently in view
     */
    function getCurrentSection() {
        const scrollY = window.scrollY + config.offset;
        let currentId = null;
        let currentTop = -Infinity;

        sections.forEach((section, id) => {
            const rect = section.element.getBoundingClientRect();
            const top = rect.top + window.scrollY;

            if (top <= scrollY && top > currentTop) {
                currentTop = top;
                currentId = id;
            }
        });

        if (!currentId && window.scrollY < config.offset * 2) {
            const firstSection = sections.entries().next().value;
            if (firstSection) {
                currentId = firstSection[0];
            }
        }

        return currentId;
    }

    /**
     * Handle scroll event
     */
    function onScroll() {
        if (ticking) return;

        ticking = true;

        requestAnimationFrame(() => {
            const currentId = getCurrentSection();
            if (currentId) {
                setActive(currentId);
            }

            // Check mobile mode on scroll
            if (isMobile()) {
                updateMobileMode();
            }

            ticking = false;
        });
    }

    /**
     * Handle click on ToC links
     */
    function onClick(e) {
        const link = e.target.closest(config.linkSelector);
        if (!link) return;

        const href = link.getAttribute('href');
        if (!href || !href.startsWith('#')) return;

        const target = document.getElementById(href.slice(1));
        if (!target) return;

        e.preventDefault();

        // Close dropdown in mobile mode
        if (isMobileMode && isOpen) {
            closeDropdown();
        }

        // Calculate offset (account for mobile toc bar when in mobile mode)
        const mobileOffset = isMobileMode ? 48 : 0;
        const targetTop = target.getBoundingClientRect().top + window.scrollY - config.offset - mobileOffset + 20;

        window.scrollTo({
            top: targetTop,
            behavior: 'smooth'
        });

        history.pushState(null, '', href);
        setActive(href.slice(1));
    }

    /**
     * Handle click outside to close dropdown
     */
    function onClickOutside(e) {
        if (isMobileMode && isOpen && !toc.contains(e.target)) {
            closeDropdown();
        }
    }

    /**
     * Handle keyboard navigation
     */
    function onKeydown(e) {
        if (!isMobileMode || !isOpen) return;

        if (e.key === 'Escape') {
            closeDropdown();
            toggleButton?.focus();
        }
    }

    /**
     * Handle resize
     */
    function onResize() {
        if (isMobile()) {
            if (!headerObserver) {
                setupHeaderObserver();
            }
            updateMobileMode();
        } else {
            exitMobileMode();
        }
    }

    // Attach event listeners
    window.addEventListener('scroll', onScroll, { passive: true });
    toc.addEventListener('click', onClick);
    document.addEventListener('click', onClickOutside);
    document.addEventListener('keydown', onKeydown);

    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(onResize, 100);
    });

    // Initial state
    onScroll();
    if (isMobile()) {
        setupHeaderObserver();
        updateMobileMode();
    }

    // Return controller
    return {
        refresh() {
            onScroll();
        },
        destroy() {
            window.removeEventListener('scroll', onScroll);
            toc.removeEventListener('click', onClick);
            document.removeEventListener('click', onClickOutside);
            document.removeEventListener('keydown', onKeydown);
            if (headerObserver) headerObserver.disconnect();
            clearActiveStates();
            exitMobileMode();
        }
    };
}

export default initToc;

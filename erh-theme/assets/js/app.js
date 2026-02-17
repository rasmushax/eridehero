/**
 * ERideHero Main Application
 * Initializes all components and global event handlers
 *
 * Uses dynamic imports for page-specific components to reduce initial bundle size.
 */

// Core components - loaded on every page
import { initMobileMenu } from './components/mobile-menu.js';
import { initSearch } from './components/search.js';
import { initDropdowns } from './components/dropdown.js';
import { initCustomSelects } from './components/custom-select.js';
import { initHeaderScroll } from './components/header-scroll.js';
import { initRegionPicker } from './components/region-picker.js';
import './components/popover.js'; // Auto-initializes popovers
import './components/modal.js'; // Auto-initializes modals
import './components/tooltip.js'; // Auto-initializes tooltips
import './components/toast.js'; // Toast notifications (auto-init container)
import { Toast } from './components/toast.js'; // For programmatic toasts

// Note: auth-modal.js and price-alert.js are imported by components that need them
// (e.g., price-intel.js imports price-alert.js) - no need to import here

(function() {
    'use strict';

    // Initialize core components (always needed)
    const mobileMenu = initMobileMenu();
    const search = initSearch();
    const dropdowns = initDropdowns();
    const customSelects = initCustomSelects();
    const headerScroll = initHeaderScroll();
    initRegionPicker(); // Footer region picker

    // ===========================================
    // URL PARAM HANDLERS
    // Handle query params that trigger UI feedback
    // ===========================================

    // Show toast when social account was auto-linked (?linked=google)
    const linkedProvider = new URLSearchParams(window.location.search).get('linked');
    if (linkedProvider) {
        const providerName = linkedProvider.charAt(0).toUpperCase() + linkedProvider.slice(1);
        Toast.success(`Your ${providerName} account has been linked.`);
        // Clean URL
        const cleanUrl = window.location.pathname + window.location.hash;
        window.history.replaceState({}, '', cleanUrl);
    }

    // ===========================================
    // CONDITIONAL COMPONENT LOADING
    // Only load JS for components that exist on the page
    // ===========================================

    // Gallery - load if there are thumbnails or video card
    if (document.querySelector('[data-gallery] .gallery-thumbs') || document.querySelector('[data-gallery] .gallery-video-card')) {
        import('./components/gallery.js');
    }

    // Price Intelligence - load for normal pricing, discontinued, or no-pricing states
    if (document.querySelector('[data-price-intel], [data-alternatives], [data-successor]')) {
        import('./components/price-intel.js');
    }

    // Charts - only if chart containers exist
    if (document.querySelector('[data-erh-chart]')) {
        import('./components/chart.js').then(module => {
            module.default.autoInit();
        });
    }

    // Sticky buy bar - only on review pages
    if (document.querySelector('.sticky-buy-bar')) {
        import('./components/sticky-buy-bar.js').then(module => {
            module.default.init();
        });
    }

    // Table of Contents - only if TOC exists
    if (document.querySelector('.toc')) {
        import('./components/toc.js').then(module => {
            module.initToc('.toc', { offset: 100 });
        });
    }

    // Archive filters/sort - only on archive pages
    if (document.querySelector('[data-archive-filters]')) {
        import('./components/archive-filter.js');
    }
    if (document.querySelector('[data-archive-sort]')) {
        import('./components/archive-sort.js');
    }

    // Search page - only on search page
    if (document.querySelector('[data-search-page]')) {
        import('./components/search-page.js').then(module => {
            module.initSearchPage();
        });
    }

    // Calculator tools - only on tool pages
    if (document.querySelector('[data-calculator]')) {
        import('./components/calculator.js').then(module => {
            module.initCalculator();
        });
    }

    // Contact form - only on contact page
    if (document.querySelector('[data-contact-form]')) {
        import('./components/contact.js').then(module => {
            module.initContactForm();
        });
    }

    // Finder tabs - only on pages with quick finder
    if (document.querySelector('.finder-tabs')) {
        import('./components/finder-tabs.js').then(module => {
            module.initFinderTabs();
        });
    }

    // Product Finder page - full filtering and comparison
    if (document.querySelector('[data-finder-page]')) {
        import('./components/finder.js').then(module => {
            module.initFinder();
        });
    }

    // Homepage deals - only on homepage
    if (document.getElementById('deals-section')) {
        import('./components/deals.js').then(module => {
            module.initDeals();
        });
    }

    // Hub deals tabs - only on hub pages
    if (document.querySelector('.hub-deals-tabs')) {
        import('./components/deals-tabs.js').then(module => {
            module.initDealsTabs({
                tabsSelector: '.hub-deals-tabs',
                gridSelector: '.hub-deals-grid',
                carouselSelector: '.hub-deals-carousel',
                filterType: 'price',
                filterAttribute: 'price'
            });
        });
    }

    // Deals page - category deals with period toggle
    if (document.querySelector('[data-deals-page]')) {
        import('./components/deals-page.js').then(module => {
            module.initDealsPage();
        });
    }

    // Deals hub - main deals portal with stats and carousels
    if (document.querySelector('[data-deals-hub]')) {
        import('./components/deals-hub.js').then(module => {
            module.initDealsHub();
        });
    }

    // Comparison tools - only if containers exist
    if (document.getElementById('comparison-container')) {
        import('./components/comparison.js').then(module => {
            // Homepage comparison (side-by-side layout)
            module.initComparison({
                containerId: 'comparison-container',
                rightColumnId: 'comparison-right-column',
                submitBtnId: 'comparison-submit',
                categoryPillId: 'comparison-category-pill',
                categoryTextId: 'comparison-category-text',
                categoryClearId: 'comparison-category-clear',
                announcerId: 'comparison-announcer',
                wrapperClass: 'comparison-input-wrapper',
                showCategoryInResults: true
            });
        });
    }

    if (document.getElementById('hub-comparison-container')) {
        import('./components/comparison.js').then(module => {
            const hubContainer = document.getElementById('hub-comparison-container');
            const hubCategoryFilter = hubContainer?.dataset.categoryFilter || null;

            module.initComparison({
                containerId: 'hub-comparison-container',
                rightColumnId: 'hub-comparison-right',
                submitBtnId: 'hub-comparison-submit',
                categoryPillId: 'hub-category-pill',
                categoryTextId: 'hub-category-text',
                categoryClearId: 'hub-category-clear',
                announcerId: 'hub-comparison-announcer',
                categoryFilter: hubCategoryFilter,
                showCategoryInResults: !hubCategoryFilter,
                allowDynamicInputs: true
            });
        });
    }

    // Category comparison widget (category landing pages - filtered to specific category)
    if (document.getElementById('category-comparison-container')) {
        import('./components/comparison.js').then(module => {
            const container = document.getElementById('category-comparison-container');
            const categoryFilter = container?.dataset.categoryFilter || null;

            module.initComparison({
                containerId: 'category-comparison-container',
                rightColumnId: 'category-comparison-right',
                submitBtnId: 'category-comparison-submit',
                announcerId: 'category-comparison-announcer',
                categoryFilter: categoryFilter,
                showCategoryInResults: false,
                allowDynamicInputs: true
            });
        });
    }

    // Sidebar comparison widget (review pages - locked product)
    if (document.getElementById('sidebar-comparison')) {
        import('./components/comparison.js').then(module => {
            const container = document.getElementById('sidebar-comparison');
            const categoryFilter = container?.dataset.lockedCategory || null;

            module.initComparison({
                containerId: 'sidebar-comparison',
                inputsContainerId: 'sidebar-comparison-inputs',
                submitBtnId: 'sidebar-comparison-submit',
                announcerId: 'sidebar-comparison-announcer',
                wrapperClass: 'comparison-input-wrapper',
                categoryFilter: categoryFilter,
                showCategoryInResults: false,
                allowDynamicInputs: true
            });
        });
    }

    // Open comparison widgets (article/guide pages - no locked product)
    const openComparisonWidgets = document.querySelectorAll('.sidebar-comparison--open');
    if (openComparisonWidgets.length > 0) {
        import('./components/comparison.js').then(module => {
            openComparisonWidgets.forEach(container => {
                module.initComparison({
                    containerId: container.id,
                    submitBtnSelector: '.sidebar-comparison-btn',
                    announcerSelector: '[aria-live="polite"]',
                    wrapperClass: 'comparison-input-wrapper',
                    showCategoryInResults: true, // Show category since multiple may be available
                    allowDynamicInputs: false // Fixed 2-product comparison
                });
            });
        });
    }

    // Compare Results page (H2H comparison)
    if (document.querySelector('[data-compare-page]')) {
        import('./components/compare-results.js').then(module => {
            module.init();
        });
    }

    // Product page (single product database view)
    if (document.querySelector('[data-product-page]')) {
        import('./components/product-page.js').then(module => {
            // Auto-initializes on import
        });

        // Also init comparison widget on product pages
        const productComparison = document.querySelector('[data-product-comparison]');
        if (productComparison) {
            import('./components/comparison.js').then(module => {
                const categoryFilter = productComparison.dataset.lockedCategory || null;

                module.initComparison({
                    containerId: null, // Uses data-product-comparison selector
                    containerSelector: '[data-product-comparison]',
                    submitBtnSelector: '[data-compare-submit]',
                    announcerSelector: '[data-comparison-announcer]',
                    wrapperClass: 'comparison-input-wrapper',
                    categoryFilter: categoryFilter,
                    showCategoryInResults: false,
                    allowDynamicInputs: false
                });
            });
        }
    }

    // Similar products carousel - geo-aware client-side rendering
    if (document.querySelector('[data-similar-products]')) {
        import('./components/similar-products.js').then(module => {
            module.initSimilarProducts();
        });
    }

    // Listicle Item blocks - advanced product display for buying guides
    if (document.querySelector('[data-listicle-item]')) {
        import('./components/listicle-item.js').then(module => {
            module.initListicleItems();
        });
    }

    // Buying Guide Table blocks - comparison tables with geo pricing
    if (document.querySelector('[data-buying-guide-table]')) {
        import('./components/buying-guide-table.js').then(module => {
            module.initBuyingGuideTables();
        });
    }

    // Auth page (login/register) - hash-based state switching
    if (document.querySelector('[data-auth-page]')) {
        import('./components/auth-page.js');
    }

    // Reset password page
    if (document.querySelector('[data-reset-password-page]')) {
        import('./components/auth-page.js').then(m => m.initResetPassword());
    }

    // Account page - tabs, settings, and trackers
    if (document.querySelector('.account-page')) {
        import('./components/account-tabs.js');
        import('./components/account-settings.js');
        import('./components/account-trackers.js');
    }

    // Onboarding page - email preferences setup
    if (document.querySelector('[data-onboarding]')) {
        import('./components/onboarding.js');
    }

    // Complete profile page - social auth email collection (Reddit)
    if (document.querySelector('[data-complete-profile]')) {
        import('./components/complete-profile.js');
    }

    // Global keyboard handling
    document.addEventListener('keydown', (e) => {
        // Escape key closes menu/search
        if (e.key === 'Escape') {
            if (search?.isOpen()) {
                search.close();
                document.querySelector('.search-toggle')?.focus();
            }
            if (mobileMenu?.isOpen()) {
                mobileMenu.close();
            }
        }

        // Cmd/Ctrl + K opens search
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            // Try inline search first (desktop), fall back to dropdown
            if (!search?.focusInlineSearch()) {
                if (!search?.isOpen()) {
                    search?.toggle();
                }
            }
        }
    });

    // Handle resize - close mobile menu/search if resizing to desktop
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            if (window.innerWidth > 820 && mobileMenu?.isOpen()) {
                mobileMenu.close();
            }
            if (window.innerWidth > 960 && search?.isOpen()) {
                search.close();
            }
        }, 100);
    });

    // Coordinate between mobile menu and search
    // Close search when opening menu and vice versa
    const searchToggle = document.querySelector('.search-toggle');
    const menuToggle = document.querySelector('.menu-toggle');

    if (searchToggle && mobileMenu) {
        searchToggle.addEventListener('click', () => {
            if (mobileMenu.isOpen()) {
                mobileMenu.close();
            }
        });
    }

    if (menuToggle && search) {
        menuToggle.addEventListener('click', () => {
            if (search.isOpen()) {
                search.close();
            }
        });
    }

})();

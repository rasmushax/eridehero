/**
 * ERideHero Main Application
 * Initializes all components and global event handlers
 */

import { initMobileMenu } from './components/mobile-menu.js';
import { initSearch } from './components/search.js';
import { initDropdowns } from './components/dropdown.js';
import { initFinderTabs } from './components/finder-tabs.js';
import { initDealsTabs } from './components/deals-tabs.js';
import { initDeals } from './components/deals.js';
import { initCustomSelects } from './components/custom-select.js';
import { initHeaderScroll } from './components/header-scroll.js';
import { initComparison } from './components/comparison.js';
import chart from './components/chart.js';
import stickyBuyBar from './components/sticky-buy-bar.js';
import './components/gallery.js'; // Auto-initializes galleries
import './components/popover.js'; // Auto-initializes popovers
import './components/modal.js'; // Auto-initializes modals
import './components/tooltip.js'; // Auto-initializes tooltips
import './components/price-alert.js'; // Price alert modal interactions
import './components/archive-filter.js'; // Auto-initializes archive filters
import './components/archive-sort.js'; // Auto-initializes archive sorting
import { initToc } from './components/toc.js';
import { initContactForm } from './components/contact.js';

(function() {
    'use strict';

    // Initialize components
    const mobileMenu = initMobileMenu();
    const search = initSearch();
    const dropdowns = initDropdowns();
    const finderTabs = initFinderTabs();
    const customSelects = initCustomSelects();

    // Homepage deals (dynamic loading with geo-aware pricing)
    initDeals();

    // Hub deals (filter by price range)
    initDealsTabs({
        tabsSelector: '.hub-deals-tabs',
        gridSelector: '.hub-deals-grid',
        carouselSelector: '.hub-deals-carousel',
        filterType: 'price',
        filterAttribute: 'price'
    });
    const headerScroll = initHeaderScroll();

    // Initialize async components

    // Homepage comparison (side-by-side layout)
    initComparison({
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

    // Hub comparison (stacked layout, category-filtered)
    initComparison({
        containerId: 'hub-comparison-container',
        inputsContainerId: 'hub-compare-inputs',
        submitBtnId: 'hub-comparison-submit',
        categoryFilter: 'escooter',
        wrapperClass: 'comparison-input-wrapper comparison-light',
        showCategoryInResults: false
    });

    // Review page: Charts (auto-init based on data attributes)
    chart.autoInit();

    // Review page: Sticky buy bar
    stickyBuyBar.init();

    // Review page: Table of Contents
    initToc('.toc', {
        offset: 100
    });

    // Contact page: Form handling
    initContactForm();

    // Review page: Sidebar comparison (with locked current product)
    initComparison({
        containerId: 'review-sidebar-compare',
        inputsContainerId: 'review-sidebar-compare-inputs',
        submitBtnId: 'review-sidebar-compare-btn',
        wrapperClass: 'comparison-input-wrapper comparison-light',
        categoryFilter: 'escooter',
        showCategoryInResults: false
    });

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

/**
 * Deals Section Tab Filtering & Carousel
 * Filters deal cards by category or price range when tabs are clicked
 * Handles carousel arrow navigation on desktop
 *
 * Supports multiple instances with different configurations.
 */

export function initDealsTabs(options = {}) {
    // Default configuration
    const config = {
        tabsSelector: '.deals-tabs',
        gridSelector: '.deals-grid',
        carouselSelector: '.deals-carousel',
        cardSelector: '.deal-card',
        pillSelector: '.filter-pill',
        filterType: 'category', // 'category' or 'price'
        filterAttribute: 'category', // data attribute on cards (data-category or data-price)
        ...options
    };

    const tabsContainer = document.querySelector(config.tabsSelector);
    const dealsGrid = document.querySelector(config.gridSelector);
    const carousel = document.querySelector(config.carouselSelector);

    if (!tabsContainer || !dealsGrid) return null;

    const tabs = tabsContainer.querySelectorAll(config.pillSelector);
    const cards = dealsGrid.querySelectorAll(config.cardSelector);
    const leftArrow = carousel?.querySelector('.carousel-arrow-left');
    const rightArrow = carousel?.querySelector('.carousel-arrow-right');

    /**
     * Filter cards by category (exact match)
     * Cards without the filter attribute are always shown (e.g., CTA cards)
     */
    function filterByCategory(category) {
        cards.forEach(card => {
            const cardValue = card.dataset[config.filterAttribute];

            // Always show cards without the filter attribute (CTA cards, etc.)
            if (cardValue === undefined) {
                card.classList.remove('hidden');
                return;
            }

            if (category === 'all' || cardValue === category) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });
    }

    /**
     * Filter cards by price range
     * Tab data-filter format: "all", "0-500", "500-1000", "1000-2000", "2000-inf"
     * Cards without the filter attribute are always shown (e.g., CTA cards)
     */
    function filterByPrice(range) {
        if (range === 'all') {
            cards.forEach(card => card.classList.remove('hidden'));
            return;
        }

        const [min, max] = range.split('-').map(v => v === 'inf' ? Infinity : parseInt(v, 10));

        cards.forEach(card => {
            const priceValue = card.dataset[config.filterAttribute];

            // Always show cards without the filter attribute (CTA cards, etc.)
            if (priceValue === undefined) {
                card.classList.remove('hidden');
                return;
            }

            const price = parseInt(priceValue, 10);

            if (!isNaN(price) && price >= min && price < max) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });
    }

    /**
     * Main filter function - delegates based on filter type
     */
    function filterCards(value) {
        if (config.filterType === 'price') {
            filterByPrice(value);
        } else {
            filterByCategory(value);
        }

        // Update carousel scroll state after filtering
        if (carousel) {
            setTimeout(updateScrollStates, 50);
        }
    }

    function setActiveTab(activeTab) {
        tabs.forEach(tab => {
            const isActive = tab === activeTab;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    }

    // Tab click handler
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const filterValue = tab.dataset.filter || tab.dataset.category || 'all';
            setActiveTab(tab);
            filterCards(filterValue);
        });
    });

    // Keyboard navigation
    tabsContainer.addEventListener('keydown', (e) => {
        const currentTab = document.activeElement;
        if (!currentTab.classList.contains('filter-pill')) return;

        const tabsArray = Array.from(tabs);
        const currentIndex = tabsArray.indexOf(currentTab);
        let newIndex;

        switch (e.key) {
            case 'ArrowRight':
                newIndex = (currentIndex + 1) % tabsArray.length;
                break;
            case 'ArrowLeft':
                newIndex = (currentIndex - 1 + tabsArray.length) % tabsArray.length;
                break;
            case 'Home':
                newIndex = 0;
                break;
            case 'End':
                newIndex = tabsArray.length - 1;
                break;
            default:
                return;
        }

        e.preventDefault();
        tabsArray[newIndex].focus();
        tabsArray[newIndex].click();
    });

    // Carousel arrow navigation (desktop only)
    let scrollAmount = 200;

    function updateScrollStates() {
        if (!carousel || !leftArrow || !rightArrow) return;

        const scrollLeft = dealsGrid.scrollLeft;
        const maxScroll = dealsGrid.scrollWidth - dealsGrid.clientWidth;

        const canScrollLeft = scrollLeft > 0;
        const canScrollRight = scrollLeft < maxScroll - 1;

        // Update arrow disabled states
        leftArrow.disabled = !canScrollLeft;
        rightArrow.disabled = !canScrollRight;

        // Update fade mask classes
        carousel.classList.toggle('can-scroll-left', canScrollLeft);
        carousel.classList.toggle('can-scroll-right', canScrollRight);
    }

    function scrollCarousel(direction) {
        const currentScroll = dealsGrid.scrollLeft;
        const targetScroll = currentScroll + (direction * scrollAmount);

        dealsGrid.scrollTo({
            left: targetScroll,
            behavior: 'smooth'
        });
    }

    if (leftArrow && rightArrow && carousel) {
        leftArrow.addEventListener('click', () => scrollCarousel(-1));
        rightArrow.addEventListener('click', () => scrollCarousel(1));

        // Update states on scroll
        dealsGrid.addEventListener('scroll', updateScrollStates);

        // Update states on resize
        window.addEventListener('resize', updateScrollStates);

        // Initial state
        updateScrollStates();
    }

    return {
        filterCards,
        setActiveTab,
        updateScrollStates
    };
}

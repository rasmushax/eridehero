/**
 * Finder Component
 * Handles product filtering, sorting, and comparison selection
 * Uses progressive loading to keep DOM light
 *
 * Architecture: Data-driven filters via FILTER_CONFIG
 * Adding a new filter requires only a config entry - no code duplication
 */

// =========================================
// FILTER CONFIGURATION (Single Source of Truth)
// =========================================

const FILTER_CONFIG = {
    // Set filters (checkboxes) - value stored in Set
    sets: {
        brands: {
            selector: 'brand',
            productKey: 'brand',
            pillLabel: v => v
        },
        motor_positions: {
            selector: 'motor_position',
            productKey: 'motor_position',
            pillLabel: v => `Motor: ${v}`
        },
        battery_types: {
            selector: 'battery_type',
            productKey: 'battery_type',
            pillLabel: v => `Battery: ${v}`
        }
    },

    // Range filters (min/max sliders) - value stored as { min, max }
    ranges: {
        price:         { unit: '',     prefix: '$', suffix: '',            pillFormat: (min, max) => `$${min} – $${max}` },
        speed:         { unit: 'mph',  prefix: '',  suffix: ' mph',        pillFormat: (min, max) => `${min}–${max} mph` },
        range:         { unit: 'mi',   prefix: '',  suffix: ' mi',         pillFormat: (min, max) => `${min}–${max} mi` },
        weight:        { unit: 'lbs',  prefix: '',  suffix: ' lbs',        pillFormat: (min, max) => `${min}–${max} lbs` },
        weight_limit:  { unit: 'lbs',  prefix: '',  suffix: ' lbs max load', pillFormat: (min, max) => `${min}–${max} lbs max load` },
        battery:       { unit: 'Wh',   prefix: '',  suffix: ' Wh',         pillFormat: (min, max) => `${min}–${max} Wh` },
        voltage:       { unit: 'V',    prefix: '',  suffix: 'V',           pillFormat: (min, max) => `${min}–${max}V` },
        amphours:      { unit: 'Ah',   prefix: '',  suffix: 'Ah',          pillFormat: (min, max) => `${min}–${max}Ah` },
        charging_time: { unit: 'hrs',  prefix: '',  suffix: 'hrs charge',  pillFormat: (min, max) => `${min}–${max}hrs charge` },
        motor_power:   { unit: 'W',    prefix: '',  suffix: 'W',           pillFormat: (min, max) => `${min}–${max}W` },
        motor_peak:    { unit: 'W',    prefix: '',  suffix: 'W peak',      pillFormat: (min, max) => `${min}–${max}W peak` }
    },

    // Boolean filters (single checkbox)
    booleans: {
        in_stock: {
            selector: 'in_stock',
            productKey: 'in_stock',
            pillLabel: 'In stock only'
        }
    },

    // Product key mapping for range filters (filter key → product property)
    rangeProductKeys: {
        price: 'price',
        speed: 'top_speed',
        range: 'range',
        weight: 'weight',
        weight_limit: 'weight_limit',
        battery: 'battery',
        voltage: 'voltage',
        amphours: 'amphours',
        charging_time: 'charging_time',
        motor_power: 'motor_power',
        motor_peak: 'motor_peak'
    }
};

class Finder {
    constructor() {
        this.container = document.querySelector('[data-finder-page]');
        if (!this.container) return;

        this.grid = this.container.querySelector('[data-finder-grid]');
        this.sidebar = this.container.querySelector('[data-finder-sidebar]');
        this.activeFiltersBar = this.container.querySelector('[data-active-filters]');
        this.emptyState = this.container.querySelector('[data-finder-empty]');
        this.resultsCount = this.container.querySelector('[data-results-count]');
        this.sortSelect = this.container.querySelector('[data-finder-sort]');
        this.comparisonBar = this.container.querySelector('[data-comparison-bar]');
        this.loadMoreContainer = this.container.querySelector('[data-load-more]');
        this.loadMoreBtn = this.container.querySelector('[data-load-more-btn]');

        // Get products from global data
        this.products = window.ERideHero?.finderProducts || [];
        this.config = window.ERideHero?.finderConfig || {};

        // Progressive loading settings
        this.displayLimit = 48;
        this.displayStep = 48;
        this.currentLimit = this.displayLimit;

        // Filtered & sorted products (in memory)
        this.filteredProducts = [...this.products];

        // Initialize filter state from config
        this.filters = this.createFilterState();

        // Sort state
        this.currentSort = 'popularity';

        // Comparison state
        this.selectedProducts = new Set();
        this.maxCompare = 4;

        this.init();
    }

    /**
     * Create initial filter state from FILTER_CONFIG
     */
    createFilterState() {
        const state = {};

        // Set filters → new Set()
        Object.keys(FILTER_CONFIG.sets).forEach(key => {
            state[key] = new Set();
        });

        // Range filters → { min: null, max: null }
        Object.keys(FILTER_CONFIG.ranges).forEach(key => {
            state[key] = { min: null, max: null };
        });

        // Boolean filters → false
        Object.keys(FILTER_CONFIG.booleans).forEach(key => {
            state[key] = false;
        });

        return state;
    }

    init() {
        this.bindFilterEvents();
        this.bindSortEvents();
        this.bindComparisonEvents();
        this.bindRangeSliders();
        this.bindFilterItemToggles();
        this.bindFilterSearch();
        this.bindFilterListSearch();
        this.bindFilterShowAll();
        this.bindViewToggle();
        this.bindLoadMore();

        // Initial render
        this.applyFilters();
    }

    // =========================================
    // LOAD MORE
    // =========================================

    bindLoadMore() {
        this.loadMoreBtn?.addEventListener('click', () => this.loadMore());
    }

    loadMore() {
        this.currentLimit += this.displayStep;
        this.renderProducts();
        this.updateResultsCount();
    }

    // =========================================
    // FILTER EVENTS (Data-Driven)
    // =========================================

    bindFilterEvents() {
        // Set filters (checkboxes) - driven by config
        Object.entries(FILTER_CONFIG.sets).forEach(([filterKey, cfg]) => {
            this.container.querySelectorAll(`[data-filter="${cfg.selector}"]`).forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    if (checkbox.checked) {
                        this.filters[filterKey].add(checkbox.value);
                    } else {
                        this.filters[filterKey].delete(checkbox.value);
                    }
                    this.applyFilters();
                });
            });
        });

        // Boolean filters - driven by config
        Object.entries(FILTER_CONFIG.booleans).forEach(([filterKey, cfg]) => {
            const checkbox = this.container.querySelector(`[data-filter="${cfg.selector}"]`);
            checkbox?.addEventListener('change', () => {
                this.filters[filterKey] = checkbox.checked;
                this.applyFilters();
            });
        });

        // Clear all filters button
        this.container.querySelectorAll('[data-clear-filters]').forEach(btn => {
            btn.addEventListener('click', () => this.clearAllFilters());
        });
    }

    bindRangeSliders() {
        this.container.querySelectorAll('[data-range-filter]').forEach(rangeContainer => {
            const filterType = rangeContainer.dataset.rangeFilter;
            const minInput = rangeContainer.querySelector('[data-range-min]');
            const maxInput = rangeContainer.querySelector('[data-range-max]');
            const slider = rangeContainer.querySelector('[data-range-slider]');
            const minHandle = slider?.querySelector('[data-handle="min"]');
            const maxHandle = slider?.querySelector('[data-handle="max"]');
            const fill = slider?.querySelector('.filter-range-fill');

            const rangeMin = parseFloat(rangeContainer.dataset.min) || 0;
            const rangeMax = parseFloat(rangeContainer.dataset.max) || 100;

            const updateFromInputs = () => {
                const minVal = parseFloat(minInput.value) || rangeMin;
                const maxVal = parseFloat(maxInput.value) || rangeMax;

                this.filters[filterType] = {
                    min: minVal > rangeMin ? minVal : null,
                    max: maxVal < rangeMax ? maxVal : null
                };

                this.updateSliderVisuals(slider, minVal, maxVal, rangeMin, rangeMax);
                this.applyFilters();
            };

            minInput?.addEventListener('change', updateFromInputs);
            maxInput?.addEventListener('change', updateFromInputs);

            if (minHandle && maxHandle && slider) {
                this.initSliderDrag(slider, minHandle, maxHandle, fill, minInput, maxInput, rangeMin, rangeMax, filterType);
            }
        });
    }

    initSliderDrag(slider, minHandle, maxHandle, fill, minInput, maxInput, rangeMin, rangeMax, filterType) {
        let activeHandle = null;

        const getPositionFromEvent = (e) => {
            const rect = slider.getBoundingClientRect();
            const handleRadius = 9;
            const trackWidth = rect.width - (handleRadius * 2);
            let x = (e.type.includes('touch') ? e.touches[0].clientX : e.clientX) - rect.left - handleRadius;
            return Math.max(0, Math.min(1, x / trackWidth));
        };

        const updateValue = (handle, pos) => {
            const value = Math.round(rangeMin + pos * (rangeMax - rangeMin));

            if (handle === minHandle) {
                const maxPos = parseFloat(maxHandle.style.getPropertyValue('--pos')) || 1;
                if (pos <= maxPos) {
                    minHandle.style.setProperty('--pos', pos);
                    minInput.value = value;
                    fill.style.setProperty('--min', pos);
                }
            } else {
                const minPos = parseFloat(minHandle.style.getPropertyValue('--pos')) || 0;
                if (pos >= minPos) {
                    maxHandle.style.setProperty('--pos', pos);
                    maxInput.value = value;
                    fill.style.setProperty('--max', pos);
                }
            }
        };

        const onMove = (e) => {
            if (!activeHandle) return;
            e.preventDefault();
            updateValue(activeHandle, getPositionFromEvent(e));
        };

        const onEnd = () => {
            if (!activeHandle) return;
            activeHandle = null;
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onEnd);
            document.removeEventListener('touchmove', onMove);
            document.removeEventListener('touchend', onEnd);

            this.filters[filterType] = {
                min: parseFloat(minInput.value) > rangeMin ? parseFloat(minInput.value) : null,
                max: parseFloat(maxInput.value) < rangeMax ? parseFloat(maxInput.value) : null
            };
            this.applyFilters();
        };

        [minHandle, maxHandle].forEach(handle => {
            handle.addEventListener('mousedown', () => {
                activeHandle = handle;
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onEnd);
            });

            handle.addEventListener('touchstart', () => {
                activeHandle = handle;
                document.addEventListener('touchmove', onMove, { passive: false });
                document.addEventListener('touchend', onEnd);
            });
        });
    }

    updateSliderVisuals(slider, minVal, maxVal, rangeMin, rangeMax) {
        if (!slider) return;

        const minPos = (minVal - rangeMin) / (rangeMax - rangeMin);
        const maxPos = (maxVal - rangeMin) / (rangeMax - rangeMin);

        const minHandle = slider.querySelector('[data-handle="min"]');
        const maxHandle = slider.querySelector('[data-handle="max"]');
        const fill = slider.querySelector('.filter-range-fill');

        minHandle?.style.setProperty('--pos', minPos);
        maxHandle?.style.setProperty('--pos', maxPos);
        fill?.style.setProperty('--min', minPos);
        fill?.style.setProperty('--max', maxPos);
    }

    bindFilterItemToggles() {
        this.container.querySelectorAll('[data-filter-item-toggle]').forEach(toggle => {
            toggle.addEventListener('click', () => {
                toggle.closest('[data-filter-item]')?.classList.toggle('is-open');
            });
        });

        // Open first filter item by default
        this.container.querySelector('[data-filter-item]')?.classList.add('is-open');
    }

    bindFilterSearch() {
        const searchInput = this.container.querySelector('[data-filter-search]');
        if (!searchInput) return;

        searchInput.addEventListener('input', () => {
            const query = searchInput.value.toLowerCase().trim();

            this.container.querySelectorAll('.filter-checkbox').forEach(checkbox => {
                const label = checkbox.querySelector('.filter-checkbox-label')?.textContent?.toLowerCase() || '';
                checkbox.style.display = label.includes(query) ? '' : 'none';
            });

            this.container.querySelectorAll('.filter-item').forEach(item => {
                const label = item.querySelector('.filter-item-label')?.textContent?.toLowerCase() || '';
                item.style.display = label.includes(query) ? '' : 'none';
            });
        });
    }

    /**
     * Search within a specific filter list (e.g., brands)
     */
    bindFilterListSearch() {
        this.container.querySelectorAll('[data-filter-list-search]').forEach(searchInput => {
            const listName = searchInput.dataset.filterListSearch;
            const container = this.container.querySelector(`[data-filter-list-search-container="${listName}"]`);
            const list = this.container.querySelector(`[data-filter-list="${listName}"]`);
            const noResults = this.container.querySelector(`[data-filter-no-results="${listName}"]`);
            const showAllBtn = this.container.querySelector(`[data-filter-show-all="${listName}"]`);
            const clearBtn = this.container.querySelector(`[data-filter-list-search-clear="${listName}"]`);

            if (!list) return;

            const performSearch = () => {
                const query = searchInput.value.toLowerCase().trim();
                let visibleCount = 0;

                // Toggle has-value class for clear button visibility
                container?.classList.toggle('has-value', !!query);

                list.querySelectorAll('.filter-checkbox').forEach(checkbox => {
                    const label = checkbox.querySelector('.filter-checkbox-label')?.textContent?.toLowerCase() || '';
                    const matches = !query || label.includes(query);

                    // When searching, show all matches (ignore limit)
                    if (query) {
                        checkbox.hidden = !matches;
                        checkbox.classList.remove('is-hidden-by-limit');
                    } else {
                        // Reset to default state when search is cleared
                        checkbox.hidden = false;
                    }

                    if (matches) visibleCount++;
                });

                // Show/hide no results message
                if (noResults) {
                    noResults.hidden = visibleCount > 0 || !query;
                }

                // Hide show all button when searching
                if (showAllBtn) {
                    showAllBtn.hidden = !!query;
                }

                // When search is cleared, reset the limit visibility
                if (!query) {
                    this.resetFilterListLimit(listName);
                }
            };

            searchInput.addEventListener('input', performSearch);

            // Clear button handler
            clearBtn?.addEventListener('click', () => {
                searchInput.value = '';
                performSearch();
                searchInput.focus();
            });
        });
    }

    /**
     * Reset a filter list to respect its limit (after search is cleared)
     */
    resetFilterListLimit(listName) {
        const list = this.container.querySelector(`[data-filter-list="${listName}"]`);
        const showAllBtn = this.container.querySelector(`[data-filter-show-all="${listName}"]`);
        if (!list) return;

        const limit = parseInt(list.dataset.limit, 10) || 8;
        const isExpanded = list.classList.contains('is-expanded');

        list.querySelectorAll('.filter-checkbox').forEach((checkbox, index) => {
            checkbox.hidden = false;
            if (!isExpanded && index >= limit) {
                checkbox.classList.add('is-hidden-by-limit');
            } else {
                checkbox.classList.remove('is-hidden-by-limit');
            }
        });

        if (showAllBtn) {
            showAllBtn.hidden = false;
        }
    }

    /**
     * Show all / Show less toggle for filter lists
     */
    bindFilterShowAll() {
        this.container.querySelectorAll('[data-filter-show-all]').forEach(btn => {
            const listName = btn.dataset.filterShowAll;
            const list = this.container.querySelector(`[data-filter-list="${listName}"]`);
            const showText = btn.querySelector('[data-show-text]');
            const hideText = btn.querySelector('[data-hide-text]');

            if (!list) return;

            btn.addEventListener('click', () => {
                const isExpanded = list.classList.toggle('is-expanded');

                // Toggle text visibility
                if (showText) showText.hidden = isExpanded;
                if (hideText) hideText.hidden = !isExpanded;

                // Toggle checkbox visibility
                list.querySelectorAll('.filter-checkbox.is-hidden-by-limit').forEach(checkbox => {
                    // CSS handles visibility via .is-expanded on parent
                });
            });
        });
    }

    // =========================================
    // APPLY FILTERS (Data-Driven)
    // =========================================

    applyFilters() {
        this.currentLimit = this.displayLimit;
        this.filteredProducts = this.products.filter(product => this.productMatchesFilters(product));
        this.sortFilteredProducts();
        this.renderProducts();
        this.updateResultsCount();
        this.updateActiveFiltersBar();
    }

    productMatchesFilters(product) {
        // Check set filters (brands, motor_positions, battery_types)
        for (const [filterKey, cfg] of Object.entries(FILTER_CONFIG.sets)) {
            const filterSet = this.filters[filterKey];
            if (filterSet.size > 0) {
                const productValue = product[cfg.productKey];
                if (!productValue || !filterSet.has(productValue)) return false;
            }
        }

        // Check range filters
        for (const [filterKey, productKey] of Object.entries(FILTER_CONFIG.rangeProductKeys)) {
            const filter = this.filters[filterKey];
            const value = product[productKey];
            const hasFilter = filter.min !== null || filter.max !== null;

            // Price filter: exclude products without price when filter is active
            if (filterKey === 'price' && hasFilter && value == null) {
                return false;
            }

            // Other range filters: products without data pass through
            if (value != null) {
                if (filter.min !== null && value < filter.min) return false;
                if (filter.max !== null && value > filter.max) return false;
            }
        }

        // Check boolean filters
        for (const [filterKey, cfg] of Object.entries(FILTER_CONFIG.booleans)) {
            if (this.filters[filterKey] && !product[cfg.productKey]) {
                return false;
            }
        }

        return true;
    }

    // =========================================
    // RENDER PRODUCTS
    // =========================================

    renderProducts() {
        const productsToShow = this.filteredProducts.slice(0, this.currentLimit);

        this.grid.innerHTML = '';
        productsToShow.forEach(product => {
            this.grid.appendChild(this.createProductCard(product));
        });

        if (this.emptyState) {
            this.emptyState.hidden = this.filteredProducts.length > 0;
        }
        if (this.grid) {
            this.grid.style.display = this.filteredProducts.length > 0 ? '' : 'none';
        }

        this.updateLoadMoreButton();
        this.bindComparisonCheckboxes();
    }

    createProductCard(product) {
        const card = document.createElement('article');
        card.className = 'product-card';
        card.dataset.productId = product.id;

        const isSelected = this.selectedProducts.has(String(product.id));
        const priceIndicator = this.getPriceIndicator(product);
        const specsText = this.formatProductSpecs(product);

        card.innerHTML = `
            <label class="product-card-select" onclick="event.stopPropagation()">
                <input type="checkbox"
                       data-compare-select
                       value="${product.id}"
                       ${isSelected ? 'checked' : ''}>
                <span class="product-card-select-box">
                    <svg class="icon"><use href="#icon-check"></use></svg>
                </span>
            </label>

            <button class="product-card-track" data-track-price="${product.id}" aria-label="Track price">
                <svg class="icon"><use href="#icon-bell"></use></svg>
            </button>

            <a href="${product.url}" class="product-card-link">
                <div class="product-card-image">
                    <img src="${product.thumbnail || '/wp-content/themes/erh-theme/assets/images/placeholder.svg'}"
                         alt="${product.name}"
                         loading="lazy">
                </div>
            </a>

            <div class="product-card-content">
                <h3 class="product-card-name">
                    <a href="${product.url}">${product.name}</a>
                </h3>
                ${specsText ? `<p class="product-card-specs">${specsText}</p>` : ''}
            </div>

            <div class="product-card-footer">
                <div class="product-card-price-row">
                    ${product.price
                        ? `<span class="product-card-price">$${Math.round(product.price).toLocaleString()}</span>`
                        : '<span class="product-card-no-price">Price unavailable</span>'}
                    ${priceIndicator}
                </div>
                ${product.in_stock !== undefined
                    ? `<span class="product-card-stock-dot product-card-stock-dot--${product.in_stock ? 'in' : 'out'}"
                             title="${product.in_stock ? 'In stock' : 'Out of stock'}"></span>`
                    : ''}
            </div>
        `;

        return card;
    }

    formatProductSpecs(product) {
        const specs = [];

        if (product.top_speed) specs.push(`${Math.round(product.top_speed)} MPH`);
        if (product.battery) specs.push(`${Math.round(product.battery)} Wh battery`);
        if (product.weight) specs.push(`${Math.round(product.weight)} lbs`);
        if (product.weight_limit) specs.push(`${Math.round(product.weight_limit)} lbs max load`);

        if (product.motor_power) {
            let motorText = `${Math.round(product.motor_power)}W motor`;
            if (product.motor_peak) motorText += ` (${Math.round(product.motor_peak)}W peak)`;
            specs.push(motorText);
        }

        return specs.join(', ');
    }

    getPriceIndicator(product) {
        if (!product.price_indicator) return '';

        const indicator = product.price_indicator;
        if (indicator < -5) {
            return `<span class="product-card-indicator product-card-indicator--below">
                <svg class="icon"><use href="#icon-trending-down"></use></svg>
                ${Math.abs(indicator)}% below avg
            </span>`;
        } else if (indicator > 10) {
            return `<span class="product-card-indicator product-card-indicator--above">
                <svg class="icon"><use href="#icon-trending-up"></use></svg>
                ${indicator}% above avg
            </span>`;
        }
        return '';
    }

    updateLoadMoreButton() {
        if (!this.loadMoreContainer) return;

        const hasMore = this.currentLimit < this.filteredProducts.length;
        this.loadMoreContainer.hidden = !hasMore;

        if (hasMore && this.loadMoreBtn) {
            const remaining = this.filteredProducts.length - this.currentLimit;
            const toLoad = Math.min(remaining, this.displayStep);
            this.loadMoreBtn.textContent = `Load ${toLoad} more`;
        }
    }

    updateResultsCount() {
        if (!this.resultsCount) return;

        const total = this.filteredProducts.length;
        const showing = Math.min(this.currentLimit, total);
        const productName = this.config.shortName || 'product';

        if (showing < total) {
            this.resultsCount.innerHTML = `Showing <strong>${showing}</strong> of <strong>${total}</strong> ${productName}s`;
        } else {
            this.resultsCount.innerHTML = `<strong>${total}</strong> ${productName}s`;
        }
    }

    // =========================================
    // ACTIVE FILTERS BAR (Data-Driven)
    // =========================================

    updateActiveFiltersBar() {
        if (!this.activeFiltersBar) return;

        const pills = [];

        // Set filter pills
        Object.entries(FILTER_CONFIG.sets).forEach(([filterKey, cfg]) => {
            this.filters[filterKey].forEach(value => {
                pills.push(this.createFilterPill(cfg.pillLabel(value), filterKey, value));
            });
        });

        // Range filter pills
        Object.entries(FILTER_CONFIG.ranges).forEach(([filterKey, cfg]) => {
            const filter = this.filters[filterKey];
            if (filter.min !== null || filter.max !== null) {
                const rangeDefaults = this.config.ranges?.[filterKey] || { min: 0, max: 100 };
                const min = filter.min ?? rangeDefaults.min;
                const max = filter.max ?? rangeDefaults.max;
                pills.push(this.createFilterPill(cfg.pillFormat(min, max), filterKey));
            }
        });

        // Boolean filter pills
        Object.entries(FILTER_CONFIG.booleans).forEach(([filterKey, cfg]) => {
            if (this.filters[filterKey]) {
                pills.push(this.createFilterPill(cfg.pillLabel, filterKey));
            }
        });

        // Update DOM
        this.activeFiltersBar.innerHTML = '';

        if (pills.length > 0) {
            pills.forEach(pill => this.activeFiltersBar.appendChild(pill));

            const clearBtn = document.createElement('button');
            clearBtn.className = 'finder-active-clear';
            clearBtn.setAttribute('data-clear-filters', '');
            clearBtn.textContent = 'Clear all';
            clearBtn.addEventListener('click', () => this.clearAllFilters());
            this.activeFiltersBar.appendChild(clearBtn);

            this.activeFiltersBar.hidden = false;
        } else {
            this.activeFiltersBar.hidden = true;
        }
    }

    createFilterPill(label, type, value = null) {
        const pill = document.createElement('span');
        pill.className = 'active-filter-pill';
        pill.innerHTML = `
            ${label}
            <button class="active-filter-pill-remove" aria-label="Remove filter">
                <svg class="icon"><use href="#icon-x"></use></svg>
            </button>
        `;

        pill.querySelector('button').addEventListener('click', () => {
            this.removeFilter(type, value);
        });

        return pill;
    }

    removeFilter(type, value = null) {
        // Set filter
        if (FILTER_CONFIG.sets[type] && value) {
            this.filters[type].delete(value);
            const cfg = FILTER_CONFIG.sets[type];
            const checkbox = this.container.querySelector(`[data-filter="${cfg.selector}"][value="${value}"]`);
            if (checkbox) checkbox.checked = false;
        }
        // Range filter
        else if (FILTER_CONFIG.ranges[type]) {
            this.filters[type] = { min: null, max: null };
            this.resetRangeInputs(type);
        }
        // Boolean filter
        else if (FILTER_CONFIG.booleans[type]) {
            this.filters[type] = false;
            const cfg = FILTER_CONFIG.booleans[type];
            const checkbox = this.container.querySelector(`[data-filter="${cfg.selector}"]`);
            if (checkbox) checkbox.checked = false;
        }

        this.applyFilters();
    }

    resetRangeInputs(filterType) {
        const rangeContainer = this.container.querySelector(`[data-range-filter="${filterType}"]`);
        if (!rangeContainer) return;

        const rangeMin = parseFloat(rangeContainer.dataset.min) || 0;
        const rangeMax = parseFloat(rangeContainer.dataset.max) || 100;
        const minInput = rangeContainer.querySelector('[data-range-min]');
        const maxInput = rangeContainer.querySelector('[data-range-max]');
        const slider = rangeContainer.querySelector('[data-range-slider]');

        if (minInput) minInput.value = rangeMin;
        if (maxInput) maxInput.value = rangeMax;
        this.updateSliderVisuals(slider, rangeMin, rangeMax, rangeMin, rangeMax);
    }

    clearAllFilters() {
        // Clear set filters
        Object.entries(FILTER_CONFIG.sets).forEach(([filterKey, cfg]) => {
            this.filters[filterKey].clear();
            this.container.querySelectorAll(`[data-filter="${cfg.selector}"]`).forEach(checkbox => {
                checkbox.checked = false;
            });
        });

        // Clear range filters
        Object.keys(FILTER_CONFIG.ranges).forEach(filterKey => {
            this.filters[filterKey] = { min: null, max: null };
            this.resetRangeInputs(filterKey);
        });

        // Clear boolean filters
        Object.entries(FILTER_CONFIG.booleans).forEach(([filterKey, cfg]) => {
            this.filters[filterKey] = false;
            const checkbox = this.container.querySelector(`[data-filter="${cfg.selector}"]`);
            if (checkbox) checkbox.checked = false;
        });

        this.applyFilters();
    }

    // =========================================
    // SORTING
    // =========================================

    bindSortEvents() {
        this.sortSelect?.addEventListener('change', () => {
            this.currentSort = this.sortSelect.value;
            this.sortFilteredProducts();
            this.renderProducts();
        });
    }

    sortFilteredProducts() {
        const sortBy = this.currentSort;

        this.filteredProducts.sort((a, b) => {
            switch (sortBy) {
                case 'price-asc':
                    return (a.price ?? Infinity) - (b.price ?? Infinity);
                case 'price-desc':
                    return (b.price ?? 0) - (a.price ?? 0);
                case 'rating':
                    return (b.rating ?? 0) - (a.rating ?? 0);
                case 'speed':
                    return (b.top_speed ?? 0) - (a.top_speed ?? 0);
                case 'range':
                    return (b.range ?? 0) - (a.range ?? 0);
                case 'weight':
                    return (a.weight ?? Infinity) - (b.weight ?? Infinity);
                case 'battery':
                    return (b.battery ?? 0) - (a.battery ?? 0);
                case 'name':
                    return (a.name || '').localeCompare(b.name || '');
                case 'popularity':
                default:
                    return (b.popularity ?? 0) - (a.popularity ?? 0);
            }
        });
    }

    // =========================================
    // VIEW TOGGLE
    // =========================================

    bindViewToggle() {
        const toggleBtns = this.container.querySelectorAll('[data-view]');

        toggleBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const view = btn.dataset.view;

                toggleBtns.forEach(b => {
                    b.classList.toggle('is-active', b === btn);
                    b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
                });

                this.grid.dataset.view = view;
            });
        });
    }

    // =========================================
    // COMPARISON
    // =========================================

    bindComparisonEvents() {
        this.bindComparisonCheckboxes();

        const clearBtn = this.container.querySelector('[data-comparison-clear]');
        clearBtn?.addEventListener('click', () => this.clearComparison());

        const compareLink = this.container.querySelector('[data-comparison-link]');
        compareLink?.addEventListener('click', (e) => {
            if (this.selectedProducts.size < 2) {
                e.preventDefault();
                return;
            }
            const ids = Array.from(this.selectedProducts).join(',');
            compareLink.href = `/compare/?ids=${ids}`;
        });
    }

    bindComparisonCheckboxes() {
        this.grid.querySelectorAll('[data-compare-select]').forEach(checkbox => {
            const newCheckbox = checkbox.cloneNode(true);
            checkbox.parentNode.replaceChild(newCheckbox, checkbox);

            newCheckbox.addEventListener('change', () => {
                const productId = newCheckbox.value;

                if (newCheckbox.checked) {
                    if (this.selectedProducts.size >= this.maxCompare) {
                        newCheckbox.checked = false;
                        return;
                    }
                    this.selectedProducts.add(productId);
                } else {
                    this.selectedProducts.delete(productId);
                }

                this.updateComparisonBar();
            });
        });
    }

    updateComparisonBar() {
        if (!this.comparisonBar) return;

        const count = this.selectedProducts.size;
        const countEl = this.comparisonBar.querySelector('[data-comparison-count]');
        const productsEl = this.comparisonBar.querySelector('[data-comparison-products]');
        const compareLink = this.comparisonBar.querySelector('[data-comparison-link]');

        if (countEl) countEl.textContent = count;

        if (count > 0) {
            this.comparisonBar.classList.add('is-visible');
            this.comparisonBar.hidden = false;
        } else {
            this.comparisonBar.classList.remove('is-visible');
        }

        if (compareLink) {
            compareLink.classList.toggle('btn-disabled', count < 2);
        }

        if (productsEl) {
            productsEl.innerHTML = '';
            this.selectedProducts.forEach(id => {
                const product = this.products.find(p => String(p.id) === String(id));
                if (product?.thumbnail) {
                    const img = document.createElement('img');
                    img.src = product.thumbnail;
                    img.alt = '';
                    img.className = 'comparison-bar-thumb';
                    productsEl.appendChild(img);
                }
            });
        }
    }

    clearComparison() {
        this.selectedProducts.clear();
        this.grid.querySelectorAll('[data-compare-select]').forEach(checkbox => {
            checkbox.checked = false;
        });
        this.updateComparisonBar();
    }
}

// Auto-init
export function initFinder() {
    return new Finder();
}

export default Finder;

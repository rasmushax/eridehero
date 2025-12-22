/**
 * Finder Component
 * Handles product filtering, sorting, and comparison selection
 * Uses progressive loading to keep DOM light
 *
 * Architecture: Config is passed from PHP via window.ERideHero.filterConfig
 * This ensures a SINGLE SOURCE OF TRUTH - PHP generates all filter metadata
 */

import { getUserGeo } from '../services/geo-price.js';

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

        // Get data from PHP (single source of truth)
        this.products = window.ERideHero?.finderProducts || [];
        this.pageConfig = window.ERideHero?.finderConfig || {};
        this.filterConfig = window.ERideHero?.filterConfig || {};

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

        // Search state
        this.searchTerm = '';

        // Geo state (populated async before first render)
        this.userGeo = 'US';
        this.userCurrency = 'USD';
        this.currencySymbol = '$';

        // Spec display config from PHP (single source of truth)
        this.specDisplayConfig = this.filterConfig.specDisplay || {};
        this.defaultSpecKeys = this.filterConfig.defaultSpecKeys || [];

        // Create debounced version for rapid input events
        this.debouncedApplyFilters = this.debounce(() => this.applyFilters(), 150);

        this.init();
    }

    /**
     * Format a spec value using config from PHP
     * Config can have: round (decimal places), prefix, suffix, raw (use value as-is), join (for arrays)
     */
    formatSpecValue(filterKey, value) {
        const config = this.specDisplayConfig[filterKey];
        if (!config || value == null) return null;

        // Handle arrays (suspension types, etc.)
        if (Array.isArray(value)) {
            const joinStr = config.join || ', ';
            return value.join(joinStr);
        }

        // Raw mode - just return the value (for strings like tire_type)
        if (config.raw) {
            return `${config.prefix || ''}${value}${config.suffix || ''}`;
        }

        // Numeric formatting
        const num = parseFloat(value);
        if (isNaN(num)) {
            return `${config.prefix || ''}${value}${config.suffix || ''}`;
        }

        // Apply rounding
        let formatted;
        if (config.round !== undefined) {
            formatted = config.round === 0 ? Math.round(num) : num.toFixed(config.round);
        } else {
            formatted = num;
        }

        return `${config.prefix || ''}${formatted}${config.suffix || ''}`;
    }

    /**
     * Create initial filter state from passed config
     */
    createFilterState() {
        const state = {};
        const config = this.filterConfig;

        // Set filters → new Set()
        Object.keys(config.sets || {}).forEach(key => {
            state[key] = new Set();
        });

        // Range filters → { min: null, max: null } or { value: null } for contains mode
        Object.keys(config.ranges || {}).forEach(key => {
            const cfg = config.ranges[key];
            if (cfg.filterMode === 'contains') {
                state[key] = { value: null };
            } else {
                state[key] = { min: null, max: null };
            }
        });

        // Boolean filters → false
        Object.keys(config.booleans || {}).forEach(key => {
            state[key] = false;
        });

        // Tristate filters → 'any'
        Object.keys(config.tristates || {}).forEach(key => {
            state[key] = 'any';
        });

        return state;
    }

    async init() {
        // Detect user geo first and process products for their region
        await this.detectUserGeo();
        this.processProductsForGeo();

        // Bind all events
        this.bindFilterEvents();
        this.bindTristateEvents();
        this.bindSortEvents();
        this.bindComparisonEvents();
        this.bindRangeSliders();
        this.bindHeightInputs();
        this.bindFilterItemToggles();
        this.bindFilterSearch();
        this.bindFilterListSearch();
        this.bindFilterShowAll();
        this.bindViewToggle();
        this.bindLoadMore();
        this.bindProductSearch();

        // Listen for manual region changes
        window.addEventListener('erh:region-changed', (e) => this.handleRegionChange(e));

        // Initial render
        this.applyFilters();
    }

    /**
     * Detect user's geo region via IPInfo
     */
    async detectUserGeo() {
        try {
            const geoData = await getUserGeo();
            this.userGeo = geoData.geo;
            this.userCurrency = geoData.currency;
            this.currencySymbol = geoData.symbol;
        } catch (e) {
            // Default to US on error (already set in constructor)
        }
    }

    /**
     * Process products to extract pricing for user's geo region
     * Called on init and when region changes
     */
    processProductsForGeo() {
        // Get the raw products from PHP and extract geo-specific pricing
        const rawProducts = window.ERideHero?.finderProducts || [];

        this.products = rawProducts.map(product => {
            const pricing = product.pricing || {};
            // Get pricing for user's region ONLY - no fallback to US
            // (Users from unmapped countries already default to US at geo-detection level)
            const geoPricing = pricing[this.userGeo] || {};

            return {
                ...product,
                price: geoPricing.current_price ?? null,
                current_price: geoPricing.current_price ?? null,
                in_stock: geoPricing.instock ?? false,
                best_link: geoPricing.bestlink ?? null,
                avg_3m: geoPricing.avg_3m ?? null,
                price_indicator: this.calculatePriceIndicator(
                    geoPricing.current_price,
                    geoPricing.avg_3m
                ),
            };
        });

        // Reset filtered products
        this.filteredProducts = [...this.products];

        // Update price filter bounds and UI for new geo
        this.updatePriceFilterBounds();
        this.updatePriceFilterUI();
    }

    /**
     * Calculate price indicator (% vs 3-month average)
     */
    calculatePriceIndicator(currentPrice, avg3m) {
        if (!currentPrice || !avg3m || avg3m <= 0) return null;
        return Math.round(((currentPrice - avg3m) / avg3m) * 100);
    }

    /**
     * Update price filter bounds based on geo-specific prices
     */
    updatePriceFilterBounds() {
        const prices = this.products
            .map(p => p.price)
            .filter(p => p !== null && p > 0);

        const maxPrice = prices.length > 0
            ? Math.ceil(Math.max(...prices) / 100) * 100
            : 5000;

        if (this.filterConfig.ranges?.price) {
            this.filterConfig.ranges.price.max = maxPrice;
        }

        // Update DOM max inputs
        const rangeContainer = this.container?.querySelector('[data-range-filter="price"]');
        if (rangeContainer) {
            const maxInput = rangeContainer.querySelector('[data-range-max]');
            if (maxInput) {
                maxInput.max = maxPrice;
                // Only update value if it was at the old max
                if (parseFloat(maxInput.value) >= maxPrice) {
                    maxInput.value = maxPrice;
                }
            }
        }
    }

    /**
     * Update price filter UI (preset labels and input prefixes) for current geo
     */
    updatePriceFilterUI() {
        if (!this.container) return;

        const symbol = this.currencySymbol;

        // Update preset labels
        const pricePresets = this.container.querySelectorAll(
            '[data-range-filter="price"] .filter-preset-label'
        );
        pricePresets.forEach(label => {
            // Store original template on first run
            if (!label.dataset.template) {
                label.dataset.template = label.textContent;
            }
            // Replace $ with current currency symbol
            label.textContent = label.dataset.template.replace(/\$/g, symbol);
        });

        // Update range input prefixes
        const prefixes = this.container.querySelectorAll(
            '[data-range-filter="price"] .filter-range-prefix'
        );
        prefixes.forEach(prefix => {
            prefix.textContent = symbol;
        });
    }

    /**
     * Handle manual region change event
     */
    handleRegionChange(event) {
        const { region, currency, symbol } = event.detail;
        if (region && region !== this.userGeo) {
            this.userGeo = region;
            this.userCurrency = currency;
            this.currencySymbol = symbol;

            // Reprocess products with new geo and re-render
            this.processProductsForGeo();
            this.applyFilters();
        }
    }

    /**
     * Format price with current geo's currency symbol
     */
    formatProductPrice(product) {
        if (!product.price) return '';
        return `${this.currencySymbol}${Math.round(product.price).toLocaleString()}`;
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
    // FILTER EVENTS (Config-Driven)
    // =========================================

    bindFilterEvents() {
        const config = this.filterConfig;

        // Set filters (checkboxes) - driven by config
        Object.entries(config.sets || {}).forEach(([filterKey, cfg]) => {
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
        Object.entries(config.booleans || {}).forEach(([filterKey, cfg]) => {
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

    bindTristateEvents() {
        // Tristate filters (Any / Yes / No segmented controls)
        this.container.querySelectorAll('[data-tristate-filter]').forEach(tristateContainer => {
            const filterKey = tristateContainer.dataset.tristateFilter;
            const buttons = tristateContainer.querySelectorAll('.filter-tristate-btn');
            const hiddenInput = tristateContainer.querySelector('[data-tristate-input]');

            buttons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const value = btn.dataset.value;

                    // Update UI
                    buttons.forEach(b => {
                        b.classList.remove('is-active');
                        b.setAttribute('aria-checked', 'false');
                    });
                    btn.classList.add('is-active');
                    btn.setAttribute('aria-checked', 'true');

                    // Update hidden input
                    if (hiddenInput) {
                        hiddenInput.value = value;
                    }

                    // Update filter state
                    this.filters[filterKey] = value;
                    this.applyFilters();
                });
            });
        });
    }

    bindRangeSliders() {
        this.container.querySelectorAll('[data-range-filter]').forEach(rangeContainer => {
            const filterType = rangeContainer.dataset.rangeFilter;
            const isContainsMode = rangeContainer.dataset.filterMode === 'contains';
            const rangeMin = parseFloat(rangeContainer.dataset.min) || 0;
            const rangeMax = parseFloat(rangeContainer.dataset.max) || 100;
            const rangeStep = parseFloat(rangeContainer.dataset.step) || 1;

            // Initialize distribution bars (all selected by default)
            this.updateDistributionBars(rangeContainer, 0, 1);

            // Presets (both modes)
            const presetsContainer = rangeContainer.querySelector('.filter-presets');
            if (presetsContainer) {
                this.bindRangePresets(rangeContainer, filterType, isContainsMode, rangeMin, rangeMax, rangeStep);
            }

            if (isContainsMode) {
                // Contains mode: single value input
                const valueInput = rangeContainer.querySelector('[data-range-value]');
                if (valueInput) {
                    valueInput.addEventListener('change', () => {
                        let value = valueInput.value ? parseFloat(valueInput.value) : null;

                        // Validate and clamp value
                        if (value !== null) {
                            if (isNaN(value) || value < rangeMin) {
                                value = null;
                                valueInput.value = '';
                            } else if (value > rangeMax) {
                                value = rangeMax;
                                valueInput.value = rangeMax;
                            }
                        }

                        this.filters[filterType] = { value };
                        this.deselectPresets(rangeContainer);
                        this.applyFilters();
                    });
                }
            } else {
                // Range mode: min/max inputs + slider
                const minInput = rangeContainer.querySelector('[data-range-min]');
                const maxInput = rangeContainer.querySelector('[data-range-max]');
                const slider = rangeContainer.querySelector('[data-range-slider]');
                const minHandle = slider?.querySelector('[data-handle="min"]');
                const maxHandle = slider?.querySelector('[data-handle="max"]');
                const fill = slider?.querySelector('.filter-range-fill');

                const updateFromInputs = () => {
                    let minVal = parseFloat(minInput.value);
                    let maxVal = parseFloat(maxInput.value);

                    // Handle invalid/empty inputs
                    if (isNaN(minVal)) minVal = rangeMin;
                    if (isNaN(maxVal)) maxVal = rangeMax;

                    // Snap to step
                    minVal = this.snapToStep(minVal, rangeStep);
                    maxVal = this.snapToStep(maxVal, rangeStep);

                    // Clamp to allowed range
                    minVal = Math.max(rangeMin, Math.min(rangeMax, minVal));
                    maxVal = Math.max(rangeMin, Math.min(rangeMax, maxVal));

                    // Ensure min doesn't exceed max
                    if (minVal > maxVal) {
                        // Swap them
                        [minVal, maxVal] = [maxVal, minVal];
                    }

                    // Update inputs with validated values
                    minInput.value = minVal;
                    maxInput.value = maxVal;

                    this.filters[filterType] = {
                        min: minVal > rangeMin ? minVal : null,
                        max: maxVal < rangeMax ? maxVal : null
                    };

                    this.updateSliderVisuals(slider, minVal, maxVal, rangeMin, rangeMax);
                    this.deselectPresets(rangeContainer);
                    this.applyFilters();
                };

                minInput?.addEventListener('change', updateFromInputs);
                maxInput?.addEventListener('change', updateFromInputs);

                if (minHandle && maxHandle && slider) {
                    this.initSliderDrag(slider, minHandle, maxHandle, fill, minInput, maxInput, rangeMin, rangeMax, rangeStep, filterType, rangeContainer);
                }
            }
        });
    }

    /**
     * Bind preset radio buttons for range filters
     */
    bindRangePresets(rangeContainer, filterType, isContainsMode, rangeMin, rangeMax, rangeStep = 1) {
        const presets = rangeContainer.querySelectorAll('[data-preset]');
        const slider = rangeContainer.querySelector('[data-range-slider]');
        const heightInput = rangeContainer.querySelector('[data-height-input]');
        const singleHandle = slider?.querySelector('[data-handle="value"]');
        const fill = slider?.querySelector('.filter-range-fill');

        presets.forEach(preset => {
            const label = preset.closest('.filter-preset');

            // Capture state before click using pointerdown (fires before click)
            const captureState = () => {
                preset.dataset.wasChecked = preset.checked ? 'true' : 'false';
            };

            label?.addEventListener('pointerdown', captureState);
            label?.addEventListener('mousedown', captureState); // Fallback

            preset.addEventListener('click', (e) => {
                // If this preset was already checked, toggle it off
                if (preset.dataset.wasChecked === 'true') {
                    delete preset.dataset.wasChecked;
                    // Defer uncheck to run after browser's default radio behavior
                    requestAnimationFrame(() => {
                        preset.checked = false;
                    });
                    this.clearRangeFilter(rangeContainer, filterType, isContainsMode, rangeMin, rangeMax, rangeStep, slider, heightInput, singleHandle, fill);
                    this.applyFilters();
                    return;
                }
                delete preset.dataset.wasChecked;
            });

            preset.addEventListener('change', () => {
                if (!preset.checked) return;

                if (isContainsMode) {
                    // Contains mode: use data-preset-value
                    const presetValue = preset.dataset.presetValue;
                    const valueInput = rangeContainer.querySelector('[data-range-value]');

                    if (presetValue !== undefined && presetValue !== '') {
                        const value = parseFloat(presetValue);
                        if (valueInput) valueInput.value = value;
                        this.filters[filterType] = { value };

                        // Update height inputs if present
                        if (heightInput) {
                            const feetInput = heightInput.querySelector('[data-height-feet]');
                            const inchesInput = heightInput.querySelector('[data-height-inches]');
                            const { feet, inches } = this.inchesToFeetInches(value);
                            if (feetInput) feetInput.value = feet;
                            if (inchesInput) inchesInput.value = inches;
                        }

                        // Update single-thumb slider if present
                        if (singleHandle && fill) {
                            const pos = (value - rangeMin) / (rangeMax - rangeMin);
                            singleHandle.style.setProperty('--pos', Math.max(0, Math.min(1, pos)));
                            fill.style.setProperty('--pos', Math.max(0, Math.min(1, pos)));
                        }
                    }
                } else {
                    // Range mode: use data-preset-min/max
                    const presetMin = preset.dataset.presetMin;
                    const presetMax = preset.dataset.presetMax;
                    const minInput = rangeContainer.querySelector('[data-range-min]');
                    const maxInput = rangeContainer.querySelector('[data-range-max]');

                    const minVal = presetMin !== undefined && presetMin !== '' ? parseFloat(presetMin) : rangeMin;
                    const maxVal = presetMax !== undefined && presetMax !== '' ? parseFloat(presetMax) : rangeMax;

                    if (minInput) minInput.value = minVal;
                    if (maxInput) maxInput.value = maxVal;

                    this.filters[filterType] = {
                        min: minVal > rangeMin ? minVal : null,
                        max: maxVal < rangeMax ? maxVal : null
                    };

                    this.updateSliderVisuals(slider, minVal, maxVal, rangeMin, rangeMax);

                    // Update distribution bars
                    const minPos = (minVal - rangeMin) / (rangeMax - rangeMin);
                    const maxPos = (maxVal - rangeMin) / (rangeMax - rangeMin);
                    this.updateDistributionBars(rangeContainer, minPos, maxPos);
                }

                this.applyFilters();
            });
        });
    }

    /**
     * Clear a range filter and reset UI to defaults
     */
    clearRangeFilter(rangeContainer, filterType, isContainsMode, rangeMin, rangeMax, rangeStep, slider, heightInput, singleHandle, fill) {
        const step = rangeStep || 1;

        if (isContainsMode) {
            const valueInput = rangeContainer.querySelector('[data-range-value]');
            if (valueInput) valueInput.value = '';
            this.filters[filterType] = { value: null };

            // Clear height inputs
            if (heightInput) {
                const feetInput = heightInput.querySelector('[data-height-feet]');
                const inchesInput = heightInput.querySelector('[data-height-inches]');
                if (feetInput) feetInput.value = '';
                if (inchesInput) inchesInput.value = '';
            }

            // Reset single-thumb slider to middle
            if (singleHandle && fill) {
                singleHandle.style.setProperty('--pos', 0.5);
                fill.style.setProperty('--pos', 0.5);
            }
        } else {
            const minInput = rangeContainer.querySelector('[data-range-min]');
            const maxInput = rangeContainer.querySelector('[data-range-max]');

            if (minInput) minInput.value = this.snapToStep(rangeMin, step);
            if (maxInput) maxInput.value = this.snapToStep(rangeMax, step);

            this.filters[filterType] = { min: null, max: null };
            this.updateSliderVisuals(slider, rangeMin, rangeMax, rangeMin, rangeMax);
            this.updateDistributionBars(rangeContainer, 0, 1);
        }
    }

    /**
     * Deselect all presets when manual input is used
     */
    deselectPresets(rangeContainer) {
        rangeContainer.querySelectorAll('[data-preset]').forEach(preset => {
            preset.checked = false;
        });
    }

    /**
     * Bind height inputs (feet/inches → total inches)
     */
    bindHeightInputs() {
        this.container.querySelectorAll('[data-height-input]').forEach(container => {
            const rangeContainer = container.closest('[data-range-filter]');
            if (!rangeContainer) return;

            const filterType = rangeContainer.dataset.rangeFilter;
            const feetInput = container.querySelector('[data-height-feet]');
            const inchesInput = container.querySelector('[data-height-inches]');
            const hiddenInput = container.querySelector('[data-range-value]');
            const slider = rangeContainer.querySelector('[data-range-slider]');
            const handle = slider?.querySelector('[data-handle="value"]');
            const fill = slider?.querySelector('.filter-range-fill');

            const rangeMin = parseFloat(rangeContainer.dataset.min) || 0;
            const rangeMax = parseFloat(rangeContainer.dataset.max) || 100;

            const updateFromInputs = () => {
                let feet = parseInt(feetInput.value, 10) || 0;
                let inches = parseInt(inchesInput.value, 10) || 0;

                // Clamp feet (0-8 reasonable range)
                feet = Math.max(0, Math.min(8, feet));
                // Clamp inches (0-11)
                inches = Math.max(0, Math.min(11, inches));

                // Update inputs with clamped values
                if (feetInput.value !== '' && parseInt(feetInput.value, 10) !== feet) {
                    feetInput.value = feet;
                }
                if (inchesInput.value !== '' && parseInt(inchesInput.value, 10) !== inches) {
                    inchesInput.value = inches;
                }

                let totalInches = this.feetInchesToInches(feet, inches);

                // Clamp total to range bounds
                if (totalInches > 0) {
                    totalInches = Math.max(rangeMin, Math.min(rangeMax, totalInches));
                }

                // Update hidden field
                hiddenInput.value = totalInches > 0 ? totalInches : '';

                // Update filter state
                this.filters[filterType] = { value: totalInches > 0 ? totalInches : null };

                // Update slider visual
                if (handle && fill && totalInches > 0) {
                    const pos = (totalInches - rangeMin) / (rangeMax - rangeMin);
                    handle.style.setProperty('--pos', Math.max(0, Math.min(1, pos)));
                    fill.style.setProperty('--pos', Math.max(0, Math.min(1, pos)));
                }

                this.deselectPresets(rangeContainer);
                this.applyFilters();
            };

            feetInput?.addEventListener('change', updateFromInputs);
            inchesInput?.addEventListener('change', updateFromInputs);

            // Also bind single-thumb slider for height
            if (slider && handle) {
                this.initSingleSliderDrag(slider, handle, fill, rangeMin, rangeMax, filterType, rangeContainer, (value) => {
                    // Update feet/inches inputs from slider value
                    const { feet, inches } = this.inchesToFeetInches(value);
                    feetInput.value = feet;
                    inchesInput.value = inches;
                    hiddenInput.value = value;
                });
            }
        });
    }

    /**
     * Initialize single-thumb slider drag
     */
    initSingleSliderDrag(slider, handle, fill, rangeMin, rangeMax, filterType, rangeContainer, onUpdate) {
        let isDragging = false;

        const updateValue = (pos) => {
            const value = Math.round(rangeMin + pos * (rangeMax - rangeMin));
            handle.style.setProperty('--pos', pos);
            fill.style.setProperty('--pos', pos);

            if (onUpdate) {
                onUpdate(value);
            }
        };

        const onMove = (e) => {
            if (!isDragging) return;
            e.preventDefault();
            updateValue(this.getPositionFromEvent(e, slider));
        };

        const onEnd = () => {
            if (!isDragging) return;
            isDragging = false;
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onEnd);
            document.removeEventListener('touchmove', onMove);
            document.removeEventListener('touchend', onEnd);

            // Get current value from handle position
            const pos = parseFloat(handle.style.getPropertyValue('--pos')) || 0;
            const value = Math.round(rangeMin + pos * (rangeMax - rangeMin));

            this.filters[filterType] = { value: value > rangeMin ? value : null };
            this.deselectPresets(rangeContainer);
            this.applyFilters();
        };

        handle.addEventListener('mousedown', () => {
            isDragging = true;
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onEnd);
        });

        handle.addEventListener('touchstart', () => {
            isDragging = true;
            document.addEventListener('touchmove', onMove, { passive: false });
            document.addEventListener('touchend', onEnd);
        });
    }

    initSliderDrag(slider, minHandle, maxHandle, fill, minInput, maxInput, rangeMin, rangeMax, rangeStep, filterType, rangeContainer) {
        let activeHandle = null;

        const updateValue = (handle, pos) => {
            const rawValue = rangeMin + pos * (rangeMax - rangeMin);
            const value = this.snapToStep(rawValue, rangeStep);
            // Recalculate position from snapped value for visual consistency
            const snappedPos = (value - rangeMin) / (rangeMax - rangeMin);

            if (handle === minHandle) {
                const maxPos = parseFloat(maxHandle.style.getPropertyValue('--pos')) || 1;
                if (snappedPos <= maxPos) {
                    minHandle.style.setProperty('--pos', snappedPos);
                    minInput.value = value;
                    fill.style.setProperty('--min', snappedPos);
                    // Update distribution bars during drag
                    this.updateDistributionBars(rangeContainer, snappedPos, maxPos);
                }
            } else {
                const minPos = parseFloat(minHandle.style.getPropertyValue('--pos')) || 0;
                if (snappedPos >= minPos) {
                    maxHandle.style.setProperty('--pos', snappedPos);
                    maxInput.value = value;
                    fill.style.setProperty('--max', snappedPos);
                    // Update distribution bars during drag
                    this.updateDistributionBars(rangeContainer, minPos, snappedPos);
                }
            }
        };

        const onMove = (e) => {
            if (!activeHandle) return;
            e.preventDefault();
            updateValue(activeHandle, this.getPositionFromEvent(e, slider));
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
            this.deselectPresets(rangeContainer);
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

        // Update distribution bar colors
        this.updateDistributionBars(slider.closest('[data-range-filter]'), minPos, maxPos);
    }

    /**
     * Update distribution bar colors based on selection range
     */
    updateDistributionBars(rangeContainer, minPos, maxPos) {
        if (!rangeContainer) return;

        const bars = rangeContainer.querySelectorAll('.filter-range-bar');
        const barCount = bars.length;
        if (barCount === 0) return;

        bars.forEach((bar, index) => {
            // Each bar represents a segment of the range
            const barStart = index / barCount;
            const barEnd = (index + 1) / barCount;

            // Bar is selected if it overlaps with the selection range
            const isSelected = barEnd > minPos && barStart < maxPos;
            bar.classList.toggle('is-selected', isSelected);
        });
    }

    /**
     * Get normalized position (0-1) from mouse/touch event on slider
     */
    getPositionFromEvent(e, slider) {
        const rect = slider.getBoundingClientRect();
        const handleRadius = 9;
        const trackWidth = rect.width - (handleRadius * 2);
        const x = (e.type.includes('touch') ? e.touches[0].clientX : e.clientX) - rect.left - handleRadius;
        return Math.max(0, Math.min(1, x / trackWidth));
    }

    /**
     * Convert height in inches to feet/inches object
     */
    inchesToFeetInches(totalInches) {
        return {
            feet: Math.floor(totalInches / 12),
            inches: totalInches % 12
        };
    }

    /**
     * Convert feet/inches to total inches
     */
    feetInchesToInches(feet, inches) {
        return (feet * 12) + inches;
    }

    /**
     * Snap a value to the nearest step with floating point precision handling
     * @param {number} value - The value to snap
     * @param {number} step - The step size (default 1)
     * @returns {number} The snapped value
     */
    snapToStep(value, step = 1) {
        const snapped = Math.round(value / step) * step;
        const decimals = step < 1 ? Math.ceil(-Math.log10(step)) : 0;
        return parseFloat(snapped.toFixed(decimals));
    }

    /**
     * Create a debounced version of a function
     * @param {Function} fn - The function to debounce
     * @param {number} delay - Delay in milliseconds
     * @returns {Function} Debounced function
     */
    debounce(fn, delay) {
        let timeoutId;
        return (...args) => {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => fn.apply(this, args), delay);
        };
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
        const searchContainer = this.container.querySelector('[data-filter-search-container]');
        const searchInput = this.container.querySelector('[data-filter-search]');
        const clearBtn = this.container.querySelector('[data-filter-search-clear]');
        if (!searchInput) return;

        const performSearch = () => {
            const query = searchInput.value.toLowerCase().trim();

            // Toggle has-value class for clear button visibility
            searchContainer?.classList.toggle('has-value', !!query);

            // Hide/show filter items based on label match
            this.container.querySelectorAll('.filter-item').forEach(item => {
                const label = item.querySelector('.filter-item-label')?.textContent?.toLowerCase() || '';
                item.style.display = (!query || label.includes(query)) ? '' : 'none';
            });

            // Hide/show tristate filters based on label match
            this.container.querySelectorAll('.filter-tristate').forEach(tristate => {
                const label = tristate.querySelector('.filter-tristate-label')?.textContent?.toLowerCase() || '';
                tristate.style.display = (!query || label.includes(query)) ? '' : 'none';
            });

            // Hide/show standalone checkboxes
            this.container.querySelectorAll('.filter-group-content > .filter-checkbox').forEach(checkbox => {
                const label = checkbox.querySelector('.filter-checkbox-label')?.textContent?.toLowerCase() || '';
                checkbox.style.display = (!query || label.includes(query)) ? '' : 'none';
            });

            // Hide/show toggle switches (like "In stock only")
            this.container.querySelectorAll('.filter-toggle').forEach(toggle => {
                const label = toggle.querySelector('.filter-toggle-label')?.textContent?.toLowerCase() || '';
                toggle.style.display = (!query || label.includes(query)) ? '' : 'none';
            });

            // Hide empty filter groups
            this.container.querySelectorAll('.filter-group').forEach(group => {
                const content = group.querySelector('.filter-group-content');
                if (!content) return;

                // Check if any visible content exists
                const hasVisibleContent = Array.from(content.children).some(child => {
                    return child.style.display !== 'none';
                });

                group.style.display = (!query || hasVisibleContent) ? '' : 'none';
            });
        };

        searchInput.addEventListener('input', performSearch);

        clearBtn?.addEventListener('click', () => {
            searchInput.value = '';
            performSearch();
            searchInput.focus();
        });
    }

    /**
     * Product name search in toolbar
     */
    bindProductSearch() {
        const searchContainer = this.container.querySelector('[data-product-search]');
        const searchInput = this.container.querySelector('[data-product-search-input]');
        const clearBtn = this.container.querySelector('[data-product-search-clear]');
        if (!searchInput) return;

        const performSearch = () => {
            this.searchTerm = searchInput.value.trim();
            searchContainer?.classList.toggle('has-value', !!this.searchTerm);
            this.debouncedApplyFilters();
        };

        searchInput.addEventListener('input', performSearch);

        // Handle Enter key to prevent form submission
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.applyFilters();
            }
        });

        clearBtn?.addEventListener('click', () => {
            searchInput.value = '';
            this.searchTerm = '';
            searchContainer?.classList.remove('has-value');
            this.applyFilters();
            searchInput.focus();
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
    // APPLY FILTERS (Config-Driven)
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
        const config = this.filterConfig;

        // Check search term first (most selective filter)
        if (this.searchTerm) {
            const searchLower = this.searchTerm.toLowerCase();
            const name = (product.name || '').toLowerCase();
            const brand = (product.brand || '').toLowerCase();
            if (!name.includes(searchLower) && !brand.includes(searchLower)) {
                return false;
            }
        }

        // Check set filters (brands, motor_positions, battery_types, etc.)
        for (const [filterKey, cfg] of Object.entries(config.sets || {})) {
            const filterSet = this.filters[filterKey];
            if (filterSet.size > 0) {
                const productValue = product[cfg.productKey];

                // Handle array-type product values (e.g., suspension_type)
                if (cfg.isArray) {
                    if (!productValue || !Array.isArray(productValue)) return false;
                    // Check if any product value matches any filter value
                    const hasMatch = productValue.some(v => filterSet.has(v));
                    if (!hasMatch) return false;
                } else {
                    if (!productValue || !filterSet.has(productValue)) return false;
                }
            }
        }

        // Check range filters
        for (const [filterKey, cfg] of Object.entries(config.ranges || {})) {
            const filter = this.filters[filterKey];

            // Contains mode: check if user's value falls within product's min/max range
            if (cfg.filterMode === 'contains') {
                if (filter.value !== null) {
                    const productMin = product[cfg.productKeyMin];
                    const productMax = product[cfg.productKeyMax];

                    // Exclude products without range data when filter is active
                    if (productMin == null && productMax == null) return false;

                    if (productMin != null && filter.value < productMin) return false;
                    if (productMax != null && filter.value > productMax) return false;
                }
                continue;
            }

            // Standard range mode
            const value = product[cfg.productKey];

            // Price filter special handling:
            // - When min is 0 or null: include products without price
            // - When min is > 0: exclude products without price
            if (filterKey === 'price') {
                if (value == null) {
                    // No price: only include if min is not set or is 0
                    if (filter.min !== null && filter.min > 0) return false;
                } else {
                    if (filter.min !== null && value < filter.min) return false;
                    if (filter.max !== null && value > filter.max) return false;
                }
                continue;
            }

            // Other range filters: exclude products without data when filter is active
            if (value == null) {
                if (filter.min !== null || filter.max !== null) return false;
            } else {
                if (filter.min !== null && value < filter.min) return false;
                if (filter.max !== null && value > filter.max) return false;
            }
        }

        // Check boolean filters
        for (const [filterKey, cfg] of Object.entries(config.booleans || {})) {
            if (this.filters[filterKey] && !product[cfg.productKey]) {
                return false;
            }
        }

        // Check tristate filters
        for (const [filterKey, cfg] of Object.entries(config.tristates || {})) {
            const filterValue = this.filters[filterKey];
            if (filterValue === 'any') continue;

            const productValue = product[cfg.productKey];
            if (filterValue === 'yes' && productValue !== true) return false;
            if (filterValue === 'no' && productValue !== false) return false;
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
                         loading="lazy">${product.price ? `<div class="product-card-price-row">
                        <span class="product-card-price">${this.formatProductPrice(product)}</span>
                        ${priceIndicator}
                    </div>` : ''}
                </div>
            </a>

            <div class="product-card-content">
                <h3 class="product-card-name">
                    <a href="${product.url}">${product.name}</a>
                </h3>
                ${specsText ? `<p class="product-card-specs">${specsText}</p>` : ''}
            </div>
        `;

        return card;
    }

    /**
     * Get list of active range filter keys (excludes non-spec filters)
     */
    getActiveSpecFilterKeys() {
        const excludeFilters = ['price', 'rider_height', 'in_stock'];
        const activeKeys = [];

        for (const [filterKey, filterState] of Object.entries(this.filters)) {
            // Skip excluded filters
            if (excludeFilters.includes(filterKey)) continue;

            // Skip if no display config for this filter
            if (!this.specDisplayConfig[filterKey]) continue;

            // Check if filter is active (has non-null/undefined values)
            if (filterState && typeof filterState === 'object') {
                // Range filter: check min/max (use != to catch both null and undefined)
                if (filterState.min != null || filterState.max != null) {
                    activeKeys.push(filterKey);
                }
                // Contains filter: check value
                else if (filterState.value != null) {
                    activeKeys.push(filterKey);
                }
            }
        }

        return activeKeys;
    }

    /**
     * Format product specs - smart version that adapts based on active filters
     * Active filter specs shown first in primary color, separated by dot from defaults
     */
    formatProductSpecs(product) {
        const maxSpecs = 7;
        const activeFilterKeys = this.getActiveSpecFilterKeys();
        const usedKeys = new Set();
        const activeSpecs = [];
        const defaultSpecs = [];

        // Helper to get formatted spec value
        const getSpecValue = (filterKey) => {
            if (usedKeys.has(filterKey)) return null;

            const config = this.specDisplayConfig[filterKey];
            if (!config) return null;

            const value = product[config.key];
            if (value == null || value === 0) return null;

            usedKeys.add(filterKey);
            return this.formatSpecValue(filterKey, value);
        };

        // First: Add specs for active filters (sorted by priority)
        const sortedActiveKeys = [...activeFilterKeys].sort((a, b) => {
            const priorityA = this.specDisplayConfig[a]?.priority ?? 99;
            const priorityB = this.specDisplayConfig[b]?.priority ?? 99;
            return priorityA - priorityB;
        });

        let totalSpecs = 0;
        for (const filterKey of sortedActiveKeys) {
            if (totalSpecs >= maxSpecs) break;
            const spec = getSpecValue(filterKey);
            if (spec) {
                activeSpecs.push(spec);
                totalSpecs++;
            }
        }

        // Second: Fill remaining slots with defaults
        for (const filterKey of this.defaultSpecKeys) {
            if (totalSpecs >= maxSpecs) break;
            const spec = getSpecValue(filterKey);
            if (spec) {
                defaultSpecs.push(spec);
                totalSpecs++;
            }
        }

        // Build output: active specs (highlighted) · default specs
        const parts = [];
        if (activeSpecs.length > 0) {
            parts.push(`<span class="specs-active">${activeSpecs.join(', ')}</span>`);
        }
        if (defaultSpecs.length > 0) {
            parts.push(defaultSpecs.join(', '));
        }

        return parts.join(' · ');
    }

    getPriceIndicator(product) {
        if (!product.price_indicator) return '';

        const indicator = product.price_indicator;
        if (indicator < -5) {
            return `<span class="product-card-indicator product-card-indicator--below">
                <span class="product-card-indicator-arrow">↓</span>${Math.abs(indicator)}%
            </span>`;
        } else if (indicator > 10) {
            return `<span class="product-card-indicator product-card-indicator--above">
                <span class="product-card-indicator-arrow">↑</span>${indicator}%
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
        const productName = this.pageConfig.shortName || 'product';

        this.resultsCount.innerHTML = `<strong>${total}</strong> ${productName}s`;
    }

    // =========================================
    // ACTIVE FILTERS BAR (Config-Driven)
    // =========================================

    updateActiveFiltersBar() {
        if (!this.activeFiltersBar) return;

        const config = this.filterConfig;
        const pills = [];

        // Search term pill (first for prominence)
        if (this.searchTerm) {
            pills.push(this.createFilterPill(`"${this.searchTerm}"`, 'search'));
        }

        // Set filter pills
        Object.entries(config.sets || {}).forEach(([filterKey, cfg]) => {
            this.filters[filterKey].forEach(value => {
                const label = this.formatSetPillLabel(cfg, value);
                pills.push(this.createFilterPill(label, filterKey, value));
            });
        });

        // Range filter pills
        Object.entries(config.ranges || {}).forEach(([filterKey, cfg]) => {
            const filter = this.filters[filterKey];

            if (cfg.filterMode === 'contains') {
                // Contains mode: single value
                if (filter.value !== null) {
                    const label = this.formatContainsPillLabel(cfg, filter.value);
                    pills.push(this.createFilterPill(label, filterKey));
                }
            } else {
                // Range mode: min/max
                if (filter.min !== null || filter.max !== null) {
                    const rangeDefaults = this.pageConfig.ranges?.[filterKey] || { min: 0, max: 100 };
                    const min = filter.min ?? rangeDefaults.min;
                    const max = filter.max ?? rangeDefaults.max;
                    const label = this.formatRangePillLabel(cfg, min, max);
                    pills.push(this.createFilterPill(label, filterKey));
                }
            }
        });

        // Boolean filter pills
        Object.entries(config.booleans || {}).forEach(([filterKey, cfg]) => {
            if (this.filters[filterKey]) {
                pills.push(this.createFilterPill(cfg.label, filterKey));
            }
        });

        // Tristate filter pills
        Object.entries(config.tristates || {}).forEach(([filterKey, cfg]) => {
            const value = this.filters[filterKey];
            if (value !== 'any') {
                const label = this.formatTristatePillLabel(cfg, value);
                pills.push(this.createFilterPill(label, filterKey));
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

    /**
     * Format pill label for set filters
     */
    formatSetPillLabel(cfg, value) {
        // Simple label - just the value, possibly with a prefix from the label
        const label = cfg.label || '';

        // For specific filter types, add context
        if (label.toLowerCase().includes('position')) {
            return `Motor: ${value}`;
        }
        if (label.toLowerCase().includes('type') && cfg.selector.includes('battery')) {
            return `Battery: ${value}`;
        }
        if (label.toLowerCase().includes('type') && cfg.selector.includes('brake')) {
            return `Brakes: ${value}`;
        }
        if (label.toLowerCase().includes('tire')) {
            return `Tires: ${value}`;
        }
        if (label.toLowerCase().includes('type') && cfg.selector.includes('suspension')) {
            return `Suspension: ${value}`;
        }

        return value;
    }

    /**
     * Format pill label for range filters
     */
    formatRangePillLabel(cfg, min, max) {
        const prefix = cfg.prefix || '';
        const suffix = cfg.suffix || '';

        // Use dynamic currency symbol for price filter
        if (prefix === '$' || cfg.field === 'current_price') {
            const symbol = this.currencySymbol;
            return `${symbol}${min.toLocaleString()} – ${symbol}${max.toLocaleString()}`;
        }

        return `${min}–${max}${suffix}`;
    }

    /**
     * Format pill label for contains mode filters (e.g., rider height)
     */
    formatContainsPillLabel(cfg, value) {
        const label = cfg.label || '';
        const suffix = cfg.suffix || '';

        // Format height in feet/inches if it looks like rider height
        if (label.toLowerCase().includes('height') && suffix === '"') {
            const { feet, inches } = this.inchesToFeetInches(value);
            return `${feet}'${inches}" tall`;
        }

        return `${label}: ${value}${suffix}`;
    }

    /**
     * Format pill label for tristate filters
     */
    formatTristatePillLabel(cfg, value) {
        const label = cfg.label || '';

        if (value === 'yes') {
            return label;
        }

        // For "no" value, negate the label
        if (label.toLowerCase().includes('removable')) {
            return 'Fixed battery';
        }
        if (label.toLowerCase().includes('regenerative')) {
            return 'No regen braking';
        }
        if (label.toLowerCase().includes('self-healing')) {
            return 'No self-healing';
        }
        if (label.toLowerCase().includes('adjustable')) {
            return 'Fixed suspension';
        }

        return `No ${label.toLowerCase()}`;
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
        const config = this.filterConfig;

        // Search filter
        if (type === 'search') {
            this.searchTerm = '';
            const searchInput = this.container.querySelector('[data-product-search-input]');
            const searchContainer = this.container.querySelector('[data-product-search]');
            if (searchInput) searchInput.value = '';
            searchContainer?.classList.remove('has-value');
        }
        // Set filter
        else if (config.sets?.[type] && value) {
            this.filters[type].delete(value);
            const cfg = config.sets[type];
            const checkbox = this.container.querySelector(`[data-filter="${cfg.selector}"][value="${value}"]`);
            if (checkbox) checkbox.checked = false;
        }
        // Range filter
        else if (config.ranges?.[type]) {
            const cfg = config.ranges[type];
            if (cfg.filterMode === 'contains') {
                this.filters[type] = { value: null };
            } else {
                this.filters[type] = { min: null, max: null };
            }
            this.resetRangeInputs(type);
        }
        // Boolean filter
        else if (config.booleans?.[type]) {
            this.filters[type] = false;
            const cfg = config.booleans[type];
            const checkbox = this.container.querySelector(`[data-filter="${cfg.selector}"]`);
            if (checkbox) checkbox.checked = false;
        }
        // Tristate filter
        else if (config.tristates?.[type]) {
            this.filters[type] = 'any';
            this.resetTristateUI(type);
        }

        this.applyFilters();
    }

    resetRangeInputs(filterType) {
        const rangeContainer = this.container.querySelector(`[data-range-filter="${filterType}"]`);
        if (!rangeContainer) return;

        const isContainsMode = rangeContainer.dataset.filterMode === 'contains';
        const rangeMin = parseFloat(rangeContainer.dataset.min) || 0;
        const rangeMax = parseFloat(rangeContainer.dataset.max) || 100;
        const rangeStep = parseFloat(rangeContainer.dataset.step) || 1;
        const slider = rangeContainer.querySelector('[data-range-slider]');
        const heightInput = rangeContainer.querySelector('[data-height-input]');
        const singleHandle = slider?.querySelector('[data-handle="value"]');
        const fill = slider?.querySelector('.filter-range-fill');

        // Deselect any active presets
        this.deselectPresets(rangeContainer);

        // Use shared clear logic
        this.clearRangeFilter(rangeContainer, filterType, isContainsMode, rangeMin, rangeMax, rangeStep, slider, heightInput, singleHandle, fill);
    }

    clearAllFilters() {
        const config = this.filterConfig;

        // Clear set filters
        Object.entries(config.sets || {}).forEach(([filterKey, cfg]) => {
            this.filters[filterKey].clear();
            this.container.querySelectorAll(`[data-filter="${cfg.selector}"]`).forEach(checkbox => {
                checkbox.checked = false;
            });
        });

        // Clear range filters
        Object.keys(config.ranges || {}).forEach(filterKey => {
            const cfg = config.ranges[filterKey];
            if (cfg.filterMode === 'contains') {
                this.filters[filterKey] = { value: null };
            } else {
                this.filters[filterKey] = { min: null, max: null };
            }
            this.resetRangeInputs(filterKey);
        });

        // Clear boolean filters
        Object.entries(config.booleans || {}).forEach(([filterKey, cfg]) => {
            this.filters[filterKey] = false;
            const checkbox = this.container.querySelector(`[data-filter="${cfg.selector}"]`);
            if (checkbox) checkbox.checked = false;
        });

        // Clear tristate filters
        Object.keys(config.tristates || {}).forEach(filterKey => {
            this.filters[filterKey] = 'any';
            this.resetTristateUI(filterKey);
        });

        // Clear search term
        this.searchTerm = '';
        const searchInput = this.container.querySelector('[data-product-search-input]');
        const searchContainer = this.container.querySelector('[data-product-search]');
        if (searchInput) searchInput.value = '';
        searchContainer?.classList.remove('has-value');

        this.applyFilters();
    }

    resetTristateUI(filterKey) {
        const container = this.container.querySelector(`[data-tristate-filter="${filterKey}"]`);
        if (!container) return;

        const buttons = container.querySelectorAll('.filter-tristate-btn');
        const hiddenInput = container.querySelector('[data-tristate-input]');

        buttons.forEach(btn => {
            const isAny = btn.dataset.value === 'any';
            btn.classList.toggle('is-active', isAny);
            btn.setAttribute('aria-checked', isAny ? 'true' : 'false');
        });

        if (hiddenInput) {
            hiddenInput.value = 'any';
        }
    }

    // =========================================
    // SORTING (Config-Driven)
    // =========================================

    bindSortEvents() {
        this.sortSelect?.addEventListener('change', () => {
            this.currentSort = this.sortSelect.value;
            this.sortFilteredProducts();
            this.renderProducts();
        });
    }

    sortFilteredProducts() {
        const sortConfig = this.filterConfig.sort || {};
        const cfg = sortConfig[this.currentSort] || sortConfig['popularity'] || {
            key: 'popularity',
            dir: 'desc',
            nullValue: 0
        };

        // Handle special nullValue strings from PHP
        let nullValue = cfg.nullValue;
        if (nullValue === 'Infinity') nullValue = Infinity;

        this.filteredProducts.sort((a, b) => {
            const aVal = a[cfg.key] ?? nullValue;
            const bVal = b[cfg.key] ?? nullValue;

            // String comparison for name sorting
            if (cfg.isString) {
                return cfg.dir === 'asc'
                    ? (aVal || '').localeCompare(bVal || '')
                    : (bVal || '').localeCompare(aVal || '');
            }

            // Numeric comparison
            return cfg.dir === 'asc' ? aVal - bVal : bVal - aVal;
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
        // Use event delegation on the grid for comparison checkboxes
        // This avoids re-binding on every render
        this.grid.addEventListener('change', (e) => {
            const checkbox = e.target.closest('[data-compare-select]');
            if (!checkbox) return;

            const productId = checkbox.value;

            if (checkbox.checked) {
                if (this.selectedProducts.size >= this.maxCompare) {
                    checkbox.checked = false;
                    return;
                }
                this.selectedProducts.add(productId);
            } else {
                this.selectedProducts.delete(productId);
            }

            this.updateComparisonBar();
        });

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

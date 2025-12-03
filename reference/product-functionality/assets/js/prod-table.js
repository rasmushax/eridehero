jQuery(document).ready(function($) {
    // Debounce utility
    const debounce = (func, wait) => {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };

    // Cache DOM elements
    const $loadingIndicator = $('#loading-indicator');
    const $filterArea = $('#filter-area');
    const $scooterTableContainer = $('#scooter-table-container');
    const $activeFilters = $('#active-filters');
    const $scooterTable = $('#scooterTable');
    const $filterModal = $('#filter-modal');
    const $filterSearch = $('#filter-search');

    // Global variables
    let scooterData = [];
    let filterData = {};
    let table = null;
    const activeFilters = new Set(['name']);
    const filterCache = new Map();
    let selectedProductIds = new Set();
    let hasUserCustomization = false;
    let defaultFilterState = {};
    
    // Filter configurations
    const filterConfigs = {
        // Product Information
        'brand': {type: 'multiselect', label: 'Brand', filter_group: 'Product Information'},
        'features': {type: 'multiselect', label: 'Features', filter_group: 'Product Information'},
        'name': {type: 'text', label: 'Name', sortable: true, filter_group: 'Product Information'},
        'price': {type: 'range', label: 'Price', unit: '$USD', step: 1, filter_group: 'Product Information'},

        // Motor
        'manufacturer_top_speed': {type: 'range', label: 'Top Speed', unit: 'MPH', step: 1, filter_group: 'Motor'},
        'motors': {type: 'multiselect', label: 'Motor(s)', filter_group: 'Motor'},
        'nominal_motor_wattage': {type: 'range', label: 'Watts (nominal)', unit: 'W', step: 1, filter_group: 'Motor'},
        'total_peak_wattage': {type: 'range', label: 'Watts (peak)', unit: 'W', step: 1, filter_group: 'Motor'},
        'max_incline': {type: 'range', label: 'Max Incline', unit: '°', step: 1, filter_group: 'Motor'},
        'ideal_incline': {type: 'range', label: 'Ideal Incline', unit: '°', step: 1, filter_group: 'Motor'},

        // Battery
        'manufacturer_range': {type: 'range', label: 'Max Range', unit: 'miles', step: 1, filter_group: 'Battery'},
        'battery_type': {type: 'multiselect', label: 'Battery Type', filter_group: 'Battery'},
        'battery_voltage': {type: 'range', label: 'Voltage', unit: 'V', step: 1, filter_group: 'Battery'},
        'battery_amphours': {type: 'range', label: 'Amp-hours', unit: 'Ah', step: 1, filter_group: 'Battery'},
        'battery_capacity': {type: 'range', label: 'Battery Capacity', unit: 'Wh', step: 1, filter_group: 'Battery'},
        'charging_time': {type: 'range', label: 'Charge Time', unit: 'hrs', step: 1, filter_group: 'Battery'},
        'battery_brand': {type: 'multiselect', label: 'Cell Brand', filter_group: 'Battery'},

        // Dimensions & Weight
        'deck_length': {type: 'range', label: 'Deck Length', unit: 'in', step: 0.1, filter_group: 'Dimensions & Weight'},
        'deck_width': {type: 'range', label: 'Deck Width', unit: 'in', step: 0.1, filter_group: 'Dimensions & Weight'},
        'ground_clearance': {type: 'range', label: 'Ground Clearance', unit: 'in', step: 0.1, filter_group: 'Dimensions & Weight'},
        'unfolded_width': {type: 'range', label: 'Unfolded Width', unit: 'in', step: 0.1, filter_group: 'Dimensions & Weight'},
        'unfolded_height': {type: 'range', label: 'Unfolded Height', unit: 'in', step: 0.1, filter_group: 'Dimensions & Weight'},
        'unfolded_depth': {type: 'range', label: 'Unfolded Depth', unit: 'in', step: 0.1, filter_group: 'Dimensions & Weight'},
        'folded_width': {type: 'range', label: 'Folded Width', unit: 'in', step: 0.1, filter_group: 'Dimensions & Weight'},
        'folded_height': {type: 'range', label: 'Folded Height', unit: 'in', step: 0.1, filter_group: 'Dimensions & Weight'},
        'folded_depth': {type: 'range', label: 'Folded Depth', unit: 'in', step: 0.1, filter_group: 'Dimensions & Weight'},
        'handlebar_width': {type: 'range', label: 'Handlebar Width', unit: 'in', step: 0.1, filter_group: 'Dimensions & Weight'},
        'deck_to_handlebar_min': {type: 'range', label: 'Handlebar Height (min)', unit: 'in', step: 0.1, filter_group: 'Dimensions & Weight'},
        'deck_to_handlebar_max': {type: 'range', label: 'Handlebar Height (max)', unit: 'in', step: 0.1, filter_group: 'Dimensions & Weight'},
        'weight': {type: 'range', label: 'Weight', unit: 'lbs', step: 1, filter_group: 'Dimensions & Weight'},
        'max_load': {type: 'range', label: 'Weight Limit', unit: 'lbs', step: 1, filter_group: 'Dimensions & Weight'},

        // Ride Characteristics
        'terrain': {type: 'multiselect', label: 'Terrain', filter_group: 'Ride Characteristics'},
        'tires': {type: 'multiselect', label: 'Tire Type', filter_group: 'Ride Characteristics'},
        'pneumatic_type': {type: 'multiselect', label: 'Pneumatic Type', filter_group: 'Ride Characteristics'},
        'suspension': {type: 'multiselect', label: 'Suspension', filter_group: 'Ride Characteristics'},
        'tire_size_front': {type: 'range', label: 'Tire Height', step: 0.1, unit: 'in', filter_group: 'Ride Characteristics'},
        'tire_width': {type: 'range', label: 'Tire Width', step: 0.1, unit: 'in', filter_group: 'Ride Characteristics'},
        'throttle_type': {type: 'multiselect', label: 'Throttle', filter_group: 'Ride Characteristics'},
        'footrest': {type: 'boolean', label: 'Footrest', filter_group: 'Ride Characteristics'},

        // Safety & Durability
        'brakes': {type: 'multiselect', label: 'Brakes', filter_group: 'Safety & Durability'},
        'fold_location': {type: 'multiselect', label: 'Fold Location', filter_group: 'Safety & Durability'},
        'lights': {type: 'multiselect', label: 'Lights', filter_group: 'Safety & Durability'},
        'weather_resistance': {type: 'multiselect', label: 'IP Rating', filter_group: 'Safety & Durability'},

        // ERideHero Performance Tests
        'tested_top_speed': {type: 'range', label: 'Top Speed Test', unit: 'MPH', step: 0.1, filter_group: 'ERideHero Tests'},
        'acceleration_0-15_mph': {type: 'range', label: 'Accel. Avg. (0-15 MPH)', unit: 's', step: 0.1, filter_group: 'ERideHero Tests'},
        'acceleration_0-20_mph': {type: 'range', label: 'Accel. Avg. (0-20 MPH)', unit: 's', step: 0.1, filter_group: 'ERideHero Tests'},
        'acceleration_0-25_mph': {type: 'range', label: 'Accel. Avg. (0-25 MPH)', unit: 's', step: 0.1, filter_group: 'ERideHero Tests'},
        'acceleration_0-30_mph': {type: 'range', label: 'Accel. Avg. (0-30 MPH)', unit: 's', step: 0.1, filter_group: 'ERideHero Tests'},
        'acceleration_0-to-top': {type: 'range', label: 'Accel. Avg. (0-Top)', unit: 's', step: 0.1, filter_group: 'ERideHero Tests'},
        'fastest_0_15': {type: 'range', label: 'Accel. Fastest (0-15 MPH)', unit: 's', step: 0.1, filter_group: 'ERideHero Tests'},
        'fastest_0_20': {type: 'range', label: 'Accel. Fastest (0-20 MPH)', unit: 's', step: 0.1, filter_group: 'ERideHero Tests'},
        'fastest_0_25': {type: 'range', label: 'Accel. Fastest (0-25 MPH)', unit: 's', step: 0.1, filter_group: 'ERideHero Tests'},
        'fastest_0_30': {type: 'range', label: 'Accel. Fastest (0-30 MPH)', unit: 's', step: 0.1, filter_group: 'ERideHero Tests'},
        'fastest_0_top': {type: 'range', label: 'Accel. Fastest (0-Top)', unit: 's', step: 0.1, filter_group: 'ERideHero Tests'},
        'tested_range_fast': {type: 'range', label: 'Range Test (Fast)', unit: 'miles', step: 0.1, filter_group: 'ERideHero Tests'},
        'tested_range_regular': {type: 'range', label: 'Range Test (Regular)', unit: 'miles', step: 0.1, filter_group: 'ERideHero Tests'},
        'tested_range_slow': {type: 'range', label: 'Range Test (Slow)', unit: 'miles', step: 0.1, filter_group: 'ERideHero Tests'},
        'brake_distance': {type: 'range', label: 'Brake Distance (15 MPH)', unit: 'ft', step: 0.1, filter_group: 'ERideHero Tests'},
        'hill_climbing': {type: 'range', label: 'Hill Climbing', unit: 's', step: 0.1, filter_group: 'ERideHero Tests'},

        // Advanced comparisons
        'price_per_lb': {type: 'range', label: 'Price vs. Weight', unit: '$/lb', step: 0.01, filter_group: 'Advanced Comparison'},
        'price_per_mph': {type: 'range', label: 'Price vs. Speed', unit: '$/MPH', step: 0.01, filter_group: 'Advanced Comparison'},
        'price_per_mile_range': {type: 'range', label: 'Price vs. Range', unit: '$/mile', step: 0.01, filter_group: 'Advanced Comparison'},
        'price_per_wh': {type: 'range', label: 'Price vs. Wh', unit: '$/wh', step: 0.01, filter_group: 'Advanced Comparison'},
        'price_per_watt': {type: 'range', label: 'Price vs. Watt', unit: '$/W', step: 0.01, filter_group: 'Advanced Comparison'},
        'price_per_lb_capacity': {type: 'range', label: 'Price vs. Max Load', unit: '$/lb', step: 0.01, filter_group: 'Advanced Comparison'},
        'price_per_tested_mile': {type: 'range', label: 'Price vs. Tested Range', unit: '$/mile', step: 0.01, filter_group: 'Advanced Comparison'},
        'price_per_tested_mph': {type: 'range', label: 'Price vs. Tested Speed', unit: '$/MPH', step: 0.01, filter_group: 'Advanced Comparison'},
        'price_per_brake_ft': {type: 'range', label: 'Price vs. Brake Distance', unit: '$/ft', step: 0.01, filter_group: 'Advanced Comparison'},
        'price_per_hill_degree': {type: 'range', label: 'Price vs. Hill Test', unit: '$/s', step: 0.01, filter_group: 'Advanced Comparison'},
        'price_per_acc_0-15_mph': {type: 'range', label: 'Price vs. Accel (15 MPH)', unit: '$/s', step: 0.01, filter_group: 'Advanced Comparison'},
        'price_per_acc_0-20_mph': {type: 'range', label: 'Price vs. Accel (20 MPH)', unit: '$/s', step: 0.01, filter_group: 'Advanced Comparison'},
        'price_per_acc_0-25_mph': {type: 'range', label: 'Price vs. Accel (25 MPH)', unit: '$/s', step: 0.01, filter_group: 'Advanced Comparison'},
        'price_per_acc_0-30_mph': {type: 'range', label: 'Price vs. Accel (30 MPH)', unit: '$/s', step: 0.01, filter_group: 'Advanced Comparison'},
        'speed_per_lb': {type: 'range', label: 'Speed vs. Weight', unit: 'MPH/lb', step: 0.01, filter_group: 'Advanced Comparison'},
        'range_per_lb': {type: 'range', label: 'Range vs. Weight', unit: 'mile/lb', step: 0.01, filter_group: 'Advanced Comparison'},
        'tested_range_per_lb': {type: 'range', label: 'Tested Range vs. Weight', unit: 'mile/lb', step: 0.01, filter_group: 'Advanced Comparison'},
        'speed_per_lb_capacity': {type: 'range', label: 'Speed vs. Max Load', unit: 'MPH/lb', step: 0.01, filter_group: 'Advanced Comparison'},
        'range_per_lb_capacity': {type: 'range', label: 'Range vs. Max Load', unit: 'mile/lb', step: 0.01, filter_group: 'Advanced Comparison'},
    };

    // Initialize the table
    init();

    function init() {
        setupEventHandlers();
        loadScooterData()
            .then(() => {})
            .catch((error) => {
                showError('Error loading data. Please try refreshing the page.');
            });
    }

    function setupEventHandlers() {
        $('#add-filter-btn').on('click', function() {
            $('#filter-modal').css("display", "flex");
        });

        $('#filter-modal .close').on('click', function() {
            $('#filter-modal').hide();
        });

        $(window).on('click', function(event) {
            if (event.target === document.getElementById('filter-modal')) {
                $('#filter-modal').hide();
            }
        });
    }

    async function loadScooterData(maxWaitTime = 10000) {
        $loadingIndicator.show();
        const startTime = Date.now();
        let lastError = null;

        const dataChecks = [
            () => {
                if (typeof pfScooterTableData !== 'undefined' && pfScooterTableData.scooters) {
                    return pfScooterTableData.scooters;
                }
                return null;
            },
            () => {
                if (typeof window.scooterData !== 'undefined' && Array.isArray(window.scooterData)) {
                    return window.scooterData;
                }
                return null;
            },
            () => {
                if (typeof pfScooterTableData !== 'undefined' && pfScooterTableData.scootersJson) {
                    return JSON.parse(pfScooterTableData.scootersJson);
                }
                return null;
            }
        ];

        while (Date.now() - startTime < maxWaitTime) {
            for (const check of dataChecks) {
                try {
                    const data = check();
                    if (data && Array.isArray(data) && data.length > 0) {
                        scooterData = data;
                        window.scooterData = data;
                        
                        processScooterData();
                        initializeFilters();
                        await rebuildTable();
                        applyFiltersFromUrl();
                        
                        $loadingIndicator.hide();
                        $filterArea.add($scooterTableContainer).show();
                        return;
                    }
                } catch (e) {
                    lastError = e;
                }
            }

            if (document.readyState !== 'complete') {
                await new Promise(resolve => {
                    if (document.readyState === 'complete') {
                        resolve();
                    } else {
                        window.addEventListener('load', resolve, { once: true });
                    }
                });
            }

            if (Date.now() - startTime > maxWaitTime / 2) {
                const ajaxData = await loadScooterDataAjax();
                if (ajaxData) {
                    scooterData = ajaxData;
                    window.scooterData = ajaxData;
                    
                    processScooterData();
                    initializeFilters();
                    await rebuildTable();
                    applyFiltersFromUrl();
                    
                    $loadingIndicator.hide();
                    $filterArea.add($scooterTableContainer).show();
                    return;
                }
            }

            await new Promise(resolve => setTimeout(resolve, 100));
        }

        showError('Error loading data. Please try refreshing the page.');
    }

    async function loadScooterDataAjax() {
        try {
            const ajaxData = {
                action: 'pf_get_scooter_data',
                nonce: pfScooterTableData?.nonce || ''
            };

            const response = await $.ajax({
                url: pfScooterTableData?.ajaxUrl || '/wp-admin/admin-ajax.php',
                method: 'POST',
                data: ajaxData,
                timeout: 30000
            });

            if (response.success && response.data && response.data.scooters) {
                return response.data.scooters;
            }
        } catch (error) {}
        return null;
    }

    function showError(message) {
        $loadingIndicator.html(
            '<div class="error-message">' +
            '<p>' + message + '</p>' +
            '<button onclick="location.reload()" class="btn btn-primary">Refresh Page</button>' +
            '</div>'
        );
    }

    function processScooterData() {
        for (const [key, config] of Object.entries(filterConfigs)) {
            if (config.type === 'multiselect') {
                filterData[key] = new Set();
            } else if (config.type === 'range') {
                filterData[key] = { min: Infinity, max: -Infinity };
            }
        }

        scooterData.forEach(scooter => {
            for (const [key, config] of Object.entries(filterConfigs)) {
                const value = scooter[key] || (scooter.specs && scooter.specs[key]);
                
                if (config.type === 'multiselect' && value) {
                    (Array.isArray(value) ? value : [value]).forEach(v => filterData[key].add(v));
                } else if (config.type === 'range' && value != null) {
                    const numValue = parseFloat(value);
                    if (!isNaN(numValue)) {
                        filterData[key].min = Math.min(filterData[key].min, numValue);
                        filterData[key].max = Math.max(filterData[key].max, numValue);
                    }
                }
            }
        });
    }

    function initializeFilters() {
        const urlParams = getUrlParameters();
        const defaultFilters = ['price', 'manufacturer_top_speed', 'nominal_motor_wattage', 'battery_capacity', 'weight', 'max_load'];
        
        hasUserCustomization = Object.keys(urlParams).length > 0;
        
        const filtersToAdd = hasUserCustomization ? Object.keys(urlParams) : defaultFilters;

        const $addFilterDiv = $('#active-filters .addfilter').detach();
        $('#active-filters').empty().append($addFilterDiv);
        activeFilters.clear();
        activeFilters.add('name');

        filtersToAdd.forEach(filterType => {
            if (filterConfigs[filterType]) {
                addFilter(filterType);
                
                if (!hasUserCustomization) {
                    storeDefaultFilterValue(filterType);
                }
                
                if (urlParams[filterType] !== undefined) {
                    applyUrlParamToFilter(filterType, urlParams[filterType]);
                }
            }
        });

        populateFilterModal();
        $filterArea.show();
    }

    function storeDefaultFilterValue(filterType) {
        const config = filterConfigs[filterType];
        
        if (config.type === 'range') {
            const min = 0;
            const max = filterData[filterType] ? filterData[filterType].max : 100;
            defaultFilterState[filterType] = { min, max };
        } else if (config.type === 'multiselect') {
            defaultFilterState[filterType] = { mode: 'include', options: [] };
        } else if (config.type === 'boolean') {
            defaultFilterState[filterType] = [];
        }
    }

    function isFilterAtDefault(filterType) {
        if (!defaultFilterState[filterType]) return true;
        
        const config = filterConfigs[filterType];
        const filterElement = $(`#filter-${filterType}`);
        
        if (config.type === 'range') {
            const currentMin = parseFloat(filterElement.find('.range-input.min').val());
            const currentMax = parseFloat(filterElement.find('.range-input.max').val());
            const defaultMin = defaultFilterState[filterType].min;
            const defaultMax = defaultFilterState[filterType].max;
            return currentMin === defaultMin && currentMax === defaultMax;
        } else if (config.type === 'multiselect') {
            const container = filterElement.find('.custom-multiselect');
            const selectedOptions = Array.from(container.data('selectedOptions') || []);
            const mode = container.find('.toggle-input').is(':checked') ? 'exclude' : 'include';
            return mode === 'include' && selectedOptions.length === 0;
        } else if (config.type === 'boolean') {
            const yesChecked = filterElement.find('.boolean-input.yes').is(':checked');
            const noChecked = filterElement.find('.boolean-input.no').is(':checked');
            return !yesChecked && !noChecked;
        }
        
        return true;
    }

    function areAllFiltersAtDefault() {
        const defaultFilters = new Set(['name', 'price', 'manufacturer_top_speed', 'nominal_motor_wattage', 'battery_capacity', 'weight', 'max_load']);
        
        if (activeFilters.size !== defaultFilters.size) return false;
        
        for (const filter of activeFilters) {
            if (filter !== 'name' && !defaultFilters.has(filter)) return false;
        }
        
        for (const filterType of activeFilters) {
            if (filterType !== 'name' && !isFilterAtDefault(filterType)) {
                return false;
            }
        }
        
        return true;
    }

    function addFilter(filterType) {
        if (filterType === 'name' || $(`#filter-${filterType}`).length > 0) return;

        const config = filterConfigs[filterType];
        const filterId = `filter-${filterType}`;
        
        const defaultFilters = ['price', 'manufacturer_top_speed', 'nominal_motor_wattage', 'battery_capacity', 'weight', 'max_load'];
        if (!defaultFilters.includes(filterType)) {
            hasUserCustomization = true;
        }
        
        let filterHtml = `
            <div id="${filterId}" class="filter-item">
                <label>${config.label}${config.unit ? ` <span class="filter-unit">${config.unit}</span>` : ''}</label>
                <button class="remove-filter" data-filter="${filterType}"><svg class="icon icon-trash-2"><use xlink:href="#icon-trash-2"></use></svg></button>
        `;

        if (config.type === 'range') {
            const minValue = filterData[filterType] ? filterData[filterType].min : 0;
            const maxValue = filterData[filterType] ? filterData[filterType].max : 100;
            filterHtml += `
                <div class="range-slider-container">
                    <input type="text" class="js-range-slider" value="" />
                </div>
                <div class="range-inputs">
                    <input type="number" class="range-input min" value="${minValue}" min="${minValue}" max="${maxValue}" step="${config.step}">-
                    <input type="number" class="range-input max" value="${maxValue}" min="${minValue}" max="${maxValue}" step="${config.step}">
                </div>
            `;
        } else if (config.type === 'multiselect') {
            filterHtml += `
                <div class="custom-multiselect" data-filter-type="${filterType}">
                    <button class="multiselect-toggle">Select ${config.label}</button>
                    <div class="multiselect-dropdown" style="display: none;">
                        <div class="multiselect-controls">
                            <div class="toggle-switch">
                                <input type="checkbox" id="${filterType}-toggle" class="toggle-input">
                                <label for="${filterType}-toggle" class="toggle-label">
                                    <span class="toggle-option include">Include</span>
                                    <span class="toggle-option exclude">Exclude</span>
                                </label>
                            </div>
                        </div>
                        <div class="search-holder">
                            <input type="text" class="multiselect-search" placeholder="Search ${config.label}...">
                            <svg class="icon icon-search"><use xlink:href="#icon-search"></use></svg>
                        </div>
                        <div class="multiselect-options scrollbar">
                        </div>
                        <div class="multiselect-actions">
                            <button class="multiselect-reset">Reset</button>
                            <button class="multiselect-apply">Apply</button>
                        </div>
                    </div>
                </div>
            `;
        } else if (config.type === 'boolean') {
            filterHtml += `
                <div class="boolean-filter">
                    <label class="checkbox-container">
                        <input type="checkbox" class="boolean-input yes">
                        <span class="checkmark"></span>
                        Yes
                    </label>
                    <label class="checkbox-container">
                        <input type="checkbox" class="boolean-input no">
                        <span class="checkmark"></span>
                        No
                    </label>
                </div>
            `;
        }

        filterHtml += '</div>';

        $(filterHtml).insertBefore($('#active-filters .addfilter'));

        if (config.type === 'range') {
            initRangeSlider(filterType, config);
        } else if (config.type === 'multiselect') {
            initMultiselect(filterType);
        } else if (config.type === 'boolean') {
			$(`#${filterId} .boolean-input`).on('change', function() {
				hasUserCustomization = true;
				debouncedApplyFilters();
			});
		}

        $(`#${filterId} .remove-filter`).on('click', function() {
            removeFilter.call(this);
        });

        activeFilters.add(filterType);
        updateUrlParameters();

        if (table && filterType !== 'name') {
            table.column(filterType + ':name').visible(true);
            table.columns.adjust().draw();
        }

        $(`#${filterType}`).prop('checked', true);
    }

    function initRangeSlider(filterType, config) {
		const $rangeSlider = $(`#filter-${filterType} .js-range-slider`);
		const $inputMin = $(`#filter-${filterType} .range-input.min`);
		const $inputMax = $(`#filter-${filterType} .range-input.max`);

		const minValue = 0;
		const maxValue = filterData[filterType] ? filterData[filterType].max : 100;

		$rangeSlider.ionRangeSlider({
			type: "double",
			min: minValue,
			max: maxValue,
			from: minValue,
			to: maxValue,
			step: config.step,
			onStart: updateInputs,
			onChange: updateInputs,
			onFinish: function(data) {
				updateInputs(data);
				hasUserCustomization = true;
				debouncedApplyFilters();
			},
			skin: "round",
			hide_min_max: true,
			hide_from_to: true
		});

		const instance = $rangeSlider.data("ionRangeSlider");

		function updateInputs(data) {
			$inputMin.val(data.from);
			$inputMax.val(data.to);
		}

		$inputMin.on("change", function() {
			let val = $(this).val();
			val = val === "" ? minValue : Math.max(minValue, parseFloat(val));
			instance.update({ from: val });
			hasUserCustomization = true;
			debouncedApplyFilters();
		});

		$inputMax.on("change", function() {
			let val = $(this).val();
			val = val === "" ? maxValue : Math.min(maxValue, parseFloat(val));
			instance.update({ to: val });
			hasUserCustomization = true;
			debouncedApplyFilters();
		});
	}

	function initMultiselect(filterType) {
		const config = filterConfigs[filterType];
		const container = $(`#filter-${filterType} .custom-multiselect`);
		
		if (container.length === 0) return;

		const toggle = container.find('.multiselect-toggle');
		const dropdown = container.find('.multiselect-dropdown');
		const optionsContainer = container.find('.multiselect-options');
		const searchInput = container.find('.multiselect-search');
		const resetButton = container.find('.multiselect-reset');
		const applyButton = container.find('.multiselect-apply');

		let selectedOptions = new Set(container.data('selectedOptions') || []);

		function updateToggleText() {
			const count = selectedOptions.size;
			toggle.text(count === 0 ? `Select ${config.label}` : `${count} selection${count !== 1 ? 's' : ''}`);
		}

		function populateOptions() {
			optionsContainer.empty();

			if (!filterData[filterType] || filterData[filterType].size === 0) {
				optionsContainer.append('<p>No options available</p>');
				return;
			}

			const sortedOptions = [...filterData[filterType]].sort((a, b) => a.localeCompare(b));

			sortedOptions.forEach(option => {
				const count = scooterData.filter(scooter => {
					const value = scooter[filterType] || (scooter.specs && scooter.specs[filterType]);
					return Array.isArray(value) ? value.includes(option) : value === option;
				}).length;

				const isChecked = selectedOptions.has(option);
				const optionHtml = `
					<label>
						<input type="checkbox" value="${option}" ${isChecked ? 'checked' : ''}>
						${option} <span class="option-count">(${count})</span>
					</label>
				`;
				optionsContainer.append(optionHtml);
			});

			optionsContainer.find('input[type="checkbox"]').on('change', function() {
				const value = $(this).val();
				if (this.checked) {
					selectedOptions.add(value);
				} else {
					selectedOptions.delete(value);
				}
				updateToggleText();
			});
		}

		populateOptions();
		updateToggleText();

		toggle.on('click', () => dropdown.toggle());

		searchInput.on('input', function() {
			const searchTerm = $(this).val().toLowerCase();
			optionsContainer.find('label').each(function() {
				const text = $(this).text().toLowerCase();
				$(this).toggle(text.includes(searchTerm));
			});
		});

		resetButton.on('click', () => {
			selectedOptions.clear();
			optionsContainer.find('input[type="checkbox"]').prop('checked', false);
			container.data('selectedOptions', new Set());
			updateToggleText();
			hasUserCustomization = true;
		});

		applyButton.on('click', () => {
			container.data('selectedOptions', new Set(selectedOptions));
			updateToggleText();
			dropdown.hide();
			hasUserCustomization = true;
			debouncedApplyFilters();
		});

		container.find('.toggle-input').on('change', function() {
			hasUserCustomization = true;
			debouncedApplyFilters();
		});

		container.data('selectedOptions', selectedOptions);
	}

    function removeFilter() {
        const filterType = $(this).data('filter');
        if (filterType === 'name') return;

        $(`#filter-${filterType}`).remove();
        activeFilters.delete(filterType);
        
        hasUserCustomization = true;

        if (table) {
            table.column(filterType + ':name').visible(false);
            table.columns.adjust().draw();
        }

        $(`#${filterType}`).prop('checked', false);
        debouncedApplyFilters();
        updateUrlParameters();

        if ($('#active-filters').children(':not(.addfilter)').length === 0) {
            const urlParams = getUrlParameters();
            if (Object.keys(urlParams).length === 0) {
                hasUserCustomization = false;
            }
            initializeFilters();
        }
    }

    function populateFilterModal() {
        const $filterGroups = $('.filter-groups').empty();
        const groups = {};

        Object.entries(filterConfigs).forEach(([key, config]) => {
            if (!groups[config.filter_group]) {
                groups[config.filter_group] = [];
            }
            groups[config.filter_group].push({ key, config });
        });

        Object.entries(groups).forEach(([groupName, filters]) => {
            const $group = $('<div class="filter-group">').append($('<h3>').text(groupName));
            
            filters.forEach(({ key, config }) => {
                $group.append(
                    $('<div class="filter-option">').append(
                        $('<input type="checkbox">').attr({ id: key, 'data-filter': key }),
                        $('<label>').attr('for', key).text(config.label)
                    )
                );
            });
            
            $filterGroups.append($group);
        });

        $('.filter-option input[type="checkbox"]').on('change', function() {
            const filterType = this.dataset.filter;
            if (this.checked) {
                if (!activeFilters.has(filterType)) {
                    addFilter(filterType);
                }
            } else {
                removeFilter.call($(`#filter-${filterType} .remove-filter`)[0]);
            }
            rebuildTable();
        });

        activeFilters.forEach(filterType => {
            $(`#${filterType}`).prop('checked', true);
        });

        $('#filter-search').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            let totalVisibleOptions = 0;

            $('.filter-group').each(function() {
                const $group = $(this);
                const $options = $group.find('.filter-option');
                let hasVisibleOption = false;

                $options.each(function() {
                    const $option = $(this);
                    const optionText = $option.text().toLowerCase();
                    const isVisible = optionText.includes(searchTerm);
                    $option.toggle(isVisible);
                    if (isVisible) {
                        hasVisibleOption = true;
                        totalVisibleOptions++;
                    }
                });

                $group.toggle(hasVisibleOption);
            });
        });
    }

    async function rebuildTable() {
        return new Promise((resolve) => {
            const allColumns = [
                {
                    data: 'name',
                    title: 'Name',
                    name: 'name',
                    visible: true,
                    render: function(data, type, row) {
                        if (type === 'display') {
                            let imageUrl = row.image_url || 'https://eridehero.com/wp-content/uploads/2024/07/image-1.svg';
                            let html = '<div class="row-first">' +
                                      '<div class="row-checkbox"></div>' +
                                      '<a href="' + row.permalink + '">' +
                                      '<img src="' + imageUrl + '" alt="' + data + '" width="40" height="40" ' +
                                      'onerror="this.onerror=null;this.src=\'https://eridehero.com/wp-content/uploads/2024/07/image-1.svg\';">' +
                                      data +
                                      '</a>';
                            
                            html += '</div>';
                            return html;
                        }
                        return data;
                    }
                },
                ...Object.keys(filterConfigs).filter(filterType => filterType !== 'name').map(filterType => {
                    const config = filterConfigs[filterType];
                    return {
                        data: function(row) {
                            return row[filterType] || (row.specs && row.specs[filterType]);
                        },
                        title: config.label,
                        name: filterType,
                        visible: activeFilters.has(filterType),
                        defaultContent: '-',
                        render: function(data, type, row) {
                            if (type === 'sort' && config.type === 'range') {
                                return parseFloat(data) || -Infinity;
                            }
                            if (filterType === 'price') {
								if (type === 'sort' || type === 'type') {
									return parseFloat(data) || -Infinity;
								}
								if (data) {
									let priceDisplay = formatUSD(data);
									if (row.bestlink) {
										priceDisplay += '<svg class="comparison-icon-external"><use xlink:href="#icon-external-link"></use></svg>';
										priceDisplay = '<a class="comparison-pricetd afflink" target="_blank" rel="nofollow external" href="' + row.bestlink + '" target="_blank">' + priceDisplay + '</a>';
									} else {
										priceDisplay = '<a class="comparison-pricetd" href="' + row.permalink + '">' + priceDisplay + '</a>';
									}
									return priceDisplay;
								}
								return '-';
							}
                            if (config.type === 'range') {
                                return data ? data + ' ' + (config.unit || '') : '-';
                            }
                            if (config.type === 'boolean') {
                                const value = (data == 1 || 
                                              String(data).toLowerCase() === 'true' || 
                                              String(data).toLowerCase() === 'yes' || 
                                              String(data).toLowerCase() === '1');
                                return value ? 'Yes' : 'No';
                            }
                            if (filterType === 'features' && type === 'display' && data) {
                                let features = Array.isArray(data) ? data : data.split(',');
                                let summary = features.slice(0, 2).join(', ');
                                let extraCount = features.length - 2;
                                if (extraCount > 0) {
                                    summary += ` <span class="feature-count">+${extraCount}</span>`;
                                }
                                return `<div class="features-container">
                                            <div class="features-summary">${summary}</div>
                                            <div class="features-popup">${features.join(', ')}</div>
                                        </div>`;
                            }
                            return data || '-';
                        },
                        type: config.type === 'range' ? 'num' : 'string'
                    };
                })
            ];

            if ($.fn.DataTable.isDataTable('#scooterTable')) {
                $('#scooterTable').DataTable().destroy();
                $('#scooterTable').empty();
            }

            table = $scooterTable.DataTable({
                data: scooterData,
                columns: allColumns,
                order: [],
                language: {
                    "emptyTable": "No data available in table",
                    "info": "Showing _START_ to _END_ of _TOTAL_ models",
                    "infoEmpty": "Showing 0 to 0 of 0 models",
                    "infoFiltered": "(filtered from _MAX_ total models)",
                    "lengthMenu": "Show _MENU_ models",
                    "search": "",
                    "searchPlaceholder": "Search models",
                    "zeroRecords": "No matching records found"
                },
                responsive: true,
                processing: true,
                deferRender: true,
                orderClasses: false,
                pageLength: 25,
                lengthMenu: [25, 50, 100, 200, { label: 'All', value: -1 }],
                columnDefs: [
                    {
                        targets: '_all',
                        visible: false,
                        defaultContent: '-'
                    }
                ],
                createdRow: function(row, data, dataIndex) {
                    $(row).attr('data-product-id', data.id);
                    if (selectedProductIds.has(data.id)) {
                        $(row).find('.row-checkbox').addClass('row-checked');
                    }
                },
                initComplete: function() {
                    activeFilters.forEach(filterType => {
                        if (filterType !== 'name') {
                            this.api().column(filterType + ':name').visible(true);
                        }
                    });
                    this.api().columns.adjust();
                    resolve();
                }
            });

            table.on('draw', function() {
                $scooterTable.find('tbody tr').each(function() {
                    const productId = $(this).data('product-id');
                    if (selectedProductIds.has(productId)) {
                        $(this).find('.row-checkbox').addClass('row-checked');
                    }
                });
            });

            $scooterTable.off('click').on('click', 'tbody tr', handleRowClick);

            applyFilters();
        });
    }

    function handleRowClick(e) {
        if (e.target.closest('a')) return;
        
        const $row = $(this);
        const productId = $row.data('product-id');
        const $checkbox = $row.find('.row-checkbox');
        
        if (selectedProductIds.has(productId)) {
            selectedProductIds.delete(productId);
            $checkbox.removeClass('row-checked');
        } else {
            selectedProductIds.add(productId);
            $checkbox.addClass('row-checked');
        }
        
        updateComparisonBox();
    }

    function formatUSD(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount);
    }

    const filterStrategies = {
        range: (value, config, filterElement) => {
            const min = parseFloat(filterElement.find('.range-input.min').val());
            const max = parseFloat(filterElement.find('.range-input.max').val());

            if (value == null || value === '') {
                return min === 0;
            }

            const numValue = parseFloat(value);
            return !isNaN(numValue) && numValue >= min && numValue <= max;
        },
        
        multiselect: (value, config, filterElement) => {
            const container = filterElement.find('.custom-multiselect');
            const selectedOptions = Array.from(container.data('selectedOptions') || []);
            const filterMode = container.find('.toggle-input').is(':checked') ? 'exclude' : 'include';

            if (selectedOptions.length === 0) return true;

            const scooterValues = Array.isArray(value) ? value : [value];
            const hasMatch = scooterValues.some(v => selectedOptions.includes(v));

            return filterMode === 'include' ? hasMatch : !hasMatch;
        },
        
        boolean: (value, config, filterElement) => {
            const yesChecked = filterElement.find('.boolean-input.yes').is(':checked');
            const noChecked = filterElement.find('.boolean-input.no').is(':checked');
            const boolValue = value != null && ['1', 'true', 'yes'].includes(String(value).toLowerCase());

            return (!yesChecked && !noChecked) || (yesChecked && noChecked) || 
                   (yesChecked && boolValue) || (noChecked && !boolValue);
        }
    };

    function applyFilters() {
        const filteredData = scooterData.filter(scooter => {
            return Array.from(activeFilters).every(filterType => {
                if (filterType === 'name') {
                    const nameFilter = ($('#filter-name input').val() || '').toLowerCase();
                    const scooterName = ((scooter.name || '') + '').toLowerCase();
                    return scooterName.includes(nameFilter);
                }

                const config = filterConfigs[filterType];
                const filterElement = $(`#filter-${filterType}`);
                const value = scooter[filterType] || (scooter.specs && scooter.specs[filterType]);

                return filterStrategies[config.type]?.(value, config, filterElement) ?? true;
            });
        });

        if (table) {
            table.clear().rows.add(filteredData).draw();
        }
        
        updateUrlParameters();
    }

    const debouncedApplyFilters = debounce(applyFilters, 300);

	function updateUrlParameters() {
		if (!hasUserCustomization && areAllFiltersAtDefault()) {
			if (window.location.search) {
				history.replaceState({}, '', location.pathname);
			}
			return;
		}
		
		const params = new URLSearchParams();
		
		activeFilters.forEach(filterType => {
			if (filterType === 'name') return;
			
			const config = filterConfigs[filterType];
			const filterElement = $(`#filter-${filterType}`);

			if (config.type === 'range') {
				const min = filterElement.find('.range-input.min').val();
				const max = filterElement.find('.range-input.max').val();
				params.set(filterType, `${min}-${max}`);
			} else if (config.type === 'multiselect') {
				const container = filterElement.find('.custom-multiselect');
				const selectedOptions = Array.from(container.data('selectedOptions') || []);
				const mode = container.find('.toggle-input').is(':checked') ? 'exclude' : 'include';
				if (selectedOptions.length > 0) {
					params.set(filterType, `${mode}:${selectedOptions.join(',')}`);
				}
			} else if (config.type === 'boolean') {
				const boolValues = ['yes', 'no'].filter(val => 
					filterElement.find(`.boolean-input.${val}`).is(':checked')
				);
				if (boolValues.length > 0) {
					params.set(filterType, boolValues.join(','));
				}
			}
		});

		history.replaceState({}, '', `${location.pathname}?${params}`);
	}

    function getUrlParameters() {
        const params = {};
        const searchParams = new URLSearchParams(location.search);
        
        for (const [key, value] of searchParams) {
            if (filterConfigs[key]) {
                if (filterConfigs[key].type === 'range') {
                    const [min, max] = value.split('-').map(Number);
                    params[key] = { min, max };
                } else if (filterConfigs[key].type === 'multiselect') {
                    const [mode, optionsStr] = value.split(':');
                    params[key] = { 
                        mode: mode === 'exclude' ? 'exclude' : 'include',
                        options: optionsStr ? optionsStr.split(',') : []
                    };
                } else if (filterConfigs[key].type === 'boolean') {
                    params[key] = value ? value.split(',') : [];
                } else {
                    params[key] = value;
                }
            }
        }
        
        return params;
    }

    function applyFiltersFromUrl() {
        const urlParams = getUrlParameters();

        Object.entries(urlParams).forEach(([filterType, value]) => {
            if (filterConfigs[filterType]) {
                applyUrlParamToFilter(filterType, value);
            }
        });

        applyFilters();
    }

    function applyUrlParamToFilter(filterType, value) {
        const config = filterConfigs[filterType];
        const filterElement = $(`#filter-${filterType}`);

        if (!filterElement.length) {
            return;
        }

        if (config.type === 'range') {
            const { min, max } = value;
            const instance = filterElement.find('.js-range-slider').data("ionRangeSlider");
            if (instance) {
                instance.update({ from: min, to: max });
            }
            filterElement.find('.range-input.min').val(min);
            filterElement.find('.range-input.max').val(max);
        } else if (config.type === 'multiselect') {
            const container = filterElement.find('.custom-multiselect');
            const selectedOptions = new Set(value.options);
            container.data('selectedOptions', selectedOptions);
            container.find('.multiselect-options input[type="checkbox"]').each(function() {
                $(this).prop('checked', selectedOptions.has($(this).val()));
            });
            container.find('.toggle-input').prop('checked', value.mode === 'exclude');
            
            const count = selectedOptions.size;
            container.find('.multiselect-toggle').text(
                count === 0 ? `Select ${config.label}` : `${count} selection${count !== 1 ? 's' : ''}`
            );
        } else if (config.type === 'boolean') {
            filterElement.find('.boolean-input').prop('checked', false);
            if (value.length > 0) {
                value.forEach(boolValue => {
                    filterElement.find(`.boolean-input.${boolValue}`).prop('checked', true);
                });
            }
        }
    }

    function updateComparisonBox() {
        const checkedCount = selectedProductIds.size;
        let comparisonBox = $('#comparison-box');

        if (checkedCount > 0) {
            if (comparisonBox.length === 0) {
                comparisonBox = $('<div id="comparison-box"></div>').appendTo('body');
                comparisonBox[0].offsetHeight; // Force reflow
            }

            const productIds = Array.from(selectedProductIds);

            comparisonBox.html(`
                <div class="comparison-box-top"><svg class="icon icon-check-circle"><use xlink:href="#icon-check-circle"></use></svg>
                <span>${checkedCount} item${checkedCount !== 1 ? 's' : ''} selected</span></div>
                <div class="comparison-box-btns"><button id="reset-comparison"><svg viewBox="0 0 24 24">
                <path d="M2.567 15.332c0.918 2.604 2.805 4.591 5.112 5.696s5.038 1.33 7.643 0.413 4.591-2.805 5.696-5.112 1.33-5.038 0.413-7.643-2.805-4.591-5.112-5.696-5.038-1.33-7.643-0.413c-1.474 0.52-2.755 1.352-3.749 2.362l-2.927 2.75v-3.689c0-0.552-0.448-1-1-1s-1 0.448-1 1v5.998c0 0.015 0 0.030 0.001 0.044 0.005 0.115 0.029 0.225 0.069 0.326 0.040 0.102 0.098 0.198 0.173 0.285 0.012 0.013 0.024 0.027 0.036 0.039 0.091 0.095 0.201 0.172 0.324 0.225 0.119 0.051 0.249 0.080 0.386 0.0820.004 0 0.007 0 0.011 0h6c0.552 0 1-0.448 1-1s-0.448-0.999-1-0.999h-3.476l2.829-2.659c0.779-0.792 1.8-1.459 2.987-1.877 2.084-0.734 4.266-0.555 6.114 0.330s3.356 2.473 4.090 4.557 0.555 4.266-0.330 6.114-2.473 3.356-4.557 4.090-4.266 0.555-6.114-0.330-3.356-2.473-4.090-4.557c-0.184-0.521-0.755-0.794-1.275-0.611s-0.794 0.755-0.611 1.275z"></path>
                </svg>Reset</button>
                <button id="compare-products"><svg viewBox="0 0 32 32"><g id="Desicion"><path d="M29.887,15.559,25.9,7.553a.993.993,0,0,0-1-.547l-6.2.69A3,3,0,0,0,17,6.184V5a1,1,0,0,0-2,0V6.184a3,3,0,0,0-1.914,2.133l-6.2.689a1,1,0,0,0-.785.547l-3.99,8A.978.978,0,0,0,2,18a4,4,0,0,0,4,4H8a4,4,0,0,0,4-4,.978.978,0,0,0-.1-.427c0-.008-3.36-6.738-3.36-6.738l4.775-.531A3,3,0,0,0,15,11.816V25H9a3,3,0,0,0-2.625,1.549A1,1,0,0,0,7.272,28H24.728a1,1,0,0,0,.9-1.451A3,3,0,0,0,23,25H17V11.816a3,3,0,0,0,1.914-2.133L23.283,9.2s-3.178,6.371-3.182,6.381A.982.982,0,0,0,20,16a4,4,0,0,0,4,4h2a4,4,0,0,0,4-4A.978.978,0,0,0,29.887,15.559ZM9.382,17H4.618L7,12.236ZM16,10a1,1,0,1,1,1-1A1,1,0,0,1,16,10Zm9,.237L27.382,15H22.618Z"></path></g></svg>Compare</button></div>
            `);

            $('#reset-comparison').on('click', resetComparison);
            $('#compare-products').on('click', function() {
                const encodedIds = encodeURIComponent(productIds.join(','));
                window.location.href = `https://eridehero.com/tool/electric-scooter-comparison/?ids=${encodedIds}`;
            });

            requestAnimationFrame(() => comparisonBox.addClass('visible'));
        } else {
            if (comparisonBox.length > 0) {
                comparisonBox.removeClass('visible');
                comparisonBox.on('transitionend', function() {
                    $(this).remove();
                });
            }
        }
    }

    function resetComparison() {
        selectedProductIds.clear();
        $scooterTable.find('tbody tr .row-checkbox').removeClass('row-checked');
        updateComparisonBox();
    }
});
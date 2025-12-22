<?php
/**
 * Finder Tool Configuration
 *
 * Centralized configuration for all finder filters.
 * This is the single source of truth - adding a new filter only requires
 * adding an entry here (and in the JS FILTER_CONFIG if needed).
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Page configuration by product type.
 */
function erh_get_finder_page_config(): array {
    return [
        'escooter' => [
            'title'        => 'Electric Scooter Database',
            'subtitle'     => 'Find your perfect electric scooter from our database of 100+ models',
            'short'        => 'E-Scooter',
            'product_type' => 'Electric Scooter',
        ],
        'ebike' => [
            'title'        => 'Electric Bike Database',
            'subtitle'     => 'Compare electric bikes with detailed specs and pricing',
            'short'        => 'E-Bike',
            'product_type' => 'Electric Bike',
        ],
        'skateboard' => [
            'title'        => 'Electric Skateboard Database',
            'subtitle'     => 'Find the perfect electric skateboard for your riding style',
            'short'        => 'E-Skateboard',
            'product_type' => 'Electric Skateboard',
        ],
        'euc' => [
            'title'        => 'Electric Unicycle Database',
            'subtitle'     => 'Compare EUCs with detailed specs and real-world testing',
            'short'        => 'EUC',
            'product_type' => 'Electric Unicycle',
        ],
        'hoverboard' => [
            'title'        => 'Hoverboard Database',
            'subtitle'     => 'Compare hoverboards with detailed specs and pricing',
            'short'        => 'Hoverboard',
            'product_type' => 'Hoverboard',
        ],
    ];
}

/**
 * Slug mappings for page slugs to JSON file types.
 */
function erh_get_finder_slug_map(): array {
    return [
        'escooter-finder'    => 'escooter',
        'ebike-finder'       => 'ebike',
        'skateboard-finder'  => 'skateboard',
        'euc-finder'         => 'euc',
        'hoverboard-finder'  => 'hoverboard',
    ];
}

/**
 * Slug mappings for product type slugs to JSON file types.
 */
function erh_get_finder_type_map(): array {
    return [
        'e-scooters'    => 'escooter',
        'e-bikes'       => 'ebike',
        'e-skateboards' => 'skateboard',
        'eucs'          => 'euc',
        'hoverboards'   => 'hoverboard',
    ];
}

/**
 * Range filter configuration.
 *
 * Each filter defines:
 * - label: Display label
 * - field: Product array key for the extracted value
 * - spec_paths: Array of paths to check in specs (dot notation for nested)
 * - unit: Unit abbreviation (mph, mi, lbs, etc.)
 * - prefix: Input prefix (e.g., '$' for price)
 * - suffix: Input suffix (e.g., 'mph')
 * - default_max: Fallback max value if no products have data
 * - round_factor: Factor to round max up to (ceil)
 * - presets: (optional) Array of quick-select options with label and min/max or value
 * - filter_mode: (optional) 'range' (default) or 'contains' for inverted logic
 * - field_min/field_max: (optional) For contains mode, the product's min/max fields
 *
 * Group membership is defined in erh_get_filter_group_config().
 */
function erh_get_range_filter_config(): array {
    return [
        'price' => [
            'label'        => 'Price',
            'field'        => 'current_price',
            'spec_paths'   => null, // Price comes from pricing data, not specs.
            'unit'         => '',
            'prefix'       => '$',
            'suffix'       => '',
            'default_max'  => 5000,
            'round_factor' => 100,
            'presets'      => [
                [ 'label' => 'Under $500', 'min' => 1, 'max' => 500 ],
                [ 'label' => '$500–$1,000', 'min' => 500, 'max' => 1000 ],
                [ 'label' => '$1,000–$2,000', 'min' => 1000, 'max' => 2000 ],
                [ 'label' => '$2,000+', 'min' => 2000 ],
            ],
        ],
        'release_year' => [
            'label'        => 'Release Year',
            'field'        => 'release_year',
            'spec_paths'   => [ 'release_year' ],
            'unit'         => '',
            'prefix'       => '',
            'suffix'       => '',
            'default_max'  => 2025,
            'default_min'  => 2018,
            'round_factor' => 1,
            'use_data_min' => true,
            'presets'      => [
                [ 'label' => '2024+', 'min' => 2024 ],
                [ 'label' => '2023+', 'min' => 2023 ],
                [ 'label' => '2022+', 'min' => 2022 ],
            ],
        ],
        'speed' => [
            'label'        => 'Top Speed (claimed)',
            'field'        => 'top_speed',
            'spec_paths'   => [ 'manufacturer_top_speed' ], // Manufacturer claim only.
            'unit'         => 'mph',
            'prefix'       => '',
            'suffix'       => 'mph',
            'default_max'  => 50,
            'round_factor' => 5,
            'use_data_min' => true,
            'is_open'      => true,
        ],
        'range' => [
            'label'        => 'Range (claimed)',
            'field'        => 'range',
            'spec_paths'   => [ 'manufacturer_range' ], // Manufacturer claim only.
            'unit'         => 'mi',
            'prefix'       => '',
            'suffix'       => 'mi',
            'default_max'  => 100,
            'round_factor' => 10,
            'use_data_min' => true,
        ],
        'weight' => [
            'label'        => 'Weight',
            'field'        => 'weight',
            'spec_paths'   => [ 'dimensions.weight' ], // Nested in e-scooters group.
            'unit'         => 'lbs',
            'prefix'       => '',
            'suffix'       => 'lbs',
            'default_max'  => 150,
            'round_factor' => 10,
            'use_data_min' => true,
            'is_open'      => true,
        ],
        'weight_limit' => [
            'label'        => 'Max Load',
            'field'        => 'weight_limit',
            'spec_paths'   => [ 'dimensions.max_load' ], // Nested in e-scooters group.
            'unit'         => 'lbs',
            'prefix'       => '',
            'suffix'       => 'lbs',
            'default_max'  => 400,
            'round_factor' => 50,
            'use_data_min' => true,
        ],
        'battery' => [
            'label'        => 'Capacity',
            'field'        => 'battery',
            'spec_paths'   => [ 'battery.capacity' ], // Nested in e-scooters group.
            'unit'         => 'Wh',
            'prefix'       => '',
            'suffix'       => 'Wh',
            'default_max'  => 2000,
            'round_factor' => 100,
            'use_data_min' => true,
            'is_open'      => true,
        ],
        'voltage' => [
            'label'        => 'Voltage',
            'field'        => 'voltage',
            'spec_paths'   => [ 'battery.voltage', 'motor.voltage' ], // Battery voltage preferred.
            'unit'         => 'V',
            'prefix'       => '',
            'suffix'       => 'V',
            'default_max'  => 72,
            'round_factor' => 12,
            'use_data_min' => true,
        ],
        'amphours' => [
            'label'        => 'Amp Hours',
            'field'        => 'amphours',
            'spec_paths'   => [ 'battery.amphours' ], // Nested in e-scooters group.
            'unit'         => 'Ah',
            'prefix'       => '',
            'suffix'       => 'Ah',
            'default_max'  => 50,
            'round_factor' => 5,
            'use_data_min' => true,
        ],
        'charging_time' => [
            'label'        => 'Charging Time',
            'field'        => 'charging_time',
            'spec_paths'   => [ 'battery.charging_time' ], // Nested in e-scooters group.
            'unit'         => 'hrs',
            'prefix'       => '',
            'suffix'       => 'hrs',
            'default_max'  => 12,
            'round_factor' => 1,
            'use_data_min' => true,
        ],
        'motor_power' => [
            'label'        => 'Nominal Power',
            'field'        => 'motor_power',
            'spec_paths'   => [ 'motor.power_nominal' ], // Nested in e-scooters group.
            'unit'         => 'W',
            'prefix'       => '',
            'suffix'       => 'W',
            'default_max'  => 2000,
            'round_factor' => 100,
            'use_data_min' => true,
        ],
        'motor_peak' => [
            'label'        => 'Peak Power',
            'field'        => 'motor_peak',
            'spec_paths'   => [ 'motor.power_peak' ], // Nested in e-scooters group.
            'unit'         => 'W',
            'prefix'       => '',
            'suffix'       => 'W',
            'default_max'  => 5000,
            'round_factor' => 500,
            'use_data_min' => true,
        ],
        'max_incline' => [
            'label'        => 'Max Incline',
            'field'        => 'max_incline',
            'spec_paths'   => [ 'max_incline' ], // Root level field.
            'unit'         => '°',
            'prefix'       => '',
            'suffix'       => '°',
            'default_max'  => 40,
            'round_factor' => 5,
            'use_data_min' => true,
        ],
        'deck_width' => [
            'label'        => 'Deck Width',
            'field'        => 'deck_width',
            'spec_paths'   => [ 'dimensions.deck_width' ],
            'unit'         => 'in',
            'prefix'       => '',
            'suffix'       => '"',
            'default_max'  => 12,
            'round_factor' => 1,
            'use_data_min' => true,
        ],
        'deck_length' => [
            'label'        => 'Deck Length',
            'field'        => 'deck_length',
            'spec_paths'   => [ 'dimensions.deck_length' ],
            'unit'         => 'in',
            'prefix'       => '',
            'suffix'       => '"',
            'default_max'  => 30,
            'round_factor' => 2,
            'use_data_min' => true,
        ],
        'handlebar_width' => [
            'label'        => 'Handlebar Width',
            'field'        => 'handlebar_width',
            'spec_paths'   => [ 'dimensions.handlebar_width' ],
            'unit'         => 'in',
            'prefix'       => '',
            'suffix'       => '"',
            'default_max'  => 30,
            'round_factor' => 2,
            'use_data_min' => true,
        ],
        'ground_clearance' => [
            'label'        => 'Ground Clearance',
            'field'        => 'ground_clearance',
            'spec_paths'   => [ 'dimensions.ground_clearance' ],
            'unit'         => 'in',
            'prefix'       => '',
            'suffix'       => '"',
            'default_max'  => 10,
            'round_factor' => 1,
            'use_data_min' => true,
        ],

        // =================================================================
        // Tires
        // =================================================================
        'tire_size' => [
            'label'        => 'Tire Size',
            'field'        => 'tire_size',
            'spec_paths'   => [ 'wheels.tire_size_front' ], // Front wheel size.
            'unit'         => 'in',
            'prefix'       => '',
            'suffix'       => '"',
            'default_max'  => 14,
            'round_factor' => 1,
            'use_data_min' => true,
        ],
        'tire_width' => [
            'label'        => 'Tire Width',
            'field'        => 'tire_width',
            'spec_paths'   => [ 'wheels.tire_width' ],
            'unit'         => 'in',
            'prefix'       => '',
            'suffix'       => '"',
            'default_max'  => 5,
            'round_factor' => 0.5,
            'use_data_min' => true,
        ],

        'rider_height' => [
            'label'        => 'Rider Height',
            'field'        => 'rider_height', // Uses computed min/max fields.
            'field_min'    => 'rider_height_min', // For contains mode.
            'field_max'    => 'rider_height_max', // For contains mode.
            'spec_paths'   => null, // Computed from handlebar heights.
            'unit'         => 'in',
            'prefix'       => '',
            'suffix'       => '"',
            'default_max'  => 84, // 7 feet.
            'round_factor' => 6,
            'filter_mode'  => 'contains', // User inputs their height, check if product fits.
            'slider_mode'  => 'single',   // Single-thumb slider for contains mode.
            'input_type'   => 'height',   // Feet/inches input (future: 'height_metric' for cm).
            'is_open'      => true,
            'presets'      => [
                [ 'label' => 'Under 5\'4"', 'value' => 62 ],   // 5'2" midpoint
                [ 'label' => '5\'4"–5\'10"', 'value' => 67 ],  // 5'7" midpoint
                [ 'label' => '5\'10"–6\'2"', 'value' => 72 ],  // 6'0" midpoint
                [ 'label' => 'Over 6\'2"', 'value' => 76 ],    // 6'4" midpoint
            ],
        ],

        // =================================================================
        // Tested Performance (ERideHero's own tests)
        // =================================================================
        'tested_speed' => [
            'label'        => 'Tested Top Speed',
            'field'        => 'tested_speed',
            'spec_paths'   => [ 'tested_top_speed' ],
            'unit'         => 'mph',
            'prefix'       => '',
            'suffix'       => 'mph',
            'default_max'  => 50,
            'round_factor' => 5,
            'use_data_min' => true,
            'is_open'      => true,
        ],
        'tested_range' => [
            'label'        => 'Tested Range',
            'field'        => 'tested_range',
            'spec_paths'   => [ 'tested_range_regular' ],
            'unit'         => 'mi',
            'prefix'       => '',
            'suffix'       => 'mi',
            'default_max'  => 60,
            'round_factor' => 5,
            'use_data_min' => true,
        ],
        'accel_0_15' => [
            'label'        => '0–15 mph',
            'field'        => 'accel_0_15',
            'spec_paths'   => [ 'acceleration_0_15_mph' ],
            'unit'         => 's',
            'prefix'       => '',
            'suffix'       => 's',
            'default_max'  => 10,
            'round_factor' => 1,
            'use_data_min' => true,
        ],
        'accel_0_20' => [
            'label'        => '0–20 mph',
            'field'        => 'accel_0_20',
            'spec_paths'   => [ 'acceleration_0_20_mph' ],
            'unit'         => 's',
            'prefix'       => '',
            'suffix'       => 's',
            'default_max'  => 15,
            'round_factor' => 1,
            'use_data_min' => true,
        ],
        'brake_distance' => [
            'label'        => 'Braking Distance',
            'field'        => 'brake_distance',
            'spec_paths'   => [ 'brake_distance' ],
            'unit'         => 'ft',
            'prefix'       => '',
            'suffix'       => 'ft',
            'default_max'  => 30,
            'round_factor' => 5,
            'use_data_min' => true,
        ],
        'hill_climb' => [
            'label'        => 'Hill Climbing',
            'field'        => 'hill_climb',
            'spec_paths'   => [ 'hill_climbing' ],
            'unit'         => '°',
            'prefix'       => '',
            'suffix'       => '°',
            'default_max'  => 30,
            'round_factor' => 5,
            'use_data_min' => true,
        ],

        // =================================================================
        // Value Metrics (Advanced comparisons)
        // =================================================================
        'price_per_lb' => [
            'label'        => 'Price per lb',
            'field'        => 'price_per_lb',
            'spec_paths'   => [ 'price_per_lb' ],
            'unit'         => '$/lb',
            'prefix'       => '$',
            'suffix'       => '/lb',
            'default_max'  => 50,
            'round_factor' => 5,
        ],
        'price_per_mph' => [
            'label'        => 'Price per mph',
            'field'        => 'price_per_mph',
            'spec_paths'   => [ 'price_per_mph' ],
            'unit'         => '$/mph',
            'prefix'       => '$',
            'suffix'       => '/mph',
            'default_max'  => 100,
            'round_factor' => 10,
        ],
        'price_per_mile' => [
            'label'        => 'Price per mile',
            'field'        => 'price_per_mile',
            'spec_paths'   => [ 'price_per_mile_range' ],
            'unit'         => '$/mi',
            'prefix'       => '$',
            'suffix'       => '/mi',
            'default_max'  => 100,
            'round_factor' => 10,
        ],
        'price_per_wh' => [
            'label'        => 'Price per Wh',
            'field'        => 'price_per_wh',
            'spec_paths'   => [ 'price_per_wh' ],
            'unit'         => '$/Wh',
            'prefix'       => '$',
            'suffix'       => '/Wh',
            'default_max'  => 5,
            'round_factor' => 1,
        ],
        'speed_per_lb' => [
            'label'        => 'Speed per lb',
            'field'        => 'speed_per_lb',
            'spec_paths'   => [ 'speed_per_lb' ],
            'unit'         => 'mph/lb',
            'prefix'       => '',
            'suffix'       => 'mph/lb',
            'default_max'  => 2,
            'round_factor' => 0.1,
            'use_data_min' => true,
        ],
        'range_per_lb' => [
            'label'        => 'Range per lb',
            'field'        => 'range_per_lb',
            'spec_paths'   => [ 'range_per_lb' ],
            'unit'         => 'mi/lb',
            'prefix'       => '',
            'suffix'       => 'mi/lb',
            'default_max'  => 2,
            'round_factor' => 0.1,
            'use_data_min' => true,
        ],
    ];
}

/**
 * Checkbox filter configuration (set filters).
 *
 * Each filter defines:
 * - label: Display label for the filter group/item
 * - field: Product array key for the extracted value
 * - spec_paths: Array of paths to check in specs
 * - visible_limit: Number of items to show before "Show all"
 * - searchable: Whether to show search input
 * - is_array: Whether the product value can be an array
 *
 * Group membership is defined in erh_get_filter_group_config().
 */
function erh_get_checkbox_filter_config(): array {
    return [
        'brand' => [
            'label'         => 'Brands',
            'field'         => 'brand',
            'spec_paths'    => null, // Brand is extracted from product name.
            'visible_limit' => 8,
            'searchable'    => true,
        ],
        'motor_position' => [
            'label'         => 'Position',
            'field'         => 'motor_position',
            'spec_paths'    => [ 'motor.motor_position' ], // Nested in e-scooters group.
            'visible_limit' => 10,
            'searchable'    => false,
        ],
        'brake_type' => [
            'label'         => 'Type',
            'field'         => 'brake_type',
            'spec_paths'    => [ 'brakes.front' ], // Nested in e-scooters group.
            'visible_limit' => 10,
            'searchable'    => false,
        ],
        'tire_type' => [
            'label'         => 'Tire type',
            'field'         => 'tire_type',
            'spec_paths'    => [ 'wheels.tire_type' ], // Nested in e-scooters group.
            'visible_limit' => 10,
            'searchable'    => false,
        ],
        'suspension_type' => [
            'label'         => 'Type',
            'field'         => 'suspension_type',
            'spec_paths'    => [ 'suspension.type' ], // Nested in e-scooters group.
            'visible_limit' => 10,
            'searchable'    => false,
            'is_array'      => true, // Values can be arrays (checkbox field).
        ],
        'terrain' => [
            'label'         => 'Terrain',
            'field'         => 'terrain',
            'spec_paths'    => [ 'other.terrain' ],
            'visible_limit' => 10,
            'searchable'    => false,
        ],
        'ip_rating' => [
            'label'         => 'IP Rating',
            'field'         => 'ip_rating',
            'spec_paths'    => [ 'other.ip_rating' ],
            'visible_limit' => 10,
            'searchable'    => false,
        ],
        'throttle_type' => [
            'label'         => 'Throttle Type',
            'field'         => 'throttle_type',
            'spec_paths'    => [ 'other.throttle_type' ],
            'visible_limit' => 10,
            'searchable'    => false,
        ],
        'features' => [
            'label'         => 'Features',
            'field'         => 'features',
            'spec_paths'    => [ 'features' ], // Root level array field.
            'visible_limit' => 8,
            'searchable'    => true,
            'is_array'      => true, // Values are arrays (checkbox field).
        ],
    ];
}

/**
 * Tristate filter configuration (Any / Yes / No).
 *
 * Each filter defines:
 * - label: Display label
 * - field: Product array key for the extracted value
 * - spec_paths: Array of paths to check in specs
 *
 * Group membership is defined in erh_get_filter_group_config().
 */
function erh_get_tristate_filter_config(): array {
    return [
        'regenerative_braking' => [
            'label'      => 'Regenerative braking',
            'field'      => 'regenerative_braking',
            'spec_paths' => [ 'brakes.regenerative' ], // Nested in e-scooters group.
        ],
        'self_healing_tires' => [
            'label'      => 'Self-healing tires',
            'field'      => 'self_healing_tires',
            'spec_paths' => [ 'wheels.self_healing' ], // Nested in e-scooters group.
        ],
        'suspension_adjustable' => [
            'label'      => 'Adjustable suspension',
            'field'      => 'suspension_adjustable',
            'spec_paths' => [ 'suspension.adjustable' ], // Nested in e-scooters group.
        ],
        'foldable_handlebars' => [
            'label'      => 'Foldable handlebars',
            'field'      => 'foldable_handlebars',
            'spec_paths' => [ 'dimensions.foldable_handlebars' ], // Nested in e-scooters group.
        ],
        'turn_signals' => [
            'label'      => 'Turn signals',
            'field'      => 'turn_signals',
            'spec_paths' => [ 'lighting.turn_signals' ], // Nested in e-scooters group.
        ],
        'has_lights' => [
            'label'      => 'Has lights',
            'field'      => 'has_lights',
            'spec_paths' => [ 'lighting.lights' ], // Array field - true if has Front/Rear/Both.
            'derive_from_array' => true, // Special handling: true if array contains non-"None" values.
        ],
    ];
}

/**
 * Filter group configuration.
 *
 * Defines the order and structure of filter groups in the sidebar.
 * The 'quick' group contains subgroups and has no main heading.
 */
function erh_get_filter_group_config(): array {
    return [
        'quick' => [
            'title'      => '', // No main heading
            'is_quick'   => true,
            'subgroups'  => [
                'price' => [
                    'title'            => 'Price',
                    'range_filters'    => [ 'price' ],
                    'checkbox_filters' => [],
                    'tristate_filters' => [],
                    'has_in_stock'     => true,
                ],
                'brands' => [
                    'title'            => 'Brands',
                    'range_filters'    => [],
                    'checkbox_filters' => [ 'brand' ],
                    'tristate_filters' => [],
                    'has_in_stock'     => false,
                ],
            ],
        ],
        'motor' => [
            'title'            => 'Motor',
            'range_filters'    => [ 'motor_power', 'motor_peak' ],
            'checkbox_filters' => [ 'motor_position' ],
            'tristate_filters' => [],
            'has_in_stock'     => false,
        ],
        'battery' => [
            'title'            => 'Battery',
            'range_filters'    => [ 'battery', 'voltage', 'amphours', 'charging_time' ],
            'checkbox_filters' => [],
            'tristate_filters' => [],
            'has_in_stock'     => false,
        ],
        'performance' => [
            'title'            => 'Claimed Specs',
            'range_filters'    => [ 'speed', 'range', 'max_incline' ],
            'checkbox_filters' => [],
            'tristate_filters' => [],
            'has_in_stock'     => false,
        ],
        'tested_performance' => [
            'title'            => 'Tested Performance',
            'range_filters'    => [ 'tested_speed', 'tested_range', 'accel_0_15', 'accel_0_20', 'brake_distance', 'hill_climb' ],
            'checkbox_filters' => [],
            'tristate_filters' => [],
            'has_in_stock'     => false,
        ],
        'portability' => [
            'title'            => 'Portability',
            'range_filters'    => [ 'weight' ],
            'checkbox_filters' => [],
            'tristate_filters' => [ 'foldable_handlebars' ],
            'has_in_stock'     => false,
        ],
        'rider_fit' => [
            'title'            => 'Rider Fit',
            'range_filters'    => [ 'rider_height', 'weight_limit', 'deck_width', 'handlebar_width', 'ground_clearance' ],
            'checkbox_filters' => [],
            'tristate_filters' => [],
            'has_in_stock'     => false,
        ],
        'brakes' => [
            'title'            => 'Brakes',
            'range_filters'    => [],
            'checkbox_filters' => [ 'brake_type' ],
            'tristate_filters' => [ 'regenerative_braking' ],
            'has_in_stock'     => false,
        ],
        'tires' => [
            'title'            => 'Tires',
            'range_filters'    => [ 'tire_size', 'tire_width' ],
            'checkbox_filters' => [ 'tire_type' ],
            'tristate_filters' => [ 'self_healing_tires' ],
            'has_in_stock'     => false,
        ],
        'suspension' => [
            'title'            => 'Suspension',
            'range_filters'    => [],
            'checkbox_filters' => [ 'suspension_type' ],
            'tristate_filters' => [ 'suspension_adjustable' ],
            'has_in_stock'     => false,
        ],
        'terrain_durability' => [
            'title'            => 'Terrain & Durability',
            'range_filters'    => [],
            'checkbox_filters' => [ 'terrain', 'ip_rating' ],
            'tristate_filters' => [],
            'has_in_stock'     => false,
        ],
        'controls_features' => [
            'title'            => 'Controls & Features',
            'range_filters'    => [],
            'checkbox_filters' => [ 'throttle_type', 'features' ],
            'tristate_filters' => [],
            'has_in_stock'     => false,
        ],
        'lighting' => [
            'title'            => 'Lighting',
            'range_filters'    => [],
            'checkbox_filters' => [],
            'tristate_filters' => [ 'has_lights', 'turn_signals' ],
            'has_in_stock'     => false,
        ],
        'model_info' => [
            'title'            => 'Model Info',
            'range_filters'    => [ 'release_year' ],
            'checkbox_filters' => [],
            'tristate_filters' => [],
            'has_in_stock'     => false,
        ],
        'value_metrics' => [
            'title'            => 'Value Metrics',
            'range_filters'    => [ 'price_per_lb', 'price_per_mph', 'price_per_mile', 'price_per_wh', 'speed_per_lb', 'range_per_lb' ],
            'checkbox_filters' => [],
            'tristate_filters' => [],
            'has_in_stock'     => false,
        ],
    ];
}

/**
 * Check if a filter group has any content to display.
 *
 * @param array $group            Group configuration.
 * @param array $filter_max       Max values for range filters.
 * @param array $checkbox_options Options for checkbox filters.
 * @return bool True if group has displayable content.
 */
function erh_group_has_content( array $group, array $filter_max, array $checkbox_options ): bool {
    // Check range filters.
    foreach ( $group['range_filters'] ?? [] as $filter_key ) {
        if ( ( $filter_max[ $filter_key ] ?? 0 ) > 0 ) {
            return true;
        }
    }

    // Check checkbox filters.
    foreach ( $group['checkbox_filters'] ?? [] as $filter_key ) {
        if ( ! empty( $checkbox_options[ $filter_key ] ?? [] ) ) {
            return true;
        }
    }

    // Check tristate filters.
    if ( ! empty( $group['tristate_filters'] ?? [] ) ) {
        return true;
    }

    // Check for in-stock toggle.
    if ( $group['has_in_stock'] ?? false ) {
        return true;
    }

    return false;
}

/**
 * Helper to extract a nested spec value.
 *
 * @param array $specs The specs array.
 * @param array $paths Array of paths to try (dot notation for nested).
 * @return mixed|null The value or null if not found.
 */
function erh_get_spec_value( array $specs, array $paths ) {
    foreach ( $paths as $path ) {
        $parts = explode( '.', $path );
        $value = $specs;

        foreach ( $parts as $part ) {
            if ( ! is_array( $value ) || ! isset( $value[ $part ] ) ) {
                $value = null;
                break;
            }
            $value = $value[ $part ];
        }

        if ( $value !== null && $value !== '' ) {
            return $value;
        }
    }

    return null;
}

/**
 * Convert a value to boolean.
 *
 * @param mixed $value The value to convert.
 * @return bool|null True, false, or null if indeterminate.
 */
function erh_to_boolean( $value ) {
    if ( $value === null || $value === '' ) {
        return null;
    }

    if ( is_bool( $value ) ) {
        return $value;
    }

    if ( is_string( $value ) ) {
        $lower = strtolower( trim( $value ) );
        if ( in_array( $lower, [ 'yes', 'true', '1', 'on' ], true ) ) {
            return true;
        }
        if ( in_array( $lower, [ 'no', 'false', '0', 'off', 'none' ], true ) ) {
            return false;
        }
    }

    if ( is_numeric( $value ) ) {
        return (bool) $value;
    }

    return null;
}

/**
 * Calculate preset counts for a range filter.
 *
 * For range mode: count products where value is within preset's min/max.
 * For contains mode: count products where product's min/max range contains preset's value.
 *
 * @param array  $products   Array of products (already processed with extracted values).
 * @param string $filter_key The filter key.
 * @param array  $cfg        Filter configuration.
 * @return array Counts per preset index.
 */
function erh_calc_preset_counts( array $products, string $filter_key, array $cfg ): array {
    $presets = $cfg['presets'] ?? [];
    if ( empty( $presets ) ) {
        return [];
    }

    $counts      = array_fill( 0, count( $presets ), 0 );
    $filter_mode = $cfg['filter_mode'] ?? 'range';
    $field       = $cfg['field'] ?? $filter_key;
    $field_min   = $cfg['field_min'] ?? $field;
    $field_max   = $cfg['field_max'] ?? $field;

    foreach ( $products as $product ) {
        if ( $filter_mode === 'contains' ) {
            // Contains mode: product has a range, preset has a single value.
            // Count products that would show when preset is selected.
            $product_min = $product[ $field_min ] ?? null;
            $product_max = $product[ $field_max ] ?? null;

            // Skip products without range data.
            if ( $product_min === null && $product_max === null ) {
                continue;
            }

            foreach ( $presets as $index => $preset ) {
                $preset_value = $preset['value'] ?? null;
                if ( $preset_value === null ) {
                    continue;
                }

                // Product matches if its range contains the preset value.
                $matches = true;
                if ( $product_min !== null && $preset_value < $product_min ) {
                    $matches = false;
                }
                if ( $product_max !== null && $preset_value > $product_max ) {
                    $matches = false;
                }

                if ( $matches ) {
                    $counts[ $index ]++;
                }
            }
        } else {
            // Standard range mode: product has a single value, preset has min/max.
            $value = $product[ $field ] ?? null;

            // Skip products without value.
            if ( $value === null ) {
                continue;
            }

            foreach ( $presets as $index => $preset ) {
                $preset_min = $preset['min'] ?? null;
                $preset_max = $preset['max'] ?? null;

                // Product matches if its value is within preset range.
                $matches = true;
                if ( $preset_min !== null && $value < $preset_min ) {
                    $matches = false;
                }
                if ( $preset_max !== null && $value > $preset_max ) {
                    $matches = false;
                }

                if ( $matches ) {
                    $counts[ $index ]++;
                }
            }
        }
    }

    return $counts;
}

/**
 * Calculate distribution histogram for a filter.
 *
 * @param array  $products   Array of products.
 * @param string $field      Product field to analyze.
 * @param float  $max_val    Maximum value for the range.
 * @param int    $num_buckets Number of histogram buckets.
 * @return array Normalized distribution (0-100 per bucket).
 */
function erh_calc_distribution( array $products, string $field, float $max_val, float $min_val = 0, int $num_buckets = 10 ): array {
    $range = $max_val - $min_val;
    if ( $range <= 0 ) {
        return array_fill( 0, $num_buckets, 0 );
    }

    $distribution = array_fill( 0, $num_buckets, 0 );
    $bucket_size  = $range / $num_buckets;

    foreach ( $products as $product ) {
        $value = $product[ $field ] ?? null;
        if ( $value !== null && $value >= $min_val ) {
            $bucket_index = min( floor( ( $value - $min_val ) / $bucket_size ), $num_buckets - 1 );
            $distribution[ (int) $bucket_index ]++;
        }
    }

    // Normalize to percentages (0-100).
    $max_count = max( $distribution ) ?: 1;

    return array_map(
        function ( $count ) use ( $max_count ) {
            return round( ( $count / $max_count ) * 100 );
        },
        $distribution
    );
}

/**
 * Process products to extract filter data.
 *
 * @param array $products Raw products from JSON.
 * @param string $user_geo User's geo for pricing.
 * @return array Processed data with products, filter_max, filter_dist, and checkbox_options.
 */
function erh_process_finder_products( array $products, string $user_geo = 'US' ): array {
    $range_config    = erh_get_range_filter_config();
    $checkbox_config = erh_get_checkbox_filter_config();
    $tristate_config = erh_get_tristate_filter_config();

    $filter_max       = [];
    $filter_min       = []; // Track actual min values for filters with use_data_min.
    $checkbox_options = [];
    $tristate_counts  = []; // Track Yes/No counts for tristate filters

    // Initialize max values, min values, checkbox options, and tristate counts.
    foreach ( $range_config as $key => $cfg ) {
        $filter_max[ $key ] = 0;
        // Initialize min to PHP_INT_MAX for tracking, or null if not using data min.
        $filter_min[ $key ] = ( $cfg['use_data_min'] ?? false ) ? PHP_INT_MAX : null;
    }
    foreach ( $checkbox_config as $key => $cfg ) {
        $checkbox_options[ $key ] = [];
    }
    foreach ( $tristate_config as $key => $cfg ) {
        $tristate_counts[ $key ] = [ 'yes' => 0, 'no' => 0 ];
    }

    // Process each product.
    foreach ( $products as &$product ) {
        $specs = $product['specs'] ?? [];

        // Extract brand from product name (first word).
        $name  = $product['name'] ?? '';
        $brand = $name ? explode( ' ', $name )[0] : '';
        $product['brand'] = $brand;

        if ( $brand ) {
            $checkbox_options['brand'][ $brand ] = ( $checkbox_options['brand'][ $brand ] ?? 0 ) + 1;
        }

        // Get pricing for user's geo (fallback to US).
        $pricing       = $product['pricing'][ $user_geo ] ?? $product['pricing']['US'] ?? [];
        $current_price = $pricing['current_price'] ?? null;

        $product['current_price']    = $current_price;
        $product['price_formatted']  = $current_price ? erh_format_price( $current_price, $pricing['currency'] ?? 'USD' ) : null;
        $product['in_stock']         = $pricing['instock'] ?? false;
        $product['best_link']        = $pricing['bestlink'] ?? null;

        // Calculate price indicator (% vs 3-month average).
        $avg_price                 = $pricing['avg_3m'] ?? null;
        $product['price_indicator'] = null;
        if ( $current_price && $avg_price && $avg_price > 0 ) {
            $product['price_indicator'] = round( ( ( $current_price - $avg_price ) / $avg_price ) * 100 );
        }

        // Track price max.
        if ( $current_price && $current_price > 0 ) {
            $filter_max['price'] = max( $filter_max['price'], $current_price );
        }

        // Extract range filter values from specs.
        foreach ( $range_config as $key => $cfg ) {
            if ( $cfg['spec_paths'] ) {
                $value = erh_get_spec_value( $specs, $cfg['spec_paths'] );
                $product[ $cfg['field'] ] = $value ? floatval( $value ) : null;

                if ( $value && floatval( $value ) > 0 ) {
                    $float_value = floatval( $value );
                    $filter_max[ $key ] = max( $filter_max[ $key ], $float_value );

                    // Track min for filters with use_data_min.
                    if ( $filter_min[ $key ] !== null ) {
                        $filter_min[ $key ] = min( $filter_min[ $key ], $float_value );
                    }
                }
            }
        }

        // Compute rider height range from handlebar heights.
        // Formula: comfortable handlebar height is ~58% of rider height.
        // We use slightly different ratios for min/max to account for preference variation.
        $handlebar_min = erh_get_spec_value( $specs, [ 'dimensions.handlebar_height_min' ] );
        $handlebar_max = erh_get_spec_value( $specs, [ 'dimensions.handlebar_height_max' ] );

        if ( $handlebar_min && $handlebar_max ) {
            // Shorter riders can handle relatively higher bars (ratio 0.62).
            // Taller riders need relatively lower bars (ratio 0.54).
            $product['rider_height_min'] = round( floatval( $handlebar_min ) / 0.62 );
            $product['rider_height_max'] = round( floatval( $handlebar_max ) / 0.54 );
            // Use max handlebar height as the reference "rider_height" for distribution.
            $product['rider_height'] = $product['rider_height_max'];

            $filter_max['rider_height'] = max( $filter_max['rider_height'] ?? 0, $product['rider_height_max'] );
        } elseif ( $handlebar_max ) {
            // Only max available - estimate min as same value.
            $product['rider_height_min'] = round( floatval( $handlebar_max ) / 0.62 );
            $product['rider_height_max'] = round( floatval( $handlebar_max ) / 0.54 );
            $product['rider_height'] = $product['rider_height_max'];

            $filter_max['rider_height'] = max( $filter_max['rider_height'] ?? 0, $product['rider_height_max'] );
        } else {
            $product['rider_height_min'] = null;
            $product['rider_height_max'] = null;
            $product['rider_height'] = null;
        }

        // Extract checkbox filter values from specs.
        foreach ( $checkbox_config as $key => $cfg ) {
            if ( $cfg['spec_paths'] ) {
                $value = erh_get_spec_value( $specs, $cfg['spec_paths'] );

                // Handle array-type fields (e.g., suspension_type can be ["Front", "Rear"]).
                if ( $cfg['is_array'] ?? false ) {
                    if ( is_array( $value ) ) {
                        $product[ $cfg['field'] ] = $value;
                        foreach ( $value as $item ) {
                            if ( $item ) {
                                $checkbox_options[ $key ][ $item ] = ( $checkbox_options[ $key ][ $item ] ?? 0 ) + 1;
                            }
                        }
                    } else {
                        $product[ $cfg['field'] ] = $value ? [ $value ] : [];
                        if ( $value ) {
                            $checkbox_options[ $key ][ $value ] = ( $checkbox_options[ $key ][ $value ] ?? 0 ) + 1;
                        }
                    }
                } else {
                    $product[ $cfg['field'] ] = $value ?: null;
                    if ( $value ) {
                        $checkbox_options[ $key ][ $value ] = ( $checkbox_options[ $key ][ $value ] ?? 0 ) + 1;
                    }
                }
            }
        }

        // Extract tristate filter values from specs.
        foreach ( $tristate_config as $key => $cfg ) {
            $value = null;

            // Check if this is derived from another field (e.g., has_suspension from suspension_type).
            if ( isset( $cfg['derive_from'] ) ) {
                $derived_value = erh_get_spec_value( $specs, [ $cfg['derive_from'], 'suspension.type', 'suspension_type' ] );
                // True if has a value and it's not "None" or empty.
                if ( is_string( $derived_value ) ) {
                    $value = $derived_value !== '' && strtolower( $derived_value ) !== 'none';
                } else {
                    $value = ! empty( $derived_value );
                }
            } elseif ( isset( $cfg['derive_from_array'] ) && $cfg['derive_from_array'] ) {
                // Derive boolean from array field (e.g., has_lights from lights array).
                // True if array contains values other than "None".
                $raw_value = erh_get_spec_value( $specs, $cfg['spec_paths'] );
                if ( is_array( $raw_value ) ) {
                    // Filter out "None" and empty values.
                    $valid_values = array_filter( $raw_value, function( $v ) {
                        return $v && strtolower( $v ) !== 'none';
                    });
                    $value = ! empty( $valid_values );
                } else {
                    $value = $raw_value && strtolower( (string) $raw_value ) !== 'none';
                }
            } elseif ( $cfg['spec_paths'] ) {
                $raw_value = erh_get_spec_value( $specs, $cfg['spec_paths'] );
                // Convert to boolean.
                $value = erh_to_boolean( $raw_value );
            }

            $product[ $cfg['field'] ] = $value;

            // Track counts.
            if ( $value === true ) {
                $tristate_counts[ $key ]['yes']++;
            } elseif ( $value === false ) {
                $tristate_counts[ $key ]['no']++;
            }
        }
    }
    unset( $product ); // Break reference.

    // Set defaults for max/min values.
    foreach ( $range_config as $key => $cfg ) {
        if ( $filter_max[ $key ] === 0 ) {
            $filter_max[ $key ] = $cfg['default_max'];
        }

        // Set min to default if no data was found (still PHP_INT_MAX) or if not tracking.
        if ( $filter_min[ $key ] === null || $filter_min[ $key ] === PHP_INT_MAX ) {
            $filter_min[ $key ] = $cfg['default_min'] ?? 0;
        }
    }

    // Calculate distributions (before rounding for accuracy).
    $filter_dist = [];
    foreach ( $range_config as $key => $cfg ) {
        // Use tracked min for distribution (or default_min if not tracking).
        $min_val = $filter_min[ $key ];
        $filter_dist[ $key ] = erh_calc_distribution( $products, $cfg['field'], $filter_max[ $key ], $min_val );
    }

    // Round max values up to nice numbers (ceil).
    // Round min values down to nice numbers (floor).
    foreach ( $range_config as $key => $cfg ) {
        $filter_max[ $key ] = ceil( $filter_max[ $key ] / $cfg['round_factor'] ) * $cfg['round_factor'];

        // Only floor-round min if using data min (otherwise keep at 0).
        if ( $cfg['use_data_min'] ?? false ) {
            $filter_min[ $key ] = floor( $filter_min[ $key ] / $cfg['round_factor'] ) * $cfg['round_factor'];
        }
    }

    // Sort checkbox options by count (descending).
    foreach ( $checkbox_options as $key => &$options ) {
        arsort( $options );
    }
    unset( $options );

    // Sort products by popularity.
    usort(
        $products,
        function ( $a, $b ) {
            return ( $b['popularity'] ?? 0 ) - ( $a['popularity'] ?? 0 );
        }
    );

    // Calculate preset counts for range filters with presets.
    $preset_counts = [];
    foreach ( $range_config as $key => $cfg ) {
        if ( ! empty( $cfg['presets'] ) ) {
            $preset_counts[ $key ] = erh_calc_preset_counts( $products, $key, $cfg );
        }
    }

    return [
        'products'         => $products,
        'filter_max'       => $filter_max,
        'filter_min'       => $filter_min,
        'filter_dist'      => $filter_dist,
        'checkbox_options' => $checkbox_options,
        'tristate_counts'  => $tristate_counts,
        'preset_counts'    => $preset_counts,
    ];
}

/**
 * Prepare products data for JavaScript.
 *
 * @param array $products Processed products.
 * @return array Simplified products for JS.
 */
function erh_prepare_js_products( array $products ): array {
    $range_config    = erh_get_range_filter_config();
    $checkbox_config = erh_get_checkbox_filter_config();
    $tristate_config = erh_get_tristate_filter_config();

    $js_products = [];

    foreach ( $products as $product ) {
        $item = [
            'id'              => $product['id'],
            'name'            => $product['name'],
            'url'             => $product['url'],
            'thumbnail'       => $product['thumbnail'] ?: get_template_directory_uri() . '/assets/images/placeholder-product.png',
            'brand'           => $product['brand'],
            'price'           => $product['current_price'],
            'in_stock'        => $product['in_stock'],
            'price_indicator' => $product['price_indicator'],
            'rating'          => $product['rating'] ?? null,
            'popularity'      => $product['popularity'] ?? 0,
            // Include full pricing object for client-side geo-aware extraction.
            'pricing'         => $product['pricing'] ?? [],
        ];

        // Add all range filter fields.
        foreach ( $range_config as $key => $cfg ) {
            $item[ $cfg['field'] ] = $product[ $cfg['field'] ] ?? null;

            // For contains mode filters, also add the min/max fields.
            if ( isset( $cfg['field_min'] ) ) {
                $item[ $cfg['field_min'] ] = $product[ $cfg['field_min'] ] ?? null;
            }
            if ( isset( $cfg['field_max'] ) ) {
                $item[ $cfg['field_max'] ] = $product[ $cfg['field_max'] ] ?? null;
            }
        }

        // Add all checkbox filter fields.
        foreach ( $checkbox_config as $key => $cfg ) {
            $item[ $cfg['field'] ] = $product[ $cfg['field'] ] ?? null;
        }

        // Add all tristate filter fields.
        foreach ( $tristate_config as $key => $cfg ) {
            $item[ $cfg['field'] ] = $product[ $cfg['field'] ] ?? null;
        }

        $js_products[] = $item;
    }

    return $js_products;
}

/**
 * Generate unified filter configuration for JavaScript.
 *
 * This is the SINGLE SOURCE OF TRUTH for filter configuration.
 * JS reads this config instead of maintaining a duplicate.
 *
 * @return array Configuration object for JavaScript.
 */
function erh_get_js_filter_config(): array {
    $range_config    = erh_get_range_filter_config();
    $checkbox_config = erh_get_checkbox_filter_config();
    $tristate_config = erh_get_tristate_filter_config();

    // Build set filters (checkboxes).
    $sets = [];
    foreach ( $checkbox_config as $key => $cfg ) {
        // Use plural key for JS (brand → brands, etc.).
        // Don't add 's' if key already ends with 's' (e.g., features).
        $js_key = str_ends_with( $key, 's' ) ? $key : $key . 's';

        $sets[ $js_key ] = [
            'selector'   => $cfg['field'],
            'productKey' => $cfg['field'],
            'label'      => $cfg['label'],
            'isArray'    => $cfg['is_array'] ?? false,
        ];
    }

    // Build range filters.
    $ranges = [];
    foreach ( $range_config as $key => $cfg ) {
        $range_item = [
            'unit'       => $cfg['unit'],
            'prefix'     => $cfg['prefix'],
            'suffix'     => $cfg['suffix'],
            'productKey' => $cfg['field'],
            'label'      => $cfg['label'],
        ];

        // Add filter mode if not default (range).
        if ( isset( $cfg['filter_mode'] ) && $cfg['filter_mode'] !== 'range' ) {
            $range_item['filterMode'] = $cfg['filter_mode'];
            // For contains mode, include the product's min/max field names.
            if ( $cfg['filter_mode'] === 'contains' ) {
                $range_item['productKeyMin'] = $cfg['field_min'] ?? $cfg['field'];
                $range_item['productKeyMax'] = $cfg['field_max'] ?? $cfg['field'];
            }
        }

        // Add presets if defined.
        if ( ! empty( $cfg['presets'] ) ) {
            $range_item['presets'] = $cfg['presets'];
        }

        $ranges[ $key ] = $range_item;
    }

    // Build tristate filters.
    $tristates = [];
    foreach ( $tristate_config as $key => $cfg ) {
        $tristates[ $key ] = [
            'productKey' => $cfg['field'],
            'label'      => $cfg['label'],
        ];
    }

    // Build boolean filters (hardcoded for now as there's only one).
    $booleans = [
        'in_stock' => [
            'selector'   => 'in_stock',
            'productKey' => 'in_stock',
            'label'      => 'In stock only',
        ],
    ];

    // Build sort configuration.
    $sort = [
        'popularity' => [
            'key'       => 'popularity',
            'dir'       => 'desc',
            'nullValue' => 0,
        ],
        'price-asc' => [
            'key'       => 'price',
            'dir'       => 'asc',
            'nullValue' => 'Infinity',
        ],
        'price-desc' => [
            'key'       => 'price',
            'dir'       => 'desc',
            'nullValue' => 0,
        ],
        'deals' => [
            'key'       => 'price_indicator',
            'dir'       => 'asc',
            'nullValue' => 'Infinity',
        ],
        'name' => [
            'key'       => 'name',
            'dir'       => 'asc',
            'nullValue' => '',
            'isString'  => true,
        ],
    ];

    // Spec display config: maps filter keys to product keys with formatting.
    // Used by product cards to show relevant specs based on active filters.
    // Format: 'round' = decimal places (0 = Math.round), 'prefix', 'suffix'.
    $spec_display = [
        // Performance (claimed).
        'speed'           => [ 'key' => 'top_speed',       'priority' => 1,  'round' => 0, 'suffix' => ' MPH' ],
        'range'           => [ 'key' => 'range',           'priority' => 2,  'round' => 0, 'suffix' => ' mi range' ],
        'max_incline'     => [ 'key' => 'max_incline',     'priority' => 10, 'round' => 0, 'suffix' => '° incline' ],

        // Tested performance.
        'tested_speed'    => [ 'key' => 'tested_speed',    'priority' => 1,  'round' => 1, 'suffix' => ' MPH tested' ],
        'tested_range'    => [ 'key' => 'tested_range',    'priority' => 2,  'round' => 1, 'suffix' => ' mi tested' ],
        'accel_0_15'      => [ 'key' => 'accel_0_15',      'priority' => 5,  'round' => 2, 'suffix' => 's 0-15 mph' ],
        'accel_0_20'      => [ 'key' => 'accel_0_20',      'priority' => 6,  'round' => 2, 'suffix' => 's 0-20 mph' ],
        'brake_distance'  => [ 'key' => 'brake_distance',  'priority' => 7,  'round' => 1, 'suffix' => ' ft braking' ],
        'hill_climb'      => [ 'key' => 'hill_climb',      'priority' => 8,  'round' => 1, 'suffix' => '° hill climb' ],

        // Battery.
        'battery'         => [ 'key' => 'battery',         'priority' => 3,  'round' => 0, 'suffix' => ' Wh battery' ],
        'voltage'         => [ 'key' => 'voltage',         'priority' => 12, 'round' => 0, 'suffix' => 'V' ],
        'amphours'        => [ 'key' => 'amphours',        'priority' => 13, 'round' => 1, 'suffix' => ' Ah' ],
        'charging_time'   => [ 'key' => 'charging_time',   'priority' => 14, 'round' => 1, 'suffix' => ' hr charge' ],

        // Motor.
        'motor_power'     => [ 'key' => 'motor_power',     'priority' => 4,  'round' => 0, 'suffix' => 'W motor' ],
        'motor_peak'      => [ 'key' => 'motor_peak',      'priority' => 11, 'round' => 0, 'suffix' => 'W peak' ],

        // Portability.
        'weight'          => [ 'key' => 'weight',          'priority' => 5,  'round' => 0, 'suffix' => ' lbs' ],

        // Rider fit.
        'weight_limit'    => [ 'key' => 'weight_limit',    'priority' => 9,  'round' => 0, 'suffix' => ' lbs max load' ],
        'deck_width'      => [ 'key' => 'deck_width',      'priority' => 15, 'round' => 1, 'suffix' => '" deck width' ],
        'deck_length'     => [ 'key' => 'deck_length',     'priority' => 16, 'round' => 1, 'suffix' => '" deck length' ],
        'handlebar_width' => [ 'key' => 'handlebar_width', 'priority' => 17, 'round' => 1, 'suffix' => '" handlebar' ],
        'ground_clearance'=> [ 'key' => 'ground_clearance','priority' => 23, 'round' => 1, 'suffix' => '" clearance' ],

        // Tires.
        'tire_size'       => [ 'key' => 'tire_size',       'priority' => 18, 'suffix' => '" tires' ],
        'tire_width'      => [ 'key' => 'tire_width',      'priority' => 19, 'suffix' => '" wide' ],
        'tires'           => [ 'key' => 'tire_type',       'priority' => 20, 'raw' => true ],

        // Suspension & Brakes.
        'suspension'      => [ 'key' => 'suspension_type', 'priority' => 21, 'raw' => true, 'join' => ', ' ],
        'brakes'          => [ 'key' => 'brake_type',      'priority' => 22, 'raw' => true ],

        // Terrain & Durability.
        'terrain'         => [ 'key' => 'terrain',         'priority' => 24, 'raw' => true ],
        'ip_rating'       => [ 'key' => 'ip_rating',       'priority' => 25, 'raw' => true ],

        // Controls.
        'throttle_type'   => [ 'key' => 'throttle_type',   'priority' => 26, 'suffix' => ' throttle' ],

        // Model info.
        'release_year'    => [ 'key' => 'release_year',    'priority' => 27, 'round' => 0, 'suffix' => ' model' ],

        // Value metrics.
        'price_per_lb'    => [ 'key' => 'price_per_lb',    'priority' => 30, 'round' => 2, 'prefix' => '$', 'suffix' => '/lb' ],
        'price_per_mph'   => [ 'key' => 'price_per_mph',   'priority' => 31, 'round' => 0, 'prefix' => '$', 'suffix' => '/mph' ],
        'price_per_mile'  => [ 'key' => 'price_per_mile',  'priority' => 32, 'round' => 0, 'prefix' => '$', 'suffix' => '/mi' ],
        'price_per_wh'    => [ 'key' => 'price_per_wh',    'priority' => 33, 'round' => 2, 'prefix' => '$', 'suffix' => '/Wh' ],
        'speed_per_lb'    => [ 'key' => 'speed_per_lb',    'priority' => 34, 'round' => 2, 'suffix' => ' mph/lb' ],
        'range_per_lb'    => [ 'key' => 'range_per_lb',    'priority' => 35, 'round' => 2, 'suffix' => ' mi/lb' ],
    ];

    // Default specs to show when no relevant filters are active.
    // Order: MPH, Wh, motor W, weight, max load, voltage, tire type, suspension, brakes.
    $default_spec_keys = [ 'speed', 'battery', 'motor_power', 'weight', 'weight_limit', 'voltage', 'tires', 'suspension', 'brakes' ];

    return [
        'sets'            => $sets,
        'ranges'          => $ranges,
        'tristates'       => $tristates,
        'booleans'        => $booleans,
        'sort'            => $sort,
        'specDisplay'     => $spec_display,
        'defaultSpecKeys' => $default_spec_keys,
    ];
}

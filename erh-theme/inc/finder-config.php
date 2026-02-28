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
            'title'            => 'Electric Scooter Finder',
            'subtitle'         => 'Browse our database of %d electric scooters. Filter by specs, find deals, and discover your next ride.',
            'short'            => 'E-Scooter',
            'product_type'     => 'Electric Scooter',
            'meta_title'       => 'Electric Scooter Finder – Browse %d+ in Our Database | ERideHero',
            'meta_description' => 'Browse %d+ electric scooters in our database. Filter by speed, range, weight, price, and more. Real specs, real prices, updated daily.',
        ],
        'ebike' => [
            'title'            => 'Electric Bike Finder',
            'subtitle'         => 'Browse our database of %d electric bikes. Filter by specs, find deals, and discover your next ride.',
            'short'            => 'E-Bike',
            'product_type'     => 'Electric Bike',
            'meta_title'       => 'Electric Bike Finder – Browse %d+ in Our Database | ERideHero',
            'meta_description' => 'Browse %d+ electric bikes in our database. Filter by motor, torque, range, price, and more. Real specs, real prices, updated daily.',
        ],
        'eskate' => [
            'title'            => 'Electric Skateboard Finder',
            'subtitle'         => 'Browse our database of %d electric skateboards. Filter by specs, find deals, and discover your next ride.',
            'short'            => 'E-Skateboard',
            'product_type'     => 'Electric Skateboard',
            'meta_title'       => 'Electric Skateboard Finder – Browse %d+ in Our Database | ERideHero',
            'meta_description' => 'Browse %d+ electric skateboards in our database. Filter by speed, range, weight, price, and more. Real specs, real prices, updated daily.',
        ],
        'euc' => [
            'title'            => 'Electric Unicycle Finder',
            'subtitle'         => 'Browse our database of %d electric unicycles. Filter by specs, find deals, and discover your next ride.',
            'short'            => 'EUC',
            'product_type'     => 'Electric Unicycle',
            'meta_title'       => 'Electric Unicycle Finder – Browse %d+ in Our Database | ERideHero',
            'meta_description' => 'Browse %d+ electric unicycles in our database. Filter by speed, range, weight, price, and more. Real specs, real prices, updated daily.',
        ],
        'hoverboard' => [
            'title'            => 'Hoverboard Finder',
            'subtitle'         => 'Browse our database of %d hoverboards. Filter by specs, find deals, and discover your next ride.',
            'short'            => 'Hoverboard',
            'product_type'     => 'Hoverboard',
            'meta_title'       => 'Hoverboard Finder – Browse %d+ in Our Database | ERideHero',
            'meta_description' => 'Browse %d+ hoverboards in our database. Filter by speed, range, weight, price, and more. Real specs, real prices, updated daily.',
        ],
    ];
}

/**
 * Slug mappings for page slugs to JSON file types.
 *
 * Single source of truth: CategoryConfig::get_finder_slug_map()
 */
function erh_get_finder_slug_map(): array {
    return \ERH\CategoryConfig::get_finder_slug_map();
}

/**
 * Slug mappings for product type slugs to JSON file types.
 */
function erh_get_finder_type_map(): array {
    return [
        'e-scooters'    => 'escooter',
        'e-bikes'       => 'ebike',
        'e-skateboards' => 'eskate',
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
 *
 * @param string $product_type Product type for type-specific config (e.g., price presets).
 * @return array Range filter configuration.
 */
function erh_get_range_filter_config( string $product_type = 'escooter' ): array {
    // Product-type specific price presets.
    $price_presets = [
        'escooter' => [
            [ 'label' => 'Under $500', 'min' => 1, 'max' => 500 ],
            [ 'label' => '$500–$1,000', 'min' => 500, 'max' => 1000 ],
            [ 'label' => '$1,000–$2,000', 'min' => 1000, 'max' => 2000 ],
            [ 'label' => '$2,000+', 'min' => 2000 ],
        ],
        'ebike' => [
            [ 'label' => 'Under $1,000', 'min' => 1, 'max' => 1000 ],
            [ 'label' => '$1,000–$2,000', 'min' => 1000, 'max' => 2000 ],
            [ 'label' => '$2,000–$3,000', 'min' => 2000, 'max' => 3000 ],
            [ 'label' => '$3,000+', 'min' => 3000 ],
        ],
        'hoverboard' => [
            [ 'label' => 'Under $100', 'min' => 1, 'max' => 100 ],
            [ 'label' => '$100–$200', 'min' => 100, 'max' => 200 ],
            [ 'label' => '$200–$300', 'min' => 200, 'max' => 300 ],
            [ 'label' => '$300+', 'min' => 300 ],
        ],
        'euc' => [
            [ 'label' => 'Under $1,000', 'min' => 1, 'max' => 1000 ],
            [ 'label' => '$1,000–$2,000', 'min' => 1000, 'max' => 2000 ],
            [ 'label' => '$2,000–$3,000', 'min' => 2000, 'max' => 3000 ],
            [ 'label' => '$3,000+', 'min' => 3000 ],
        ],
    ];

    return [
        'price' => [
            'label'        => 'Price',
            'field'        => 'current_price',
            'spec_paths'   => null, // Price comes from pricing data, not specs.
            'unit'         => '',
            'prefix'       => '$',
            'suffix'       => '',
            'default_max'  => $product_type === 'ebike' ? 10000 : 5000,
            'round_factor' => 100,
            'presets'      => $price_presets[ $product_type ] ?? $price_presets['escooter'],
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
            'spec_paths'   => [
                'manufacturer_top_speed',                // E-scooter.
                'speed_and_class.top_assist_speed',      // E-bike (top assist speed).
            ],
            'unit'         => 'mph',
            'prefix'       => '',
            'suffix'       => 'mph',
            'default_max'  => 50,
            'round_factor' => 5,
            'use_data_min' => true,
        ],
        'range' => [
            'label'        => 'Range (claimed)',
            'field'        => 'range',
            'spec_paths'   => [
                'manufacturer_range',   // E-scooter.
                'battery.range',        // E-bike.
            ],
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
            'spec_paths'   => [
                'dimensions.weight',           // E-scooter.
                'weight_and_capacity.weight',  // E-bike.
            ],
            'unit'         => 'lbs',
            'prefix'       => '',
            'suffix'       => 'lbs',
            'default_max'  => 150,
            'round_factor' => 10,
            'use_data_min' => true,
        ],
        'weight_limit' => [
            'label'        => 'Max Load',
            'field'        => 'weight_limit',
            'spec_paths'   => [
                'dimensions.max_load',              // E-scooter.
                'weight_and_capacity.weight_limit', // E-bike.
            ],
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
            'spec_paths'   => [
                'battery.capacity',          // E-scooter.
                'battery.battery_capacity',  // E-bike.
            ],
            'unit'         => 'Wh',
            'prefix'       => '',
            'suffix'       => 'Wh',
            'default_max'  => 2000,
            'round_factor' => 100,
            'use_data_min' => true,
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
            'spec_paths'   => [
                'battery.charging_time',  // E-scooter.
                'battery.charge_time',    // E-bike.
            ],
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
            'spec_paths'   => [ 'dimensions.deck_width', 'deck.width' ],
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
            'spec_paths'   => [ 'dimensions.deck_length', 'deck.length' ],
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
        // Tires / Wheels
        // =================================================================
        'tire_size' => [
            'label'        => 'Tire/Wheel Size',
            'field'        => 'tire_size',
            'spec_paths'   => [
                'wheels.tire_size_front',        // E-scooter (front wheel).
                'wheels_and_tires.wheel_size',   // E-bike.
                'wheel.tire_size',               // EUC.
                'wheels.wheel_size',             // E-skateboard (mm).
            ],
            'unit'         => 'in',
            'prefix'       => '',
            'suffix'       => '"',
            'default_max'  => 29,  // Increased for 27.5" and 29" e-bike wheels.
            'round_factor' => 1,
            'use_data_min' => true,
        ],
        'tire_width' => [
            'label'        => 'Tire Width',
            'field'        => 'tire_width',
            'spec_paths'   => [
                'wheels.tire_width',             // E-scooter.
                'wheels_and_tires.tire_width',   // E-bike.
                'wheel.tire_width',              // EUC.
                'wheels.wheel_width',            // E-skateboard (mm).
            ],
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

        // =================================================================
        // E-Bike Specific Range Filters
        // =================================================================
        'torque' => [
            'label'        => 'Torque',
            'field'        => 'torque',
            'spec_paths'   => [ 'motor.torque' ],
            'unit'         => 'Nm',
            'prefix'       => '',
            'suffix'       => 'Nm',
            'default_max'  => 120,
            'round_factor' => 10,
            'use_data_min' => true,
        ],
        'wheel_size' => [
            'label'        => 'Wheel Size',
            'field'        => 'wheel_size',
            'spec_paths'   => [
                'wheels_and_tires.wheel_size',  // E-bike.
                'wheels.wheel_size',            // Hoverboard.
            ],
            'unit'         => 'in',
            'prefix'       => '',
            'suffix'       => '"',
            'default_max'  => 29,
            'round_factor' => 1,
            'use_data_min' => true,
        ],
        'front_travel' => [
            'label'        => 'Front Travel',
            'field'        => 'front_travel',
            'spec_paths'   => [ 'suspension.front_travel' ],
            'unit'         => 'mm',
            'prefix'       => '',
            'suffix'       => 'mm',
            'default_max'  => 200,
            'round_factor' => 10,
            'use_data_min' => true,
        ],
        'rear_travel' => [
            'label'        => 'Rear Travel',
            'field'        => 'rear_travel',
            'spec_paths'   => [ 'suspension.rear_travel' ],
            'unit'         => 'mm',
            'prefix'       => '',
            'suffix'       => 'mm',
            'default_max'  => 200,
            'round_factor' => 10,
            'use_data_min' => true,
        ],
        'gears' => [
            'label'        => 'Gears',
            'field'        => 'gears',
            'spec_paths'   => [ 'drivetrain.gears' ],
            'unit'         => '',
            'prefix'       => '',
            'suffix'       => '-speed',
            'default_max'  => 12,
            'round_factor' => 1,
            'use_data_min' => true,
        ],
        'top_assist_speed' => [
            'label'        => 'Top Assist Speed',
            'field'        => 'top_assist_speed',
            'spec_paths'   => [ 'speed_and_class.top_assist_speed' ],
            'unit'         => 'mph',
            'prefix'       => '',
            'suffix'       => 'mph',
            'default_max'  => 32,
            'round_factor' => 4,
            'use_data_min' => true,
        ],
        'rotor_size' => [
            'label'        => 'Rotor Size',
            'field'        => 'rotor_size',
            'spec_paths'   => [ 'brakes.rotor_size_front' ],
            'unit'         => 'mm',
            'prefix'       => '',
            'suffix'       => 'mm',
            'default_max'  => 220,
            'round_factor' => 20,
            'use_data_min' => true,
        ],
        'assist_levels' => [
            'label'        => 'Assist Levels',
            'field'        => 'assist_levels',
            'spec_paths'   => [ 'motor.assist_levels' ],
            'unit'         => '',
            'prefix'       => '',
            'suffix'       => ' levels',
            'default_max'  => 10,
            'round_factor' => 1,
            'use_data_min' => true,
        ],
        'rack_capacity' => [
            'label'        => 'Rack Capacity',
            'field'        => 'rack_capacity',
            'spec_paths'   => [ 'weight_and_capacity.rack_capacity' ],
            'unit'         => 'lbs',
            'prefix'       => '',
            'suffix'       => 'lbs',
            'default_max'  => 100,
            'round_factor' => 10,
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
            'spec_paths'    => [
                'brakes.front',       // E-scooter.
                'brakes.brake_type',  // E-bike.
            ],
            'visible_limit' => 10,
            'searchable'    => false,
        ],
        'tire_type' => [
            'label'         => 'Tire type',
            'field'         => 'tire_type',
            'spec_paths'    => [
                'wheels.tire_type',           // E-scooter.
                'wheels_and_tires.tire_type', // E-bike.
                'wheel.tire_type',            // EUC.
            ],
            'visible_limit' => 10,
            'searchable'    => false,
        ],
        'suspension_type' => [
            'label'         => 'Type',
            'field'         => 'suspension_type',
            'spec_paths'    => [
                'suspension.type',            // E-scooter.
                'suspension.suspension_type',  // EUC.
            ],
            'visible_limit' => 10,
            'searchable'    => false,
            'is_array'      => true, // Values can be arrays (checkbox field).
        ],
        'terrain' => [
            'label'         => 'Terrain',
            'field'         => 'terrain',
            'spec_paths'    => [ 'other.terrain', 'wheels.terrain' ],
            'visible_limit' => 10,
            'searchable'    => false,
        ],
        'ip_rating' => [
            'label'         => 'IP Rating',
            'field'         => 'ip_rating',
            'spec_paths'    => [
                'other.ip_rating',                   // E-scooter.
                'safety_and_compliance.ip_rating',   // E-bike.
                'safety.ip_rating',                  // Hoverboard.
            ],
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
            'spec_paths'    => [
                'features',          // E-scooter.
                'special_features',  // E-bike.
            ],
            'visible_limit' => 8,
            'searchable'    => true,
            'is_array'      => true, // Values are arrays (checkbox field).
        ],

        // =================================================================
        // E-Bike Specific Checkbox Filters
        // =================================================================
        'ebike_class' => [
            'label'         => 'E-Bike Class',
            'field'         => 'ebike_class',
            'spec_paths'    => [ 'speed_and_class.class' ],
            'visible_limit' => 5,
            'searchable'    => false,
            'is_array'      => true,
        ],
        'motor_brand' => [
            'label'         => 'Motor Brand',
            'field'         => 'motor_brand',
            'spec_paths'    => [ 'motor.motor_brand' ],
            'visible_limit' => 10,
            'searchable'    => true,
        ],
        'motor_type' => [
            'label'         => 'Motor Type',
            'field'         => 'motor_type',
            'spec_paths'    => [
                'motor.motor_type',      // E-bike (Mid-drive, Hub, etc.).
                'motor.motor_position',  // E-scooter (fallback).
            ],
            'visible_limit' => 10,
            'searchable'    => false,
        ],
        'sensor_type' => [
            'label'         => 'Sensor Type',
            'field'         => 'sensor_type',
            'spec_paths'    => [ 'motor.sensor_type' ],
            'visible_limit' => 5,
            'searchable'    => false,
        ],
        'frame_style' => [
            'label'         => 'Frame Style',
            'field'         => 'frame_style',
            'spec_paths'    => [ 'frame_and_geometry.frame_style' ],
            'visible_limit' => 5,
            'searchable'    => false,
            'is_array'      => true,
        ],
        'frame_material' => [
            'label'         => 'Frame Material',
            'field'         => 'frame_material',
            'spec_paths'    => [ 'frame_and_geometry.frame_material' ],
            'visible_limit' => 5,
            'searchable'    => false,
            'is_array'      => true,
        ],
        'battery_position' => [
            'label'         => 'Battery Position',
            'field'         => 'battery_position',
            'spec_paths'    => [ 'battery.battery_position' ],
            'visible_limit' => 5,
            'searchable'    => false,
        ],
        'drive_system' => [
            'label'         => 'Drive System',
            'field'         => 'drive_system',
            'spec_paths'    => [ 'drivetrain.drive_system' ],
            'visible_limit' => 5,
            'searchable'    => false,
        ],
        'front_suspension' => [
            'label'         => 'Front Suspension',
            'field'         => 'front_suspension',
            'spec_paths'    => [ 'suspension.front_suspension' ],
            'visible_limit' => 5,
            'searchable'    => false,
        ],
        'rear_suspension' => [
            'label'         => 'Rear Suspension',
            'field'         => 'rear_suspension',
            'spec_paths'    => [ 'suspension.rear_suspension' ],
            'visible_limit' => 5,
            'searchable'    => false,
        ],
        'display' => [
            'label'         => 'Display Type',
            'field'         => 'display',
            'spec_paths'    => [
                'components.display',  // E-bike.
                'other.display_type',  // E-scooter.
            ],
            'visible_limit' => 5,
            'searchable'    => false,
        ],
        'connectivity' => [
            'label'         => 'Connectivity',
            'field'         => 'connectivity',
            'spec_paths'    => [ 'components.connectivity' ],
            'visible_limit' => 5,
            'searchable'    => false,
            'is_array'      => true,
        ],
        'sizes_available' => [
            'label'         => 'Sizes Available',
            'field'         => 'sizes_available',
            'spec_paths'    => [ 'frame_and_geometry.sizes_available' ],
            'visible_limit' => 6,
            'searchable'    => false,
            'is_array'      => true,
        ],
        'certifications' => [
            'label'         => 'Certifications',
            'field'         => 'certifications',
            'spec_paths'    => [ 'safety_and_compliance.certifications' ],
            'visible_limit' => 5,
            'searchable'    => false,
            'is_array'      => true,
        ],
        'category' => [
            'label'         => 'Category',
            'field'         => 'category',
            'spec_paths'    => [ 'category' ],
            'visible_limit' => 6,
            'searchable'    => true,
            'is_array'      => true,
        ],
        'brake_brand' => [
            'label'         => 'Brake Brand',
            'field'         => 'brake_brand',
            'spec_paths'    => [ 'brakes.brake_brand' ],
            'visible_limit' => 8,
            'searchable'    => true,
        ],
        'tire_brand' => [
            'label'         => 'Tire Brand',
            'field'         => 'tire_brand',
            'spec_paths'    => [ 'wheels_and_tires.tire_brand' ],
            'visible_limit' => 8,
            'searchable'    => true,
        ],

        // =================================================================
        // EUC Specific Checkbox Filters
        // =================================================================
        'battery_brand' => [
            'label'         => 'Battery Brand',
            'field'         => 'battery_brand',
            'spec_paths'    => [ 'battery.battery_brand' ],
            'visible_limit' => 10,
            'searchable'    => false,
        ],

        // =================================================================
        // Hoverboard Specific Checkbox Filters
        // =================================================================
        'wheel_type' => [
            'label'         => 'Wheel Type',
            'field'         => 'wheel_type',
            'spec_paths'    => [ 'wheels.tire_type', 'wheel_type' ],
            'visible_limit' => 5,
            'searchable'    => false,
        ],

        // =================================================================
        // E-Skateboard Specific Checkbox Filters
        // =================================================================
        'drive_type' => [
            'label'         => 'Drive',
            'field'         => 'drive_type',
            'spec_paths'    => [ 'motor.drive' ],
            'visible_limit' => 5,
            'searchable'    => false,
        ],
        'wheel_material' => [
            'label'         => 'Wheel Material',
            'field'         => 'wheel_material',
            'spec_paths'    => [ 'wheels.wheel_material' ],
            'visible_limit' => 5,
            'searchable'    => false,
        ],
        'concave' => [
            'label'         => 'Deck Concave',
            'field'         => 'concave',
            'spec_paths'    => [ 'deck.concave' ],
            'visible_limit' => 5,
            'searchable'    => false,
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
            'spec_paths' => [
                'wheels.self_healing', // E-scooter.
                'wheel.self_healing',  // EUC.
            ],
        ],
        'suspension_adjustable' => [
            'label'      => 'Adjustable suspension',
            'field'      => 'suspension_adjustable',
            'spec_paths' => [
                'suspension.adjustable',             // E-scooter.
                'suspension.adjustable_suspension',  // EUC.
            ],
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
            'spec_paths' => [
                'lighting.lights',                      // E-scooter (array field).
                'integrated_features.integrated_lights', // E-bike (boolean).
            ],
            'derive_from_array' => true, // Special handling: true if array contains non-"None" values or boolean true.
        ],

        // =================================================================
        // E-Bike Specific Tristate Filters
        // =================================================================
        'has_throttle' => [
            'label'      => 'Has throttle',
            'field'      => 'has_throttle',
            'spec_paths' => [ 'speed_and_class.throttle' ],
        ],
        'removable_battery' => [
            'label'      => 'Removable battery',
            'field'      => 'removable_battery',
            'spec_paths' => [ 'battery.removable' ],
        ],
        'puncture_protection' => [
            'label'      => 'Puncture protection',
            'field'      => 'puncture_protection',
            'spec_paths' => [ 'wheels_and_tires.puncture_protection' ],
        ],
        'seatpost_suspension' => [
            'label'      => 'Seatpost suspension',
            'field'      => 'seatpost_suspension',
            'spec_paths' => [ 'suspension.seatpost_suspension' ],
        ],
        'app_compatible' => [
            'label'      => 'App compatible',
            'field'      => 'app_compatible',
            'spec_paths' => [ 'components.app_compatible' ],
        ],
        'has_fenders' => [
            'label'      => 'Has fenders',
            'field'      => 'has_fenders',
            'spec_paths' => [ 'integrated_features.fenders' ],
        ],
        'has_rear_rack' => [
            'label'      => 'Has rear rack',
            'field'      => 'has_rear_rack',
            'spec_paths' => [ 'integrated_features.rear_rack' ],
        ],
        'has_front_rack' => [
            'label'      => 'Has front rack',
            'field'      => 'has_front_rack',
            'spec_paths' => [ 'integrated_features.front_rack' ],
        ],
        'has_kickstand' => [
            'label'      => 'Has kickstand',
            'field'      => 'has_kickstand',
            'spec_paths' => [
                'integrated_features.kickstand',  // E-bike.
                'other.kickstand',                // E-scooter.
            ],
        ],
        'walk_assist' => [
            'label'      => 'Walk assist',
            'field'      => 'walk_assist',
            'spec_paths' => [ 'integrated_features.walk_assist' ],
        ],

        // =================================================================
        // Hoverboard Specific Tristate Filters
        // =================================================================
        'ul_2272' => [
            'label'      => 'UL 2272 Certified',
            'field'      => 'ul_2272',
            'spec_paths' => [ 'safety.ul_2272', 'ul_certified' ],
        ],
        'bluetooth_speaker' => [
            'label'      => 'Bluetooth Speaker',
            'field'      => 'bluetooth_speaker',
            'spec_paths' => [ 'connectivity.bluetooth_speaker' ],
        ],
        'app_enabled' => [
            'label'      => 'App Enabled',
            'field'      => 'app_enabled',
            'spec_paths' => [ 'connectivity.app_enabled' ],
        ],
        'speed_modes' => [
            'label'      => 'Speed Modes',
            'field'      => 'speed_modes',
            'spec_paths' => [ 'connectivity.speed_modes' ],
        ],

        // =================================================================
        // EUC Specific Tristate Filters
        // =================================================================
        'hollow_motor' => [
            'label'      => 'Hollow motor',
            'field'      => 'hollow_motor',
            'spec_paths' => [ 'motor.hollow_motor' ],
        ],
        'dual_charging' => [
            'label'      => 'Dual charging',
            'field'      => 'dual_charging',
            'spec_paths' => [ 'battery.dual_charging' ],
        ],
        'fast_charger' => [
            'label'      => 'Fast charger',
            'field'      => 'fast_charger',
            'spec_paths' => [ 'battery.fast_charger' ],
        ],
        'headlight' => [
            'label'      => 'Headlight',
            'field'      => 'headlight',
            'spec_paths' => [ 'lighting.headlight' ],
        ],
        'taillight' => [
            'label'      => 'Taillight',
            'field'      => 'taillight',
            'spec_paths' => [ 'lighting.taillight' ],
        ],
        'brake_light' => [
            'label'      => 'Brake light',
            'field'      => 'brake_light',
            'spec_paths' => [ 'lighting.brake_light' ],
        ],
        'lift_sensor' => [
            'label'      => 'Lift sensor',
            'field'      => 'lift_sensor',
            'spec_paths' => [ 'safety.lift_sensor' ],
        ],
        'spiked_pedals' => [
            'label'      => 'Spiked pedals',
            'field'      => 'spiked_pedals',
            'spec_paths' => [ 'pedals.spiked_pedals' ],
        ],
        'adjustable_pedals' => [
            'label'      => 'Adjustable pedals',
            'field'      => 'adjustable_pedals',
            'spec_paths' => [ 'pedals.adjustable_pedals' ],
        ],

        // =================================================================
        // E-Skateboard Specific Tristate Filters
        // =================================================================
        'has_suspension' => [
            'label'      => 'Has suspension',
            'field'      => 'has_suspension',
            'spec_paths' => [ 'suspension.has_suspension' ],
        ],
        'ambient_lights' => [
            'label'      => 'Ambient lights',
            'field'      => 'ambient_lights',
            'spec_paths' => [ 'lighting.ambient_lights' ],
        ],
    ];
}

/**
 * Filter group configuration.
 *
 * Defines the order and structure of filter groups in the sidebar.
 * The 'quick' group contains subgroups and has no main heading.
 *
 * Product-type-aware: returns different filter groups based on product type.
 * Groups with filters that don't apply to a product type auto-hide via erh_group_has_content().
 *
 * Each product type's config lives in inc/finder/filter-groups-{type}.php.
 *
 * @param string $product_type Product type key (escooter, ebike, etc.). Default 'escooter'.
 * @return array Filter group configuration.
 */
function erh_get_filter_group_config( string $product_type = 'escooter' ): array {
    $file = get_template_directory() . "/inc/finder/filter-groups-{$product_type}.php";
    if ( file_exists( $file ) ) {
        return include $file;
    }
    return include get_template_directory() . '/inc/finder/filter-groups-default.php';
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
 * @param array  $products     Raw products from JSON.
 * @param string $user_geo     User's geo for pricing.
 * @param string $product_type Product type key for type-specific config (e.g., price presets).
 * @return array Processed data with products, filter_max, filter_dist, and checkbox_options.
 */
function erh_process_finder_products( array $products, string $user_geo = 'US', string $product_type = 'escooter' ): array {
    $range_config    = erh_get_range_filter_config( $product_type );
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
        $product['best_link']        = $pricing['tracked_url'] ?? null;

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
                            if ( $item && is_string( $item ) ) {
                                $checkbox_options[ $key ][ $item ] = ( $checkbox_options[ $key ][ $item ] ?? 0 ) + 1;
                            }
                        }
                    } else {
                        $product[ $cfg['field'] ] = $value ? [ $value ] : [];
                        if ( $value && is_string( $value ) ) {
                            $checkbox_options[ $key ][ $value ] = ( $checkbox_options[ $key ][ $value ] ?? 0 ) + 1;
                        }
                    }
                } else {
                    // Handle case where spec returns array but config doesn't expect it.
                    // This can happen when e-bike and e-scooter have different data types.
                    if ( is_array( $value ) ) {
                        $product[ $cfg['field'] ] = $value;
                        foreach ( $value as $item ) {
                            if ( $item && is_string( $item ) ) {
                                $checkbox_options[ $key ][ $item ] = ( $checkbox_options[ $key ][ $item ] ?? 0 ) + 1;
                            }
                        }
                    } else {
                        $product[ $cfg['field'] ] = $value ?: null;
                        if ( $value && is_string( $value ) ) {
                            $checkbox_options[ $key ][ $value ] = ( $checkbox_options[ $key ][ $value ] ?? 0 ) + 1;
                        }
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
function erh_get_js_filter_config( string $product_type = 'escooter' ): array {
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
            'searchable' => $cfg['searchable'] ?? false,
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
        'speed-desc' => [
            'key'       => 'top_speed',
            'dir'       => 'desc',
            'nullValue' => 0,
        ],
        'range-desc' => [
            'key'       => 'battery',
            'dir'       => 'desc',
            'nullValue' => 0,
        ],
        'weight-asc' => [
            'key'       => 'weight',
            'dir'       => 'asc',
            'nullValue' => 'Infinity',
        ],
        'torque-desc' => [
            'key'       => 'torque',
            'dir'       => 'desc',
            'nullValue' => 0,
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

        // E-Bike specific.
        'torque'          => [ 'key' => 'torque',          'priority' => 4,  'round' => 0, 'suffix' => ' Nm torque' ],
        'wheel_size'      => [ 'key' => 'wheel_size',      'priority' => 18, 'round' => 1, 'suffix' => '" wheels' ],
        'front_travel'    => [ 'key' => 'front_travel',    'priority' => 21, 'round' => 0, 'suffix' => 'mm front' ],
        'rear_travel'     => [ 'key' => 'rear_travel',     'priority' => 22, 'round' => 0, 'suffix' => 'mm rear' ],
        'gears'           => [ 'key' => 'gears',           'priority' => 15, 'round' => 0, 'suffix' => '-speed' ],
        'top_assist_speed'=> [ 'key' => 'top_assist_speed','priority' => 1,  'round' => 0, 'suffix' => ' mph assist' ],
        'rotor_size'      => [ 'key' => 'rotor_size',      'priority' => 23, 'round' => 0, 'suffix' => 'mm rotors' ],
        'assist_levels'   => [ 'key' => 'assist_levels',   'priority' => 16, 'round' => 0, 'suffix' => ' assist levels' ],
        'rack_capacity'   => [ 'key' => 'rack_capacity',   'priority' => 24, 'round' => 0, 'suffix' => ' lbs rack' ],
        'ebike_class'     => [ 'key' => 'ebike_class',     'priority' => 2,  'raw' => true, 'join' => ', ' ],
        'category'        => [ 'key' => 'category',        'priority' => 3,  'raw' => true, 'join' => ', ' ],
        'motor_brand'     => [ 'key' => 'motor_brand',     'priority' => 5,  'raw' => true ],
        'motor_type'      => [ 'key' => 'motor_type',      'priority' => 6,  'raw' => true ],
        'sensor_type'     => [ 'key' => 'sensor_type',     'priority' => 7,  'raw' => true ],
        'frame_style'     => [ 'key' => 'frame_style',     'priority' => 10, 'raw' => true, 'join' => ', ' ],
        'frame_material'  => [ 'key' => 'frame_material',  'priority' => 11, 'raw' => true, 'join' => ', ' ],
        'brake_brand'     => [ 'key' => 'brake_brand',     'priority' => 23, 'raw' => true ],
        'tire_brand'      => [ 'key' => 'tire_brand',      'priority' => 19, 'raw' => true ],
    ];

    // Default specs to show when no relevant filters are active.
    // Product-type specific to match "similar products" format.
    $default_spec_keys = [
        'escooter' => [ 'speed', 'battery', 'motor_power', 'weight', 'weight_limit', 'voltage', 'tires', 'suspension', 'brakes' ],
        'ebike'      => [ 'category', 'motor_power', 'motor_type', 'torque', 'battery', 'weight', 'frame_material', 'frame_style', 'wheel_size', 'tires' ],
        'hoverboard' => [ 'speed', 'battery', 'motor_power', 'weight', 'weight_limit' ],
        'eskate' => [ 'speed', 'battery', 'motor_power', 'weight', 'deck_length', 'tire_size' ],
    ];

    // Column groups for table view modal.
    // MUST match filter groups in erh_get_filter_group_config() exactly.
    $column_groups = [
        'price' => [
            'label'   => 'Price',
            'columns' => [ 'price' ],
        ],
        'brands' => [
            'label'   => 'Brands',
            'columns' => [ 'brand' ],
        ],
        'motor' => [
            'label'   => 'Motor',
            'columns' => [ 'motor_power', 'motor_peak', 'motor_position' ],
        ],
        'battery' => [
            'label'   => 'Battery',
            'columns' => [ 'battery', 'voltage', 'amphours', 'charging_time' ],
        ],
        'claimed_specs' => [
            'label'   => 'Claimed Specs',
            'columns' => [ 'top_speed', 'range', 'max_incline' ],
        ],
        'tested' => [
            'label'   => 'Tested Performance',
            'columns' => [ 'tested_speed', 'tested_range', 'accel_0_15', 'accel_0_20', 'brake_distance', 'hill_climb' ],
        ],
        'portability' => [
            'label'   => 'Portability',
            'columns' => [ 'weight', 'foldable_handlebars' ],
        ],
        'rider_fit' => [
            'label'   => 'Rider Fit',
            'columns' => [ 'rider_height', 'weight_limit', 'deck_width', 'handlebar_width', 'ground_clearance' ],
        ],
        'brakes' => [
            'label'   => 'Brakes',
            'columns' => [ 'brake_type', 'regenerative_braking' ],
        ],
        'tires' => [
            'label'   => 'Tires',
            'columns' => [ 'tire_size', 'tire_width', 'tire_type', 'self_healing_tires' ],
        ],
        'suspension' => [
            'label'   => 'Suspension',
            'columns' => [ 'suspension_type', 'suspension_adjustable' ],
        ],
        'durability' => [
            'label'   => 'Terrain & Durability',
            'columns' => [ 'terrain', 'ip_rating' ],
        ],
        'controls' => [
            'label'   => 'Controls & Features',
            'columns' => [ 'throttle_type', 'features' ],
        ],
        'lighting' => [
            'label'   => 'Lighting',
            'columns' => [ 'has_lights', 'turn_signals' ],
        ],
        'model_info' => [
            'label'   => 'Model Info',
            'columns' => [ 'release_year' ],
        ],
        'value' => [
            'label'   => 'Value Metrics',
            'columns' => [ 'price_per_lb', 'price_per_mph', 'price_per_mile', 'price_per_wh', 'speed_per_lb', 'range_per_lb' ],
        ],

        // E-Bike specific column groups.
        'classification' => [
            'label'   => 'Classification',
            'columns' => [ 'ebike_class', 'category', 'top_assist_speed', 'has_throttle' ],
        ],
        'motor_ebike' => [
            'label'   => 'Motor & Drive',
            'columns' => [ 'motor_power', 'motor_peak', 'torque', 'assist_levels', 'motor_brand', 'motor_type', 'sensor_type' ],
        ],
        'battery_ebike' => [
            'label'   => 'Battery & Range',
            'columns' => [ 'battery', 'range', 'voltage', 'amphours', 'charging_time', 'battery_position', 'removable_battery' ],
        ],
        'frame' => [
            'label'   => 'Frame & Geometry',
            'columns' => [ 'weight', 'weight_limit', 'rack_capacity', 'frame_style', 'frame_material', 'sizes_available' ],
        ],
        'drivetrain' => [
            'label'   => 'Drivetrain',
            'columns' => [ 'gears', 'drive_system' ],
        ],
        'brakes_ebike' => [
            'label'   => 'Brakes',
            'columns' => [ 'brake_type', 'brake_brand', 'rotor_size' ],
        ],
        'suspension_ebike' => [
            'label'   => 'Suspension',
            'columns' => [ 'front_suspension', 'rear_suspension', 'front_travel', 'rear_travel', 'seatpost_suspension' ],
        ],
        'wheels_tires' => [
            'label'   => 'Wheels & Tires',
            'columns' => [ 'wheel_size', 'tire_width', 'tire_type', 'tire_brand', 'puncture_protection' ],
        ],
        'features_ebike' => [
            'label'   => 'Features & Tech',
            'columns' => [ 'display', 'connectivity', 'features', 'app_compatible', 'has_lights', 'walk_assist' ],
        ],
        'accessories' => [
            'label'   => 'Accessories',
            'columns' => [ 'has_fenders', 'has_rear_rack', 'has_front_rack', 'has_kickstand' ],
        ],
        'safety_ebike' => [
            'label'   => 'Safety & Compliance',
            'columns' => [ 'ip_rating', 'certifications' ],
        ],
    ];

    // Column configuration for table view.
    // Maps column keys to display properties.
    // filterKey: the key used to find the filter in sidebar (data-range-filter, data-filter-list, data-tristate-filter)
    // filterType: 'range', 'set', or 'tristate'
    $column_config = [
        // Core.
        'price'           => [ 'label' => 'Price',         'type' => 'currency', 'sortable' => true, 'key' => 'price',      'filterKey' => 'price',      'filterType' => 'range' ],
        'rating'          => [ 'label' => 'Rating',        'type' => 'rating',   'sortable' => true, 'key' => 'rating' ],
        'brand'           => [ 'label' => 'Brand',         'type' => 'text',     'sortable' => true, 'key' => 'brand',      'filterKey' => 'brand',      'filterType' => 'set' ],
        'release_year'    => [ 'label' => 'Year',          'type' => 'number',   'sortable' => true, 'key' => 'release_year', 'filterKey' => 'release_year', 'filterType' => 'range' ],

        // Performance (claimed).
        'top_speed'       => [ 'label' => 'Top Speed',     'suffix' => 'mph',    'sortable' => true, 'key' => 'top_speed',  'filterKey' => 'speed',      'filterType' => 'range' ],
        'range'           => [ 'label' => 'Range',         'suffix' => 'mi',     'sortable' => true, 'key' => 'range',      'filterKey' => 'range',      'filterType' => 'range' ],
        'max_incline'     => [ 'label' => 'Max Incline',   'suffix' => '°',      'sortable' => true, 'key' => 'max_incline','filterKey' => 'max_incline','filterType' => 'range' ],

        // Tested performance.
        'tested_speed'    => [ 'label' => 'Tested Speed',  'suffix' => 'mph',    'sortable' => true, 'key' => 'tested_speed',   'filterKey' => 'tested_speed',   'filterType' => 'range', 'round' => 1 ],
        'tested_range'    => [ 'label' => 'Tested Range',  'suffix' => 'mi',     'sortable' => true, 'key' => 'tested_range',   'filterKey' => 'tested_range',   'filterType' => 'range', 'round' => 1 ],
        'accel_0_15'      => [ 'label' => '0-15 mph',      'suffix' => 's',      'sortable' => true, 'key' => 'accel_0_15',     'filterKey' => 'accel_0_15',     'filterType' => 'range', 'round' => 2, 'sort_dir' => 'asc' ],
        'accel_0_20'      => [ 'label' => '0-20 mph',      'suffix' => 's',      'sortable' => true, 'key' => 'accel_0_20',     'filterKey' => 'accel_0_20',     'filterType' => 'range', 'round' => 2, 'sort_dir' => 'asc' ],
        'brake_distance'  => [ 'label' => 'Braking',       'suffix' => 'ft',     'sortable' => true, 'key' => 'brake_distance', 'filterKey' => 'brake_distance', 'filterType' => 'range', 'round' => 1, 'sort_dir' => 'asc' ],
        'hill_climb'      => [ 'label' => 'Hill Climb',    'suffix' => '°',      'sortable' => true, 'key' => 'hill_climb',     'filterKey' => 'hill_climb',     'filterType' => 'range', 'round' => 1 ],

        // Motor.
        'motor_power'     => [ 'label' => 'Motor',         'suffix' => 'W',      'sortable' => true, 'key' => 'motor_power',    'filterKey' => 'motor_power',    'filterType' => 'range' ],
        'motor_peak'      => [ 'label' => 'Peak Power',    'suffix' => 'W',      'sortable' => true, 'key' => 'motor_peak',     'filterKey' => 'motor_peak',     'filterType' => 'range' ],
        'motor_position'  => [ 'label' => 'Motor Pos.',    'type' => 'text',     'sortable' => true, 'key' => 'motor_position', 'filterKey' => 'motor_position', 'filterType' => 'set' ],

        // Battery.
        'battery'         => [ 'label' => 'Battery',       'suffix' => 'Wh',     'sortable' => true, 'key' => 'battery',       'filterKey' => 'battery',       'filterType' => 'range' ],
        'voltage'         => [ 'label' => 'Voltage',       'suffix' => 'V',      'sortable' => true, 'key' => 'voltage',       'filterKey' => 'voltage',       'filterType' => 'range' ],
        'amphours'        => [ 'label' => 'Amp Hours',     'suffix' => 'Ah',     'sortable' => true, 'key' => 'amphours',      'filterKey' => 'amphours',      'filterType' => 'range', 'round' => 1 ],
        'charging_time'   => [ 'label' => 'Charge Time',   'suffix' => 'hrs',    'sortable' => true, 'key' => 'charging_time', 'filterKey' => 'charging_time', 'filterType' => 'range', 'round' => 1, 'sort_dir' => 'asc' ],

        // Portability.
        'weight'          => [ 'label' => 'Weight',        'suffix' => 'lbs',    'sortable' => true, 'key' => 'weight',             'filterKey' => 'weight',             'filterType' => 'range', 'sort_dir' => 'asc' ],
        'foldable_handlebars' => [ 'label' => 'Foldable',  'type' => 'boolean',  'sortable' => true, 'key' => 'foldable_handlebars','filterKey' => 'foldable_handlebars','filterType' => 'tristate' ],

        // Rider fit.
        'weight_limit'    => [ 'label' => 'Max Load',      'suffix' => 'lbs',    'sortable' => true, 'key' => 'weight_limit',    'filterKey' => 'weight_limit',    'filterType' => 'range' ],
        'rider_height'    => [ 'label' => 'Rider Height',  'suffix' => '"',      'sortable' => true, 'key' => 'rider_height',    'filterKey' => 'rider_height',    'filterType' => 'range' ],
        'deck_width'      => [ 'label' => 'Deck Width',    'suffix' => '"',      'sortable' => true, 'key' => 'deck_width',      'filterKey' => 'deck_width',      'filterType' => 'range', 'round' => 1 ],
        'deck_length'     => [ 'label' => 'Deck Length',   'suffix' => '"',      'sortable' => true, 'key' => 'deck_length', 'round' => 1 ],
        'handlebar_width' => [ 'label' => 'Bar Width',     'suffix' => '"',      'sortable' => true, 'key' => 'handlebar_width', 'filterKey' => 'handlebar_width', 'filterType' => 'range', 'round' => 1 ],
        'ground_clearance'=> [ 'label' => 'Clearance',     'suffix' => '"',      'sortable' => true, 'key' => 'ground_clearance','filterKey' => 'ground_clearance','filterType' => 'range', 'round' => 1 ],

        // Wheels & Tires.
        'tire_size'       => [ 'label' => 'Tire Size',     'suffix' => '"',      'sortable' => true, 'key' => 'tire_size',        'filterKey' => 'tire_size',        'filterType' => 'range' ],
        'tire_width'      => [ 'label' => 'Tire Width',    'suffix' => '"',      'sortable' => true, 'key' => 'tire_width',       'filterKey' => 'tire_width',       'filterType' => 'range', 'round' => 1 ],
        'tire_type'       => [ 'label' => 'Tire Type',     'type' => 'text',     'sortable' => true, 'key' => 'tire_type',        'filterKey' => 'tire_type',        'filterType' => 'set' ],
        'self_healing_tires' => [ 'label' => 'Self-Healing', 'type' => 'boolean', 'sortable' => true, 'key' => 'self_healing_tires','filterKey' => 'self_healing_tires','filterType' => 'tristate' ],

        // Brakes.
        'brake_type'      => [ 'label' => 'Brake Type',    'type' => 'text',     'sortable' => true, 'key' => 'brake_type',         'filterKey' => 'brake_type',         'filterType' => 'set' ],
        'regenerative_braking' => [ 'label' => 'Regen',    'type' => 'boolean',  'sortable' => true, 'key' => 'regenerative_braking','filterKey' => 'regenerative_braking','filterType' => 'tristate' ],

        // Suspension.
        'suspension_type' => [ 'label' => 'Suspension',    'type' => 'array',    'sortable' => false, 'key' => 'suspension_type',      'filterKey' => 'suspension_type',      'filterType' => 'set' ],
        'suspension_adjustable' => [ 'label' => 'Adjustable', 'type' => 'boolean', 'sortable' => true, 'key' => 'suspension_adjustable','filterKey' => 'suspension_adjustable','filterType' => 'tristate' ],

        // Durability.
        'terrain'         => [ 'label' => 'Terrain',       'type' => 'text',     'sortable' => true, 'key' => 'terrain',   'filterKey' => 'terrain',   'filterType' => 'set' ],
        'ip_rating'       => [ 'label' => 'IP Rating',     'type' => 'text',     'sortable' => true, 'key' => 'ip_rating', 'filterKey' => 'ip_rating', 'filterType' => 'set' ],

        // Features.
        'throttle_type'   => [ 'label' => 'Throttle',      'type' => 'text',     'sortable' => true, 'key' => 'throttle_type', 'filterKey' => 'throttle_type', 'filterType' => 'set' ],
        'features'        => [ 'label' => 'Features',      'type' => 'array',    'sortable' => false,'key' => 'features',      'filterKey' => 'features',      'filterType' => 'set' ],
        'has_lights'      => [ 'label' => 'Lights',        'type' => 'boolean',  'sortable' => true, 'key' => 'has_lights',    'filterKey' => 'has_lights',    'filterType' => 'tristate' ],
        'turn_signals'    => [ 'label' => 'Turn Signals',  'type' => 'boolean',  'sortable' => true, 'key' => 'turn_signals',  'filterKey' => 'turn_signals',  'filterType' => 'tristate' ],

        // Value metrics.
        'price_per_lb'    => [ 'label' => '$/lb',          'prefix' => '$', 'suffix' => '/lb',  'sortable' => true, 'key' => 'price_per_lb',  'filterKey' => 'price_per_lb',  'filterType' => 'range', 'round' => 2, 'sort_dir' => 'asc' ],
        'price_per_mph'   => [ 'label' => '$/mph',         'prefix' => '$', 'suffix' => '/mph', 'sortable' => true, 'key' => 'price_per_mph', 'filterKey' => 'price_per_mph', 'filterType' => 'range', 'round' => 0, 'sort_dir' => 'asc' ],
        'price_per_mile'  => [ 'label' => '$/mi',          'prefix' => '$', 'suffix' => '/mi',  'sortable' => true, 'key' => 'price_per_mile','filterKey' => 'price_per_mile','filterType' => 'range', 'round' => 0, 'sort_dir' => 'asc' ],
        'price_per_wh'    => [ 'label' => '$/Wh',          'prefix' => '$', 'suffix' => '/Wh',  'sortable' => true, 'key' => 'price_per_wh',  'filterKey' => 'price_per_wh',  'filterType' => 'range', 'round' => 2, 'sort_dir' => 'asc' ],
        'speed_per_lb'    => [ 'label' => 'mph/lb',        'suffix' => 'mph/lb', 'sortable' => true, 'key' => 'speed_per_lb',  'filterKey' => 'speed_per_lb',  'filterType' => 'range', 'round' => 2 ],
        'range_per_lb'    => [ 'label' => 'mi/lb',         'suffix' => 'mi/lb',  'sortable' => true, 'key' => 'range_per_lb',  'filterKey' => 'range_per_lb',  'filterType' => 'range', 'round' => 2 ],

        // E-Bike specific columns.
        'torque'          => [ 'label' => 'Torque',        'suffix' => 'Nm',     'sortable' => true, 'key' => 'torque',         'filterKey' => 'torque',         'filterType' => 'range' ],
        'wheel_size'      => [ 'label' => 'Wheel Size',    'suffix' => '"',      'sortable' => true, 'key' => 'wheel_size',     'filterKey' => 'wheel_size',     'filterType' => 'range', 'round' => 1 ],
        'front_travel'    => [ 'label' => 'Front Travel',  'suffix' => 'mm',     'sortable' => true, 'key' => 'front_travel',   'filterKey' => 'front_travel',   'filterType' => 'range' ],
        'rear_travel'     => [ 'label' => 'Rear Travel',   'suffix' => 'mm',     'sortable' => true, 'key' => 'rear_travel',    'filterKey' => 'rear_travel',    'filterType' => 'range' ],
        'gears'           => [ 'label' => 'Gears',         'type' => 'number',   'sortable' => true, 'key' => 'gears',          'filterKey' => 'gears',          'filterType' => 'range' ],
        'top_assist_speed'=> [ 'label' => 'Assist Speed',  'suffix' => 'mph',    'sortable' => true, 'key' => 'top_assist_speed','filterKey' => 'top_assist_speed','filterType' => 'range' ],
        'rotor_size'      => [ 'label' => 'Rotor Size',    'suffix' => 'mm',     'sortable' => true, 'key' => 'rotor_size',     'filterKey' => 'rotor_size',     'filterType' => 'range' ],
        'assist_levels'   => [ 'label' => 'Assist Levels', 'type' => 'number',   'sortable' => true, 'key' => 'assist_levels',  'filterKey' => 'assist_levels',  'filterType' => 'range' ],
        'rack_capacity'   => [ 'label' => 'Rack Capacity', 'suffix' => 'lbs',    'sortable' => true, 'key' => 'rack_capacity',  'filterKey' => 'rack_capacity',  'filterType' => 'range' ],
        'ebike_class'     => [ 'label' => 'Class',         'type' => 'array',    'sortable' => false,'key' => 'ebike_class',    'filterKey' => 'ebike_class',    'filterType' => 'set' ],
        'motor_brand'     => [ 'label' => 'Motor Brand',   'type' => 'text',     'sortable' => true, 'key' => 'motor_brand',    'filterKey' => 'motor_brand',    'filterType' => 'set' ],
        'motor_type'      => [ 'label' => 'Motor Type',    'type' => 'text',     'sortable' => true, 'key' => 'motor_type',     'filterKey' => 'motor_type',     'filterType' => 'set' ],
        'sensor_type'     => [ 'label' => 'Sensor',        'type' => 'text',     'sortable' => true, 'key' => 'sensor_type',    'filterKey' => 'sensor_type',    'filterType' => 'set' ],
        'frame_style'     => [ 'label' => 'Frame Style',   'type' => 'array',    'sortable' => false,'key' => 'frame_style',    'filterKey' => 'frame_style',    'filterType' => 'set' ],
        'frame_material'  => [ 'label' => 'Material',      'type' => 'array',    'sortable' => false,'key' => 'frame_material', 'filterKey' => 'frame_material', 'filterType' => 'set' ],
        'battery_position'=> [ 'label' => 'Battery Pos.',  'type' => 'text',     'sortable' => true, 'key' => 'battery_position','filterKey' => 'battery_position','filterType' => 'set' ],
        'drive_system'    => [ 'label' => 'Drive System',  'type' => 'text',     'sortable' => true, 'key' => 'drive_system',   'filterKey' => 'drive_system',   'filterType' => 'set' ],
        'front_suspension'=> [ 'label' => 'Front Susp.',   'type' => 'text',     'sortable' => true, 'key' => 'front_suspension','filterKey' => 'front_suspension','filterType' => 'set' ],
        'rear_suspension' => [ 'label' => 'Rear Susp.',    'type' => 'text',     'sortable' => true, 'key' => 'rear_suspension','filterKey' => 'rear_suspension','filterType' => 'set' ],
        'display'         => [ 'label' => 'Display',       'type' => 'text',     'sortable' => true, 'key' => 'display',        'filterKey' => 'display',        'filterType' => 'set' ],
        'connectivity'    => [ 'label' => 'Connectivity',  'type' => 'array',    'sortable' => false,'key' => 'connectivity',   'filterKey' => 'connectivity',   'filterType' => 'set' ],
        'sizes_available' => [ 'label' => 'Sizes',         'type' => 'array',    'sortable' => false,'key' => 'sizes_available','filterKey' => 'sizes_available','filterType' => 'set' ],
        'certifications'  => [ 'label' => 'Certifications','type' => 'array',    'sortable' => false,'key' => 'certifications', 'filterKey' => 'certifications', 'filterType' => 'set' ],
        'category'        => [ 'label' => 'Category',      'type' => 'array',    'sortable' => false,'key' => 'category',       'filterKey' => 'category',       'filterType' => 'set' ],
        'has_throttle'    => [ 'label' => 'Throttle',      'type' => 'boolean',  'sortable' => true, 'key' => 'has_throttle',   'filterKey' => 'has_throttle',   'filterType' => 'tristate' ],
        'removable_battery'=> [ 'label' => 'Removable Batt.','type' => 'boolean','sortable' => true, 'key' => 'removable_battery','filterKey' => 'removable_battery','filterType' => 'tristate' ],
        'puncture_protection'=> [ 'label' => 'Puncture Prot.','type' => 'boolean','sortable' => true,'key' => 'puncture_protection','filterKey' => 'puncture_protection','filterType' => 'tristate' ],
        'seatpost_suspension'=> [ 'label' => 'Seatpost Susp.','type' => 'boolean','sortable' => true,'key' => 'seatpost_suspension','filterKey' => 'seatpost_suspension','filterType' => 'tristate' ],
        'app_compatible'  => [ 'label' => 'App',           'type' => 'boolean',  'sortable' => true, 'key' => 'app_compatible', 'filterKey' => 'app_compatible', 'filterType' => 'tristate' ],
        'has_fenders'     => [ 'label' => 'Fenders',       'type' => 'boolean',  'sortable' => true, 'key' => 'has_fenders',    'filterKey' => 'has_fenders',    'filterType' => 'tristate' ],
        'has_rear_rack'   => [ 'label' => 'Rear Rack',     'type' => 'boolean',  'sortable' => true, 'key' => 'has_rear_rack',  'filterKey' => 'has_rear_rack',  'filterType' => 'tristate' ],
        'has_front_rack'  => [ 'label' => 'Front Rack',    'type' => 'boolean',  'sortable' => true, 'key' => 'has_front_rack', 'filterKey' => 'has_front_rack', 'filterType' => 'tristate' ],
        'has_kickstand'   => [ 'label' => 'Kickstand',     'type' => 'boolean',  'sortable' => true, 'key' => 'has_kickstand',  'filterKey' => 'has_kickstand',  'filterType' => 'tristate' ],
        'walk_assist'     => [ 'label' => 'Walk Assist',   'type' => 'boolean',  'sortable' => true, 'key' => 'walk_assist',    'filterKey' => 'walk_assist',    'filterType' => 'tristate' ],
        'brake_brand'     => [ 'label' => 'Brake Brand',   'type' => 'text',     'sortable' => true, 'key' => 'brake_brand',    'filterKey' => 'brake_brand',    'filterType' => 'set' ],
        'tire_brand'      => [ 'label' => 'Tire Brand',    'type' => 'text',     'sortable' => true, 'key' => 'tire_brand',     'filterKey' => 'tire_brand',     'filterType' => 'set' ],
    ];

    // Default columns to show in table view (price first, then specs).
    $default_table_columns = [ 'price', 'top_speed', 'motor_power', 'battery', 'weight', 'weight_limit' ];

    return [
        'sets'                => $sets,
        'ranges'              => $ranges,
        'tristates'           => $tristates,
        'booleans'            => $booleans,
        'sort'                => $sort,
        'specDisplay'         => $spec_display,
        'defaultSpecKeys'     => $default_spec_keys[ $product_type ] ?? $default_spec_keys['escooter'],
        'columnGroups'        => $column_groups,
        'columnConfig'        => $column_config,
        'defaultTableColumns' => $default_table_columns,
    ];
}

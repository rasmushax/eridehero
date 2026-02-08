<?php
/**
 * Escooter finder filter groups.
 *
 * @package ERideHero
 * @return array Filter group configuration.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return [
    'quick' => [
        'title'      => '', // No main heading.
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

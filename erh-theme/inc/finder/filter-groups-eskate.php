<?php
/**
 * E-Skateboard finder filter groups.
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
        'title'      => '',
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
        'checkbox_filters' => [ 'motor_type', 'drive_type' ],
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
        'range_filters'    => [ 'speed', 'range' ],
        'checkbox_filters' => [],
        'tristate_filters' => [],
        'has_in_stock'     => false,
    ],
    'ride_quality' => [
        'title'            => 'Ride Quality',
        'range_filters'    => [ 'tire_size', 'tire_width', 'deck_length', 'deck_width' ],
        'checkbox_filters' => [ 'tire_type', 'wheel_material', 'terrain', 'concave' ],
        'tristate_filters' => [ 'has_suspension' ],
        'has_in_stock'     => false,
    ],
    'portability' => [
        'title'            => 'Portability',
        'range_filters'    => [ 'weight', 'weight_limit', 'ground_clearance' ],
        'checkbox_filters' => [],
        'tristate_filters' => [],
        'has_in_stock'     => false,
    ],
    'safety' => [
        'title'            => 'Safety',
        'range_filters'    => [],
        'checkbox_filters' => [ 'ip_rating' ],
        'tristate_filters' => [ 'has_lights', 'ambient_lights' ],
        'has_in_stock'     => false,
    ],
    'model_info' => [
        'title'            => 'Model Info',
        'range_filters'    => [ 'release_year' ],
        'checkbox_filters' => [],
        'tristate_filters' => [],
        'has_in_stock'     => false,
    ],
];

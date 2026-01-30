<?php
/**
 * Hoverboard finder filter groups.
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
        'checkbox_filters' => [],
        'tristate_filters' => [],
        'has_in_stock'     => false,
    ],
    'battery' => [
        'title'            => 'Battery',
        'range_filters'    => [ 'battery', 'voltage', 'charging_time' ],
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
    'build' => [
        'title'            => 'Build',
        'range_filters'    => [ 'weight', 'weight_limit', 'wheel_size' ],
        'checkbox_filters' => [ 'wheel_type' ],
        'tristate_filters' => [],
        'has_in_stock'     => false,
    ],
    'safety' => [
        'title'            => 'Safety',
        'range_filters'    => [],
        'checkbox_filters' => [ 'ip_rating' ],
        'tristate_filters' => [ 'ul_2272' ],
        'has_in_stock'     => false,
    ],
    'connectivity' => [
        'title'            => 'Connectivity',
        'range_filters'    => [],
        'checkbox_filters' => [],
        'tristate_filters' => [ 'bluetooth_speaker', 'app_enabled', 'speed_modes' ],
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

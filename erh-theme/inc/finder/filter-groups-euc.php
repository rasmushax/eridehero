<?php
/**
 * EUC (Electric Unicycle) finder filter groups.
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
        'range_filters'    => [ 'motor_power', 'motor_peak', 'torque' ],
        'checkbox_filters' => [],
        'tristate_filters' => [ 'hollow_motor' ],
        'has_in_stock'     => false,
    ],
    'battery' => [
        'title'            => 'Battery',
        'range_filters'    => [ 'battery', 'voltage', 'amphours', 'charging_time' ],
        'checkbox_filters' => [ 'battery_brand' ],
        'tristate_filters' => [ 'dual_charging', 'fast_charger' ],
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
    'pedals' => [
        'title'            => 'Pedals',
        'range_filters'    => [],
        'checkbox_filters' => [],
        'tristate_filters' => [ 'spiked_pedals', 'adjustable_pedals' ],
        'has_in_stock'     => false,
    ],
    'portability' => [
        'title'            => 'Portability',
        'range_filters'    => [ 'weight', 'weight_limit' ],
        'checkbox_filters' => [],
        'tristate_filters' => [],
        'has_in_stock'     => false,
    ],
    'safety' => [
        'title'            => 'Safety',
        'range_filters'    => [],
        'checkbox_filters' => [ 'ip_rating' ],
        'tristate_filters' => [ 'lift_sensor' ],
        'has_in_stock'     => false,
    ],
    'lighting' => [
        'title'            => 'Lighting',
        'range_filters'    => [],
        'checkbox_filters' => [],
        'tristate_filters' => [ 'headlight', 'taillight', 'brake_light' ],
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

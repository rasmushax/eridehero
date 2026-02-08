<?php
/**
 * E-bike finder filter groups.
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
            'category' => [
                'title'            => 'Category',
                'range_filters'    => [],
                'checkbox_filters' => [ 'category' ],
                'tristate_filters' => [],
                'has_in_stock'     => false,
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
    'classification' => [
        'title'            => 'Classification',
        'range_filters'    => [ 'top_assist_speed' ],
        'checkbox_filters' => [ 'ebike_class' ],
        'tristate_filters' => [ 'has_throttle' ],
        'has_in_stock'     => false,
    ],
    'motor' => [
        'title'            => 'Motor & Drive',
        'range_filters'    => [ 'motor_power', 'motor_peak', 'torque', 'assist_levels' ],
        'checkbox_filters' => [ 'motor_brand', 'motor_type', 'sensor_type' ],
        'tristate_filters' => [],
        'has_in_stock'     => false,
    ],
    'battery' => [
        'title'            => 'Battery & Range',
        'range_filters'    => [ 'battery', 'range', 'voltage', 'amphours', 'charging_time' ],
        'checkbox_filters' => [ 'battery_position' ],
        'tristate_filters' => [ 'removable_battery' ],
        'has_in_stock'     => false,
    ],
    'frame' => [
        'title'            => 'Frame & Geometry',
        'range_filters'    => [ 'weight', 'weight_limit', 'rack_capacity' ],
        'checkbox_filters' => [ 'frame_style', 'frame_material', 'sizes_available' ],
        'tristate_filters' => [],
        'has_in_stock'     => false,
    ],
    'drivetrain' => [
        'title'            => 'Drivetrain',
        'range_filters'    => [ 'gears' ],
        'checkbox_filters' => [ 'drive_system' ],
        'tristate_filters' => [],
        'has_in_stock'     => false,
    ],
    'brakes' => [
        'title'            => 'Brakes',
        'range_filters'    => [ 'rotor_size' ],
        'checkbox_filters' => [ 'brake_type', 'brake_brand' ],
        'tristate_filters' => [],
        'has_in_stock'     => false,
    ],
    'suspension' => [
        'title'            => 'Suspension',
        'range_filters'    => [ 'front_travel', 'rear_travel' ],
        'checkbox_filters' => [ 'front_suspension', 'rear_suspension' ],
        'tristate_filters' => [ 'seatpost_suspension' ],
        'has_in_stock'     => false,
    ],
    'wheels_tires' => [
        'title'            => 'Wheels & Tires',
        'range_filters'    => [ 'wheel_size', 'tire_width' ],
        'checkbox_filters' => [ 'tire_type', 'tire_brand' ],
        'tristate_filters' => [ 'puncture_protection' ],
        'has_in_stock'     => false,
    ],
    'features' => [
        'title'            => 'Features & Tech',
        'range_filters'    => [],
        'checkbox_filters' => [ 'display', 'connectivity', 'features' ],
        'tristate_filters' => [ 'app_compatible', 'has_lights', 'walk_assist' ],
        'has_in_stock'     => false,
    ],
    'accessories' => [
        'title'            => 'Accessories',
        'range_filters'    => [],
        'checkbox_filters' => [],
        'tristate_filters' => [ 'has_fenders', 'has_rear_rack', 'has_front_rack', 'has_kickstand' ],
        'has_in_stock'     => false,
    ],
    'safety' => [
        'title'            => 'Safety & Compliance',
        'range_filters'    => [],
        'checkbox_filters' => [ 'ip_rating', 'certifications' ],
        'tristate_filters' => [],
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

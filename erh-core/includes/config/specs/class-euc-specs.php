<?php
/**
 * EUC (Electric Unicycle) Spec Groups
 *
 * @package ERH\Config\Specs
 */

declare( strict_types=1 );

namespace ERH\Config\Specs;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * EUC spec group definitions for the compare system.
 */
class EucSpecs {

    /**
     * Get EUC spec groups.
     *
     * @return array Spec groups keyed by display name.
     */
    public static function get(): array {
        return [
            'Performance' => [
                'icon'  => 'zap',
                'specs' => [
                    [ 'key' => 'manufacturer_top_speed', 'label' => 'Top Speed', 'unit' => 'mph', 'higherBetter' => true ],
                    [ 'key' => 'nominal_motor_wattage', 'label' => 'Motor Power', 'unit' => 'W', 'higherBetter' => true ],
                    [ 'key' => 'peak_motor_wattage', 'label' => 'Peak Power', 'unit' => 'W', 'higherBetter' => true ],
                    [ 'key' => 'hill_climb_angle', 'label' => 'Hill Grade', 'unit' => '°', 'higherBetter' => true ],
                ],
            ],
            'Range & Battery' => [
                'icon'  => 'battery',
                'specs' => [
                    [ 'key' => 'manufacturer_range', 'label' => 'Claimed Range', 'unit' => 'mi', 'higherBetter' => true ],
                    [ 'key' => 'battery_capacity', 'label' => 'Battery', 'unit' => 'Wh', 'higherBetter' => true ],
                    [ 'key' => 'battery_voltage', 'label' => 'Voltage', 'unit' => 'V', 'higherBetter' => true ],
                    [ 'key' => 'charge_time', 'label' => 'Charge Time', 'unit' => 'h', 'higherBetter' => false ],
                ],
            ],
            'Build' => [
                'icon'  => 'box',
                'specs' => [
                    [ 'key' => 'weight', 'label' => 'Weight', 'unit' => 'lbs', 'higherBetter' => false ],
                    [ 'key' => 'max_weight_capacity', 'label' => 'Max Load', 'unit' => 'lbs', 'higherBetter' => true ],
                    [ 'key' => 'wheel_size', 'label' => 'Wheel Size', 'unit' => '"' ],
                    [ 'key' => 'suspension', 'label' => 'Suspension', 'format' => 'suspension' ],
                    [ 'key' => 'ip_rating', 'label' => 'IP Rating', 'format' => 'ip' ],
                ],
            ],
        ];
    }
}

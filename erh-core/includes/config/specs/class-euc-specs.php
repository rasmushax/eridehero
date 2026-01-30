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
            'Motor Performance' => [
                'icon'      => 'zap',
                'showScore' => true,
                'scoreKey'  => 'motor_performance',
                'specs'     => [
                    [ 'key' => 'manufacturer_top_speed', 'label' => 'Top Speed', 'unit' => 'mph', 'higherBetter' => true ],
                    [ 'key' => 'nominal_motor_wattage', 'label' => 'Motor Power', 'unit' => 'W', 'higherBetter' => true ],
                    [ 'key' => 'peak_motor_wattage', 'label' => 'Peak Power', 'unit' => 'W', 'higherBetter' => true ],
                    [ 'key' => 'torque', 'label' => 'Torque', 'unit' => 'Nm', 'higherBetter' => true ],
                    [ 'key' => 'hill_climb_angle', 'label' => 'Hill Grade', 'unit' => '°', 'higherBetter' => true ],
                    [ 'key' => 'hollow_motor', 'label' => 'Hollow Motor', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'motor_diameter', 'label' => 'Motor Diameter', 'unit' => '"', 'higherBetter' => true ],
                ],
            ],
            'Range & Battery' => [
                'icon'      => 'battery',
                'showScore' => true,
                'scoreKey'  => 'battery_range',
                'specs'     => [
                    [ 'key' => 'battery_capacity', 'label' => 'Battery', 'unit' => 'Wh', 'higherBetter' => true ],
                    [ 'key' => 'battery_voltage', 'label' => 'Voltage', 'unit' => 'V', 'higherBetter' => true ],
                    [ 'key' => 'manufacturer_range', 'label' => 'Claimed Range', 'unit' => 'mi', 'higherBetter' => true ],
                    [ 'key' => 'charge_time', 'label' => 'Charge Time', 'unit' => 'h', 'higherBetter' => false ],
                    [ 'key' => 'dual_charging', 'label' => 'Dual Charging', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'fast_charger', 'label' => 'Fast Charger', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'battery_type', 'label' => 'Battery Type' ],
                    [ 'key' => 'battery_brand', 'label' => 'Battery Brand' ],
                    [ 'key' => 'battery_packs', 'label' => 'Battery Packs', 'higherBetter' => true ],
                ],
            ],
            'Ride Quality' => [
                'icon'      => 'smile',
                'showScore' => true,
                'scoreKey'  => 'ride_quality',
                'specs'     => [
                    [ 'key' => 'wheel_size', 'label' => 'Wheel Size', 'unit' => '"', 'higherBetter' => true ],
                    [ 'key' => 'tire_width', 'label' => 'Tire Width', 'unit' => '"', 'higherBetter' => true ],
                    [ 'key' => 'tire_type', 'label' => 'Tire Type' ],
                    [ 'key' => 'suspension', 'label' => 'Suspension', 'format' => 'suspension' ],
                    [ 'key' => 'suspension_travel', 'label' => 'Suspension Travel', 'unit' => 'mm', 'higherBetter' => true ],
                    [ 'key' => 'pedal_height', 'label' => 'Pedal Height', 'unit' => '"', 'higherBetter' => true ],
                    [ 'key' => 'pedal_width', 'label' => 'Pedal Width', 'unit' => '"', 'higherBetter' => true ],
                    [ 'key' => 'pedal_length', 'label' => 'Pedal Length', 'unit' => '"', 'higherBetter' => true ],
                ],
            ],
            'Safety' => [
                'icon'      => 'shield',
                'showScore' => true,
                'scoreKey'  => 'safety',
                'specs'     => [
                    [ 'key' => 'ip_rating', 'label' => 'IP Rating', 'format' => 'ip', 'higherBetter' => true ],
                    [ 'key' => 'tiltback_speed', 'label' => 'Tiltback Speed', 'unit' => 'mph' ],
                    [ 'key' => 'cutoff_speed', 'label' => 'Cutoff Speed', 'unit' => 'mph' ],
                    [ 'key' => 'lift_sensor', 'label' => 'Lift Sensor', 'format' => 'boolean', 'higherBetter' => true ],
                ],
            ],
            'Portability' => [
                'icon'      => 'box',
                'showScore' => true,
                'scoreKey'  => 'portability',
                'specs'     => [
                    [ 'key' => 'weight', 'label' => 'Weight', 'unit' => 'lbs', 'higherBetter' => false ],
                    [ 'key' => 'max_weight_capacity', 'label' => 'Max Load', 'unit' => 'lbs', 'higherBetter' => true ],
                ],
            ],
            'Features' => [
                'icon'      => 'settings',
                'showScore' => true,
                'scoreKey'  => 'features',
                'specs'     => [
                    [ 'key' => 'features', 'label' => 'Features', 'format' => 'featureArray' ],
                ],
            ],
        ];
    }
}

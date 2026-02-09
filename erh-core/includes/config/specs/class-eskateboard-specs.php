<?php
/**
 * E-Skateboard Spec Groups
 *
 * Full spec group definitions for the compare system.
 * Uses nested ACF paths like motor.power_nominal, deck.length, etc.
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
 * E-Skateboard spec group definitions for the compare system.
 */
class EskateboardSpecs {

    /**
     * Get e-skateboard spec groups.
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
                    [ 'key' => 'motor.power_nominal', 'label' => 'Motor Power', 'unit' => 'W', 'higherBetter' => true ],
                    [ 'key' => 'motor.power_peak', 'label' => 'Peak Power', 'unit' => 'W', 'higherBetter' => true ],
                    [ 'key' => 'motor.motor_type', 'label' => 'Motor Type' ],
                    [ 'key' => 'motor.drive', 'label' => 'Drive' ],
                    [ 'key' => 'motor.motor_count', 'label' => 'Motors', 'higherBetter' => true ],
                ],
            ],
            'Range & Battery' => [
                'icon'      => 'battery',
                'showScore' => true,
                'scoreKey'  => 'battery_range',
                'specs'     => [
                    [ 'key' => 'battery.capacity', 'label' => 'Battery', 'unit' => 'Wh', 'higherBetter' => true ],
                    [ 'key' => 'battery.voltage', 'label' => 'Voltage', 'unit' => 'V', 'higherBetter' => true ],
                    [ 'key' => 'manufacturer_range', 'label' => 'Claimed Range', 'unit' => 'mi', 'higherBetter' => true ],
                    [ 'key' => 'battery.charging_time', 'label' => 'Charge Time', 'unit' => 'h', 'higherBetter' => false ],
                    [ 'key' => 'battery.battery_type', 'label' => 'Battery Type' ],
                    [ 'key' => 'battery.brand', 'label' => 'Battery Brand' ],
                    [ 'key' => 'battery.configuration', 'label' => 'Config' ],
                    [ 'key' => 'battery.amphours', 'label' => 'Amp Hours', 'unit' => 'Ah', 'higherBetter' => true ],
                ],
            ],
            'Ride Quality' => [
                'icon'      => 'smile',
                'showScore' => true,
                'scoreKey'  => 'ride_quality',
                'specs'     => [
                    [ 'key' => 'wheels.wheel_size', 'label' => 'Wheel Size', 'unit' => 'mm', 'higherBetter' => true ],
                    [ 'key' => 'wheels.wheel_width', 'label' => 'Wheel Width', 'unit' => 'mm', 'higherBetter' => true ],
                    [ 'key' => 'wheels.durometer', 'label' => 'Durometer', 'unit' => 'A' ],
                    [ 'key' => 'wheels.wheel_type', 'label' => 'Wheel Type' ],
                    [ 'key' => 'wheels.wheel_material', 'label' => 'Wheel Material' ],
                    [ 'key' => 'wheels.terrain', 'label' => 'Terrain' ],
                    [ 'key' => 'deck.length', 'label' => 'Deck Length', 'unit' => '"' ],
                    [ 'key' => 'deck.width', 'label' => 'Deck Width', 'unit' => '"' ],
                    [ 'key' => 'deck.material', 'label' => 'Deck Material' ],
                    [ 'key' => 'deck.concave', 'label' => 'Concave' ],
                    [ 'key' => 'trucks.trucks', 'label' => 'Trucks' ],
                    [ 'key' => 'trucks.bushings', 'label' => 'Bushings' ],
                    [ 'key' => 'suspension.has_suspension', 'label' => 'Suspension', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'suspension.suspension_type', 'label' => 'Suspension Type', 'format' => 'suspension' ],
                ],
            ],
            'Portability' => [
                'icon'      => 'box',
                'showScore' => true,
                'scoreKey'  => 'portability',
                'specs'     => [
                    [ 'key' => 'dimensions.weight', 'label' => 'Weight', 'unit' => 'lbs', 'higherBetter' => false ],
                    [ 'key' => 'dimensions.max_load', 'label' => 'Max Load', 'unit' => 'lbs', 'higherBetter' => true ],
                    [ 'key' => 'dimensions.wheelbase', 'label' => 'Wheelbase', 'unit' => '"' ],
                    [ 'key' => 'dimensions.ground_clearance', 'label' => 'Ground Clearance', 'unit' => '"', 'higherBetter' => true ],
                ],
            ],
            'Features' => [
                'icon'      => 'settings',
                'showScore' => true,
                'scoreKey'  => 'features',
                'specs'     => [
                    [ 'key' => 'electronics.esc', 'label' => 'ESC' ],
                    [ 'key' => 'electronics.remote_type', 'label' => 'Remote' ],
                    [ 'key' => 'other.ip_rating', 'label' => 'IP Rating', 'format' => 'ip', 'higherBetter' => true ],
                    [ 'key' => 'features', 'label' => 'Features', 'format' => 'featureArray' ],
                ],
            ],
        ];
    }
}

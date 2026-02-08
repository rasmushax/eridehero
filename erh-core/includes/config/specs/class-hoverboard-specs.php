<?php
/**
 * Hoverboard Spec Groups
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
 * Hoverboard spec group definitions for the compare system.
 */
class HoverboardSpecs {

    /**
     * Get hoverboard spec groups.
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
                    [ 'key' => 'motor.power_nominal', 'label' => 'Motor Power', 'unit' => 'W', 'higherBetter' => true ],
                    [ 'key' => 'motor.power_peak', 'label' => 'Peak Power', 'unit' => 'W', 'higherBetter' => true ],
                    [ 'key' => 'tested_top_speed', 'label' => 'Top Speed (Tested)', 'unit' => 'mph', 'higherBetter' => true ],
                    [ 'key' => 'manufacturer_top_speed', 'label' => 'Top Speed (Claimed)', 'unit' => 'mph', 'higherBetter' => true ],
                ],
            ],
            'Range & Battery' => [
                'icon'      => 'battery',
                'showScore' => true,
                'scoreKey'  => 'battery_range',
                'specs'     => [
                    [ 'key' => 'battery.capacity', 'label' => 'Battery', 'unit' => 'Wh', 'higherBetter' => true ],
                    [ 'key' => 'battery.amphours', 'label' => 'Amp Hours', 'unit' => 'Ah', 'higherBetter' => true ],
                    [ 'key' => 'battery.voltage', 'label' => 'Voltage', 'unit' => 'V', 'higherBetter' => true ],
                    [ 'key' => 'manufacturer_range', 'label' => 'Range (Claimed)', 'unit' => 'mi', 'higherBetter' => true ],
                    [ 'key' => 'tested_range_fast', 'label' => 'Range (Fast)', 'unit' => 'mi', 'higherBetter' => true ],
                    [ 'key' => 'tested_range_regular', 'label' => 'Range (Tested)', 'unit' => 'mi', 'higherBetter' => true ],
                    [ 'key' => 'tested_range_slow', 'label' => 'Range (Slow)', 'unit' => 'mi', 'higherBetter' => true ],
                    [ 'key' => 'battery.charging_time', 'label' => 'Charge Time', 'unit' => 'h', 'higherBetter' => false ],
                    [ 'key' => 'battery.battery_type', 'label' => 'Battery Type' ],
                ],
            ],
            'Portability' => [
                'icon'      => 'box',
                'showScore' => true,
                'scoreKey'  => 'portability',
                'specs'     => [
                    [ 'key' => 'dimensions.weight', 'label' => 'Weight', 'unit' => 'lbs', 'higherBetter' => false ],
                    [ 'key' => 'dimensions.max_load', 'label' => 'Max Rider Weight', 'unit' => 'lbs', 'higherBetter' => true ],
                    [ 'key' => 'dimensions.length', 'label' => 'Length', 'unit' => '"' ],
                    [ 'key' => 'dimensions.width', 'label' => 'Width', 'unit' => '"' ],
                    [ 'key' => 'dimensions.height', 'label' => 'Height', 'unit' => '"' ],
                ],
            ],
            'Ride Comfort' => [
                'icon'      => 'smile',
                'showScore' => true,
                'scoreKey'  => 'ride_comfort',
                'specs'     => [
                    [ 'key' => 'wheels.tire_type', 'label' => 'Tire Type', 'format' => 'tire' ],
                    [ 'key' => 'wheels.wheel_size', 'label' => 'Wheel Size', 'unit' => '"', 'higherBetter' => true ],
                    [ 'key' => 'other.terrain', 'label' => 'Terrain' ],
                ],
            ],
            'Features' => [
                'icon'      => 'settings',
                'showScore' => true,
                'scoreKey'  => 'features',
                'specs'     => [
                    [ 'key' => 'lighting.lights', 'label' => 'Lights', 'format' => 'array' ],
                    [ 'key' => 'connectivity.bluetooth_speaker', 'label' => 'Bluetooth Speaker', 'format' => 'boolean' ],
                    [ 'key' => 'connectivity.app_enabled', 'label' => 'App Enabled', 'format' => 'boolean' ],
                    [ 'key' => 'connectivity.speed_modes', 'label' => 'Speed Modes', 'format' => 'boolean' ],
                    [ 'key' => 'safety.ul_2272', 'label' => 'UL 2272 Certified', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'safety.ip_rating', 'label' => 'IP Rating', 'format' => 'ip', 'higherBetter' => true ],
                    [ 'key' => 'features', 'label' => 'Features', 'format' => 'featureArray' ],
                    [ 'key' => 'other.min_age', 'label' => 'Minimum Age', 'unit' => 'yrs', 'higherBetter' => false ],
                ],
            ],
        ];
    }
}

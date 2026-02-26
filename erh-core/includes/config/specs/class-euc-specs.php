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
                    [ 'key' => 'tested_top_speed', 'label' => 'Top Speed (Tested)', 'unit' => 'mph', 'higherBetter' => true ],
                    [ 'key' => 'manufacturer_top_speed', 'label' => 'Top Speed (Claimed)', 'unit' => 'mph', 'higherBetter' => true ],
                    [ 'key' => 'motor.power_nominal', 'label' => 'Motor Power', 'unit' => 'W', 'higherBetter' => true ],
                    [ 'key' => 'motor.power_peak', 'label' => 'Peak Power', 'unit' => 'W', 'higherBetter' => true ],
                    [ 'key' => 'motor.torque', 'label' => 'Torque', 'unit' => 'Nm', 'higherBetter' => true ],
                    [ 'key' => 'motor.hollow_motor', 'label' => 'Hollow Motor', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'motor.motor_diameter', 'label' => 'Motor Diameter', 'unit' => '"', 'higherBetter' => true ],
                ],
            ],
            'Range & Battery' => [
                'icon'      => 'battery',
                'showScore' => true,
                'scoreKey'  => 'battery_range',
                'specs'     => [
                    [ 'key' => 'tested_range_regular', 'label' => 'Range (Tested)', 'unit' => 'mi', 'higherBetter' => true ],
                    [ 'key' => 'manufacturer_range', 'label' => 'Range (Claimed)', 'unit' => 'mi', 'higherBetter' => true ],
                    [ 'key' => 'battery.capacity', 'label' => 'Battery', 'unit' => 'Wh', 'higherBetter' => true ],
                    [ 'key' => 'battery.voltage', 'label' => 'Voltage', 'unit' => 'V', 'higherBetter' => true ],
                    [ 'key' => 'battery.charging_time', 'label' => 'Charge Time', 'unit' => 'h', 'higherBetter' => false ],
                    [ 'key' => 'battery.dual_charging', 'label' => 'Dual Charging', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'battery.fast_charger', 'label' => 'Fast Charger', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'battery.battery_type', 'label' => 'Battery Type' ],
                    [ 'key' => 'battery.battery_brand', 'label' => 'Battery Brand' ],
                    [ 'key' => 'battery.battery_packs', 'label' => 'Battery Packs', 'higherBetter' => true ],
                    [ 'key' => 'battery.amphours', 'label' => 'Amp Hours', 'unit' => 'Ah', 'higherBetter' => true ],
                ],
            ],
            'Ride Quality' => [
                'icon'      => 'smile',
                'showScore' => true,
                'scoreKey'  => 'ride_quality',
                'specs'     => [
                    [ 'key' => 'wheel.tire_size', 'label' => 'Wheel Size', 'unit' => '"', 'higherBetter' => true ],
                    [ 'key' => 'wheel.tire_width', 'label' => 'Tire Width', 'unit' => '"', 'higherBetter' => true ],
                    [ 'key' => 'wheel.tire_type', 'label' => 'Tire Type' ],
                    [ 'key' => 'suspension.suspension_type', 'label' => 'Suspension', 'format' => 'suspension' ],
                    [ 'key' => 'suspension.suspension_travel', 'label' => 'Suspension Travel', 'unit' => 'mm', 'higherBetter' => true ],
                    [ 'key' => 'pedals.pedal_height', 'label' => 'Pedal Height', 'unit' => '"', 'higherBetter' => true ],
                    [ 'key' => 'pedals.pedal_width', 'label' => 'Pedal Width', 'unit' => '"', 'higherBetter' => true ],
                    [ 'key' => 'pedals.pedal_length', 'label' => 'Pedal Length', 'unit' => '"', 'higherBetter' => true ],
                    [ 'key' => 'wheel.tire_tread', 'label' => 'Tire Tread' ],
                    [ 'key' => 'wheel.self_healing', 'label' => 'Self-Healing Tire', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'suspension.adjustable_suspension', 'label' => 'Adjustable Suspension', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'pedals.spiked_pedals', 'label' => 'Spiked Pedals', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'pedals.adjustable_pedals', 'label' => 'Adjustable Pedals', 'format' => 'boolean', 'higherBetter' => true ],
                ],
            ],
            'Safety' => [
                'icon'      => 'shield',
                'showScore' => true,
                'scoreKey'  => 'safety',
                'specs'     => [
                    [ 'key' => 'safety.ip_rating', 'label' => 'IP Rating', 'format' => 'ip', 'higherBetter' => true ],
                    [ 'key' => 'safety.tiltback_speed', 'label' => 'Tiltback Speed', 'unit' => 'mph' ],
                    [ 'key' => 'safety.cutoff_speed', 'label' => 'Cutoff Speed', 'unit' => 'mph' ],
                    [ 'key' => 'safety.lift_sensor', 'label' => 'Lift Sensor', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'lighting.headlight', 'label' => 'Headlight', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'lighting.headlight_lumens', 'label' => 'Headlight Lumens', 'unit' => 'lm', 'higherBetter' => true ],
                    [ 'key' => 'lighting.taillight', 'label' => 'Taillight', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'lighting.brake_light', 'label' => 'Brake Light', 'format' => 'boolean', 'higherBetter' => true ],
                ],
            ],
            'Portability' => [
                'icon'      => 'box',
                'showScore' => true,
                'scoreKey'  => 'portability',
                'specs'     => [
                    [ 'key' => 'dimensions.weight', 'label' => 'Weight', 'unit' => 'lbs', 'higherBetter' => false ],
                    [ 'key' => 'dimensions.max_load', 'label' => 'Max Load', 'unit' => 'lbs', 'higherBetter' => true ],
                ],
            ],
            'Features' => [
                'icon'      => 'settings',
                'showScore' => true,
                'scoreKey'  => 'features',
                'specs'     => [
                    [ 'key' => 'features', 'label' => 'Features', 'format' => 'featureArray' ],
                    [ 'key' => 'connectivity.bluetooth', 'label' => 'Bluetooth', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'connectivity.app', 'label' => 'Mobile App', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'connectivity.speaker', 'label' => 'Speaker', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'connectivity.gps', 'label' => 'GPS', 'format' => 'boolean', 'higherBetter' => true ],
                ],
            ],
        ];
    }
}

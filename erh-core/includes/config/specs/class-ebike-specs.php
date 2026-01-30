<?php
/**
 * E-Bike Spec Groups
 *
 * Keys use flattened paths (no 'e-bikes.' prefix) because erh_flatten_compare_specs()
 * moves nested content to top level before comparison.
 *
 * Groups are organized to match the scoring system.
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
 * E-Bike spec group definitions for the compare system.
 */
class EbikeSpecs {

    /**
     * Get e-bike spec groups.
     *
     * @return array Spec groups keyed by display name.
     */
    public static function get(): array {
        return [
            // =====================================================================
            // New 5-category structure (use-case agnostic, component quality aware)
            // =====================================================================
            'Motor & Drive' => [
                'icon'      => 'zap',
                'question'  => 'How good is the motor system?',
                'showScore' => true,
                'scoreKey'  => 'motor_drive',
                'specs'     => [
                    [ 'key' => 'motor.torque', 'label' => 'Torque', 'unit' => 'Nm', 'higherBetter' => true, 'tooltip' => 'Climbing power - higher is better for hills' ],
                    [ 'key' => 'motor.motor_brand', 'label' => 'Motor Brand', 'tooltip' => 'Premium brands (Bosch, Shimano) offer refined power delivery' ],
                    [ 'key' => 'motor.motor_position', 'label' => 'Motor Position', 'tooltip' => 'Mid-drive > Rear hub > Front hub' ],
                    [ 'key' => 'motor.sensor_type', 'label' => 'Sensor Type', 'tooltip' => 'Torque sensors feel more natural than cadence' ],
                    [ 'key' => 'motor.power_nominal', 'label' => 'Motor Power', 'unit' => 'W', 'higherBetter' => true ],
                    [ 'key' => 'motor.power_peak', 'label' => 'Peak Power', 'unit' => 'W', 'higherBetter' => true ],
                    [ 'key' => 'motor.motor_type', 'label' => 'Motor Type' ],
                    [ 'key' => 'motor.assist_levels', 'label' => 'Assist Levels', 'higherBetter' => true ],
                ],
            ],
            'Battery & Range' => [
                'icon'      => 'battery',
                'question'  => 'How far can I go?',
                'showScore' => true,
                'scoreKey'  => 'battery_range',
                'specs'     => [
                    [ 'key' => 'battery.battery_capacity', 'label' => 'Battery', 'unit' => 'Wh', 'higherBetter' => true ],
                    [ 'key' => 'battery.range', 'label' => 'Max Range', 'unit' => 'mi', 'higherBetter' => true ],
                    [ 'key' => 'battery.battery_brand', 'label' => 'Battery/Cell Brand', 'tooltip' => 'Samsung/LG/Panasonic cells are highest quality' ],
                    [ 'key' => 'battery.charge_time', 'label' => 'Charge Time', 'unit' => 'h', 'higherBetter' => false ],
                    [ 'key' => 'battery.voltage', 'label' => 'Voltage', 'unit' => 'V', 'higherBetter' => true ],
                    [ 'key' => 'battery.amphours', 'label' => 'Amp Hours', 'unit' => 'Ah', 'higherBetter' => true ],
                    [ 'key' => 'battery.removable', 'label' => 'Removable Battery', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'battery.battery_position', 'label' => 'Battery Position' ],
                ],
            ],
            'Component Quality' => [
                'icon'      => 'settings',
                'question'  => 'How good are the components?',
                'showScore' => true,
                'scoreKey'  => 'component_quality',
                'specs'     => [
                    // Brakes
                    [ 'key' => 'brakes.brake_brand', 'label' => 'Brake Brand', 'tooltip' => 'Shimano XT/XTR, SRAM Code are premium' ],
                    [ 'key' => 'brakes.brake_type', 'label' => 'Brake Type', 'format' => 'array', 'tooltip' => 'Hydraulic > Mechanical > Rim' ],
                    [ 'key' => 'brakes.rotor_size_front', 'label' => 'Rotor Size (Front)', 'unit' => 'mm', 'higherBetter' => true ],
                    [ 'key' => 'brakes.rotor_size_rear', 'label' => 'Rotor Size (Rear)', 'unit' => 'mm', 'higherBetter' => true ],
                    // Drivetrain
                    [ 'key' => 'drivetrain.derailleur', 'label' => 'Derailleur', 'tooltip' => 'SRAM Eagle, Shimano Deore+ are premium' ],
                    [ 'key' => 'drivetrain.shifter', 'label' => 'Shifter' ],
                    [ 'key' => 'drivetrain.drive_system', 'label' => 'Drive System', 'tooltip' => 'Belt drive is maintenance-free' ],
                    [ 'key' => 'drivetrain.gears', 'label' => 'Gears', 'higherBetter' => true ],
                    [ 'key' => 'drivetrain.cassette', 'label' => 'Cassette' ],
                    // Build quality
                    [ 'key' => 'wheels_and_tires.tire_brand', 'label' => 'Tire Brand', 'tooltip' => 'Maxxis, Schwalbe, Continental are premium' ],
                    [ 'key' => 'frame_and_geometry.frame_material', 'label' => 'Frame Material', 'format' => 'array', 'tooltip' => 'Carbon > Aluminum > Steel' ],
                    // Safety/compliance (moved from old category)
                    [ 'key' => 'safety_and_compliance.ip_rating', 'label' => 'IP Rating', 'format' => 'ip', 'higherBetter' => true, 'tooltip' => 'IPX4+ for weather protection' ],
                    [ 'key' => 'safety_and_compliance.certifications', 'label' => 'Certifications', 'format' => 'array', 'tooltip' => 'UL 2849 is the gold standard' ],
                ],
            ],
            'Comfort' => [
                'icon'      => 'smile',
                'question'  => 'How comfortable is the ride?',
                'showScore' => true,
                'scoreKey'  => 'comfort',
                'specs'     => [
                    [ 'key' => 'suspension.front_suspension', 'label' => 'Front Suspension', 'tooltip' => 'Air > Coil > Rigid' ],
                    [ 'key' => 'suspension.front_travel', 'label' => 'Front Travel', 'unit' => 'mm', 'higherBetter' => true ],
                    [ 'key' => 'suspension.rear_suspension', 'label' => 'Rear Suspension' ],
                    [ 'key' => 'suspension.rear_travel', 'label' => 'Rear Travel', 'unit' => 'mm', 'higherBetter' => true ],
                    [ 'key' => 'suspension.seatpost_suspension', 'label' => 'Seatpost Suspension', 'format' => 'boolean', 'higherBetter' => true ],
                ],
            ],
            'Practicality' => [
                'icon'      => 'box',
                'question'  => 'How practical for daily use?',
                'showScore' => true,
                'scoreKey'  => 'practicality',
                'specs'     => [
                    // Weight
                    [ 'key' => 'weight_and_capacity.weight', 'label' => 'Weight', 'unit' => 'lbs', 'higherBetter' => false ],
                    [ 'key' => 'weight_and_capacity.weight_limit', 'label' => 'Weight Limit', 'unit' => 'lbs', 'higherBetter' => true ],
                    // Tech
                    [ 'key' => 'components.display', 'label' => 'Display', 'tooltip' => 'Color TFT > LCD > LED' ],
                    [ 'key' => 'components.display_size', 'label' => 'Display Size', 'unit' => '"' ],
                    [ 'key' => 'components.app_compatible', 'label' => 'App Compatible', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'components.connectivity', 'label' => 'Connectivity', 'format' => 'array' ],
                    // Lights & throttle (moved from old Safety)
                    [ 'key' => 'integrated_features.integrated_lights', 'label' => 'Integrated Lights', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'speed_and_class.throttle', 'label' => 'Throttle', 'format' => 'boolean', 'higherBetter' => true ],
                    // Accessories
                    [ 'key' => 'integrated_features.fenders', 'label' => 'Fenders', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'integrated_features.rear_rack', 'label' => 'Rear Rack', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'integrated_features.front_rack', 'label' => 'Front Rack', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'integrated_features.kickstand', 'label' => 'Kickstand', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'integrated_features.walk_assist', 'label' => 'Walk Assist', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'integrated_features.alarm', 'label' => 'Alarm/Security', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'special_features', 'label' => 'Special Features', 'format' => 'array' ],
                ],
            ],
            // =====================================================================
            // Non-scored supplementary groups
            // =====================================================================
            'Speed & Class' => [
                'icon'      => 'activity',
                'collapsed' => true,
                'specs'     => [
                    [ 'key' => 'speed_and_class.class', 'label' => 'E-Bike Class', 'format' => 'array' ],
                    [ 'key' => 'speed_and_class.top_assist_speed', 'label' => 'Top Assist Speed', 'unit' => 'mph', 'higherBetter' => true ],
                    [ 'key' => 'speed_and_class.throttle_top_speed', 'label' => 'Throttle Top Speed', 'unit' => 'mph', 'higherBetter' => true ],
                ],
            ],
            'Wheels & Tires' => [
                'icon'      => 'circle',
                'collapsed' => true,
                'specs'     => [
                    [ 'key' => 'wheels_and_tires.wheel_size', 'label' => 'Wheel Size', 'unit' => '"' ],
                    [ 'key' => 'wheels_and_tires.wheel_size_rear', 'label' => 'Wheel Size (Rear)', 'unit' => '"' ],
                    [ 'key' => 'wheels_and_tires.tire_type', 'label' => 'Tire Type' ],
                    [ 'key' => 'wheels_and_tires.tire_width', 'label' => 'Tire Width', 'unit' => '"' ],
                    [ 'key' => 'wheels_and_tires.puncture_protection', 'label' => 'Puncture Protection', 'format' => 'boolean' ],
                ],
            ],
            'Frame & Geometry' => [
                'icon'      => 'square',
                'collapsed' => true,
                'specs'     => [
                    [ 'key' => 'frame_and_geometry.frame_style', 'label' => 'Frame Style', 'format' => 'array' ],
                    [ 'key' => 'frame_and_geometry.sizes_available', 'label' => 'Sizes Available', 'format' => 'array' ],
                    [ 'key' => 'weight_and_capacity.rack_capacity', 'label' => 'Rack Capacity', 'unit' => 'lbs', 'higherBetter' => true ],
                ],
            ],
        ];
    }
}

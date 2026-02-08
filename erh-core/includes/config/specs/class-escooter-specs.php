<?php
/**
 * E-Scooter Spec Groups
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
 * E-Scooter spec group definitions for the compare system.
 */
class EscooterSpecs {

    /**
     * Get e-scooter spec groups.
     *
     * @return array Spec groups keyed by display name.
     */
    public static function get(): array {
        return [
            'Motor Performance' => [
                'icon'      => 'zap',
                'question'  => 'How fast and powerful is it?',
                'showScore' => true,
                'scoreKey'  => 'motor_performance',
                'specs'     => [
                    // Tooltips auto-enriched from TOOLTIPS constant.
                    [ 'key' => 'tested_top_speed', 'label' => 'Top Speed (Tested)', 'unit' => 'mph', 'higherBetter' => true ],
                    [ 'key' => 'manufacturer_top_speed', 'label' => 'Top Speed (Claimed)', 'unit' => 'mph', 'higherBetter' => true ],
                    [ 'key' => 'motor.power_nominal', 'label' => 'Motor Power', 'unit' => 'W', 'higherBetter' => true ],
                    [ 'key' => 'motor.power_peak', 'label' => 'Peak Power', 'unit' => 'W', 'higherBetter' => true ],
                    [ 'key' => 'motor.voltage', 'label' => 'Voltage', 'unit' => 'V', 'higherBetter' => true ],
                    [ 'key' => 'motor.motor_position', 'label' => 'Motor Config' ],
                    [ 'key' => 'hill_climbing', 'label' => 'Hill Climb', 'unit' => 's', 'higherBetter' => false ],
                    [ 'key' => 'max_incline', 'label' => 'Hill Grade', 'unit' => '°', 'higherBetter' => true ],
                    [ 'key' => 'acceleration_0_15_mph', 'label' => '0-15 mph', 'unit' => 's', 'higherBetter' => false ],
                    [ 'key' => 'acceleration_0_20_mph', 'label' => '0-20 mph', 'unit' => 's', 'higherBetter' => false ],
                    [ 'key' => 'acceleration_0_25_mph', 'label' => '0-25 mph', 'unit' => 's', 'higherBetter' => false ],
                    [ 'key' => 'acceleration_0_30_mph', 'label' => '0-30 mph', 'unit' => 's', 'higherBetter' => false ],
                    [ 'key' => 'acceleration_0_to_top', 'label' => '0-Top', 'unit' => 's', 'higherBetter' => false ],
                ],
            ],

            'Range & Battery' => [
                'icon'      => 'battery',
                'question'  => 'How far can I go?',
                'showScore' => true,
                'scoreKey'  => 'range_battery',
                'specs'     => [
                    [ 'key' => 'tested_range_fast', 'label' => 'Range (Fast)', 'unit' => 'mi', 'higherBetter' => true ],
                    [ 'key' => 'tested_range_regular', 'label' => 'Range (Tested)', 'unit' => 'mi', 'higherBetter' => true ],
                    [ 'key' => 'tested_range_slow', 'label' => 'Range (Slow)', 'unit' => 'mi', 'higherBetter' => true ],
                    [ 'key' => 'manufacturer_range', 'label' => 'Range (Claimed)', 'unit' => 'mi', 'higherBetter' => true ],
                    [ 'key' => 'battery.capacity', 'label' => 'Battery Capacity', 'unit' => 'Wh', 'higherBetter' => true ],
                    [ 'key' => 'battery.ah', 'label' => 'Amp Hours', 'unit' => 'Ah', 'higherBetter' => true ],
                    [ 'key' => 'battery.voltage', 'label' => 'Voltage', 'unit' => 'V', 'higherBetter' => true ],
                    [ 'key' => 'battery.charging_time', 'label' => 'Charge Time', 'unit' => 'h', 'higherBetter' => false ],
                    [ 'key' => 'battery.battery_type', 'label' => 'Battery Type' ],
                ],
            ],

            'Ride Quality' => [
                'icon'      => 'smile',
                'question'  => 'Is it comfortable?',
                'showScore' => true,
                'scoreKey'  => 'ride_quality',
                'specs'     => [
                    [ 'key' => 'suspension.type', 'label' => 'Suspension', 'higherBetter' => true, 'format' => 'suspensionArray' ],
                    [ 'key' => 'suspension.adjustable', 'label' => 'Adjustable Suspension', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'wheels.tire_type', 'label' => 'Tire Type', 'format' => 'tire' ],
                    [ 'key' => 'wheels.tire_size_front', 'label' => 'Front Tire Size', 'unit' => '"', 'higherBetter' => true ],
                    [ 'key' => 'wheels.tire_size_rear', 'label' => 'Rear Tire Size', 'unit' => '"', 'higherBetter' => true ],
                    [ 'key' => 'wheels.tire_width', 'label' => 'Tire Width', 'unit' => '"', 'higherBetter' => true ],
                    [ 'key' => 'dimensions.deck_length', 'label' => 'Deck Length', 'unit' => '"', 'higherBetter' => true ],
                    [ 'key' => 'dimensions.deck_width', 'label' => 'Deck Width', 'unit' => '"', 'higherBetter' => true ],
                    [ 'key' => 'dimensions.ground_clearance', 'label' => 'Ground Clearance', 'unit' => '"' ],
                    [ 'key' => 'dimensions.handlebar_height_min', 'label' => 'Handlebar Height (min)', 'unit' => '"' ],
                    [ 'key' => 'dimensions.handlebar_height_max', 'label' => 'Handlebar Height (max)', 'unit' => '"' ],
                    [ 'key' => 'dimensions.handlebar_width', 'label' => 'Handlebar Width', 'unit' => '"', 'higherBetter' => true ],
                    [ 'key' => 'other.footrest', 'label' => 'Footrest', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'other.terrain', 'label' => 'Terrain Type' ],
                ],
            ],

            'Portability & Fit' => [
                'icon'      => 'box',
                'question'  => 'Does this product fit my life and body?',
                'showScore' => true,
                'scoreKey'  => 'portability',
                'specs'     => [
                    [ 'key' => 'dimensions.weight', 'label' => 'Weight', 'unit' => 'lbs', 'higherBetter' => false ],
                    [ 'key' => 'dimensions.max_load', 'label' => 'Max Rider Weight', 'unit' => 'lbs', 'higherBetter' => true ],
                    [ 'key' => 'dimensions.folded_length', 'label' => 'Folded Length', 'unit' => '"', 'higherBetter' => false ],
                    [ 'key' => 'dimensions.folded_width', 'label' => 'Folded Width', 'unit' => '"', 'higherBetter' => false ],
                    [ 'key' => 'dimensions.folded_height', 'label' => 'Folded Height', 'unit' => '"', 'higherBetter' => false ],
                    [ 'key' => 'dimensions.foldable_handlebars', 'label' => 'Foldable Bars', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'other.fold_location', 'label' => 'Fold Mechanism' ],
                    [ 'key' => 'speed_per_lb', 'label' => 'mph/lb', 'format' => 'decimal', 'higherBetter' => true, 'valueUnit' => 'mph/lb' ],
                    [ 'key' => 'wh_per_lb', 'label' => 'Wh/lb', 'format' => 'decimal', 'higherBetter' => true, 'valueUnit' => 'Wh/lb' ],
                    [ 'key' => 'tested_range_per_lb', 'label' => 'mi/lb', 'format' => 'decimal', 'higherBetter' => true, 'valueUnit' => 'mi/lb' ],
                ],
            ],

            'Safety' => [
                'icon'      => 'shield',
                'question'  => 'Is it safe to ride?',
                'showScore' => true,
                'scoreKey'  => 'safety',
                'specs'     => [
                    [ 'key' => 'brakes.front', 'label' => 'Front Brake', 'format' => 'brakeType', 'noWinner' => true ],
                    [ 'key' => 'brakes.rear', 'label' => 'Rear Brake', 'format' => 'brakeType', 'noWinner' => true ],
                    [ 'key' => 'brakes.regenerative', 'label' => 'Regen Braking', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'brake_distance', 'label' => 'Brake Distance', 'unit' => 'ft', 'higherBetter' => false ],
                    [ 'key' => 'lighting.lights', 'label' => 'Lights', 'format' => 'array' ],
                    [ 'key' => 'lighting.turn_signals', 'label' => 'Turn Signals', 'format' => 'boolean', 'higherBetter' => true ],
                ],
            ],

            'Features' => [
                'icon'      => 'settings',
                'question'  => 'What extras does it have?',
                'showScore' => true,
                'scoreKey'  => 'features',
                'specs'     => [
                    [ 'key' => 'features', 'label' => 'Features', 'format' => 'featureArray' ],
                    [ 'key' => 'other.display_type', 'label' => 'Display', 'format' => 'displayType' ],
                    [ 'key' => 'other.throttle_type', 'label' => 'Throttle' ],
                    [ 'key' => 'other.kickstand', 'label' => 'Kickstand', 'format' => 'boolean', 'higherBetter' => true ],
                ],
            ],

            'Maintenance' => [
                'icon'        => 'tool',
                'question'    => 'Is it hassle-free?',
                'showScore'   => true,
                'scoreKey'    => 'maintenance',
                'contextNote' => 'Score also factors in brake type (see Safety)',
                'specs'       => [
                    [ 'key' => 'wheels.tire_type', 'label' => 'Tire Type', 'format' => 'tire' ],
                    [ 'key' => 'wheels.self_healing', 'label' => 'Self-Healing Tires', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'other.ip_rating', 'label' => 'IP Rating', 'format' => 'ip', 'higherBetter' => true ],
                ],
            ],

            'Value Analysis' => [
                'icon'           => 'dollar-sign',
                'question'       => 'Am I getting good value?',
                'showScore'      => false,
                'isValueSection' => true,
                'specs'          => [
                    [ 'key' => 'value_metrics.{geo}.price_per_tested_mile', 'label' => '{symbol}/mi', 'higherBetter' => false, 'format' => 'currency', 'geoAware' => true, 'valueUnit' => '/mi' ],
                    [ 'key' => 'value_metrics.{geo}.price_per_mph', 'label' => '{symbol}/mph', 'higherBetter' => false, 'format' => 'currency', 'geoAware' => true, 'valueUnit' => '/mph' ],
                    [ 'key' => 'value_metrics.{geo}.price_per_watt', 'label' => '{symbol}/W', 'higherBetter' => false, 'format' => 'currency', 'geoAware' => true, 'valueUnit' => '/W' ],
                    [ 'key' => 'value_metrics.{geo}.price_per_wh', 'label' => '{symbol}/Wh', 'higherBetter' => false, 'format' => 'currency', 'geoAware' => true, 'valueUnit' => '/Wh' ],
                ],
            ],
        ];
    }
}

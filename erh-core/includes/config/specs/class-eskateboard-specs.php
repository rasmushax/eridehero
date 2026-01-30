<?php
/**
 * E-Skateboard Spec Groups
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
            'Performance' => [
                'icon'  => 'zap',
                'specs' => [
                    [ 'key' => 'manufacturer_top_speed', 'label' => 'Top Speed', 'unit' => 'mph', 'higherBetter' => true ],
                    [ 'key' => 'nominal_motor_wattage', 'label' => 'Motor Power', 'unit' => 'W', 'higherBetter' => true ],
                    [ 'key' => 'motor_count', 'label' => 'Motors', 'higherBetter' => true ],
                    [ 'key' => 'hill_climb_angle', 'label' => 'Hill Grade', 'unit' => '°', 'higherBetter' => true ],
                ],
            ],
            'Range & Battery' => [
                'icon'  => 'battery',
                'specs' => [
                    [ 'key' => 'manufacturer_range', 'label' => 'Claimed Range', 'unit' => 'mi', 'higherBetter' => true ],
                    [ 'key' => 'battery_capacity', 'label' => 'Battery', 'unit' => 'Wh', 'higherBetter' => true ],
                    [ 'key' => 'charge_time', 'label' => 'Charge Time', 'unit' => 'h', 'higherBetter' => false ],
                    [ 'key' => 'swappable_battery', 'label' => 'Swappable Battery', 'format' => 'boolean' ],
                ],
            ],
            'Build' => [
                'icon'  => 'box',
                'specs' => [
                    [ 'key' => 'weight', 'label' => 'Weight', 'unit' => 'lbs', 'higherBetter' => false ],
                    [ 'key' => 'max_weight_capacity', 'label' => 'Max Load', 'unit' => 'lbs', 'higherBetter' => true ],
                    [ 'key' => 'deck_type', 'label' => 'Deck Type' ],
                    [ 'key' => 'deck_length', 'label' => 'Deck Length', 'unit' => '"' ],
                    [ 'key' => 'wheel_type', 'label' => 'Wheel Type' ],
                ],
            ],
        ];
    }
}

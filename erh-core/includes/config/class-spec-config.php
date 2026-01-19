<?php
/**
 * Spec Configuration - Single Source of Truth
 *
 * Defines all product spec metadata in one place:
 * - Labels, units, tooltips
 * - Higher/lower better flags for comparison
 * - Format types (boolean, array, currency, etc.)
 * - Category groupings and icons
 *
 * Used by:
 * - Compare pages (PHP SSR + JS hydration)
 * - Product pages (spec display)
 * - Scoring system (which specs matter)
 *
 * @package ERH\Config
 */

namespace ERH\Config;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Centralized spec configuration.
 */
class SpecConfig {

    // =========================================================================
    // Constants - Ranking Arrays
    // =========================================================================

    /**
     * IP rating ranking (higher index = better).
     */
    public const IP_RATINGS = [
        'None', 'IPX4', 'IPX5', 'IP54', 'IP55', 'IP56', 'IP65', 'IP66', 'IP67', 'IP68',
    ];

    /**
     * Suspension type ranking (higher index = better).
     */
    public const SUSPENSION_TYPES = [
        'None', 'Front only', 'Rear only', 'Dual spring', 'Dual hydraulic', 'Full suspension',
    ];

    /**
     * Tire type ranking.
     */
    public const TIRE_TYPES = [
        'Solid', 'Honeycomb', 'Semi-pneumatic', 'Pneumatic', 'Tubeless pneumatic',
    ];

    /**
     * Brake type ranking (higher index = better).
     */
    public const BRAKE_TYPES = [
        'None', 'Foot', 'Drum', 'Disc (Mechanical)', 'Disc (Hydraulic)',
    ];

    /**
     * Display type ranking (higher index = better).
     */
    public const DISPLAY_TYPES = [
        'None', 'Unknown', 'LED', 'LCD', 'OLED', 'TFT',
    ];

    /**
     * Comparison thresholds.
     */
    public const TIE_THRESHOLD       = 3;  // 3% difference = tie
    public const ADVANTAGE_THRESHOLD = 5;  // 5% min for advantage list
    public const MAX_ADVANTAGES      = 5;  // Max advantages per product

    // =========================================================================
    // Advantage Specs - What to show when a product wins a category
    // =========================================================================

    /**
     * Primary and secondary specs to highlight for each category advantage.
     * Based on scoring algorithm weights - shows what actually drives the score.
     *
     * Structure:
     * - primary: The headline spec (most user-relatable)
     * - secondary: Supporting specs (shown if significantly different)
     * - headlines: Adjective headlines for different specs
     * - lowerBetter: Specs where lower values win
     */
    public const ADVANTAGE_SPECS = [
        'motor_performance' => [
            'primary'     => 'manufacturer_top_speed', // Most relatable to users
            'secondary'   => [ 'motor.power_peak', 'motor.motor_position' ],
            'headlines'   => [
                'manufacturer_top_speed' => 'Faster',
                'tested_top_speed'       => 'Faster',
                'motor.power_peak'       => 'More Powerful',
                'motor.power_nominal'    => 'More Powerful',
                'motor.motor_position'   => 'Dual Motors',
            ],
            'lowerBetter' => [],
        ],
        'range_battery' => [
            'primary'     => 'battery.capacity', // Based on scoring algo (70pts)
            'secondary'   => [ 'tested_range_regular', 'battery.charging_time' ],
            'headlines'   => [
                'battery.capacity'       => 'Bigger Battery',
                'tested_range_regular'   => 'More Range',
                'tested_range_fast'      => 'More Range',
                'battery.charging_time'  => 'Faster Charging',
            ],
            'lowerBetter' => [ 'battery.charging_time' ],
        ],
        'ride_quality' => [
            'primary'     => 'suspension.type', // Biggest factor (40pts)
            'secondary'   => [ 'wheels.tire_size_front', 'wheels.tire_type' ],
            'headlines'   => [
                'suspension.type'        => 'Smoother Ride',
                'wheels.tire_size_front' => 'Larger Tires',
                'wheels.tire_type'       => 'Better Tires',
            ],
            'lowerBetter' => [],
        ],
        'portability' => [
            'primary'     => 'dimensions.weight', // Dominant factor (60pts)
            'secondary'   => [ 'dimensions.max_load' ],
            'headlines'   => [
                'dimensions.weight'   => 'Lighter',
                'dimensions.max_load' => 'Higher Weight Capacity',
            ],
            'lowerBetter' => [ 'dimensions.weight' ],
        ],
        'safety' => [
            'primary'     => 'other.ip_rating', // Most user-relatable
            'secondary'   => [ 'brakes.front', 'lighting.turn_signals' ],
            'headlines'   => [
                'other.ip_rating'       => 'Better Protected',
                'brakes.front'          => 'Better Brakes',
                'lighting.turn_signals' => 'Turn Signals',
            ],
            'lowerBetter' => [],
        ],
        'features' => [
            'primary'     => 'features', // Feature count
            'secondary'   => [ 'other.display_type' ],
            'headlines'   => [
                'features'          => 'More Features',
                'other.display_type' => 'Better Display',
            ],
            'lowerBetter' => [],
        ],
        'maintenance' => [
            'primary'     => 'wheels.tire_type', // Dominant factor (45pts)
            'secondary'   => [ 'other.ip_rating', 'wheels.self_healing' ],
            'headlines'   => [
                'wheels.tire_type'    => 'Easier Maintenance',
                'other.ip_rating'     => 'Better Protected',
                'wheels.self_healing' => 'Self-Healing Tires',
            ],
            'lowerBetter' => [],
        ],
    ];

    /**
     * Spec display units for advantage formatting.
     */
    public const ADVANTAGE_UNITS = [
        'manufacturer_top_speed' => 'mph',
        'tested_top_speed'       => 'mph',
        'motor.power_peak'       => 'W',
        'motor.power_nominal'    => 'W',
        'battery.capacity'       => 'Wh',
        'battery.charging_time'  => 'hrs',
        'tested_range_regular'   => 'mi',
        'tested_range_fast'      => 'mi',
        'dimensions.weight'      => 'lbs',
        'dimensions.max_load'    => 'lbs',
        'wheels.tire_size_front' => '"',
    ];

    // =========================================================================
    // Category Weights
    // =========================================================================

    /**
     * Category weights for overall scoring.
     */
    public const CATEGORY_WEIGHTS = [
        'escooter' => [
            'Motor Performance'  => 0.20,
            'Range & Battery'    => 0.20,
            'Ride Quality'       => 0.20,
            'Portability & Fit'  => 0.15,
            'Safety'             => 0.10,
            'Features'           => 0.10,
            'Maintenance'        => 0.05,
        ],
        'ebike' => [
            'Motor & Power'        => 0.25,
            'Range & Battery'      => 0.25,
            'Speed & Performance'  => 0.20,
            'Build & Frame'        => 0.20,
            'Components'           => 0.10,
        ],
        'euc' => [
            'Performance'      => 0.35,
            'Range & Battery'  => 0.35,
            'Build'            => 0.30,
        ],
        'eskateboard' => [
            'Performance'      => 0.35,
            'Range & Battery'  => 0.35,
            'Build'            => 0.30,
        ],
        'hoverboard' => [
            'Performance'      => 0.30,
            'Range & Battery'  => 0.35,
            'Build'            => 0.35,
        ],
    ];

    // =========================================================================
    // Master Spec Groups
    // =========================================================================

    /**
     * Get spec groups for a category.
     *
     * This is the single source of truth for all spec metadata.
     *
     * @param string $category Category key (escooter, ebike, etc.).
     * @return array Spec groups configuration.
     */
    public static function get_spec_groups( string $category ): array {
        $groups = self::get_all_spec_groups();
        return $groups[ $category ] ?? [];
    }

    /**
     * Get all spec groups for all categories.
     *
     * @return array All spec groups by category.
     */
    private static function get_all_spec_groups(): array {
        return [
            'escooter' => self::get_escooter_specs(),
            'ebike'    => self::get_ebike_specs(),
            'euc'      => self::get_euc_specs(),
            'eskateboard' => self::get_eskateboard_specs(),
            'hoverboard'  => self::get_hoverboard_specs(),
        ];
    }

    /**
     * E-Scooter spec groups.
     */
    private static function get_escooter_specs(): array {
        return [
            'Motor Performance' => [
                'icon'      => 'zap',
                'question'  => 'How fast and powerful is it?',
                'showScore' => true,
                'scoreKey'  => 'motor_performance',
                'specs'     => [
                    [ 'key' => 'tested_top_speed', 'label' => 'Top Speed (Tested)', 'unit' => 'mph', 'higherBetter' => true, 'tooltip' => 'Our real-world test result with a 175 lbs rider' ],
                    [ 'key' => 'manufacturer_top_speed', 'label' => 'Top Speed (Claimed)', 'unit' => 'mph', 'higherBetter' => true, 'tooltip' => 'What the manufacturer claims' ],
                    [ 'key' => 'motor.power_nominal', 'label' => 'Nominal Power', 'unit' => 'W', 'higherBetter' => true, 'tooltip' => 'Combined if dual motors' ],
                    [ 'key' => 'motor.power_peak', 'label' => 'Peak Power', 'unit' => 'W', 'higherBetter' => true, 'tooltip' => 'Maximum output under load' ],
                    [ 'key' => 'motor.voltage', 'label' => 'Voltage', 'unit' => 'V', 'higherBetter' => true ],
                    [ 'key' => 'motor.motor_position', 'label' => 'Motor Config', 'tooltip' => 'Single rear, dual, etc.' ],
                    [ 'key' => 'hill_climbing', 'label' => 'Hill Climb (Tested)', 'unit' => 's', 'higherBetter' => false, 'tooltip' => 'Time to climb our 250ft test hill at 8% average grade. 175 lbs rider' ],
                    [ 'key' => 'max_incline', 'label' => 'Hill Grade (Claimed)', 'unit' => '°', 'higherBetter' => true, 'tooltip' => 'Maximum incline the manufacturer claims' ],
                    [ 'key' => 'acceleration_0_15_mph', 'label' => '0-15 mph (Tested)', 'unit' => 's', 'higherBetter' => false, 'tooltip' => 'Average across 10+ test runs. 175 lbs rider' ],
                    [ 'key' => 'acceleration_0_20_mph', 'label' => '0-20 mph (Tested)', 'unit' => 's', 'higherBetter' => false, 'tooltip' => 'Average across 10+ test runs. 175 lbs rider' ],
                    [ 'key' => 'acceleration_0_25_mph', 'label' => '0-25 mph (Tested)', 'unit' => 's', 'higherBetter' => false, 'tooltip' => 'Average across 10+ test runs. 175 lbs rider' ],
                    [ 'key' => 'acceleration_0_30_mph', 'label' => '0-30 mph (Tested)', 'unit' => 's', 'higherBetter' => false, 'tooltip' => 'Average across 10+ test runs. 175 lbs rider' ],
                    [ 'key' => 'acceleration_0_to_top', 'label' => '0-Top Speed (Tested)', 'unit' => 's', 'higherBetter' => false, 'tooltip' => 'Average across 10+ test runs. 175 lbs rider' ],
                ],
            ],

            'Range & Battery' => [
                'icon'      => 'battery',
                'question'  => 'How far can I go?',
                'showScore' => true,
                'scoreKey'  => 'range_battery',
                'specs'     => [
                    [ 'key' => 'tested_range_fast', 'label' => 'Range - Fast (Tested)', 'unit' => 'mi', 'higherBetter' => true, 'tooltip' => 'Range at high speed riding. 175 lbs rider' ],
                    [ 'key' => 'tested_range_regular', 'label' => 'Range - Regular (Tested)', 'unit' => 'mi', 'higherBetter' => true, 'tooltip' => 'Range at normal riding pace. 175 lbs rider' ],
                    [ 'key' => 'tested_range_slow', 'label' => 'Range - Slow (Tested)', 'unit' => 'mi', 'higherBetter' => true, 'tooltip' => 'Range in eco mode, prioritizing distance over speed. 175 lbs rider' ],
                    [ 'key' => 'manufacturer_range', 'label' => 'Range (Claimed)', 'unit' => 'mi', 'higherBetter' => true, 'tooltip' => 'What the manufacturer claims. Wh is often a better comparison metric' ],
                    [ 'key' => 'battery.capacity', 'label' => 'Battery Capacity', 'unit' => 'Wh', 'higherBetter' => true, 'tooltip' => 'Watt-hours = Voltage × Amp-hours' ],
                    [ 'key' => 'battery.ah', 'label' => 'Amp Hours', 'unit' => 'Ah', 'higherBetter' => true, 'tooltip' => 'Battery capacity in amp-hours' ],
                    [ 'key' => 'battery.voltage', 'label' => 'Battery Voltage', 'unit' => 'V', 'higherBetter' => true ],
                    [ 'key' => 'battery.charging_time', 'label' => 'Charge Time', 'unit' => 'h', 'higherBetter' => false, 'tooltip' => 'Time to full charge with the included charger' ],
                    [ 'key' => 'battery.battery_type', 'label' => 'Battery Type' ],
                ],
            ],

            'Ride Quality' => [
                'icon'      => 'smile',
                'question'  => 'Is it comfortable?',
                'showScore' => true,
                'scoreKey'  => 'ride_quality',
                'specs'     => [
                    [ 'key' => 'suspension.type', 'label' => 'Suspension', 'higherBetter' => true, 'format' => 'suspensionArray', 'tooltip' => 'Dual suspension wins, then hydraulic, spring, rubber' ],
                    [ 'key' => 'suspension.adjustable', 'label' => 'Adjustable Suspension', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'wheels.tire_type', 'label' => 'Tire Type', 'format' => 'tire' ],
                    [ 'key' => 'wheels.tire_size_front', 'label' => 'Front Tire Size', 'unit' => '"', 'higherBetter' => true ],
                    [ 'key' => 'wheels.tire_size_rear', 'label' => 'Rear Tire Size', 'unit' => '"', 'higherBetter' => true ],
                    [ 'key' => 'wheels.tire_width', 'label' => 'Tire Width', 'unit' => '"', 'higherBetter' => true ],
                    [ 'key' => 'dimensions.deck_length', 'label' => 'Deck Length', 'unit' => '"', 'higherBetter' => true ],
                    [ 'key' => 'dimensions.deck_width', 'label' => 'Deck Width', 'unit' => '"', 'higherBetter' => true ],
                    [ 'key' => 'dimensions.ground_clearance', 'label' => 'Ground Clearance', 'unit' => '"', 'tooltip' => 'More is not always better - depends on riding style' ],
                    [ 'key' => 'dimensions.handlebar_height_min', 'label' => 'Handlebar Height (min)', 'unit' => '"', 'tooltip' => 'Personal preference - depends on rider height' ],
                    [ 'key' => 'dimensions.handlebar_height_max', 'label' => 'Handlebar Height (max)', 'unit' => '"', 'tooltip' => 'Personal preference - depends on rider height' ],
                    [ 'key' => 'dimensions.handlebar_width', 'label' => 'Handlebar Width', 'unit' => '"', 'higherBetter' => true, 'tooltip' => 'Wider bars provide more control and stability' ],
                    [ 'key' => 'other.footrest', 'label' => 'Footrest', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'other.terrain', 'label' => 'Terrain Type' ],
                ],
            ],

            'Portability & Fit' => [
                'icon'      => 'box',
                'question'  => 'Does this scooter fit my life and body?',
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
                    [ 'key' => 'speed_per_lb', 'label' => 'mph/lb', 'format' => 'decimal', 'higherBetter' => true, 'tooltip' => 'Top speed divided by weight. Higher = more speed per pound of scooter', 'valueUnit' => 'mph/lb' ],
                    [ 'key' => 'wh_per_lb', 'label' => 'Wh/lb', 'format' => 'decimal', 'higherBetter' => true, 'tooltip' => 'Battery capacity divided by weight. Higher = more energy storage per pound', 'valueUnit' => 'Wh/lb' ],
                    [ 'key' => 'tested_range_per_lb', 'label' => 'mi/lb', 'format' => 'decimal', 'higherBetter' => true, 'tooltip' => 'Tested range divided by weight. Higher = more miles per pound of scooter', 'valueUnit' => 'mi/lb' ],
                ],
            ],

            'Safety' => [
                'icon'      => 'shield',
                'question'  => 'Is it safe to ride?',
                'showScore' => true,
                'scoreKey'  => 'safety',
                'specs'     => [
                    [ 'key' => 'brakes.front', 'label' => 'Front Brake', 'format' => 'brakeType', 'tooltip' => 'Best brake type depends on scooter speed and weight', 'noWinner' => true ],
                    [ 'key' => 'brakes.rear', 'label' => 'Rear Brake', 'format' => 'brakeType', 'tooltip' => 'Best brake type depends on scooter speed and weight', 'noWinner' => true ],
                    [ 'key' => 'brakes.regenerative', 'label' => 'Regen Braking', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'brake_distance', 'label' => 'Brake Distance (Tested)', 'unit' => 'ft', 'higherBetter' => false, 'tooltip' => 'Stopping distance from 15 mph. 175 lbs rider' ],
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
                    [ 'key' => 'wheels.tire_type', 'label' => 'Tire Type', 'format' => 'tire', 'tooltip' => 'Solid = no flats, Pneumatic = more comfort' ],
                    [ 'key' => 'wheels.self_healing', 'label' => 'Self-Healing Tires', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'other.ip_rating', 'label' => 'IP Rating', 'format' => 'ip', 'higherBetter' => true, 'tooltip' => 'Water/dust resistance' ],
                ],
            ],

            'Value Analysis' => [
                'icon'           => 'dollar-sign',
                'question'       => 'Am I getting good value?',
                'showScore'      => false,
                'isValueSection' => true,
                'specs'          => [
                    [ 'key' => 'value_metrics.{geo}.price_per_tested_mile', 'label' => '{symbol}/mi', 'higherBetter' => false, 'format' => 'currency', 'geoAware' => true, 'tooltip' => 'Price divided by tested range (regular). Lower = more miles for your money', 'valueUnit' => '/mi' ],
                    [ 'key' => 'value_metrics.{geo}.price_per_mph', 'label' => '{symbol}/mph', 'higherBetter' => false, 'format' => 'currency', 'geoAware' => true, 'tooltip' => 'Price divided by claimed top speed. Lower = more speed for your money', 'valueUnit' => '/mph' ],
                    [ 'key' => 'value_metrics.{geo}.price_per_watt', 'label' => '{symbol}/W', 'higherBetter' => false, 'format' => 'currency', 'geoAware' => true, 'tooltip' => 'Price divided by nominal motor power. Lower = more power for your money', 'valueUnit' => '/W' ],
                    [ 'key' => 'value_metrics.{geo}.price_per_wh', 'label' => '{symbol}/Wh', 'higherBetter' => false, 'format' => 'currency', 'geoAware' => true, 'tooltip' => 'Price divided by battery capacity (Wh). Lower = more energy storage for your money', 'valueUnit' => '/Wh' ],
                ],
            ],
        ];
    }

    /**
     * E-Bike spec groups.
     */
    private static function get_ebike_specs(): array {
        return [
            'Motor & Power' => [
                'icon'      => 'zap',
                'showScore' => true,
                'scoreKey'  => 'motor_performance',
                'specs'     => [
                    [ 'key' => 'e-bikes.motor.power_nominal', 'label' => 'Motor Power', 'unit' => 'W', 'higherBetter' => true ],
                    [ 'key' => 'e-bikes.motor.power_peak', 'label' => 'Peak Power', 'unit' => 'W', 'higherBetter' => true ],
                    [ 'key' => 'e-bikes.motor.torque', 'label' => 'Torque', 'unit' => 'Nm', 'higherBetter' => true ],
                    [ 'key' => 'e-bikes.motor.type', 'label' => 'Motor Type' ],
                    [ 'key' => 'e-bikes.motor.position', 'label' => 'Motor Position' ],
                ],
            ],
            'Range & Battery' => [
                'icon'      => 'battery',
                'showScore' => true,
                'scoreKey'  => 'range_battery',
                'specs'     => [
                    [ 'key' => 'e-bikes.battery.range_claimed', 'label' => 'Claimed Range', 'unit' => 'mi', 'higherBetter' => true ],
                    [ 'key' => 'e-bikes.battery.capacity', 'label' => 'Battery', 'unit' => 'Wh', 'higherBetter' => true ],
                    [ 'key' => 'e-bikes.battery.voltage', 'label' => 'Voltage', 'unit' => 'V', 'higherBetter' => true ],
                    [ 'key' => 'e-bikes.battery.removable', 'label' => 'Removable Battery', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'e-bikes.battery.charge_time', 'label' => 'Charge Time', 'unit' => 'h', 'higherBetter' => false ],
                ],
            ],
            'Speed & Performance' => [
                'icon'      => 'gauge',
                'showScore' => true,
                'specs'     => [
                    [ 'key' => 'e-bikes.performance.top_speed', 'label' => 'Top Speed', 'unit' => 'mph', 'higherBetter' => true ],
                    [ 'key' => 'e-bikes.performance.class', 'label' => 'Class' ],
                    [ 'key' => 'e-bikes.performance.pedal_assist_levels', 'label' => 'Assist Levels', 'higherBetter' => true ],
                    [ 'key' => 'e-bikes.performance.throttle', 'label' => 'Throttle', 'format' => 'boolean' ],
                ],
            ],
            'Build & Frame' => [
                'icon'      => 'box',
                'showScore' => true,
                'specs'     => [
                    [ 'key' => 'e-bikes.frame.weight', 'label' => 'Weight', 'unit' => 'lbs', 'higherBetter' => false ],
                    [ 'key' => 'e-bikes.frame.max_load', 'label' => 'Max Load', 'unit' => 'lbs', 'higherBetter' => true ],
                    [ 'key' => 'e-bikes.frame.material', 'label' => 'Frame Material' ],
                    [ 'key' => 'e-bikes.frame.type', 'label' => 'Frame Type' ],
                    [ 'key' => 'e-bikes.frame.suspension', 'label' => 'Suspension', 'format' => 'suspension' ],
                    [ 'key' => 'e-bikes.frame.foldable', 'label' => 'Foldable', 'format' => 'boolean' ],
                ],
            ],
            'Components' => [
                'icon'      => 'settings',
                'collapsed' => true,
                'specs'     => [
                    [ 'key' => 'e-bikes.components.gears', 'label' => 'Gears' ],
                    [ 'key' => 'e-bikes.components.brakes', 'label' => 'Brakes' ],
                    [ 'key' => 'e-bikes.components.wheel_size', 'label' => 'Wheel Size', 'unit' => '"' ],
                    [ 'key' => 'e-bikes.components.tire_type', 'label' => 'Tire Type' ],
                    [ 'key' => 'e-bikes.components.display', 'label' => 'Display' ],
                    [ 'key' => 'e-bikes.components.lights', 'label' => 'Lights' ],
                ],
            ],
        ];
    }

    /**
     * EUC spec groups.
     */
    private static function get_euc_specs(): array {
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

    /**
     * E-Skateboard spec groups.
     */
    private static function get_eskateboard_specs(): array {
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

    /**
     * Hoverboard spec groups.
     */
    private static function get_hoverboard_specs(): array {
        return [
            'Performance' => [
                'icon'  => 'zap',
                'specs' => [
                    [ 'key' => 'manufacturer_top_speed', 'label' => 'Top Speed', 'unit' => 'mph', 'higherBetter' => true ],
                    [ 'key' => 'nominal_motor_wattage', 'label' => 'Motor Power', 'unit' => 'W', 'higherBetter' => true ],
                    [ 'key' => 'hill_climb_angle', 'label' => 'Hill Grade', 'unit' => '°', 'higherBetter' => true ],
                ],
            ],
            'Range & Battery' => [
                'icon'  => 'battery',
                'specs' => [
                    [ 'key' => 'manufacturer_range', 'label' => 'Claimed Range', 'unit' => 'mi', 'higherBetter' => true ],
                    [ 'key' => 'battery_capacity', 'label' => 'Battery', 'unit' => 'Wh', 'higherBetter' => true ],
                    [ 'key' => 'charge_time', 'label' => 'Charge Time', 'unit' => 'h', 'higherBetter' => false ],
                ],
            ],
            'Build' => [
                'icon'  => 'box',
                'specs' => [
                    [ 'key' => 'weight', 'label' => 'Weight', 'unit' => 'lbs', 'higherBetter' => false ],
                    [ 'key' => 'max_weight_capacity', 'label' => 'Max Load', 'unit' => 'lbs', 'higherBetter' => true ],
                    [ 'key' => 'wheel_size', 'label' => 'Wheel Size', 'unit' => '"' ],
                    [ 'key' => 'ul_certified', 'label' => 'UL Certified', 'format' => 'boolean', 'higherBetter' => true ],
                ],
            ],
        ];
    }

    // =========================================================================
    // Export Methods
    // =========================================================================

    /**
     * Export spec groups for JavaScript injection.
     *
     * Returns JSON-encodable array matching the JS SPEC_GROUPS format.
     *
     * @param string $category Category key.
     * @return array Spec groups ready for JSON encoding.
     */
    public static function export_for_js( string $category ): array {
        return self::get_spec_groups( $category );
    }

    /**
     * Export full compare config for JavaScript.
     *
     * Includes spec groups, category weights, ranking arrays, and thresholds.
     *
     * @param string $category Category key.
     * @return array Full config for JS.
     */
    public static function export_compare_config( string $category ): array {
        return [
            'specGroups'         => self::get_spec_groups( $category ),
            'categoryWeights'    => self::CATEGORY_WEIGHTS[ $category ] ?? [],
            'ipRatings'          => self::IP_RATINGS,
            'suspensionTypes'    => self::SUSPENSION_TYPES,
            'tireTypes'          => self::TIRE_TYPES,
            'brakeTypes'         => self::BRAKE_TYPES,
            'displayTypes'       => self::DISPLAY_TYPES,
            'tieThreshold'       => self::TIE_THRESHOLD,
            'advantageThreshold' => self::ADVANTAGE_THRESHOLD,
            'maxAdvantages'      => self::MAX_ADVANTAGES,
        ];
    }

    /**
     * Get category weights for a category.
     *
     * @param string $category Category key.
     * @return array Category weights.
     */
    public static function get_category_weights( string $category ): array {
        return self::CATEGORY_WEIGHTS[ $category ] ?? [];
    }

    /**
     * Get score key for a category name.
     *
     * @param string $category    Product category (escooter, ebike, etc.).
     * @param string $group_name  Group name (e.g., 'Motor Performance').
     * @return string|null Score key (e.g., 'motor_performance') or null.
     */
    public static function get_score_key( string $category, string $group_name ): ?string {
        $groups = self::get_spec_groups( $category );
        return $groups[ $group_name ]['scoreKey'] ?? null;
    }

    // =========================================================================
    // Product Page Config
    // =========================================================================

    /**
     * Get spec groups formatted for product page display.
     *
     * This returns a simplified view suitable for product pages,
     * with tooltips included where defined.
     *
     * @param string $category Category key.
     * @return array Spec groups for product page.
     */
    public static function get_product_display_config( string $category ): array {
        $groups = self::get_spec_groups( $category );
        $result = [];

        foreach ( $groups as $name => $group ) {
            // Skip value analysis for product pages.
            if ( ! empty( $group['isValueSection'] ) ) {
                continue;
            }

            $result[ $name ] = [
                'icon'  => $group['icon'] ?? 'info',
                'specs' => array_map( function( $spec ) {
                    return [
                        'key'     => $spec['key'],
                        'label'   => $spec['label'],
                        'unit'    => $spec['unit'] ?? null,
                        'format'  => $spec['format'] ?? null,
                        'tooltip' => $spec['tooltip'] ?? null,
                    ];
                }, $group['specs'] ),
            ];
        }

        return $result;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Get all supported categories.
     *
     * @return array List of category keys.
     */
    public static function get_supported_categories(): array {
        return array_keys( self::get_all_spec_groups() );
    }

    /**
     * Check if a category is supported.
     *
     * @param string $category Category key.
     * @return bool True if supported.
     */
    public static function is_supported( string $category ): bool {
        return in_array( $category, self::get_supported_categories(), true );
    }
}

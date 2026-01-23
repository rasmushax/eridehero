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
     *
     * Comparison rules:
     * 1. Water rating (second digit) is primary - higher wins
     * 2. If water equal, having dust rating (IP) beats no dust (IPX)
     *
     * Examples: IPX5 > IP54 (water 5 > 4), IP55 > IPX5 (both water 5, but IP55 has dust)
     *
     * NOTE: Use erh_normalize_ip_rating() for actual comparisons - it returns
     * a composite score: water*10 + (has_dust ? 1 : 0)
     */
    public const IP_RATINGS = [
        'None',   // score: 0
        'IPX4',   // score: 40
        'IP54',   // score: 41
        'IPX5',   // score: 50
        'IP55',   // score: 51
        'IPX6',   // score: 60
        'IP56',   // score: 61
        'IP65',   // score: 51 (rarely used)
        'IP66',   // score: 61
        'IP67',   // score: 71
        'IP68',   // score: 81
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
    // Spec-Based Comparison - Independent spec advantages (Versus.com style)
    // =========================================================================

    /**
     * Minimum percentage difference to show as an advantage.
     * Set to 0 - a winner is a winner, any difference matters.
     */
    public const SPEC_ADVANTAGE_THRESHOLD = 0;

    // =========================================================================
    // Centralized Tooltips - Single Source of Truth
    // =========================================================================

    /**
     * Centralized tooltip definitions for all specs.
     *
     * Tooltip Tiers:
     * - methodology: Full explanation of the spec/test protocol (default)
     * - comparison: Why this matters when comparing products
     *
     * Fallback chain: comparison → methodology
     *
     * Weight notation: Always use "175-lb rider" (hyphenated compound adjective)
     */
    public const TOOLTIPS = [
        // =====================================================================
        // Motor Performance
        // =====================================================================
        'tested_top_speed' => [
            'methodology' => 'GPS-verified max speed on flat ground, averaged between two opposite runs. 175-lb rider, 80%+ battery.',
            'comparison'  => 'Higher top speed means faster travel and more overtaking ability.',
        ],
        'manufacturer_top_speed' => [
            'methodology' => 'Speed claimed by manufacturer. Actual results vary by rider weight and conditions.',
        ],
        'motor.power_nominal' => [
            'methodology' => 'Continuous power the motor can sustain without overheating. Combined total for dual motors.',
            'comparison'  => 'More motor power provides better hill climbing and acceleration.',
        ],
        'motor.power_peak' => [
            'methodology' => 'Peak power for brief periods (hills, acceleration). Limited duration to prevent overheating.',
        ],
        'motor.voltage' => [
            'methodology' => 'Motor operating voltage. Higher voltage typically means more power and efficiency.',
        ],
        'motor.motor_position' => [
            'methodology' => 'Location and configuration of the motor(s). Hub motors are in the wheels, belt drives use external motors.',
            'comparison'  => 'Dual motors provide better traction and hill climbing vs single motor.',
        ],
        'motor.motor_count' => [
            'methodology' => 'Single motor is lighter and more efficient. Dual motors provide more power and traction.',
            'comparison'  => 'Dual motors provide better traction, acceleration, and hill climbing compared to single motor.',
        ],
        'hill_climbing' => [
            'methodology' => 'Average speed climbing 250 ft at 8% grade from kick-off start. Average of 5+ runs. 175-lb rider, 80%+ battery.',
            'comparison'  => 'Higher hill climb speed means better real-world climbing performance.',
        ],
        'max_incline' => [
            'methodology' => 'Maximum incline angle the manufacturer claims the scooter can climb. Actual results depend on rider weight and battery level.',
        ],
        'acceleration_0_15_mph' => [
            'methodology' => 'Median time from standstill to 15 mph over 10+ runs. Max acceleration setting. 175-lb rider, 80%+ battery.',
            'comparison'  => 'Faster acceleration means quicker starts from lights and stops.',
        ],
        'acceleration_0_20_mph' => [
            'methodology' => 'Median time from standstill to 20 mph over 10+ runs. Max acceleration setting. 175-lb rider, 80%+ battery.',
            'comparison'  => 'Faster acceleration means quicker starts from lights and stops.',
        ],
        'acceleration_0_25_mph' => [
            'methodology' => 'Median time from standstill to 25 mph over 10+ runs. Max acceleration setting. 175-lb rider, 80%+ battery.',
            'comparison'  => 'Faster acceleration means quicker starts from lights and stops.',
        ],
        'acceleration_0_30_mph' => [
            'methodology' => 'Median time from standstill to 30 mph over 10+ runs. Max acceleration setting. 175-lb rider, 80%+ battery.',
            'comparison'  => 'Faster acceleration means quicker starts from lights and stops.',
        ],
        'acceleration_0_to_top' => [
            'methodology' => 'Median time from standstill to top speed over 10+ runs. Max acceleration setting. 175-lb rider, 80%+ battery.',
            'comparison'  => 'Faster acceleration means quicker starts from lights and stops.',
        ],

        // =====================================================================
        // Range & Battery
        // =====================================================================
        'tested_range_fast' => [
            'methodology' => 'Real-world range on mixed terrain (city, country, minor hills). Speed priority riding from 100% to empty. 175-lb rider.',
            'comparison'  => 'More range means you can travel further on a single charge.',
        ],
        'tested_range_regular' => [
            'methodology' => 'Real-world range on mixed terrain (city, country, minor hills). Moderate speed riding from 100% to empty. 175-lb rider.',
            'comparison'  => 'More range means you can travel further on a single charge.',
        ],
        'tested_range_slow' => [
            'methodology' => 'Real-world range on mixed terrain (city, country, minor hills). Range priority riding (eco mode) from 100% to empty. 175-lb rider.',
            'comparison'  => 'More range means you can travel further on a single charge.',
        ],
        'manufacturer_range' => [
            'methodology' => 'Range claimed by manufacturer. Actual range varies significantly based on rider weight, terrain, and riding style.',
        ],
        'battery.capacity' => [
            'methodology' => 'Total energy storage in Watt-hours (Voltage × Amp-hours). Larger battery = longer range but more weight.',
            'comparison'  => 'Larger battery capacity means more energy stored, which directly translates to longer range potential.',
        ],
        'battery.ah' => [
            'methodology' => 'Battery capacity in amp-hours. Multiply by voltage to get Wh.',
        ],
        'battery.voltage' => [
            'methodology' => 'Battery voltage affects power delivery and efficiency. Higher voltage systems can deliver more power.',
            'comparison'  => 'Higher voltage delivers more consistent power output, especially under load and on hills.',
        ],
        'battery.charging_time' => [
            'methodology' => 'Time to charge from empty to full using the included charger. Fast chargers sold separately may reduce this.',
            'comparison'  => 'Faster charging means less downtime between rides.',
        ],
        'battery.battery_type' => [
            'methodology' => 'Battery chemistry. Li-ion is standard. LiFePO4 lasts longer but weighs more.',
        ],

        // =====================================================================
        // Ride Quality
        // =====================================================================
        'suspension.type' => [
            'methodology' => 'Suspension absorbs bumps and vibrations. Hydraulic provides best damping, spring is good, rubber is basic.',
            'comparison'  => 'Better suspension provides a smoother, more comfortable ride on rough roads.',
        ],
        'suspension.adjustable' => [
            'methodology' => 'Adjustable preload or damping lets you tune firmness for your weight and riding style.',
            'comparison'  => 'Adjustable suspension lets you tune the ride for your weight and preferences.',
        ],
        'wheels.tire_type' => [
            'methodology' => 'Pneumatic (air-filled) tires provide the smoothest ride. Solid tires are maintenance-free but harsher.',
            'comparison'  => 'Pneumatic tires absorb bumps better for a smoother ride.',
        ],
        'wheels.tire_size_front' => [
            'methodology' => 'Front tire diameter in inches. Larger tires handle cracks and bumps better.',
            'comparison'  => 'Larger tires roll over cracks and bumps more easily, providing better stability and comfort.',
        ],
        'wheels.tire_size_rear' => [
            'methodology' => 'Rear tire diameter in inches. May differ from front on some models.',
        ],
        'wheels.tire_width' => [
            'methodology' => 'Tire width affects grip and stability. Wider tires provide more contact with the road.',
            'comparison'  => 'Wider tires provide more stability and grip.',
        ],
        'wheels.pneumatic_type' => [
            'methodology' => 'Tubed tires are easier to repair but can pinch flat. Tubeless resist punctures better.',
            'comparison'  => 'Tubeless tires are more resistant to punctures.',
        ],
        'wheels.self_healing' => [
            'methodology' => 'Tires contain sealant that automatically plugs small punctures from glass, thorns, etc.',
            'comparison'  => 'Self-healing tires automatically seal small punctures, reducing flat risk.',
        ],
        'dimensions.deck_length' => [
            'methodology' => 'Length of the standing platform. Longer decks give more room for repositioning your feet.',
            'comparison'  => 'Longer deck gives more room for foot positioning.',
        ],
        'dimensions.deck_width' => [
            'methodology' => 'Width of the standing platform. Wider decks feel more stable.',
            'comparison'  => 'Wider deck provides more stable footing.',
        ],
        'dimensions.ground_clearance' => [
            'methodology' => 'Height from ground to the lowest point (usually deck or motor). Important for curbs and speed bumps.',
        ],
        'dimensions.handlebar_height_min' => [
            'methodology' => 'Minimum handlebar height from ground. Best height depends on rider height and preference.',
        ],
        'dimensions.handlebar_height_max' => [
            'methodology' => 'Maximum handlebar height from ground. Best height depends on rider height and preference.',
        ],
        'dimensions.handlebar_width' => [
            'methodology' => 'Width between handlebar grips. Wider bars provide more leverage for steering.',
            'comparison'  => 'Wider bars provide more control and stability.',
        ],
        'other.footrest' => [
            'methodology' => 'Rear pegs for a standing passenger. Check local laws for legality.',
        ],
        'other.terrain' => [
            'methodology' => 'Recommended terrain based on tire type, suspension, and ground clearance.',
        ],

        // =====================================================================
        // Portability & Fit
        // =====================================================================
        'dimensions.weight' => [
            'methodology' => 'Total weight including battery. Heavier scooters are harder to carry but often more stable.',
            'comparison'  => 'Lighter scooters are easier to carry upstairs, onto public transit, or into your office.',
        ],
        'dimensions.max_load' => [
            'methodology' => 'Maximum recommended rider weight. Exceeding may reduce performance and void warranty.',
            'comparison'  => 'Higher weight capacity accommodates heavier riders.',
        ],
        'dimensions.folded_length' => [
            'methodology' => 'Length when folded. Important for fitting in car trunks or storage spaces.',
            'comparison'  => 'Smaller folded size is easier to store and transport.',
        ],
        'dimensions.folded_width' => [
            'methodology' => 'Width when folded. Usually same as unfolded (handlebar width).',
        ],
        'dimensions.folded_height' => [
            'methodology' => 'Height when folded. Depends on folding mechanism and handlebar design.',
        ],
        'dimensions.foldable_handlebars' => [
            'methodology' => 'Handlebars fold down for more compact storage. Reduces folded height significantly.',
            'comparison'  => 'Foldable handlebars make the scooter more compact for storage.',
        ],
        'other.fold_location' => [
            'methodology' => 'Where the scooter folds. Stem-fold is most common. Some fold at the deck.',
        ],
        'folded_footprint' => [
            'methodology' => 'Floor space when folded (length × width). Important for closets, car trunks, and under-desk storage.',
            'comparison'  => 'Smaller folded footprint is easier to fit in car trunks, closets, or under desks.',
        ],
        'speed_per_lb' => [
            'methodology' => 'Top speed divided by weight. Measures performance relative to weight.',
            'comparison'  => 'Higher ratio means more speed per pound of scooter.',
        ],
        'wh_per_lb' => [
            'methodology' => 'Battery capacity divided by weight. Measures energy efficiency relative to weight.',
            'comparison'  => 'Higher ratio means more energy storage per pound of scooter.',
        ],
        'tested_range_per_lb' => [
            'methodology' => 'Tested range divided by weight. Measures real-world range efficiency.',
            'comparison'  => 'Higher ratio means more miles per pound of scooter.',
        ],

        // =====================================================================
        // Safety
        // =====================================================================
        'brakes.front' => [
            'methodology' => 'Front brake type. Disc brakes offer more stopping power. Drum brakes are sealed and low-maintenance.',
        ],
        'brakes.rear' => [
            'methodology' => 'Rear brake type. Some scooters use foot brake, electronic brake, or same type as front.',
        ],
        'brakes.regenerative' => [
            'methodology' => 'Electronic braking that recovers energy back to the battery. Extends range slightly and reduces brake wear.',
        ],
        'brake_distance' => [
            'methodology' => 'Average stopping distance from 15 mph with all brakes applied (max force, no lockup). Average of 10+ runs. 175-lb rider.',
            'comparison'  => 'Shorter stopping distance means better safety in emergency situations.',
        ],
        'lighting.lights' => [
            'methodology' => 'Built-in lights for visibility. Front headlight, rear brake light, and deck lights are common.',
        ],
        'lighting.turn_signals' => [
            'methodology' => 'Built-in turn signal indicators. Usually on handlebars or rear of deck.',
            'comparison'  => 'Turn signals improve visibility and safety when changing lanes.',
        ],
        'other.ip_rating' => [
            'methodology' => 'IP rating indicates dust and water resistance. First digit = dust (6 = dustproof), second = water (4 = splash, 5 = jets, 6 = powerful jets, 7 = immersion).',
            'comparison'  => 'Higher IP rating means better protection from water and dust damage.',
        ],

        // =====================================================================
        // Features
        // =====================================================================
        'features' => [
            'methodology' => 'Built-in features beyond the basics. Common features include app connectivity, cruise control, speed modes, and alarm.',
            'comparison'  => 'More features like app connectivity, cruise control, and speed modes add convenience.',
        ],
        'other.display_type' => [
            'methodology' => 'Type of dashboard display. LCD and OLED are easier to read in sunlight. LED is basic.',
            'comparison'  => 'Better displays show more information and are easier to read in sunlight.',
        ],
        'other.throttle_type' => [
            'methodology' => 'Throttle style. Trigger and thumb are most common. Twist grip is rare on scooters.',
        ],
        'other.kickstand' => [
            'methodology' => 'Built-in kickstand for parking. Side-mount is standard, center-mount is more stable.',
        ],

        // =====================================================================
        // Maintenance
        // =====================================================================
        'maintenance.tire_type' => [
            'methodology' => 'Maintenance level based on tire type. Solid = zero maintenance. Tubeless = occasional pressure checks. Tubed = higher puncture risk.',
            'comparison'  => 'Solid and tubeless tires require less maintenance than pneumatic tires with tubes.',
        ],
        'maintenance.brake_type' => [
            'methodology' => 'Maintenance level based on brake type. Drum = sealed and maintenance-free. Disc = occasional pad replacement and adjustment.',
            'comparison'  => 'Drum brakes are sealed and maintenance-free. Disc brakes need pad replacements and adjustments.',
        ],

        // =====================================================================
        // Composite Categories (for product analysis)
        // =====================================================================
        '_ride_quality' => [
            'methodology' => 'Combined ride comfort score based on suspension quality, tire size, and tire type.',
            'comparison'  => 'Ride comfort based on suspension, tire size, and tire type. Better = smoother ride over bumps.',
        ],
        '_portability' => [
            'methodology' => 'Combined portability score based on weight and folded dimensions.',
            'comparison'  => 'Portability combines weight and folded size. Lighter and smaller = easier to carry and store.',
        ],
        '_safety' => [
            'methodology' => 'Combined safety score based on tire grip (pneumatic vs solid) and braking performance.',
            'comparison'  => 'Safety based on tire type (pneumatic = better grip) and stopping distance.',
        ],
        '_maintenance' => [
            'methodology' => 'Combined maintenance score based on tire type, brake type, and water resistance.',
            'comparison'  => 'Lower maintenance based on tire type and brake type. Solid tires and drum brakes need the least maintenance.',
        ],

        // =====================================================================
        // Value Metrics (geo-specific)
        // =====================================================================
        'value_metrics.price_per_wh' => [
            'methodology' => 'Price divided by battery capacity. Shows how much you pay per Wh of battery.',
            'comparison'  => 'Lower cost per Wh means better battery value for your money.',
        ],
        'value_metrics.price_per_watt' => [
            'methodology' => 'Price divided by motor power. Shows how much you pay per Watt of power.',
            'comparison'  => 'Lower cost per Watt means better motor value for your money.',
        ],
        'value_metrics.price_per_tested_mile' => [
            'methodology' => 'Price divided by tested range. Shows how much you pay per mile of range.',
            'comparison'  => 'Lower cost per mile means better range value for your money.',
        ],
        'value_metrics.price_per_mph' => [
            'methodology' => 'Price divided by top speed. Shows how much you pay per mph of speed.',
            'comparison'  => 'Lower cost per mph means better speed value for your money.',
        ],

        // =====================================================================
        // Score Categories
        // =====================================================================
        'overall_score' => [
            'methodology' => 'ERideHero Score based on performance, value, build quality, and features. Weighted average of all category scores.',
        ],
        'motor_performance' => [
            'methodology' => 'Category score based on top speed, motor power, and acceleration test results.',
            'comparison'  => 'Motor performance based on top speed, motor power, and acceleration.',
        ],
        'range_battery' => [
            'methodology' => 'Category score based on tested range, battery capacity, and charge time.',
            'comparison'  => 'Range score based on tested range, battery capacity, and charge time.',
        ],
        'ride_quality' => [
            'methodology' => 'Category score based on suspension type, tire size, tire type, and deck dimensions.',
            'comparison'  => 'Ride quality based on suspension, tires, and deck dimensions.',
        ],
        'portability' => [
            'methodology' => 'Category score based on weight, folded dimensions, and foldable handlebars.',
            'comparison'  => 'Portability based on weight, folded dimensions, and foldable handlebars.',
        ],
        'safety' => [
            'methodology' => 'Category score based on brake type, tested stopping distance, and lighting.',
            'comparison'  => 'Safety based on brake type, stopping distance, and lighting.',
        ],
        'maintenance' => [
            'methodology' => 'Category score based on tire type, brake type, and water resistance rating.',
            'comparison'  => 'Maintenance based on tire type, brake type, and water resistance.',
        ],
    ];

    /**
     * Maximum advantages per product.
     */
    public const SPEC_ADVANTAGE_MAX = 10;

    /**
     * Individual specs for comparison with independent winners.
     *
     * Each spec can have a different winner - not grouped by category.
     * Format: "X mph faster top speed" not "23 mph vs 22 mph"
     *
     * Structure:
     * - key: ACF field path (dot notation)
     * - label: Human-readable label for the spec
     * - unit: Unit string (mph, W, V, etc.)
     * - diffFormat: How to phrase the difference ('faster', 'more', 'higher', 'lighter')
     * - higherBetter: Whether higher values win
     * - tooltip: Explanation of why this spec matters (optional)
     * - minDiff: Minimum absolute difference to show (optional, overrides percentage)
     * - category: Grouping for future use (motor, battery, etc.)
     */
    public const COMPARISON_SPECS = [
        // Motor Performance Specs
        // Tooltips auto-enriched from TOOLTIPS constant via get_comparison_specs().
        'tested_top_speed' => [
            'label'        => 'tested top speed',
            'unit'         => 'mph',
            'diffFormat'   => 'faster',
            'higherBetter' => true,
            'category'     => 'motor',
            'priority'     => 1,
        ],
        'manufacturer_top_speed' => [
            'label'        => 'top speed',
            'unit'         => 'mph',
            'diffFormat'   => 'faster',
            'higherBetter' => true,
            'category'     => 'motor',
            'priority'     => 2,
            'fallbackFor'  => 'tested_top_speed', // Only show if tested_top_speed not available
        ],
        'motor.power_nominal' => [
            'label'        => 'motor power',
            'unit'         => 'W',
            'diffFormat'   => 'more',
            'higherBetter' => true,
            'category'     => 'motor',
            'priority'     => 3,
            'minDiff'      => 50, // At least 50W difference to be meaningful
        ],
        'motor.motor_count' => [
            'label'        => 'motors',
            'unit'         => '',
            'diffFormat'   => 'dual_vs_single', // Special format
            'higherBetter' => true,
            'category'     => 'motor',
            'priority'     => 4,
        ],
        'battery.voltage' => [
            'label'        => 'battery voltage',
            'unit'         => 'V',
            'diffFormat'   => 'higher',
            'higherBetter' => true,
            'category'     => 'motor',
            'priority'     => 5,
            'minDiff'      => 6, // At least 6V difference (e.g., 48V vs 36V)
        ],

        // Battery & Range Specs
        'battery.capacity' => [
            'label'        => 'battery capacity',
            'unit'         => 'Wh',
            'diffFormat'   => 'larger',
            'higherBetter' => true,
            'category'     => 'battery',
            'priority'     => 10,
            'minDiff'      => 50, // At least 50Wh difference to be meaningful
        ],
        'tested_range_regular' => [
            'label'        => 'tested range',
            'unit'         => 'miles',
            'diffFormat'   => 'longer',
            'higherBetter' => true,
            'category'     => 'battery',
            'priority'     => 11,
        ],

        // Ride Quality - Composite category
        // If one product wins by >5 points, show "Better ride quality" consolidated
        // Loser gets individual bullets for specs they win
        // If close (<5 points), show all individual specs
        '_ride_quality' => [
            'type'         => 'composite',
            'scoreKey'     => 'ride_quality',  // Category score key
            'label'        => 'ride quality',
            'diffFormat'   => 'better',
            'threshold'    => 5,  // Points difference to declare clear winner
            'specs'        => [
                'suspension.type',
                'wheels.tire_size_front',
                'wheels.tire_type',
            ],
            'category'     => 'ride',
            'priority'     => 20,
        ],

        // Individual ride quality specs (used by composite or shown granularly)
        // Note: suspension.type requires normalization (raw value is array like ["Front hydraulic", "Rear spring"])
        // Normalizer returns numeric score for comparison; display uses erh_format_suspension_display()
        'suspension.type' => [
            'label'        => 'suspension',
            'unit'         => '',
            'diffFormat'   => 'suspension',  // Special format - uses display formatter
            'higherBetter' => true,
            'category'     => 'ride',
            'priority'     => 21,
            'normalizer'   => 'erh_normalize_suspension_level', // Returns numeric score for comparison
            'displayFormatter' => 'erh_format_suspension_display', // For human-readable output
            'minDiff'      => 3, // At least 3 points difference (e.g., rubber vs spring)
        ],
        'wheels.tire_size_front' => [
            'label'        => 'tires',
            'unit'         => '"',
            'diffFormat'   => 'larger',
            'higherBetter' => true,
            'category'     => 'ride',
            'priority'     => 22,
            'minDiff'      => 0.5,  // At least 0.5" difference
        ],
        // Note: wheels.tire_type requires normalization (combines tire_type + pneumatic_type + self_healing)
        'wheels.tire_type' => [
            'label'        => 'tire type',
            'unit'         => '',
            'diffFormat'   => 'tire_type',  // Special format for ride quality context
            'higherBetter' => true,
            'category'     => 'ride',
            'priority'     => 23,
            'normalizer'   => 'erh_normalize_tire_type', // Combines multiple fields
            'normalizerFullSpecs' => true, // Normalizer receives full specs array, not just raw value
            'ranking'      => [ 'Solid', 'Mixed', 'Self-healing', 'Pneumatic', 'Tubeless' ],
        ],

        // =====================================================================
        // Portability Specs (Composite)
        // If one scooter wins both weight AND footprint → consolidated "More Portable"
        // If split → granular for each
        // =====================================================================

        '_portability' => [
            'type'         => 'composite',
            'scoreKey'     => 'portability',
            'label'        => 'portable',
            'diffFormat'   => 'more',
            'threshold'    => 0,  // Any difference counts
            'specs'        => [
                'dimensions.weight',
                'folded_footprint',  // Calculated: length × width × height
            ],
            'category'     => 'portability',
            'priority'     => 30,
        ],
        'dimensions.weight' => [
            'label'        => '',  // No label needed, "lighter" is self-explanatory
            'unit'         => 'lbs',
            'diffFormat'   => 'lighter',
            'higherBetter' => false,  // Lower weight is better
            'category'     => 'portability',
            'priority'     => 31,
        ],
        'dimensions.max_load' => [
            'label'        => 'weight capacity',
            'unit'         => 'lbs',
            'diffFormat'   => 'higher',
            'higherBetter' => true,
            'category'     => 'portability',
            'priority'     => 32,
            'minDiff'      => 10,  // At least 10 lbs difference to be meaningful
        ],
        'wh_per_lb' => [
            'label'        => 'Wh/lb ratio',
            'unit'         => 'Wh/lb',
            'diffFormat'   => 'better',
            'higherBetter' => true,
            'category'     => 'portability',
            'priority'     => 33,
            'isDerived'    => true,  // Calculated from other specs
            'format'       => 'decimal',
        ],
        'speed_per_lb' => [
            'label'        => 'mph/lb ratio',
            'unit'         => 'mph/lb',
            'diffFormat'   => 'better',
            'higherBetter' => true,
            'category'     => 'portability',
            'priority'     => 34,
            'isDerived'    => true,  // Calculated from other specs
            'format'       => 'decimal',
        ],
        'folded_footprint' => [
            'label'        => 'when folded',
            'unit'         => '',
            'diffFormat'   => 'smaller',
            'higherBetter' => false,  // Smaller footprint is better
            'category'     => 'portability',
            'priority'     => 35,
            'normalizer'   => 'erh_calculate_folded_footprint',
            'normalizerFullSpecs' => true,
            'displayFormatter' => 'erh_format_folded_footprint',
        ],

        // =====================================================================
        // SAFETY SPECS
        // =====================================================================

        // Safety - Composite category
        // If one product wins by >3 points, show "Safer" consolidated
        // Loser gets individual bullets for specs they win
        // If close (<3 points), show all individual specs
        // NOTE: Tire size excluded - already shown in ride quality
        '_safety' => [
            'type'         => 'composite',
            'scoreKey'     => 'safety',  // Category score key
            'label'        => 'safer',
            'diffFormat'   => 'safer',   // "Safer" headline
            'threshold'    => 3,         // Points difference to declare clear winner
            'specs'        => [
                'safety.tire_type',
                'brake_distance',
            ],
            'category'     => 'safety',
            'priority'     => 38,
        ],

        // Safety tire type - OPPOSITE of maintenance ranking.
        // For safety: pneumatic > semi-pneumatic > solid (grip, braking, comfort)
        'safety.tire_type' => [
            'label'        => 'tires',
            'unit'         => '',
            'diffFormat'   => 'safer_tires',  // "Safer tires" headline
            'higherBetter' => true,
            'category'     => 'safety',
            'priority'     => 39,
            'normalizer'   => 'erh_normalize_tire_type_for_safety',
            'normalizerFullSpecs' => true,
            // Safety ranking: Pneumatic/Tubeless = best grip, solid = worst
            'ranking'      => [ 'Solid', 'Mixed', 'Tubeless', 'Pneumatic' ],
        ],

        // NOTE: Tire size for safety removed - already covered by ride quality's wheels.tire_size_front

        'other.ip_rating' => [
            'label'        => 'water resistance',
            'unit'         => '',
            'diffFormat'   => 'water_resistance',  // "Higher water resistance"
            'higherBetter' => true,
            'category'     => 'maintenance',  // Moved from safety - IP prevents water damage maintenance
            'priority'     => 58,             // Between maintenance composite and individual specs
            'normalizer'        => 'erh_normalize_ip_rating',  // Extract numeric score for comparison
            'displayFormatter'  => 'erh_format_ip_rating_display',  // Show raw IP rating for display
            'requireValidPair'  => true,  // Skip if either value is none/unknown (normalized to 0)
        ],
        'brake_distance' => [
            'label'        => 'stopping distance',
            'unit'         => 'ft',
            'diffFormat'   => 'shorter',
            'higherBetter' => false,  // Shorter stopping distance is better
            'category'     => 'safety',
            'priority'     => 41,
            'minDiff'      => 1,  // At least 1 foot difference
        ],
        'lighting.turn_signals' => [
            'label'        => 'turn signals',
            'unit'         => '',
            'diffFormat'   => 'has_feature',  // Binary: has turn signals vs doesn't
            'higherBetter' => true,
            'category'     => 'safety',
            'priority'     => 42,
        ],

        // =====================================================================
        // FEATURES SPECS
        // =====================================================================

        'features' => [
            'label'        => 'features',
            'unit'         => '',
            'diffFormat'   => 'feature_count',  // Custom: compare feature arrays
            'higherBetter' => true,
            'category'     => 'features',
            'priority'     => 49,  // Before display_type
            'minDiff'      => 2,   // Require at least 2 more features to show advantage
        ],
        'other.display_type' => [
            'label'        => 'display',
            'unit'         => '',
            'diffFormat'   => 'better',
            'higherBetter' => true,
            'category'     => 'features',
            'priority'     => 50,
            'ranking'      => [ 'None', 'Basic LED', 'LED', 'LCD', 'Color LCD' ],
        ],
        'other.kickstand' => [
            'label'        => 'kickstand',
            'unit'         => '',
            'diffFormat'   => 'has_feature',  // Binary: has kickstand vs doesn't
            'higherBetter' => true,
            'category'     => 'features',
            'priority'     => 51,
        ],

        // =====================================================================
        // MAINTENANCE SPECS (Composite)
        // If one scooter wins maintenance category → "Lower maintenance"
        // Note: Tire type ranking is OPPOSITE of ride quality - solid = better for maintenance
        // =====================================================================

        '_maintenance' => [
            'type'         => 'composite',
            'scoreKey'     => 'maintenance',  // Category score key
            'label'        => 'maintenance',
            'diffFormat'   => 'lower',
            'threshold'    => 0,  // Any difference counts
            'specs'        => [
                'maintenance.tire_type',
                'maintenance.brake_type',
            ],
            'category'     => 'maintenance',
            'priority'     => 59,  // Before individual maintenance specs
        ],

        'maintenance.tire_type' => [
            'label'        => 'tires',
            'unit'         => '',
            'diffFormat'   => 'lower',  // "lower maintenance tires"
            'higherBetter' => true,
            'category'     => 'maintenance',
            'priority'     => 60,
            // Maintenance ranking based on scoring: Solid (50) > Mixed (30) > Self-healing (20) > Tubeless (10) > Pneumatic (0)
            // Self-healing is +20 bonus, so tubed+self-healing (20) > tubeless (10)
            'normalizer'   => 'erh_normalize_tire_type',
            'normalizerFullSpecs' => true,
            'ranking'      => [ 'Pneumatic', 'Tubeless', 'Self-healing', 'Mixed', 'Solid' ],
        ],
        'wheels.self_healing' => [
            'label'        => 'self-healing tires',
            'unit'         => '',
            'diffFormat'   => 'has_feature',  // Binary: has self-healing vs doesn't
            'higherBetter' => true,
            'category'     => 'maintenance',
            'priority'     => 61,
        ],
        // Brake type for maintenance (drum = best, disc = worst).
        // Note: This uses a maintenance-specific normalizer that scores based on least maintenance.
        'maintenance.brake_type' => [
            'label'        => 'brakes',
            'unit'         => '',
            'diffFormat'   => 'lower',  // "lower maintenance brakes"
            'higherBetter' => true,
            'category'     => 'maintenance',
            'priority'     => 62,
            'normalizer'   => 'erh_normalize_brake_maintenance',
            'normalizerFullSpecs' => true,
            // Ranking: Drum best (sealed), Electronic/Foot minimal, Disc worst (needs pads/adjustment).
            'ranking'      => [ 'Disc', 'Electronic', 'Foot', 'Drum' ],
        ],
    ];

    // =========================================================================
    // Legacy: Category-Based Advantage Specs (kept for backwards compatibility)
    // =========================================================================

    /**
     * @deprecated Use COMPARISON_SPECS instead for spec-based advantages.
     */
    public const ADVANTAGE_SPECS = [
        'motor_performance' => [
            'specOrder'   => [
                'tested_top_speed',
                'manufacturer_top_speed',
                'motor.power_nominal',
                'motor.motor_count',
                'battery.voltage',
            ],
            'headlines'   => [
                'tested_top_speed'         => 'Faster',
                'manufacturer_top_speed'   => 'Faster',
                'motor.power_nominal'      => 'More Powerful',
                'motor.power_peak'         => 'More Powerful',
                'motor.motor_count'        => 'Dual Motors',
                'battery.voltage'          => 'Higher Voltage',
            ],
            'lowerBetter' => [],
            'maxItems'    => 3,
        ],
        'range_battery' => [
            'specOrder'   => [
                'tested_range_regular',
                'range_claimed',
                'battery.capacity',
                'battery.charging_time',
            ],
            'headlines'   => [
                'tested_range_regular'   => 'More Range',
                'tested_range_fast'      => 'More Range',
                'range_claimed'          => 'More Range',
                'battery.capacity'       => 'Bigger Battery',
                'battery.charging_time'  => 'Faster Charging',
            ],
            'lowerBetter' => [ 'battery.charging_time' ],
            'maxItems'    => 3,
        ],
        'ride_quality' => [
            // Composite approach: winner gets consolidated "Better ride quality" with specs.
            'composite'   => '_ride_quality',
            'specOrder'   => [
                'suspension.type',
                'wheels.tire_size_front',
                'wheels.tire_type',
            ],
            'headlines'   => [
                '_ride_quality'          => 'Better ride quality',
                'suspension.type'        => 'Better Suspension',
                'wheels.tire_size_front' => 'Larger Tires',
                'wheels.tire_type'       => 'Better Tires',
            ],
            'lowerBetter' => [],
            'maxItems'    => 3,
        ],
        'portability' => [
            'specOrder'   => [
                'dimensions.weight',
                'dimensions.folded_length',
                'dimensions.foldable_handlebars',
                'dimensions.max_load',
                'dimensions.deck_length',
            ],
            'headlines'   => [
                'dimensions.weight'              => 'Lighter',
                'dimensions.folded_length'       => 'More Compact',
                'dimensions.foldable_handlebars' => 'Foldable Handlebars',
                'dimensions.max_load'            => 'Higher Weight Capacity',
                'dimensions.deck_length'         => 'Larger Deck',
            ],
            'lowerBetter' => [ 'dimensions.weight', 'dimensions.folded_length' ],
            'maxItems'    => 3,
        ],
        'safety' => [
            // Composite approach: winner gets consolidated "Safer" with supporting specs.
            // Tire size excluded - already shown in ride quality.
            // Turn signals handled by features comparison.
            'composite'   => '_safety',
            'specOrder'   => [
                'safety.tire_type',  // Pneumatic > semi > solid (for safety)
                'brake_distance',    // Only if both have tested data
            ],
            'headlines'   => [
                '_safety'          => 'Safer',
                'safety.tire_type' => 'Safer Tires',
                'brake_distance'   => 'Shorter Stopping',
            ],
            'lowerBetter' => [ 'brake_distance' ],
            'maxItems'    => 2,
        ],
        'features' => [
            'specOrder'   => [
                'features',  // Feature array comparison (requires 2+ difference)
            ],
            'headlines'   => [
                'features' => 'More Features',
            ],
            'lowerBetter' => [],
            'maxItems'    => 1,  // Only show feature count advantage
        ],
        'maintenance' => [
            'specOrder'   => [
                'maintenance.tire_type',
                'other.ip_rating',        // Higher IP = less maintenance from water damage
                'maintenance.brake_type',
                'wheels.self_healing',
            ],
            'headlines'   => [
                'maintenance.tire_type'  => 'Lower Maintenance',
                'other.ip_rating'        => 'Higher Water Resistance',
                'maintenance.brake_type' => 'Lower Maintenance Brakes',
                'wheels.self_healing'    => 'Self-Healing Tires',
            ],
            'lowerBetter' => [],
            'maxItems'    => 4,
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
        'motor.motor_count'      => '',  // Special handling: "dual motor vs single"
        'battery.voltage'        => 'V',
        'battery.capacity'       => 'Wh',
        'battery.charging_time'  => 'hrs',
        'tested_range_regular'   => 'mi',
        'tested_range_fast'      => 'mi',
        'range_claimed'          => 'mi',
        'dimensions.weight'        => 'lbs',
        'dimensions.folded_length' => '"',
        'dimensions.max_load'      => 'lbs',
        'dimensions.deck_length'   => '"',
        'wheels.tire_size_front'   => '"',
        'brake_distance'           => 'ft',
        'wh_per_lb'                => 'Wh/lb',
        'speed_per_lb'             => 'mph/lb',
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
            'Motor Performance'      => 0.20,
            'Battery & Range'        => 0.20,
            'Ride Quality'           => 0.20,
            'Drivetrain & Components'=> 0.15,
            'Weight & Portability'   => 0.10,
            'Features & Tech'        => 0.10,
            'Safety & Compliance'    => 0.05,
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
     * Tooltips are automatically enriched from the TOOLTIPS constant.
     *
     * @param string $category Category key (escooter, ebike, etc.).
     * @return array Spec groups configuration with tooltips.
     */
    public static function get_spec_groups( string $category ): array {
        $groups = self::get_all_spec_groups();
        $category_groups = $groups[ $category ] ?? [];

        // Enrich specs with tooltips from centralized TOOLTIPS constant.
        return self::enrich_with_tooltips( $category_groups );
    }

    /**
     * Enrich spec groups with tooltips from TOOLTIPS constant.
     *
     * Adds 'tooltip' (methodology tier - full explanation) to each spec
     * from the centralized TOOLTIPS constant.
     *
     * @param array $groups Spec groups array.
     * @return array Enriched spec groups with tooltips.
     */
    private static function enrich_with_tooltips( array $groups ): array {
        foreach ( $groups as $group_name => &$group ) {
            if ( empty( $group['specs'] ) || ! is_array( $group['specs'] ) ) {
                continue;
            }

            foreach ( $group['specs'] as &$spec ) {
                $key = $spec['key'] ?? '';
                if ( empty( $key ) ) {
                    continue;
                }

                // Get tooltip from centralized constant (skip if already set inline).
                // Uses 'methodology' tier which provides full explanations.
                if ( ! isset( $spec['tooltip'] ) ) {
                    $tooltip = self::get_tooltip( $key, 'methodology' );
                    if ( $tooltip ) {
                        $spec['tooltip'] = $tooltip;
                    }
                }
            }
            unset( $spec );
        }
        unset( $group );

        return $groups;
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

    /**
     * E-Bike spec groups.
     *
     * Keys use flattened paths (no 'e-bikes.' prefix) because erh_flatten_compare_specs()
     * moves nested content to top level before comparison.
     *
     * Groups are organized to match the 7-category scoring system.
     */
    private static function get_ebike_specs(): array {
        return [
            'Motor Performance' => [
                'icon'      => 'zap',
                'question'  => 'How powerful is it?',
                'showScore' => true,
                'scoreKey'  => 'motor_performance',
                'specs'     => [
                    [ 'key' => 'motor.torque', 'label' => 'Torque', 'unit' => 'Nm', 'higherBetter' => true ],
                    [ 'key' => 'motor.motor_position', 'label' => 'Motor Position' ],
                    [ 'key' => 'motor.sensor_type', 'label' => 'Sensor Type' ],
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
                'scoreKey'  => 'range_battery',
                'specs'     => [
                    [ 'key' => 'battery.battery_capacity', 'label' => 'Battery', 'unit' => 'Wh', 'higherBetter' => true ],
                    [ 'key' => 'battery.range', 'label' => 'Max Range', 'unit' => 'mi', 'higherBetter' => true ],
                    [ 'key' => 'battery.charge_time', 'label' => 'Charge Time', 'unit' => 'h', 'higherBetter' => false ],
                    [ 'key' => 'battery.voltage', 'label' => 'Voltage', 'unit' => 'V', 'higherBetter' => true ],
                    [ 'key' => 'battery.amphours', 'label' => 'Amp Hours', 'unit' => 'Ah', 'higherBetter' => true ],
                    [ 'key' => 'battery.removable', 'label' => 'Removable Battery', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'battery.battery_position', 'label' => 'Battery Position' ],
                ],
            ],
            'Ride Quality' => [
                'icon'      => 'smile',
                'question'  => 'Is it comfortable?',
                'showScore' => true,
                'scoreKey'  => 'ride_quality',
                'specs'     => [
                    [ 'key' => 'suspension.front_suspension', 'label' => 'Front Suspension' ],
                    [ 'key' => 'suspension.rear_suspension', 'label' => 'Rear Suspension' ],
                    [ 'key' => 'suspension.seatpost_suspension', 'label' => 'Seatpost Suspension', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'wheels_and_tires.tire_width', 'label' => 'Tire Width', 'unit' => '"', 'higherBetter' => true ],
                    [ 'key' => 'wheels_and_tires.puncture_protection', 'label' => 'Puncture Protection', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'frame_and_geometry.frame_style', 'label' => 'Frame Style', 'format' => 'array' ],
                    [ 'key' => 'suspension.front_travel', 'label' => 'Front Travel', 'unit' => 'mm', 'higherBetter' => true ],
                    [ 'key' => 'suspension.rear_travel', 'label' => 'Rear Travel', 'unit' => 'mm', 'higherBetter' => true ],
                ],
            ],
            'Drivetrain & Components' => [
                'icon'      => 'settings',
                'question'  => 'How good are the components?',
                'showScore' => true,
                'scoreKey'  => 'drivetrain',
                'specs'     => [
                    [ 'key' => 'brakes.brake_type', 'label' => 'Brake Type', 'format' => 'array' ],
                    [ 'key' => 'brakes.brake_brand', 'label' => 'Brake Brand' ],
                    [ 'key' => 'brakes.rotor_size_front', 'label' => 'Rotor Size (Front)', 'unit' => 'mm', 'higherBetter' => true ],
                    [ 'key' => 'brakes.rotor_size_rear', 'label' => 'Rotor Size (Rear)', 'unit' => 'mm', 'higherBetter' => true ],
                    [ 'key' => 'drivetrain.gears', 'label' => 'Gears', 'higherBetter' => true ],
                    [ 'key' => 'drivetrain.drive_system', 'label' => 'Drive System' ],
                    [ 'key' => 'drivetrain.shifter', 'label' => 'Shifter' ],
                    [ 'key' => 'drivetrain.derailleur', 'label' => 'Derailleur' ],
                    [ 'key' => 'drivetrain.cassette', 'label' => 'Cassette' ],
                ],
            ],
            'Weight & Portability' => [
                'icon'      => 'box',
                'question'  => 'Is it easy to handle?',
                'showScore' => true,
                'scoreKey'  => 'portability',
                'specs'     => [
                    [ 'key' => 'weight_and_capacity.weight', 'label' => 'Weight', 'unit' => 'lbs', 'higherBetter' => false ],
                    [ 'key' => 'weight_and_capacity.weight_limit', 'label' => 'Weight Limit', 'unit' => 'lbs', 'higherBetter' => true ],
                    [ 'key' => 'frame_and_geometry.frame_material', 'label' => 'Frame Material', 'format' => 'array' ],
                    [ 'key' => 'frame_and_geometry.sizes_available', 'label' => 'Sizes Available', 'format' => 'array' ],
                    [ 'key' => 'weight_and_capacity.rack_capacity', 'label' => 'Rack Capacity', 'unit' => 'lbs', 'higherBetter' => true ],
                ],
            ],
            'Features & Tech' => [
                'icon'      => 'monitor',
                'question'  => 'What extras does it have?',
                'showScore' => true,
                'scoreKey'  => 'features',
                'specs'     => [
                    [ 'key' => 'components.display', 'label' => 'Display' ],
                    [ 'key' => 'components.display_size', 'label' => 'Display Size', 'unit' => '"' ],
                    [ 'key' => 'integrated_features.integrated_lights', 'label' => 'Integrated Lights', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'components.app_compatible', 'label' => 'App Compatible', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'components.connectivity', 'label' => 'Connectivity', 'format' => 'array' ],
                    [ 'key' => 'integrated_features.fenders', 'label' => 'Fenders', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'integrated_features.rear_rack', 'label' => 'Rear Rack', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'integrated_features.front_rack', 'label' => 'Front Rack', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'integrated_features.kickstand', 'label' => 'Kickstand', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'integrated_features.walk_assist', 'label' => 'Walk Assist', 'format' => 'boolean', 'higherBetter' => true ],
                    [ 'key' => 'special_features', 'label' => 'Special Features', 'format' => 'array' ],
                ],
            ],
            'Safety & Compliance' => [
                'icon'      => 'shield',
                'question'  => 'Is it safe and compliant?',
                'showScore' => true,
                'scoreKey'  => 'safety',
                'specs'     => [
                    [ 'key' => 'safety_and_compliance.ip_rating', 'label' => 'IP Rating', 'format' => 'ip', 'higherBetter' => true ],
                    [ 'key' => 'safety_and_compliance.certifications', 'label' => 'Certifications', 'format' => 'array' ],
                    [ 'key' => 'speed_and_class.throttle', 'label' => 'Throttle', 'format' => 'boolean', 'higherBetter' => true ],
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

    /**
     * Get flat list of specs for buying guide table column picker.
     *
     * Returns an associative array suitable for ACF checkbox/select fields:
     * [ 'spec_key' => 'Label (unit)' ]
     *
     * @param string $category Category key (escooter, ebike, etc.).
     * @return array Flat list of spec choices.
     */
    public static function get_table_column_choices( string $category ): array {
        $groups  = self::get_spec_groups( $category );
        $choices = [];

        foreach ( $groups as $group_name => $group ) {
            // Skip value analysis sections.
            if ( ! empty( $group['isValueSection'] ) ) {
                continue;
            }

            foreach ( $group['specs'] as $spec ) {
                $key   = $spec['key'];
                $label = $spec['label'];

                // Append unit if present.
                if ( ! empty( $spec['unit'] ) ) {
                    $label .= ' (' . $spec['unit'] . ')';
                }

                $choices[ $key ] = $label;
            }
        }

        return $choices;
    }

    /**
     * Get spec definition by key.
     *
     * @param string $category Category key.
     * @param string $spec_key Spec key to find.
     * @return array|null Spec definition or null if not found.
     */
    public static function get_spec_definition( string $category, string $spec_key ): ?array {
        $groups = self::get_spec_groups( $category );

        foreach ( $groups as $group ) {
            foreach ( $group['specs'] as $spec ) {
                if ( $spec['key'] === $spec_key ) {
                    return $spec;
                }
            }
        }

        return null;
    }

    /**
     * Get tooltip for a spec.
     *
     * @deprecated Use get_tooltip() instead for centralized tooltips.
     *
     * @param string $category Category key (no longer used).
     * @param string $spec_key Spec key to find.
     * @param bool   $long     No longer used - methodology tier is always returned.
     * @return string|null Tooltip text or null if not found.
     */
    public static function get_spec_tooltip( string $category, string $spec_key, bool $long = false ): ?string {
        // Delegate to centralized get_tooltip() - always returns methodology (full explanation).
        return self::get_tooltip( $spec_key, 'methodology' );
    }

    // =========================================================================
    // Centralized Tooltip Methods
    // =========================================================================

    /**
     * Get tooltip for a spec key with fallback chain.
     *
     * Fallback chain by tier:
     * - 'comparison': comparison → methodology
     * - 'methodology': methodology only (default)
     *
     * @param string $spec_key Spec key (e.g., 'tested_top_speed', 'motor.power_nominal').
     * @param string $tier     Tooltip tier: 'methodology' (default) or 'comparison'.
     * @return string|null Tooltip text or null if not found.
     */
    public static function get_tooltip( string $spec_key, string $tier = 'methodology' ): ?string {
        // Normalize spec key - strip any geo placeholders.
        $normalized_key = preg_replace( '/\.\{geo\}\./', '.', $spec_key );
        // Also handle value_metrics.US.price_per_wh → value_metrics.price_per_wh
        $normalized_key = preg_replace( '/\.(US|GB|EU|CA|AU)\./', '.', $normalized_key );

        // Check if spec exists in TOOLTIPS.
        if ( ! isset( self::TOOLTIPS[ $normalized_key ] ) ) {
            return null;
        }

        $tooltip_data = self::TOOLTIPS[ $normalized_key ];

        // Apply fallback chain based on tier.
        // comparison → methodology (comparison context falls back to standard explanation)
        // methodology is the default (full explanation)
        switch ( $tier ) {
            case 'comparison':
                return $tooltip_data['comparison']
                    ?? $tooltip_data['methodology']
                    ?? null;

            case 'methodology':
            default:
                return $tooltip_data['methodology'] ?? null;
        }
    }

    /**
     * Export tooltips for JavaScript.
     *
     * Returns the full TOOLTIPS structure for use with wp_localize_script().
     *
     * @return array Tooltips keyed by spec key, each with methodology/comparison.
     */
    public static function export_tooltips(): array {
        return self::TOOLTIPS;
    }

    /**
     * Export tooltips for a specific tier only.
     *
     * Useful when you only need one tier in JS (e.g., just 'comparison' tooltips).
     * Applies fallback chain for each spec.
     *
     * @param string $tier Tooltip tier: 'methodology' (default) or 'comparison'.
     * @return array Flat array of tooltips keyed by spec key.
     */
    public static function export_tooltips_flat( string $tier = 'methodology' ): array {
        $result = [];

        foreach ( array_keys( self::TOOLTIPS ) as $spec_key ) {
            $tooltip = self::get_tooltip( $spec_key, $tier );
            if ( $tooltip ) {
                $result[ $spec_key ] = $tooltip;
            }
        }

        return $result;
    }

    // =========================================================================
    // Comparison Specs Methods
    // =========================================================================

    /**
     * Get comparison specs with tooltips enriched from TOOLTIPS constant.
     *
     * This is the preferred way to access COMPARISON_SPECS as it ensures
     * tooltips are pulled from the centralized TOOLTIPS constant.
     *
     * @return array Comparison specs with tooltips from centralized source.
     */
    public static function get_comparison_specs(): array {
        $specs = self::COMPARISON_SPECS;

        foreach ( $specs as $key => &$spec ) {
            // Skip composite specs (they use 'type' => 'composite').
            // Their tooltips are pulled from TOOLTIPS for the composite key itself.
            if ( ! isset( $spec['tooltip'] ) ) {
                $tooltip = self::get_tooltip( $key, 'comparison' );
                if ( $tooltip ) {
                    $spec['tooltip'] = $tooltip;
                }
            }
        }
        unset( $spec );

        return $specs;
    }

    /**
     * Get a single comparison spec definition with tooltip enriched.
     *
     * @param string $spec_key Spec key (e.g., 'tested_top_speed').
     * @return array|null Spec definition with tooltip, or null if not found.
     */
    public static function get_comparison_spec( string $spec_key ): ?array {
        if ( ! isset( self::COMPARISON_SPECS[ $spec_key ] ) ) {
            return null;
        }

        $spec = self::COMPARISON_SPECS[ $spec_key ];

        // Enrich with tooltip if not already set.
        if ( ! isset( $spec['tooltip'] ) ) {
            $tooltip = self::get_tooltip( $spec_key, 'comparison' );
            if ( $tooltip ) {
                $spec['tooltip'] = $tooltip;
            }
        }

        return $spec;
    }
}

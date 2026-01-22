<?php
/**
 * Spec Configuration Functions
 *
 * Provides spec group configurations for different contexts.
 *
 * NOTE: The single source of truth for spec metadata is now ERH\Config\SpecConfig.
 * Functions here delegate to SpecConfig where appropriate.
 *
 * - erh_get_spec_groups_config() → SpecConfig::get_spec_groups() (compare context)
 * - erh_get_compare_spec_groups() → SpecConfig::get_spec_groups() (compare context)
 * - erh_get_product_spec_groups_config() → Custom groupings for product page
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ERH\Config\SpecConfig;

/**
 * Get spec groups configuration for a product category.
 *
 * Delegates to SpecConfig (single source of truth).
 *
 * @param string $category Category key ('escooter', 'ebike', etc.).
 * @return array Spec groups configuration.
 */
function erh_get_spec_groups_config( string $category ): array {
    // SpecConfig is the single source of truth.
    if ( class_exists( 'ERH\Config\SpecConfig' ) ) {
        return SpecConfig::get_spec_groups( $category );
    }

    // Fallback if SpecConfig not available (should not happen in production).
    return [];
}

/**
 * Get compare spec groups config for SSR (mirrors JS config).
 *
 * Delegates to SpecConfig (single source of truth).
 *
 * @param string $category Category key (escooter, ebike, etc.).
 * @return array Spec groups with higherBetter, format, geoAware flags.
 */
function erh_get_compare_spec_groups( string $category ): array {
    // SpecConfig is the single source of truth.
    if ( class_exists( 'ERH\Config\SpecConfig' ) ) {
        return SpecConfig::get_spec_groups( $category );
    }

    // Fallback if SpecConfig not available (should not happen in production).
    return [];
}

/**
 * Get SEO-friendly spec groups for product page.
 *
 * Unlike the comparison tool's score-based groupings, this uses
 * logical groupings that match how users search for specs.
 * This is intentionally different from SpecConfig to serve product page needs.
 *
 * Tooltips are now fetched from SpecConfig::get_tooltip() (single source of truth).
 *
 * @param string $category Category key ('escooter', 'ebike', etc.).
 * @return array Spec groups configuration with tooltips.
 */
function erh_get_product_spec_groups_config( string $category ): array {
    // Helper to get tooltip from centralized SpecConfig.
    // Uses 'methodology' tier for product page (detailed explanations).
    $get_tooltip = function( string $spec_key ) {
        return SpecConfig::get_tooltip( $spec_key, 'methodology' );
    };

    $configs = array(
        'escooter' => array(
            'Motor & Power' => array(
                'icon'  => 'zap',
                'specs' => array(
                    array( 'key' => 'motor.power_nominal', 'label' => 'Nominal Power', 'unit' => 'W', 'tooltip' => $get_tooltip( 'motor.power_nominal' ) ),
                    array( 'key' => 'motor.power_peak', 'label' => 'Peak Power', 'unit' => 'W', 'tooltip' => $get_tooltip( 'motor.power_peak' ) ),
                    array( 'key' => 'motor.voltage', 'label' => 'Voltage', 'unit' => 'V', 'tooltip' => $get_tooltip( 'motor.voltage' ) ),
                    array( 'key' => 'motor.motor_position', 'label' => 'Motor Configuration' ),
                    array( 'key' => 'motor.motor_type', 'label' => 'Motor Type' ),
                ),
            ),
            'Battery & Charging' => array(
                'icon'  => 'battery',
                'specs' => array(
                    array( 'key' => 'battery.capacity', 'label' => 'Battery Capacity', 'unit' => 'Wh', 'tooltip' => $get_tooltip( 'battery.capacity' ) ),
                    array( 'key' => 'battery.voltage', 'label' => 'Battery Voltage', 'unit' => 'V' ),
                    array( 'key' => 'battery.amphours', 'label' => 'Amp Hours', 'unit' => 'Ah' ),
                    array( 'key' => 'battery.battery_type', 'label' => 'Battery Type' ),
                    array( 'key' => 'battery.brand', 'label' => 'Battery Brand' ),
                    array( 'key' => 'battery.charging_time', 'label' => 'Charge Time', 'unit' => 'hrs', 'tooltip' => $get_tooltip( 'battery.charging_time' ) ),
                ),
            ),
            'Claimed Performance' => array(
                'icon'  => 'gauge',
                'specs' => array(
                    array( 'key' => 'manufacturer_top_speed', 'label' => 'Top Speed (Claimed)', 'unit' => 'mph' ),
                    array( 'key' => 'manufacturer_range', 'label' => 'Range (Claimed)', 'unit' => 'mi' ),
                    array( 'key' => 'max_incline', 'label' => 'Max Hill Grade', 'unit' => '°' ),
                ),
            ),
            'ERideHero Test Results' => array(
                'icon'  => 'clipboard-check',
                'specs' => array(
                    array( 'key' => 'tested_top_speed', 'label' => 'Top Speed (Tested)', 'unit' => 'mph', 'tooltip' => $get_tooltip( 'tested_top_speed' ) ),
                    array( 'key' => 'tested_range_regular', 'label' => 'Range (Regular Riding)', 'unit' => 'mi', 'tooltip' => $get_tooltip( 'tested_range_regular' ) ),
                    array( 'key' => 'tested_range_fast', 'label' => 'Range (Fast Riding)', 'unit' => 'mi' ),
                    array( 'key' => 'tested_range_slow', 'label' => 'Range (Eco Mode)', 'unit' => 'mi' ),
                    array( 'key' => 'acceleration_0_15_mph', 'label' => '0–15 mph', 'unit' => 's', 'tooltip' => $get_tooltip( 'acceleration_0_15_mph' ) ),
                    array( 'key' => 'acceleration_0_20_mph', 'label' => '0–20 mph', 'unit' => 's' ),
                    array( 'key' => 'acceleration_0_25_mph', 'label' => '0–25 mph', 'unit' => 's' ),
                    array( 'key' => 'acceleration_0_30_mph', 'label' => '0–30 mph', 'unit' => 's' ),
                    array( 'key' => 'hill_climbing', 'label' => 'Hill Climb Speed', 'unit' => 'mph', 'tooltip' => $get_tooltip( 'hill_climbing' ) ),
                    array( 'key' => 'brake_distance', 'label' => 'Braking Distance', 'unit' => 'ft', 'tooltip' => $get_tooltip( 'brake_distance' ) ),
                ),
            ),
            'Weight & Dimensions' => array(
                'icon'  => 'box',
                'specs' => array(
                    array( 'key' => 'dimensions.weight', 'label' => 'Weight', 'unit' => 'lbs' ),
                    array( 'key' => 'dimensions.max_load', 'label' => 'Max Rider Weight', 'unit' => 'lbs', 'tooltip' => $get_tooltip( 'dimensions.max_load' ) ),
                    array( 'key' => 'dimensions.deck_length', 'label' => 'Deck Length', 'unit' => '"' ),
                    array( 'key' => 'dimensions.deck_width', 'label' => 'Deck Width', 'unit' => '"' ),
                    array( 'key' => 'dimensions.ground_clearance', 'label' => 'Ground Clearance', 'unit' => '"', 'tooltip' => $get_tooltip( 'dimensions.ground_clearance' ) ),
                    array( 'key' => 'dimensions.handlebar_height_min', 'label' => 'Handlebar Height (Min)', 'unit' => '"' ),
                    array( 'key' => 'dimensions.handlebar_height_max', 'label' => 'Handlebar Height (Max)', 'unit' => '"' ),
                    array( 'key' => 'dimensions.handlebar_width', 'label' => 'Handlebar Width', 'unit' => '"' ),
                    array( 'key' => 'dimensions.unfolded_length', 'label' => 'Length (Unfolded)', 'unit' => '"' ),
                    array( 'key' => 'dimensions.unfolded_width', 'label' => 'Width (Unfolded)', 'unit' => '"' ),
                    array( 'key' => 'dimensions.unfolded_height', 'label' => 'Height (Unfolded)', 'unit' => '"' ),
                    array( 'key' => 'dimensions.folded_length', 'label' => 'Length (Folded)', 'unit' => '"' ),
                    array( 'key' => 'dimensions.folded_width', 'label' => 'Width (Folded)', 'unit' => '"' ),
                    array( 'key' => 'dimensions.folded_height', 'label' => 'Height (Folded)', 'unit' => '"' ),
                    array( 'key' => 'dimensions.foldable_handlebars', 'label' => 'Foldable Handlebars', 'format' => 'feature_check', 'feature_value' => true ),
                ),
            ),
            'Wheels & Suspension' => array(
                'icon'  => 'circle',
                'specs' => array(
                    array( 'key' => 'wheels.tire_type', 'label' => 'Tire Type', 'tooltip' => $get_tooltip( 'wheels.tire_type' ) ),
                    array( 'key' => 'wheels.tire_size_front', 'label' => 'Front Tire Size', 'unit' => '"' ),
                    array( 'key' => 'wheels.tire_size_rear', 'label' => 'Rear Tire Size', 'unit' => '"' ),
                    array( 'key' => 'wheels.tire_width', 'label' => 'Tire Width', 'unit' => '"' ),
                    array( 'key' => 'wheels.pneumatic_type', 'label' => 'Pneumatic Type' ),
                    array( 'key' => 'wheels.self_healing', 'label' => 'Self-Healing Tires', 'format' => 'feature_check', 'feature_value' => true, 'tooltip' => $get_tooltip( 'wheels.self_healing' ) ),
                    array( 'key' => 'suspension.type', 'label' => 'Suspension', 'format' => 'array', 'tooltip' => $get_tooltip( 'suspension.type' ) ),
                    array( 'key' => 'suspension.adjustable', 'label' => 'Adjustable Suspension', 'format' => 'feature_check', 'feature_value' => true ),
                ),
            ),
            'Brakes & Safety' => array(
                'icon'  => 'shield',
                'specs' => array(
                    array( 'key' => 'brakes.front', 'label' => 'Front Brake' ),
                    array( 'key' => 'brakes.rear', 'label' => 'Rear Brake' ),
                    array( 'key' => 'brakes.regenerative', 'label' => 'Regenerative Braking', 'format' => 'feature_check', 'feature_value' => true, 'tooltip' => $get_tooltip( 'brakes.regenerative' ) ),
                    array( 'key' => 'lighting.lights', 'label' => 'Lights', 'format' => 'array' ),
                    array( 'key' => 'lighting.turn_signals', 'label' => 'Turn Signals', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'other.ip_rating', 'label' => 'IP Rating', 'format' => 'ip', 'higherBetter' => true, 'tooltip' => $get_tooltip( 'other.ip_rating' ) ),
                ),
            ),
            'Features' => array(
                'icon'  => 'settings',
                'specs' => array(
                    array( 'key' => 'other.display_type', 'label' => 'Display Type' ),
                    array( 'key' => 'other.throttle_type', 'label' => 'Throttle Type' ),
                    array( 'key' => 'other.terrain', 'label' => 'Terrain Type' ),
                    array( 'key' => 'other.kickstand', 'label' => 'Kickstand', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'other.footrest', 'label' => 'Footrest', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'features', 'label' => 'App Connectivity', 'format' => 'feature_check', 'feature_value' => 'App' ),
                    array( 'key' => 'features', 'label' => 'Speed Modes', 'format' => 'feature_check', 'feature_value' => 'Speed Modes' ),
                    array( 'key' => 'features', 'label' => 'Cruise Control', 'format' => 'feature_check', 'feature_value' => 'Cruise Control' ),
                    array( 'key' => 'features', 'label' => 'Folding', 'format' => 'feature_check', 'feature_value' => 'Folding' ),
                    array( 'key' => 'features', 'label' => 'Push-To-Start', 'format' => 'feature_check', 'feature_value' => 'Push-To-Start' ),
                    array( 'key' => 'features', 'label' => 'Zero-Start', 'format' => 'feature_check', 'feature_value' => 'Zero-Start' ),
                    array( 'key' => 'features', 'label' => 'Brake Curve Adjustment', 'format' => 'feature_check', 'feature_value' => 'Brake Curve Adjustment' ),
                    array( 'key' => 'features', 'label' => 'Acceleration Adjustment', 'format' => 'feature_check', 'feature_value' => 'Acceleration Adjustment' ),
                    array( 'key' => 'features', 'label' => 'Speed Limiting', 'format' => 'feature_check', 'feature_value' => 'Speed Limiting' ),
                    array( 'key' => 'features', 'label' => 'OTA Updates', 'format' => 'feature_check', 'feature_value' => 'OTA Updates' ),
                    array( 'key' => 'features', 'label' => 'Location Tracking', 'format' => 'feature_check', 'feature_value' => 'Location Tracking' ),
                    array( 'key' => 'features', 'label' => 'Quick-Swap Battery', 'format' => 'feature_check', 'feature_value' => 'Quick-Swap Battery' ),
                    array( 'key' => 'features', 'label' => 'Steering Damper', 'format' => 'feature_check', 'feature_value' => 'Steering Damper' ),
                    array( 'key' => 'features', 'label' => 'Electronic Horn', 'format' => 'feature_check', 'feature_value' => 'Electronic Horn' ),
                    array( 'key' => 'features', 'label' => 'NFC Unlock', 'format' => 'feature_check', 'feature_value' => 'NFC Unlock' ),
                    array( 'key' => 'features', 'label' => 'Seat Option', 'format' => 'feature_check', 'feature_value' => 'Seat Option' ),
                ),
            ),
        ),
        'ebike' => array(
            'Motor & Power' => array(
                'icon'  => 'zap',
                'specs' => array(
                    array( 'key' => 'motor.power_nominal', 'label' => 'Nominal Power', 'unit' => 'W', 'tooltip' => $get_tooltip( 'motor.power_nominal' ) ),
                    array( 'key' => 'motor.power_peak', 'label' => 'Peak Power', 'unit' => 'W', 'tooltip' => $get_tooltip( 'motor.power_peak' ) ),
                    array( 'key' => 'motor.torque', 'label' => 'Torque', 'unit' => 'Nm' ),
                    array( 'key' => 'motor.type', 'label' => 'Motor Type' ),
                    array( 'key' => 'motor.position', 'label' => 'Motor Position' ),
                ),
            ),
            'Battery & Range' => array(
                'icon'  => 'battery',
                'specs' => array(
                    array( 'key' => 'battery.capacity', 'label' => 'Battery Capacity', 'unit' => 'Wh', 'tooltip' => $get_tooltip( 'battery.capacity' ) ),
                    array( 'key' => 'battery.voltage', 'label' => 'Voltage', 'unit' => 'V' ),
                    array( 'key' => 'battery.range_claimed', 'label' => 'Range (Claimed)', 'unit' => 'mi' ),
                    array( 'key' => 'battery.charging_time', 'label' => 'Charge Time', 'unit' => 'hrs', 'tooltip' => $get_tooltip( 'battery.charging_time' ) ),
                    array( 'key' => 'battery.removable', 'label' => 'Removable Battery', 'format' => 'boolean' ),
                ),
            ),
            'Speed & Performance' => array(
                'icon'  => 'gauge',
                'specs' => array(
                    array( 'key' => 'performance.top_speed', 'label' => 'Top Speed', 'unit' => 'mph' ),
                    array( 'key' => 'performance.class', 'label' => 'E-Bike Class' ),
                    array( 'key' => 'performance.pedal_assist_levels', 'label' => 'Pedal Assist Levels' ),
                    array( 'key' => 'performance.throttle', 'label' => 'Throttle', 'format' => 'boolean' ),
                ),
            ),
            'Frame & Build' => array(
                'icon'  => 'box',
                'specs' => array(
                    array( 'key' => 'frame.weight', 'label' => 'Weight', 'unit' => 'lbs' ),
                    array( 'key' => 'frame.max_load', 'label' => 'Max Load', 'unit' => 'lbs', 'tooltip' => $get_tooltip( 'dimensions.max_load' ) ),
                    array( 'key' => 'frame.material', 'label' => 'Frame Material' ),
                    array( 'key' => 'frame.type', 'label' => 'Frame Type' ),
                    array( 'key' => 'frame.suspension', 'label' => 'Suspension' ),
                    array( 'key' => 'frame.foldable', 'label' => 'Foldable', 'format' => 'boolean' ),
                ),
            ),
            'Components' => array(
                'icon'  => 'settings',
                'specs' => array(
                    array( 'key' => 'components.gears', 'label' => 'Gears' ),
                    array( 'key' => 'components.brakes', 'label' => 'Brakes' ),
                    array( 'key' => 'components.wheel_size', 'label' => 'Wheel Size', 'unit' => '"' ),
                    array( 'key' => 'components.tire_type', 'label' => 'Tire Type' ),
                    array( 'key' => 'components.display', 'label' => 'Display' ),
                    array( 'key' => 'components.lights', 'label' => 'Lights' ),
                ),
            ),
        ),
    );

    return $configs[ $category ] ?? array();
}

/**
 * Get the nested wrapper key for a category.
 *
 * Returns the key used in product_data.specs for nested ACF fields.
 *
 * @param string $category Category key ('escooter', 'ebike', etc.).
 * @return string Wrapper key ('e-scooters', 'e-bikes', etc.).
 */
function erh_get_specs_wrapper_key( string $category ): string {
    $wrappers = array(
        'escooter'    => 'e-scooters',
        'ebike'       => 'e-bikes',
        'eskateboard' => 'e-skateboards',
        'euc'         => 'eucs',
        'hoverboard'  => 'hoverboards',
    );

    return $wrappers[ $category ] ?? 'e-scooters';
}

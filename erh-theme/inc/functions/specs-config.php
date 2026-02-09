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
            'Category' => array(
                'icon'  => 'tag',
                'specs' => array(
                    array( 'key' => 'category', 'label' => 'Category', 'format' => 'array' ),
                ),
            ),
            'Motor & Assistance' => array(
                'icon'  => 'zap',
                'specs' => array(
                    array( 'key' => 'motor.motor_type', 'label' => 'Motor Type' ),
                    array( 'key' => 'motor.motor_position', 'label' => 'Motor Position' ),
                    array( 'key' => 'motor.power_nominal', 'label' => 'Nominal Power', 'unit' => 'W', 'tooltip' => $get_tooltip( 'motor.power_nominal' ) ),
                    array( 'key' => 'motor.power_peak', 'label' => 'Peak Power', 'unit' => 'W', 'tooltip' => $get_tooltip( 'motor.power_peak' ) ),
                    array( 'key' => 'motor.torque', 'label' => 'Torque', 'unit' => 'Nm' ),
                    array( 'key' => 'motor.sensor_type', 'label' => 'Sensor Type' ),
                    array( 'key' => 'motor.assist_levels', 'label' => 'Assist Levels' ),
                ),
            ),
            'Speed & Class' => array(
                'icon'  => 'gauge',
                'specs' => array(
                    array( 'key' => 'speed_and_class.class', 'label' => 'E-Bike Class', 'format' => 'array' ),
                    array( 'key' => 'speed_and_class.top_assist_speed', 'label' => 'Top Assist Speed', 'unit' => 'mph' ),
                    array( 'key' => 'speed_and_class.throttle', 'label' => 'Throttle', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'speed_and_class.throttle_top_speed', 'label' => 'Throttle Top Speed', 'unit' => 'mph' ),
                ),
            ),
            'Battery & Range' => array(
                'icon'  => 'battery',
                'specs' => array(
                    array( 'key' => 'battery.battery_capacity', 'label' => 'Battery Capacity', 'unit' => 'Wh', 'tooltip' => $get_tooltip( 'battery.capacity' ) ),
                    array( 'key' => 'battery.voltage', 'label' => 'Voltage', 'unit' => 'V' ),
                    array( 'key' => 'battery.amphours', 'label' => 'Amp Hours', 'unit' => 'Ah' ),
                    array( 'key' => 'battery.range', 'label' => 'Max Range', 'unit' => 'mi' ),
                    array( 'key' => 'battery.charge_time', 'label' => 'Charge Time', 'unit' => 'hrs', 'tooltip' => $get_tooltip( 'battery.charging_time' ) ),
                    array( 'key' => 'battery.battery_position', 'label' => 'Battery Position' ),
                    array( 'key' => 'battery.removable', 'label' => 'Removable Battery', 'format' => 'feature_check', 'feature_value' => true ),
                ),
            ),
            'Frame & Fit' => array(
                'icon'  => 'box',
                'specs' => array(
                    array( 'key' => 'frame_and_geometry.frame_material', 'label' => 'Frame Material', 'format' => 'array' ),
                    array( 'key' => 'frame_and_geometry.frame_style', 'label' => 'Frame Style', 'format' => 'array' ),
                    array( 'key' => 'frame_and_geometry.sizes_available', 'label' => 'Sizes Available', 'format' => 'array' ),
                    array( 'key' => 'weight_and_capacity.weight', 'label' => 'Weight', 'unit' => 'lbs' ),
                    array( 'key' => 'weight_and_capacity.weight_limit', 'label' => 'Weight Limit', 'unit' => 'lbs' ),
                    array( 'key' => 'weight_and_capacity.rack_capacity', 'label' => 'Rack Capacity', 'unit' => 'lbs' ),
                    array( 'key' => 'frame_and_geometry.standover_height', 'label' => 'Standover Height', 'unit' => '"' ),
                    array( 'key' => 'frame_and_geometry.min_rider_height', 'label' => 'Min Rider Height', 'unit' => '"' ),
                    array( 'key' => 'frame_and_geometry.max_rider_height', 'label' => 'Max Rider Height', 'unit' => '"' ),
                ),
            ),
            'Suspension' => array(
                'icon'  => 'smile',
                'specs' => array(
                    array( 'key' => 'suspension.front_suspension', 'label' => 'Front Suspension' ),
                    array( 'key' => 'suspension.front_travel', 'label' => 'Front Travel', 'unit' => 'mm' ),
                    array( 'key' => 'suspension.rear_suspension', 'label' => 'Rear Suspension' ),
                    array( 'key' => 'suspension.rear_travel', 'label' => 'Rear Travel', 'unit' => 'mm' ),
                    array( 'key' => 'suspension.seatpost_suspension', 'label' => 'Seatpost Suspension', 'format' => 'feature_check', 'feature_value' => true ),
                ),
            ),
            'Drivetrain' => array(
                'icon'  => 'settings',
                'specs' => array(
                    array( 'key' => 'drivetrain.gears', 'label' => 'Gears' ),
                    array( 'key' => 'drivetrain.drive_system', 'label' => 'Drive System' ),
                    array( 'key' => 'drivetrain.shifter', 'label' => 'Shifter' ),
                    array( 'key' => 'drivetrain.derailleur', 'label' => 'Derailleur' ),
                    array( 'key' => 'drivetrain.cassette', 'label' => 'Cassette' ),
                ),
            ),
            'Brakes' => array(
                'icon'  => 'shield',
                'specs' => array(
                    array( 'key' => 'brakes.brake_type', 'label' => 'Brake Type', 'format' => 'array' ),
                    array( 'key' => 'brakes.brake_brand', 'label' => 'Brake Brand' ),
                    array( 'key' => 'brakes.rotor_size_front', 'label' => 'Rotor Size (Front)', 'unit' => 'mm' ),
                    array( 'key' => 'brakes.rotor_size_rear', 'label' => 'Rotor Size (Rear)', 'unit' => 'mm' ),
                ),
            ),
            'Wheels & Tires' => array(
                'icon'  => 'circle',
                'specs' => array(
                    array( 'key' => 'wheels_and_tires.wheel_size', 'label' => 'Wheel Size', 'unit' => '"' ),
                    array( 'key' => 'wheels_and_tires.wheel_size_rear', 'label' => 'Wheel Size (Rear)', 'unit' => '"' ),
                    array( 'key' => 'wheels_and_tires.tire_width', 'label' => 'Tire Width', 'unit' => '"' ),
                    array( 'key' => 'wheels_and_tires.tire_type', 'label' => 'Tire Type' ),
                    array( 'key' => 'wheels_and_tires.puncture_protection', 'label' => 'Puncture Protection', 'format' => 'feature_check', 'feature_value' => true ),
                ),
            ),
            'Components & Tech' => array(
                'icon'  => 'monitor',
                'specs' => array(
                    array( 'key' => 'components.display', 'label' => 'Display' ),
                    array( 'key' => 'components.display_size', 'label' => 'Display Size', 'unit' => '"' ),
                    array( 'key' => 'components.connectivity', 'label' => 'Connectivity', 'format' => 'array' ),
                    array( 'key' => 'components.app_compatible', 'label' => 'App Compatible', 'format' => 'feature_check', 'feature_value' => true ),
                ),
            ),
            'Features & Safety' => array(
                'icon'  => 'package',
                'specs' => array(
                    array( 'key' => 'integrated_features.integrated_lights', 'label' => 'Integrated Lights', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'integrated_features.fenders', 'label' => 'Fenders', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'integrated_features.rear_rack', 'label' => 'Rear Rack', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'integrated_features.front_rack', 'label' => 'Front Rack', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'integrated_features.kickstand', 'label' => 'Kickstand', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'integrated_features.chain_guard', 'label' => 'Chain Guard', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'integrated_features.walk_assist', 'label' => 'Walk Assist', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'integrated_features.alarm', 'label' => 'Alarm', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'integrated_features.usb', 'label' => 'USB Charging', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'integrated_features.bottle_cage_mount', 'label' => 'Bottle Cage Mount', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'safety_and_compliance.ip_rating', 'label' => 'IP Rating', 'format' => 'ip', 'higherBetter' => true ),
                    array( 'key' => 'safety_and_compliance.certifications', 'label' => 'Certifications', 'format' => 'array' ),
                    array( 'key' => 'special_features', 'label' => 'Special Features', 'format' => 'array' ),
                ),
            ),
        ),
        'euc' => array(
            'Motor & Performance' => array(
                'icon'  => 'zap',
                'specs' => array(
                    array( 'key' => 'motor.power_nominal', 'label' => 'Nominal Power', 'unit' => 'W' ),
                    array( 'key' => 'motor.power_peak', 'label' => 'Peak Power', 'unit' => 'W' ),
                    array( 'key' => 'motor.torque', 'label' => 'Torque', 'unit' => 'Nm' ),
                    array( 'key' => 'motor.hollow_motor', 'label' => 'Hollow Motor', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'motor.motor_diameter', 'label' => 'Motor Diameter', 'unit' => '"' ),
                    array( 'key' => 'manufacturer_top_speed', 'label' => 'Top Speed (Claimed)', 'unit' => 'mph' ),
                    array( 'key' => 'manufacturer_range', 'label' => 'Range (Claimed)', 'unit' => 'mi' ),
                ),
            ),
            'Battery & Charging' => array(
                'icon'  => 'battery',
                'specs' => array(
                    array( 'key' => 'battery.capacity', 'label' => 'Battery Capacity', 'unit' => 'Wh' ),
                    array( 'key' => 'battery.voltage', 'label' => 'Voltage', 'unit' => 'V' ),
                    array( 'key' => 'battery.amphours', 'label' => 'Amp Hours', 'unit' => 'Ah' ),
                    array( 'key' => 'battery.battery_type', 'label' => 'Battery Type' ),
                    array( 'key' => 'battery.battery_brand', 'label' => 'Battery Brand' ),
                    array( 'key' => 'battery.battery_packs', 'label' => 'Battery Packs' ),
                    array( 'key' => 'battery.charging_time', 'label' => 'Charge Time', 'unit' => 'hrs' ),
                    array( 'key' => 'battery.charger_output', 'label' => 'Charger Output', 'unit' => 'A' ),
                    array( 'key' => 'battery.fast_charger', 'label' => 'Fast Charger', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'battery.dual_charging', 'label' => 'Dual Charging Port', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'battery.bms', 'label' => 'BMS' ),
                ),
            ),
            'Weight & Dimensions' => array(
                'icon'  => 'box',
                'specs' => array(
                    array( 'key' => 'dimensions.weight', 'label' => 'Weight', 'unit' => 'lbs' ),
                    array( 'key' => 'dimensions.max_load', 'label' => 'Max Rider Weight', 'unit' => 'lbs' ),
                    array( 'key' => 'dimensions.height', 'label' => 'Height', 'unit' => '"' ),
                    array( 'key' => 'dimensions.width', 'label' => 'Width', 'unit' => '"' ),
                    array( 'key' => 'dimensions.depth', 'label' => 'Depth', 'unit' => '"' ),
                ),
            ),
            'Wheel & Tire' => array(
                'icon'  => 'circle',
                'specs' => array(
                    array( 'key' => 'wheel.tire_size', 'label' => 'Tire Size', 'unit' => '"' ),
                    array( 'key' => 'wheel.tire_width', 'label' => 'Tire Width', 'unit' => '"' ),
                    array( 'key' => 'wheel.tire_type', 'label' => 'Tire Type' ),
                    array( 'key' => 'wheel.tire_tread', 'label' => 'Tire Tread' ),
                    array( 'key' => 'wheel.self_healing', 'label' => 'Self-Healing Tire', 'format' => 'feature_check', 'feature_value' => true ),
                ),
            ),
            'Suspension' => array(
                'icon'  => 'smile',
                'specs' => array(
                    array( 'key' => 'suspension.suspension_type', 'label' => 'Suspension Type' ),
                    array( 'key' => 'suspension.suspension_travel', 'label' => 'Suspension Travel', 'unit' => 'mm' ),
                    array( 'key' => 'suspension.adjustable_suspension', 'label' => 'Adjustable', 'format' => 'feature_check', 'feature_value' => true ),
                ),
            ),
            'Pedals' => array(
                'icon'  => 'layers',
                'specs' => array(
                    array( 'key' => 'pedals.pedal_height', 'label' => 'Pedal Height', 'unit' => '"' ),
                    array( 'key' => 'pedals.pedal_width', 'label' => 'Pedal Width', 'unit' => '"' ),
                    array( 'key' => 'pedals.pedal_length', 'label' => 'Pedal Length', 'unit' => '"' ),
                    array( 'key' => 'pedals.pedal_angle', 'label' => 'Pedal Angle', 'unit' => '°' ),
                    array( 'key' => 'pedals.adjustable_pedals', 'label' => 'Adjustable Pedals', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'pedals.spiked_pedals', 'label' => 'Spiked Pedals', 'format' => 'feature_check', 'feature_value' => true ),
                ),
            ),
            'Lighting' => array(
                'icon'  => 'sun',
                'specs' => array(
                    array( 'key' => 'lighting.headlight', 'label' => 'Headlight', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'lighting.headlight_lumens', 'label' => 'Headlight Brightness', 'unit' => 'lm' ),
                    array( 'key' => 'lighting.taillight', 'label' => 'Taillight', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'lighting.brake_light', 'label' => 'Brake Light', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'lighting.rgb_lights', 'label' => 'RGB Lights', 'format' => 'feature_check', 'feature_value' => true ),
                ),
            ),
            'Safety' => array(
                'icon'  => 'shield',
                'specs' => array(
                    array( 'key' => 'safety.ip_rating', 'label' => 'IP Rating', 'format' => 'ip', 'higherBetter' => true ),
                    array( 'key' => 'safety.tiltback_speed', 'label' => 'Tiltback Speed', 'unit' => 'mph' ),
                    array( 'key' => 'safety.cutoff_speed', 'label' => 'Cutoff Speed', 'unit' => 'mph' ),
                    array( 'key' => 'safety.lift_sensor', 'label' => 'Lift Sensor', 'format' => 'feature_check', 'feature_value' => true ),
                ),
            ),
            'Connectivity & Features' => array(
                'icon'  => 'settings',
                'specs' => array(
                    array( 'key' => 'connectivity.bluetooth', 'label' => 'Bluetooth', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'connectivity.app', 'label' => 'Mobile App', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'connectivity.speaker', 'label' => 'Built-in Speaker', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'connectivity.gps', 'label' => 'GPS', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'features', 'label' => 'Kickstand', 'format' => 'feature_check', 'feature_value' => 'Kickstand' ),
                    array( 'key' => 'features', 'label' => 'Trolley Handle', 'format' => 'feature_check', 'feature_value' => 'Trolley Handle' ),
                    array( 'key' => 'features', 'label' => 'Retractable Handle', 'format' => 'feature_check', 'feature_value' => 'Retractable Handle' ),
                    array( 'key' => 'features', 'label' => 'Mudguard', 'format' => 'feature_check', 'feature_value' => 'Mudguard' ),
                    array( 'key' => 'features', 'label' => 'Power Pads', 'format' => 'feature_check', 'feature_value' => 'Power Pads' ),
                    array( 'key' => 'features', 'label' => 'Jump Pads', 'format' => 'feature_check', 'feature_value' => 'Jump Pads' ),
                    array( 'key' => 'features', 'label' => 'Display Screen', 'format' => 'feature_check', 'feature_value' => 'Display Screen' ),
                    array( 'key' => 'features', 'label' => 'USB Charging Port', 'format' => 'feature_check', 'feature_value' => 'USB Charging Port' ),
                    array( 'key' => 'features', 'label' => 'Anti-Spin Button', 'format' => 'feature_check', 'feature_value' => 'Anti-Spin Button' ),
                    array( 'key' => 'features', 'label' => 'Learning Mode', 'format' => 'feature_check', 'feature_value' => 'Learning Mode' ),
                ),
            ),
        ),
        'hoverboard' => array(
            'Motor & Performance' => array(
                'icon'  => 'zap',
                'specs' => array(
                    array( 'key' => 'motor.power_nominal', 'label' => 'Motor Power', 'unit' => 'W' ),
                    array( 'key' => 'motor.power_peak', 'label' => 'Peak Power', 'unit' => 'W' ),
                    array( 'key' => 'manufacturer_top_speed', 'label' => 'Top Speed (Claimed)', 'unit' => 'mph' ),
                    array( 'key' => 'manufacturer_range', 'label' => 'Range (Claimed)', 'unit' => 'mi' ),
                    array( 'key' => 'hill_climb_angle', 'label' => 'Hill Grade', 'unit' => '°' ),
                ),
            ),
            'Battery & Charging' => array(
                'icon'  => 'battery',
                'specs' => array(
                    array( 'key' => 'battery.capacity', 'label' => 'Battery Capacity', 'unit' => 'Wh' ),
                    array( 'key' => 'battery.voltage', 'label' => 'Voltage', 'unit' => 'V' ),
                    array( 'key' => 'battery.amphours', 'label' => 'Amp Hours', 'unit' => 'Ah' ),
                    array( 'key' => 'battery.battery_type', 'label' => 'Battery Type' ),
                    array( 'key' => 'battery.charging_time', 'label' => 'Charge Time', 'unit' => 'hrs' ),
                ),
            ),
            'Build & Dimensions' => array(
                'icon'  => 'box',
                'specs' => array(
                    array( 'key' => 'dimensions.weight', 'label' => 'Weight', 'unit' => 'lbs' ),
                    array( 'key' => 'dimensions.max_load', 'label' => 'Max Rider Weight', 'unit' => 'lbs' ),
                    array( 'key' => 'dimensions.length', 'label' => 'Length', 'unit' => '"' ),
                    array( 'key' => 'dimensions.width', 'label' => 'Width', 'unit' => '"' ),
                    array( 'key' => 'dimensions.height', 'label' => 'Height', 'unit' => '"' ),
                ),
            ),
            'Wheels' => array(
                'icon'  => 'circle',
                'specs' => array(
                    array( 'key' => 'wheels.wheel_size', 'label' => 'Wheel Size', 'unit' => '"' ),
                    array( 'key' => 'wheels.wheel_width', 'label' => 'Wheel Width', 'unit' => '"' ),
                    array( 'key' => 'wheels.wheel_type', 'label' => 'Wheel Type' ),
                    array( 'key' => 'wheels.tire_type', 'label' => 'Tire Type' ),
                ),
            ),
            'Safety & Connectivity' => array(
                'icon'  => 'shield',
                'specs' => array(
                    array( 'key' => 'safety.ul_2272', 'label' => 'UL 2272 Certified', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'safety.ip_rating', 'label' => 'IP Rating', 'format' => 'ip', 'higherBetter' => true ),
                    array( 'key' => 'connectivity.bluetooth_speaker', 'label' => 'Bluetooth Speaker', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'connectivity.app_enabled', 'label' => 'App Enabled', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'connectivity.speed_modes', 'label' => 'Speed Modes', 'format' => 'feature_check', 'feature_value' => true ),
                ),
            ),
            'Features' => array(
                'icon'  => 'settings',
                'specs' => array(
                    array( 'key' => 'lighting.lights', 'label' => 'Lights', 'format' => 'array' ),
                    array( 'key' => 'features', 'label' => 'Features', 'format' => 'array' ),
                    array( 'key' => 'other.terrain', 'label' => 'Terrain' ),
                    array( 'key' => 'other.min_age', 'label' => 'Minimum Age' ),
                ),
            ),
        ),
        'eskateboard' => array(
            'Motor & Power' => array(
                'icon'  => 'zap',
                'specs' => array(
                    array( 'key' => 'motor.power_nominal', 'label' => 'Motor Power', 'unit' => 'W' ),
                    array( 'key' => 'motor.power_peak', 'label' => 'Peak Power', 'unit' => 'W' ),
                    array( 'key' => 'motor.motor_type', 'label' => 'Motor Type' ),
                    array( 'key' => 'motor.drive', 'label' => 'Drive' ),
                    array( 'key' => 'motor.motor_count', 'label' => 'Motors' ),
                    array( 'key' => 'motor.motor_size', 'label' => 'Motor Size' ),
                    array( 'key' => 'manufacturer_top_speed', 'label' => 'Top Speed (Claimed)', 'unit' => 'mph' ),
                    array( 'key' => 'manufacturer_range', 'label' => 'Range (Claimed)', 'unit' => 'mi' ),
                ),
            ),
            'ERideHero Test Results' => array(
                'icon'  => 'clipboard-check',
                'specs' => array(
                    array( 'key' => 'tested_top_speed', 'label' => 'Top Speed (Tested)', 'unit' => 'mph' ),
                    array( 'key' => 'tested_range_regular', 'label' => 'Range (Regular Riding)', 'unit' => 'mi' ),
                    array( 'key' => 'tested_range_fast', 'label' => 'Range (Fast Riding)', 'unit' => 'mi' ),
                    array( 'key' => 'tested_range_slow', 'label' => 'Range (Eco Mode)', 'unit' => 'mi' ),
                    array( 'key' => 'acceleration_0_15_mph', 'label' => '0–15 mph', 'unit' => 's' ),
                    array( 'key' => 'acceleration_0_20_mph', 'label' => '0–20 mph', 'unit' => 's' ),
                    array( 'key' => 'acceleration_0_to_top', 'label' => '0–Top Speed', 'unit' => 's' ),
                ),
            ),
            'Battery & Charging' => array(
                'icon'  => 'battery',
                'specs' => array(
                    array( 'key' => 'battery.capacity', 'label' => 'Battery Capacity', 'unit' => 'Wh' ),
                    array( 'key' => 'battery.voltage', 'label' => 'Voltage', 'unit' => 'V' ),
                    array( 'key' => 'battery.amphours', 'label' => 'Amp Hours', 'unit' => 'Ah' ),
                    array( 'key' => 'battery.battery_type', 'label' => 'Battery Type' ),
                    array( 'key' => 'battery.brand', 'label' => 'Battery Brand' ),
                    array( 'key' => 'battery.configuration', 'label' => 'Configuration' ),
                    array( 'key' => 'battery.charging_time', 'label' => 'Charge Time', 'unit' => 'hrs' ),
                ),
            ),
            'Deck & Trucks' => array(
                'icon'  => 'layers',
                'specs' => array(
                    array( 'key' => 'deck.length', 'label' => 'Deck Length', 'unit' => '"' ),
                    array( 'key' => 'deck.width', 'label' => 'Deck Width', 'unit' => '"' ),
                    array( 'key' => 'deck.material', 'label' => 'Deck Material' ),
                    array( 'key' => 'deck.concave', 'label' => 'Concave' ),
                    array( 'key' => 'trucks.trucks', 'label' => 'Trucks' ),
                    array( 'key' => 'trucks.bushings', 'label' => 'Bushings' ),
                ),
            ),
            'Wheels & Suspension' => array(
                'icon'  => 'circle',
                'specs' => array(
                    array( 'key' => 'wheels.wheel_size', 'label' => 'Wheel Size', 'unit' => 'mm' ),
                    array( 'key' => 'wheels.wheel_width', 'label' => 'Wheel Width', 'unit' => 'mm' ),
                    array( 'key' => 'wheels.durometer', 'label' => 'Durometer', 'unit' => 'A' ),
                    array( 'key' => 'wheels.wheel_type', 'label' => 'Wheel Type' ),
                    array( 'key' => 'wheels.wheel_material', 'label' => 'Wheel Material' ),
                    array( 'key' => 'wheels.terrain', 'label' => 'Terrain' ),
                    array( 'key' => 'suspension.has_suspension', 'label' => 'Suspension', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'suspension.suspension_type', 'label' => 'Suspension Type' ),
                ),
            ),
            'Weight & Dimensions' => array(
                'icon'  => 'box',
                'specs' => array(
                    array( 'key' => 'dimensions.weight', 'label' => 'Weight', 'unit' => 'lbs' ),
                    array( 'key' => 'dimensions.max_load', 'label' => 'Max Rider Weight', 'unit' => 'lbs' ),
                    array( 'key' => 'dimensions.wheelbase', 'label' => 'Wheelbase', 'unit' => '"' ),
                    array( 'key' => 'dimensions.ground_clearance', 'label' => 'Ground Clearance', 'unit' => '"' ),
                ),
            ),
            'Electronics & Features' => array(
                'icon'  => 'settings',
                'specs' => array(
                    array( 'key' => 'electronics.esc', 'label' => 'ESC' ),
                    array( 'key' => 'electronics.remote_type', 'label' => 'Remote' ),
                    array( 'key' => 'other.ip_rating', 'label' => 'IP Rating', 'format' => 'ip', 'higherBetter' => true ),
                    array( 'key' => 'lighting.lights', 'label' => 'Lights', 'format' => 'array' ),
                    array( 'key' => 'lighting.ambient_lights', 'label' => 'Ambient Lights', 'format' => 'feature_check', 'feature_value' => true ),
                    array( 'key' => 'features', 'label' => 'Cruise Control', 'format' => 'feature_check', 'feature_value' => 'Cruise Control' ),
                    array( 'key' => 'features', 'label' => 'Speed Modes', 'format' => 'feature_check', 'feature_value' => 'Speed Modes' ),
                    array( 'key' => 'features', 'label' => 'App', 'format' => 'feature_check', 'feature_value' => 'App' ),
                    array( 'key' => 'features', 'label' => 'Regenerative Braking', 'format' => 'feature_check', 'feature_value' => 'Regenerative Braking' ),
                    array( 'key' => 'features', 'label' => 'Push Start', 'format' => 'feature_check', 'feature_value' => 'Push Start' ),
                    array( 'key' => 'features', 'label' => 'Quick-Swap Battery', 'format' => 'feature_check', 'feature_value' => 'Quick-Swap Battery' ),
                    array( 'key' => 'features', 'label' => 'Reverse', 'format' => 'feature_check', 'feature_value' => 'Reverse' ),
                    array( 'key' => 'features', 'label' => 'Braking Modes', 'format' => 'feature_check', 'feature_value' => 'Braking Modes' ),
                    array( 'key' => 'features', 'label' => 'Bindings Compatible', 'format' => 'feature_check', 'feature_value' => 'Bindings Compatible' ),
                    array( 'key' => 'features', 'label' => 'Handle', 'format' => 'feature_check', 'feature_value' => 'Handle' ),
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

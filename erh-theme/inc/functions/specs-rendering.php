<?php
/**
 * Spec Rendering Functions
 *
 * Functions for retrieving and rendering product specifications.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get a nested value from an array using dot notation.
 *
 * @param array  $array The array to traverse.
 * @param string $path  Dot-separated path (e.g., 'motor.power_nominal').
 * @return mixed|null Value at path or null if not found.
 */
function erh_get_nested_value( array $array, string $path ) {
    if ( empty( $path ) ) {
        return null;
    }

    // Direct key access if no dot.
    if ( strpos( $path, '.' ) === false ) {
        return $array[ $path ] ?? null;
    }

    // Navigate nested path.
    $parts   = explode( '.', $path );
    $current = $array;

    foreach ( $parts as $part ) {
        if ( ! is_array( $current ) || ! isset( $current[ $part ] ) ) {
            return null;
        }
        $current = $current[ $part ];
    }

    return $current;
}

/**
 * Format a spec value for display.
 *
 * @param mixed  $value  The raw value.
 * @param array  $spec   Spec configuration with optional 'unit' and 'format'.
 * @return string Formatted display value.
 */
function erh_format_spec_value( $value, array $spec ): string {
    if ( $value === null || $value === '' ) {
        return '';
    }

    // Handle arrays (suspension, features, lights, etc.).
    if ( is_array( $value ) ) {
        if ( empty( $value ) ) {
            return '';
        }

        // Features array - show count + first few.
        if ( ( $spec['format'] ?? '' ) === 'features' ) {
            $filtered = array_filter( $value, function( $v ) {
                return ! empty( $v ) && $v !== 'None';
            } );
            if ( count( $filtered ) <= 3 ) {
                return implode( ', ', $filtered );
            }
            return implode( ', ', array_slice( $filtered, 0, 3 ) ) . ' +' . ( count( $filtered ) - 3 ) . ' more';
        }

        // Generic array format.
        $filtered = array_filter( $value, function( $v ) {
            return ! empty( $v ) && $v !== 'None';
        } );
        if ( empty( $filtered ) ) {
            return '';
        }
        return implode( ', ', $filtered );
    }

    // Handle booleans.
    if ( ( $spec['format'] ?? '' ) === 'boolean' ) {
        if ( $value === true || $value === 'Yes' || $value === 'yes' || $value === 1 || $value === '1' ) {
            return 'Yes';
        }
        if ( $value === false || $value === 'No' || $value === 'no' || $value === 0 || $value === '0' ) {
            return 'No';
        }
        return (string) $value;
    }

    // Numeric with unit.
    if ( isset( $spec['unit'] ) && is_numeric( $value ) ) {
        $formatted = is_float( $value + 0 ) && floor( $value ) != $value
            ? number_format( (float) $value, 1 )
            : (string) $value;
        return $formatted . ' ' . $spec['unit'];
    }

    return (string) $value;
}

/**
 * Get spec value from product_data specs array.
 *
 * Handles the database structure where:
 * - Flat keys (tested_top_speed, etc.) are at root level
 * - Nested specs are under product type key (e-scooters, e-bikes, etc.)
 *
 * @param array  $specs          Full specs array from product_data.
 * @param string $key            Spec key (may be dot-notation like 'motor.power_nominal').
 * @param string $nested_wrapper The wrapper key for nested specs ('e-scooters', 'e-bikes', etc.).
 * @return mixed|null Spec value or null.
 */
function erh_get_spec_from_cache( array $specs, string $key, string $nested_wrapper ) {
    // First try direct/flat key at root level (e.g., 'tested_top_speed').
    if ( strpos( $key, '.' ) === false ) {
        if ( isset( $specs[ $key ] ) && $specs[ $key ] !== '' && $specs[ $key ] !== null ) {
            return $specs[ $key ];
        }
    }

    // For nested keys (e.g., 'motor.power_nominal'), look inside the wrapper.
    if ( ! empty( $specs[ $nested_wrapper ] ) && is_array( $specs[ $nested_wrapper ] ) ) {
        $value = erh_get_nested_value( $specs[ $nested_wrapper ], $key );
        if ( $value !== null && $value !== '' ) {
            return $value;
        }
    }

    // Also try at root level for nested keys (in case data structure varies).
    $value = erh_get_nested_value( $specs, $key );
    if ( $value !== null && $value !== '' ) {
        return $value;
    }

    return null;
}

/**
 * Get specification groups for a product
 *
 * Returns organized spec groups with labels and formatted values.
 * Adapts to different product types (e-scooter, e-bike, etc.)
 *
 * @param int    $product_id   The product ID.
 * @param string $product_type The product type.
 * @return array Array of spec groups with 'label' and 'specs' arrays.
 */
function erh_get_spec_groups( int $product_id, string $product_type ): array {
    // Normalize product type to category key.
    $type_key = strtolower( str_replace( array( ' ', '-' ), '_', $product_type ) );
    $category = match ( $type_key ) {
        'electric_scooter' => 'escooter',
        'electric_bike'    => 'ebike',
        'electric_skateboard' => 'eskateboard',
        'electric_unicycle'   => 'euc',
        'hoverboard'       => 'hoverboard',
        default            => 'escooter',
    };

    // Check transient cache first (6 hour TTL).
    $cache_key = \ERH\CacheKeys::productSpecs( $product_id, $category );
    $cached = get_transient( $cache_key );

    if ( $cached !== false ) {
        return $cached;
    }

    // Try to get from wp_product_data cache table (single source of truth).
    $groups = erh_build_spec_groups_from_cache( $product_id, $category );

    // Fall back to ACF if cache not available (during cache rebuild).
    if ( empty( $groups ) ) {
        if ( $category === 'escooter' ) {
            $groups = erh_get_escooter_spec_groups( $product_id );
        } elseif ( $category === 'ebike' ) {
            $groups = erh_get_ebike_spec_groups( $product_id );
        } else {
            $groups = erh_get_escooter_spec_groups( $product_id );
        }
    }

    // Filter out empty groups.
    $groups = array_filter( $groups, function( $group ) {
        return ! empty( $group['specs'] );
    } );

    // Cache for 6 hours (aligned with listicle specs cache).
    set_transient( $cache_key, $groups, 6 * HOUR_IN_SECONDS );

    return $groups;
}

/**
 * Get e-scooter specification groups
 *
 * @param int $product_id The product ID.
 * @return array Array of spec groups.
 */
function erh_get_escooter_spec_groups( int $product_id ): array {
    // Get nested e-scooter data.
    $escooter = get_field( 'e-scooters', $product_id );

    $groups = array();

    // Claimed Performance (manufacturer specs - NOT tested data).
    $groups['claimed'] = array(
        'label' => 'Claimed performance',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Top speed', 'value' => get_field( 'manufacturer_top_speed', $product_id ), 'unit' => 'mph' ),
            array( 'label' => 'Range', 'value' => get_field( 'manufacturer_range', $product_id ), 'unit' => 'mi' ),
            array( 'label' => 'Max incline', 'value' => get_field( 'max_incline', $product_id ), 'unit' => '°' ),
        ) ),
    );

    // Motor & Power.
    $motor = $escooter['motor'] ?? array();
    $groups['motor'] = array(
        'label' => 'Motor & power',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Motor position', 'value' => $motor['motor_position'] ?? '' ),
            array( 'label' => 'Motor type', 'value' => $motor['motor_type'] ?? '' ),
            array( 'label' => 'Voltage', 'value' => $motor['voltage'] ?? '', 'unit' => 'V' ),
            array( 'label' => 'Nominal power', 'value' => $motor['power_nominal'] ?? '', 'unit' => 'W' ),
            array( 'label' => 'Peak power', 'value' => $motor['power_peak'] ?? '', 'unit' => 'W' ),
        ) ),
    );

    // Battery & Charging.
    $battery = $escooter['battery'] ?? array();
    $groups['battery'] = array(
        'label' => 'Battery & charging',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Capacity', 'value' => $battery['capacity'] ?? '', 'unit' => 'Wh' ),
            array( 'label' => 'Voltage', 'value' => $battery['voltage'] ?? '', 'unit' => 'V' ),
            array( 'label' => 'Amp hours', 'value' => $battery['amphours'] ?? '', 'unit' => 'Ah' ),
            array( 'label' => 'Battery type', 'value' => $battery['battery_type'] ?? '' ),
            array( 'label' => 'Battery brand', 'value' => $battery['brand'] ?? '' ),
            array( 'label' => 'Charging time', 'value' => $battery['charging_time'] ?? '', 'unit' => 'hrs' ),
        ) ),
    );

    // Brakes.
    $brakes = $escooter['brakes'] ?? array();
    $groups['brakes'] = array(
        'label' => 'Brakes',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Front brake', 'value' => $brakes['front'] ?? '' ),
            array( 'label' => 'Rear brake', 'value' => $brakes['rear'] ?? '' ),
            array( 'label' => 'Regenerative braking', 'value' => erh_format_boolean( $brakes['regenerative'] ?? false ) ),
        ) ),
    );

    // Wheels & Tires.
    $wheels = $escooter['wheels'] ?? array();
    $tire_size = erh_format_tire_sizes( $wheels['tire_size_front'] ?? '', $wheels['tire_size_rear'] ?? '' );
    $groups['wheels'] = array(
        'label' => 'Wheels & tires',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Tire size', 'value' => $tire_size ),
            array( 'label' => 'Tire width', 'value' => $wheels['tire_width'] ?? '', 'unit' => '"' ),
            array( 'label' => 'Tire type', 'value' => $wheels['tire_type'] ?? '' ),
            array( 'label' => 'Pneumatic type', 'value' => $wheels['pneumatic_type'] ?? '' ),
            array( 'label' => 'Self-healing tires', 'value' => erh_format_boolean( $wheels['self_healing'] ?? false ) ),
        ) ),
    );

    // Suspension.
    $suspension = $escooter['suspension'] ?? array();
    $suspension_type = $suspension['type'] ?? array();
    $groups['suspension'] = array(
        'label' => 'Suspension',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Suspension type', 'value' => is_array( $suspension_type ) ? implode( ', ', $suspension_type ) : $suspension_type ),
            array( 'label' => 'Adjustable', 'value' => erh_format_boolean( $suspension['adjustable'] ?? false ) ),
        ) ),
    );

    // Dimensions & Weight.
    $dims = $escooter['dimensions'] ?? array();
    $handlebar_height = erh_format_range( $dims['handlebar_height_min'] ?? '', $dims['handlebar_height_max'] ?? '', '"' );
    $groups['dimensions'] = array(
        'label' => 'Dimensions & weight',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Weight', 'value' => $dims['weight'] ?? '', 'unit' => 'lbs' ),
            array( 'label' => 'Max load', 'value' => $dims['max_load'] ?? '', 'unit' => 'lbs' ),
            array( 'label' => 'Deck length', 'value' => $dims['deck_length'] ?? '', 'unit' => '"' ),
            array( 'label' => 'Deck width', 'value' => $dims['deck_width'] ?? '', 'unit' => '"' ),
            array( 'label' => 'Ground clearance', 'value' => $dims['ground_clearance'] ?? '', 'unit' => '"' ),
            array( 'label' => 'Handlebar height', 'value' => $handlebar_height ),
            array( 'label' => 'Handlebar width', 'value' => $dims['handlebar_width'] ?? '', 'unit' => '"' ),
            array( 'label' => 'Unfolded (L×W×H)', 'value' => erh_format_dimensions( $dims['unfolded_length'] ?? '', $dims['unfolded_width'] ?? '', $dims['unfolded_height'] ?? '' ) ),
            array( 'label' => 'Folded (L×W×H)', 'value' => erh_format_dimensions( $dims['folded_length'] ?? '', $dims['folded_width'] ?? '', $dims['folded_height'] ?? '' ) ),
            array( 'label' => 'Foldable handlebars', 'value' => erh_format_boolean( $dims['foldable_handlebars'] ?? false ) ),
        ) ),
    );

    // Lighting.
    $lighting = $escooter['lighting'] ?? array();
    $lights = $lighting['lights'] ?? array();
    $groups['lighting'] = array(
        'label' => 'Lighting',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Lights', 'value' => is_array( $lights ) ? implode( ', ', $lights ) : $lights ),
            array( 'label' => 'Turn signals', 'value' => erh_format_boolean( $lighting['turn_signals'] ?? false ) ),
        ) ),
    );

    // Controls & Other.
    $other = $escooter['other'] ?? array();
    $groups['other'] = array(
        'label' => 'Controls & other',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Throttle type', 'value' => $other['throttle_type'] ?? '' ),
            array( 'label' => 'Display type', 'value' => $other['display_type'] ?? '' ),
            array( 'label' => 'Fold location', 'value' => $other['fold_location'] ?? '' ),
            array( 'label' => 'Terrain', 'value' => $other['terrain'] ?? '' ),
            array( 'label' => 'IP rating', 'value' => $other['ip_rating'] ?? '' ),
            array( 'label' => 'Kickstand', 'value' => erh_format_boolean( $other['kickstand'] ?? false ) ),
            array( 'label' => 'Footrest', 'value' => erh_format_boolean( $other['footrest'] ?? false ) ),
        ) ),
    );

    // Features.
    $features = $escooter['features'] ?? array();
    if ( ! empty( $features ) && is_array( $features ) ) {
        $groups['features'] = array(
            'label' => 'Features',
            'specs' => array(
                array( 'label' => 'Included features', 'value' => implode( ', ', $features ) ),
            ),
        );
    }

    return $groups;
}

/**
 * Get e-bike specification groups
 *
 * ACF fallback when cache is not available (during cache rebuild).
 * Uses correct ACF field paths from acf-json/acf-fields-ebike.json.
 *
 * @param int $product_id The product ID.
 * @return array Array of spec groups.
 */
function erh_get_ebike_spec_groups( int $product_id ): array {
    // Get nested e-bike data.
    $ebike = get_field( 'e-bikes', $product_id );

    if ( empty( $ebike ) ) {
        return array();
    }

    $groups = array();

    // Category.
    $category = $ebike['category'] ?? array();
    if ( ! empty( $category ) && is_array( $category ) ) {
        $groups['category'] = array(
            'label' => 'Category',
            'specs' => array(
                array( 'label' => 'Category', 'value' => implode( ', ', $category ) ),
            ),
        );
    }

    // Motor & Assistance.
    $motor = $ebike['motor'] ?? array();
    $groups['motor'] = array(
        'label' => 'Motor & assistance',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Motor type', 'value' => $motor['motor_type'] ?? '' ),
            array( 'label' => 'Motor position', 'value' => $motor['motor_position'] ?? '' ),
            array( 'label' => 'Nominal power', 'value' => $motor['power_nominal'] ?? '', 'unit' => 'W' ),
            array( 'label' => 'Peak power', 'value' => $motor['power_peak'] ?? '', 'unit' => 'W' ),
            array( 'label' => 'Torque', 'value' => $motor['torque'] ?? '', 'unit' => 'Nm' ),
            array( 'label' => 'Sensor type', 'value' => $motor['sensor_type'] ?? '' ),
            array( 'label' => 'Assist levels', 'value' => $motor['assist_levels'] ?? '' ),
        ) ),
    );

    // Speed & Class.
    $speed = $ebike['speed_and_class'] ?? array();
    $class_arr = $speed['class'] ?? array();
    $groups['speed'] = array(
        'label' => 'Speed & class',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'E-Bike class', 'value' => is_array( $class_arr ) ? implode( ', ', $class_arr ) : $class_arr ),
            array( 'label' => 'Top assist speed', 'value' => $speed['top_assist_speed'] ?? '', 'unit' => 'mph' ),
            array( 'label' => 'Throttle', 'value' => erh_format_boolean( $speed['throttle'] ?? false ) ),
            array( 'label' => 'Throttle top speed', 'value' => $speed['throttle_top_speed'] ?? '', 'unit' => 'mph' ),
        ) ),
    );

    // Battery & Range.
    $battery = $ebike['battery'] ?? array();
    $groups['battery'] = array(
        'label' => 'Battery & range',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Battery capacity', 'value' => $battery['battery_capacity'] ?? '', 'unit' => 'Wh' ),
            array( 'label' => 'Voltage', 'value' => $battery['voltage'] ?? '', 'unit' => 'V' ),
            array( 'label' => 'Amp hours', 'value' => $battery['amphours'] ?? '', 'unit' => 'Ah' ),
            array( 'label' => 'Max range', 'value' => $battery['range'] ?? '', 'unit' => 'mi' ),
            array( 'label' => 'Charge time', 'value' => $battery['charge_time'] ?? '', 'unit' => 'hrs' ),
            array( 'label' => 'Battery position', 'value' => $battery['battery_position'] ?? '' ),
            array( 'label' => 'Removable battery', 'value' => erh_format_boolean( $battery['removable'] ?? false ) ),
        ) ),
    );

    // Frame & Fit.
    $frame = $ebike['frame_and_geometry'] ?? array();
    $weight_cap = $ebike['weight_and_capacity'] ?? array();
    $frame_material = $frame['frame_material'] ?? array();
    $frame_style = $frame['frame_style'] ?? array();
    $sizes = $frame['sizes_available'] ?? array();
    $groups['frame'] = array(
        'label' => 'Frame & fit',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Frame material', 'value' => is_array( $frame_material ) ? implode( ', ', $frame_material ) : $frame_material ),
            array( 'label' => 'Frame style', 'value' => is_array( $frame_style ) ? implode( ', ', $frame_style ) : $frame_style ),
            array( 'label' => 'Sizes available', 'value' => is_array( $sizes ) ? implode( ', ', $sizes ) : $sizes ),
            array( 'label' => 'Weight', 'value' => $weight_cap['weight'] ?? '', 'unit' => 'lbs' ),
            array( 'label' => 'Weight limit', 'value' => $weight_cap['weight_limit'] ?? '', 'unit' => 'lbs' ),
            array( 'label' => 'Rack capacity', 'value' => $weight_cap['rack_capacity'] ?? '', 'unit' => 'lbs' ),
            array( 'label' => 'Standover height', 'value' => $frame['standover_height'] ?? '', 'unit' => '"' ),
            array( 'label' => 'Min rider height', 'value' => $frame['min_rider_height'] ?? '', 'unit' => '"' ),
            array( 'label' => 'Max rider height', 'value' => $frame['max_rider_height'] ?? '', 'unit' => '"' ),
        ) ),
    );

    // Suspension.
    $suspension = $ebike['suspension'] ?? array();
    $groups['suspension'] = array(
        'label' => 'Suspension',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Front suspension', 'value' => $suspension['front_suspension'] ?? '' ),
            array( 'label' => 'Front travel', 'value' => $suspension['front_travel'] ?? '', 'unit' => 'mm' ),
            array( 'label' => 'Rear suspension', 'value' => $suspension['rear_suspension'] ?? '' ),
            array( 'label' => 'Rear travel', 'value' => $suspension['rear_travel'] ?? '', 'unit' => 'mm' ),
            array( 'label' => 'Seatpost suspension', 'value' => erh_format_boolean( $suspension['seatpost_suspension'] ?? false ) ),
        ) ),
    );

    // Drivetrain.
    $drivetrain = $ebike['drivetrain'] ?? array();
    $groups['drivetrain'] = array(
        'label' => 'Drivetrain',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Gears', 'value' => $drivetrain['gears'] ?? '' ),
            array( 'label' => 'Drive system', 'value' => $drivetrain['drive_system'] ?? '' ),
            array( 'label' => 'Shifter', 'value' => $drivetrain['shifter'] ?? '' ),
            array( 'label' => 'Derailleur', 'value' => $drivetrain['derailleur'] ?? '' ),
            array( 'label' => 'Cassette', 'value' => $drivetrain['cassette'] ?? '' ),
        ) ),
    );

    // Brakes.
    $brakes = $ebike['brakes'] ?? array();
    $brake_type = $brakes['brake_type'] ?? array();
    $groups['brakes'] = array(
        'label' => 'Brakes',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Brake type', 'value' => is_array( $brake_type ) ? implode( ', ', $brake_type ) : $brake_type ),
            array( 'label' => 'Brake brand', 'value' => $brakes['brake_brand'] ?? '' ),
            array( 'label' => 'Rotor size (front)', 'value' => $brakes['rotor_size_front'] ?? '', 'unit' => 'mm' ),
            array( 'label' => 'Rotor size (rear)', 'value' => $brakes['rotor_size_rear'] ?? '', 'unit' => 'mm' ),
        ) ),
    );

    // Wheels & Tires.
    $wheels = $ebike['wheels_and_tires'] ?? array();
    $groups['wheels'] = array(
        'label' => 'Wheels & tires',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Wheel size', 'value' => $wheels['wheel_size'] ?? '', 'unit' => '"' ),
            array( 'label' => 'Wheel size (rear)', 'value' => $wheels['wheel_size_rear'] ?? '', 'unit' => '"' ),
            array( 'label' => 'Tire width', 'value' => $wheels['tire_width'] ?? '', 'unit' => '"' ),
            array( 'label' => 'Tire type', 'value' => $wheels['tire_type'] ?? '' ),
            array( 'label' => 'Puncture protection', 'value' => erh_format_boolean( $wheels['puncture_protection'] ?? false ) ),
        ) ),
    );

    // Components & Tech.
    $components = $ebike['components'] ?? array();
    $connectivity = $components['connectivity'] ?? array();
    $groups['components'] = array(
        'label' => 'Components & tech',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Display', 'value' => $components['display'] ?? '' ),
            array( 'label' => 'Display size', 'value' => $components['display_size'] ?? '', 'unit' => '"' ),
            array( 'label' => 'Connectivity', 'value' => is_array( $connectivity ) ? implode( ', ', $connectivity ) : $connectivity ),
            array( 'label' => 'App compatible', 'value' => erh_format_boolean( $components['app_compatible'] ?? false ) ),
        ) ),
    );

    // Features & Safety.
    $features = $ebike['integrated_features'] ?? array();
    $safety = $ebike['safety_and_compliance'] ?? array();
    $special = $ebike['special_features'] ?? array();
    $certifications = $safety['certifications'] ?? array();
    $groups['features'] = array(
        'label' => 'Features & safety',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Integrated lights', 'value' => erh_format_boolean( $features['integrated_lights'] ?? false ) ),
            array( 'label' => 'Fenders', 'value' => erh_format_boolean( $features['fenders'] ?? false ) ),
            array( 'label' => 'Rear rack', 'value' => erh_format_boolean( $features['rear_rack'] ?? false ) ),
            array( 'label' => 'Front rack', 'value' => erh_format_boolean( $features['front_rack'] ?? false ) ),
            array( 'label' => 'Kickstand', 'value' => erh_format_boolean( $features['kickstand'] ?? false ) ),
            array( 'label' => 'Chain guard', 'value' => erh_format_boolean( $features['chain_guard'] ?? false ) ),
            array( 'label' => 'Walk assist', 'value' => erh_format_boolean( $features['walk_assist'] ?? false ) ),
            array( 'label' => 'Alarm', 'value' => erh_format_boolean( $features['alarm'] ?? false ) ),
            array( 'label' => 'USB charging', 'value' => erh_format_boolean( $features['usb'] ?? false ) ),
            array( 'label' => 'Bottle cage mount', 'value' => erh_format_boolean( $features['bottle_cage_mount'] ?? false ) ),
            array( 'label' => 'IP rating', 'value' => $safety['ip_rating'] ?? '' ),
            array( 'label' => 'Certifications', 'value' => is_array( $certifications ) ? implode( ', ', $certifications ) : $certifications ),
            array( 'label' => 'Special features', 'value' => is_array( $special ) ? implode( ', ', $special ) : $special ),
        ) ),
    );

    return $groups;
}

/**
 * Build spec groups from wp_product_data cache table.
 *
 * Uses the same source of truth as listicle items, ensuring consistency
 * between product pages and listicle blocks.
 *
 * @param int    $product_id Product ID.
 * @param string $category   Category key ('escooter', 'ebike', etc.).
 * @return array Array of spec groups with 'label' and 'specs' arrays.
 */
function erh_build_spec_groups_from_cache( int $product_id, string $category ): array {
    // Get product data from wp_product_data cache table.
    $product_data = erh_get_product_cache_data( $product_id );

    if ( ! $product_data || empty( $product_data['specs'] ) ) {
        return array();
    }

    $specs = $product_data['specs'];
    if ( ! is_array( $specs ) ) {
        return array();
    }

    // Get spec groups configuration.
    $spec_config = erh_get_spec_groups_config( $category );
    if ( empty( $spec_config ) ) {
        return array();
    }

    // Get the wrapper key for nested specs (e.g., 'e-scooters' for escooter).
    $nested_wrapper = erh_get_specs_wrapper_key( $category );

    $groups = array();
    $group_index = 0;

    foreach ( $spec_config as $group_name => $group_config ) {
        // Skip Value Analysis section on product page.
        if ( ! empty( $group_config['is_value_section'] ) ) {
            continue;
        }

        $spec_defs = $group_config['specs'] ?? array();
        $group_specs = array();

        foreach ( $spec_defs as $spec_def ) {
            // Special handling for feature_check format - show as Yes/No.
            if ( ( $spec_def['format'] ?? '' ) === 'feature_check' ) {
                $features_array = erh_get_spec_from_cache( $specs, $spec_def['key'], $nested_wrapper );
                $has_feature = is_array( $features_array ) && in_array( $spec_def['feature_value'], $features_array, true );
                // Only show "Yes" features to match erh_filter_specs behavior.
                if ( $has_feature ) {
                    $group_specs[] = array(
                        'label' => $spec_def['label'],
                        'value' => 'Yes',
                    );
                }
                continue;
            }

            // Get value from cache.
            $value = erh_get_spec_from_cache( $specs, $spec_def['key'], $nested_wrapper );

            // Skip empty values.
            if ( $value === null || $value === '' ) {
                continue;
            }

            // Format the value.
            $formatted = erh_format_spec_value( $value, $spec_def );

            // Skip empty or "No" formatted values (matches erh_filter_specs behavior).
            if ( $formatted === '' || $formatted === 'No' ) {
                continue;
            }

            $group_specs[] = array(
                'label' => $spec_def['label'],
                'value' => $formatted,
            );
        }

        // Only add groups with specs.
        if ( ! empty( $group_specs ) ) {
            $group_key = 'group_' . $group_index;
            $groups[ $group_key ] = array(
                'label' => $group_name,
                'specs' => $group_specs,
            );
            $group_index++;
        }
    }

    return $groups;
}

/**
 * Filter out specs with empty values
 *
 * @param array $specs Array of specs with label, value, and optional unit.
 * @return array Filtered specs with formatted values.
 */
function erh_filter_specs( array $specs ): array {
    $filtered = array();

    foreach ( $specs as $spec ) {
        $value = $spec['value'] ?? '';

        // Skip empty values.
        if ( $value === '' || $value === null || $value === 'Unknown' || $value === 'None' ) {
            continue;
        }

        // Skip "No" for boolean fields (only show if "Yes").
        if ( $value === 'No' ) {
            continue;
        }

        // Format value with unit.
        $formatted_value = $value;
        if ( isset( $spec['unit'] ) && is_numeric( $value ) ) {
            $formatted_value = $value . ' ' . $spec['unit'];
        } elseif ( isset( $spec['unit'] ) && $spec['unit'] === '"' && is_numeric( $value ) ) {
            $formatted_value = $value . '"';
        }

        $filtered[] = array(
            'label' => $spec['label'],
            'value' => $formatted_value,
        );
    }

    return $filtered;
}

/**
 * Render specs HTML from wp_product_data table.
 *
 * Simple flat table layout with category headers - no expand/collapse.
 * Matches the compare tool's spec groupings.
 *
 * @param int    $product_id  Product ID.
 * @param string $category    Category key ('escooter', 'ebike', etc.).
 * @return string HTML for specs table.
 */
function erh_render_specs_from_cache( int $product_id, string $category ): string {
    // Get product data from wp_product_data cache table.
    $product_data = erh_get_product_cache_data( $product_id );

    if ( ! $product_data || empty( $product_data['specs'] ) ) {
        return '<p class="specs-empty">Specifications not available.</p>';
    }

    $specs = $product_data['specs'];

    // Ensure specs is an array (it should be unserialized by erh_get_product_cache_data).
    if ( ! is_array( $specs ) ) {
        $specs = maybe_unserialize( $specs );
    }

    if ( ! is_array( $specs ) ) {
        return '<p class="specs-empty">Specifications not available.</p>';
    }

    $spec_groups = erh_get_spec_groups_config( $category );

    if ( empty( $spec_groups ) ) {
        return '<p class="specs-empty">Specifications not available for this product type.</p>';
    }

    // Get the wrapper key for nested specs (e.g., 'e-scooters' for escooter).
    $nested_wrapper = erh_get_specs_wrapper_key( $category );

    $html = '<div class="specs-table">';

    foreach ( $spec_groups as $category_name => $category_config ) {
        // Skip Value Analysis section on product page.
        if ( ! empty( $category_config['is_value_section'] ) ) {
            continue;
        }

        $spec_defs = $category_config['specs'] ?? array();

        // Build spec rows.
        $rows_html = '';
        foreach ( $spec_defs as $spec_def ) {
            // Special handling for feature_check format.
            if ( ( $spec_def['format'] ?? '' ) === 'feature_check' ) {
                $features_array = erh_get_spec_from_cache( $specs, $spec_def['key'], $nested_wrapper );
                $has_feature    = is_array( $features_array ) && in_array( $spec_def['feature_value'], $features_array, true );
                $rows_html     .= erh_render_feature_check_row( $spec_def['label'], $has_feature );
                continue;
            }

            $value     = erh_get_spec_from_cache( $specs, $spec_def['key'], $nested_wrapper );
            $formatted = erh_format_spec_value( $value, $spec_def );

            // Skip empty values.
            if ( $formatted === '' || $formatted === 'No' ) {
                continue;
            }

            $rows_html .= sprintf(
                '<tr><td class="specs-label">%s</td><td class="specs-value">%s</td></tr>',
                esc_html( $spec_def['label'] ),
                esc_html( $formatted )
            );
        }

        // Skip categories with no specs.
        if ( empty( $rows_html ) ) {
            continue;
        }

        // Category header + rows.
        $html .= sprintf(
            '<table class="specs-group">
                <thead><tr><th colspan="2" class="specs-group-header">%s</th></tr></thead>
                <tbody>%s</tbody>
            </table>',
            esc_html( $category_name ),
            $rows_html
        );
    }

    $html .= '</div>';

    return $html;
}

/**
 * Render a feature check row with Yes/No indicator.
 *
 * Displays a circle background with check/x icon from the sprite.
 *
 * @param string $label       Feature label.
 * @param bool   $has_feature Whether the product has this feature.
 * @return string HTML for the table row.
 */
function erh_render_feature_check_row( string $label, bool $has_feature ): string {
    $status_class = $has_feature ? 'feature-yes' : 'feature-no';
    $status_text  = $has_feature ? 'Yes' : 'No';
    $icon_name    = $has_feature ? 'check' : 'x';

    // Use icon from sprite with circle background.
    $icon = sprintf(
        '<span class="feature-badge"><svg class="icon" aria-hidden="true"><use href="#icon-%s"></use></svg></span>',
        $icon_name
    );

    return sprintf(
        '<tr class="%s"><td class="specs-label">%s</td><td class="specs-value specs-feature-value">%s<span class="feature-text">%s</span></td></tr>',
        esc_attr( $status_class ),
        esc_html( $label ),
        $icon,
        esc_html( $status_text )
    );
}

/**
 * Render product specs HTML with bordered sections.
 *
 * Uses SEO-friendly groupings with heading + bordered table per group.
 * Similar styling to the tested-performance section.
 *
 * @param int    $product_id Product ID.
 * @param string $category   Category key ('escooter', 'ebike', etc.).
 * @return string HTML for specs sections.
 */
function erh_render_product_specs( int $product_id, string $category ): string {
    // Get product data from wp_product_data cache table.
    $product_data = erh_get_product_cache_data( $product_id );

    if ( ! $product_data || empty( $product_data['specs'] ) ) {
        return '<p class="specs-empty">Specifications not available.</p>';
    }

    $specs = $product_data['specs'];

    // Ensure specs is an array.
    if ( ! is_array( $specs ) ) {
        $specs = maybe_unserialize( $specs );
    }

    if ( ! is_array( $specs ) ) {
        return '<p class="specs-empty">Specifications not available.</p>';
    }

    $spec_groups = erh_get_product_spec_groups_config( $category );

    if ( empty( $spec_groups ) ) {
        return '<p class="specs-empty">Specifications not available for this product type.</p>';
    }

    // Get the wrapper key for nested specs.
    $nested_wrapper = erh_get_specs_wrapper_key( $category );

    $html = '<div class="product-specs">';

    foreach ( $spec_groups as $group_name => $group_config ) {
        $spec_defs = $group_config['specs'] ?? array();
        $icon      = $group_config['icon'] ?? 'info';

        // Build spec rows.
        $rows_html     = '';
        $has_any_specs = false;

        foreach ( $spec_defs as $spec_def ) {
            // Special handling for feature_check format.
            if ( ( $spec_def['format'] ?? '' ) === 'feature_check' ) {
                $feature_value = $spec_def['feature_value'] ?? '';
                $raw_value     = erh_get_spec_from_cache( $specs, $spec_def['key'], $nested_wrapper );

                // Determine if feature is present.
                if ( $feature_value === true ) {
                    // Boolean field - check if truthy.
                    $has_feature = ! empty( $raw_value ) && $raw_value !== 'No' && $raw_value !== 'no' && $raw_value !== '0';
                } else {
                    // Array field - check if value is in array.
                    $has_feature = is_array( $raw_value ) && in_array( $feature_value, $raw_value, true );
                }

                $rows_html .= erh_render_spec_row_with_tooltip(
                    $spec_def['label'],
                    $has_feature ? 'Yes' : 'No',
                    $spec_def['tooltip'] ?? '',
                    $has_feature ? 'feature-yes' : 'feature-no',
                    true
                );
                $has_any_specs = true;
                continue;
            }

            $value     = erh_get_spec_from_cache( $specs, $spec_def['key'], $nested_wrapper );
            $formatted = erh_format_spec_value( $value, $spec_def );

            // Skip empty values and "No" for booleans.
            if ( $formatted === '' || $formatted === 'No' ) {
                continue;
            }

            $rows_html .= erh_render_spec_row_with_tooltip(
                $spec_def['label'],
                $formatted,
                $spec_def['tooltip'] ?? ''
            );
            $has_any_specs = true;
        }

        // Skip groups with no specs.
        if ( ! $has_any_specs ) {
            continue;
        }

        // Render group section.
        // Special handling for ERideHero Test Results - add "How we test" popover.
        if ( $group_name === 'ERideHero Test Results' ) {
            $html .= sprintf(
                '<section class="product-specs-section">
                    <div class="product-specs-header">
                        <h3 class="product-specs-title">%s</h3>
                        <div class="popover-wrapper">
                            <button type="button" class="btn btn-link btn-sm" data-popover-trigger="how-we-test-popover-product">
                                <svg class="icon" aria-hidden="true"><use href="#icon-info"></use></svg>
                                How we test
                            </button>
                            <div id="how-we-test-popover-product" class="popover popover--top" aria-hidden="true">
                                <div class="popover-arrow"></div>
                                <h4 class="popover-title">Data-driven testing</h4>
                                <p class="popover-text">All performance data is captured using a VBox Sport GPS logger — professional-grade equipment for precise vehicle measurements. Tests follow strict protocols with a 175 lb rider under controlled conditions.</p>
                                <a href="/how-we-test/" class="popover-link">
                                    Full methodology
                                    <svg class="icon" aria-hidden="true"><use href="#icon-arrow-right"></use></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="product-specs-box">
                        <table class="product-specs-table">
                            <tbody>%s</tbody>
                        </table>
                    </div>
                </section>',
                esc_html( $group_name ),
                $rows_html
            );
        } else {
            $html .= sprintf(
                '<section class="product-specs-section">
                    <h3 class="product-specs-title">%s</h3>
                    <div class="product-specs-box">
                        <table class="product-specs-table">
                            <tbody>%s</tbody>
                        </table>
                    </div>
                </section>',
                esc_html( $group_name ),
                $rows_html
            );
        }
    }

    $html .= '</div>';

    return $html;
}

/**
 * Render a spec row with optional tooltip.
 *
 * @param string $label       Spec label.
 * @param string $value       Formatted value.
 * @param string $tooltip     Tooltip text (optional).
 * @param string $row_class   Additional row class (optional).
 * @param bool   $is_feature  Whether this is a feature check row.
 * @return string HTML for the table row.
 */
function erh_render_spec_row_with_tooltip( string $label, string $value, string $tooltip = '', string $row_class = '', bool $is_feature = false ): string {
    // Build label - wrap in flex container if tooltip present.
    if ( ! empty( $tooltip ) ) {
        $label_html = sprintf(
            '<div class="product-specs-label-inner">%s<span class="info-trigger" data-tooltip="%s" data-tooltip-position="top"><svg class="icon" aria-hidden="true"><use href="#icon-info"></use></svg></span></div>',
            esc_html( $label ),
            esc_attr( $tooltip )
        );
    } else {
        $label_html = esc_html( $label );
    }

    // Build value HTML - wrap in flex container if feature check.
    if ( $is_feature ) {
        $is_yes    = $value === 'Yes';
        $icon_name = $is_yes ? 'check' : 'x';
        $value_html = sprintf(
            '<div class="product-specs-value-inner"><span class="feature-badge"><svg class="icon" aria-hidden="true"><use href="#icon-%s"></use></svg></span><span class="feature-text">%s</span></div>',
            $icon_name,
            esc_html( $value )
        );
        $value_class = 'product-specs-value';
    } else {
        $value_html  = esc_html( $value );
        $value_class = 'product-specs-value';
    }

    return sprintf(
        '<tr class="%s"><td class="product-specs-label">%s</td><td class="%s">%s</td></tr>',
        esc_attr( $row_class ),
        $label_html,
        $value_class,
        $value_html
    );
}

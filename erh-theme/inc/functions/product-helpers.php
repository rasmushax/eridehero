<?php
/**
 * Product Helper Functions
 *
 * Product type, pricing, similar products, and card display utilities.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ERH\CategoryConfig;

/**
 * Get product type label
 *
 * @param int|null $post_id Post ID (optional, uses current post if null)
 * @return string Product type label (e.g., "Electric Scooter")
 */
function erh_get_product_type( ?int $post_id = null ): string {
    $post_id = $post_id ?? get_the_ID();

    // Product type is stored as a taxonomy, not an ACF field
    $terms = get_the_terms( $post_id, 'product_type' );

    if ( $terms && ! is_wp_error( $terms ) ) {
        // Return the first term's name
        return $terms[0]->name;
    }

    return '';
}

/**
 * Get product type slug for URLs and classes
 *
 * @param string $type Product type label
 * @return string Slugified type
 */
function erh_product_type_slug( string $type ): string {
    $category = CategoryConfig::get_by_type( $type );
    return $category ? $category['archive_slug'] : sanitize_title( $type );
}

/**
 * Get finder page URL for a product type
 *
 * Returns the ACF finder_page URL if set on the taxonomy term,
 * otherwise returns null.
 *
 * @param int|null $post_id Post ID (optional, uses current post if null)
 * @return string|null Finder page URL or null if not set
 */
function erh_get_finder_url( ?int $post_id = null ): ?string {
    $post_id = $post_id ?? get_the_ID();

    $terms = get_the_terms( $post_id, 'product_type' );

    if ( ! $terms || is_wp_error( $terms ) ) {
        return null;
    }

    $term = $terms[0];

    // Get the finder_page ACF field from the taxonomy term
    $finder_url = get_field( 'finder_page', 'product_type_' . $term->term_id );

    return $finder_url ?: null;
}

/**
 * Get product obsolete status
 *
 * Returns information about whether a product is obsolete and if it has
 * been superseded by another product.
 *
 * @param int|null $post_id Post ID (optional, uses current post if null)
 * @return array {
 *     @type bool     $is_obsolete   Whether the product is obsolete/discontinued.
 *     @type bool     $is_superseded Whether the product has been replaced by a newer model.
 *     @type int|null $new_product_id ID of the replacement product (if superseded).
 *     @type string|null $new_product_name Name of the replacement product.
 *     @type string|null $new_product_url URL of the replacement product.
 * }
 */
function erh_get_product_obsolete_status( ?int $post_id = null ): array {
    $post_id = $post_id ?? get_the_ID();

    $result = array(
        'is_obsolete'       => false,
        'is_superseded'     => false,
        'new_product_id'    => null,
        'new_product_name'  => null,
        'new_product_url'   => null,
    );

    // Get obsolete group from ACF.
    $obsolete = get_field( 'obsolete', $post_id );

    if ( ! is_array( $obsolete ) ) {
        return $result;
    }

    $result['is_obsolete'] = ! empty( $obsolete['is_product_obsolete'] );

    if ( $result['is_obsolete'] && ! empty( $obsolete['has_the_product_been_superseded'] ) ) {
        $result['is_superseded'] = true;

        // Get replacement product info.
        $new_product = $obsolete['new_product'] ?? null;
        if ( $new_product ) {
            // ACF post_object can return ID or object depending on config.
            $new_product_id = is_object( $new_product ) ? $new_product->ID : $new_product;
            $result['new_product_id']   = $new_product_id;
            $result['new_product_name'] = get_the_title( $new_product_id );
            $result['new_product_url']  = get_permalink( $new_product_id );
        }
    }

    return $result;
}

/**
 * Check if product is obsolete (simple boolean helper)
 *
 * @param int|null $post_id Post ID (optional, uses current post if null)
 * @return bool
 */
function erh_is_product_obsolete( ?int $post_id = null ): bool {
    $status = erh_get_product_obsolete_status( $post_id );
    return $status['is_obsolete'];
}

/**
 * Check if product has any active pricing data
 *
 * Checks the wp_product_data cache for any region with a current price.
 * Returns false if product has no pricing in any region.
 *
 * @param int $product_id The product ID to check.
 * @return bool True if product has pricing, false otherwise.
 */
function erh_product_has_pricing( int $product_id ): bool {
    // Check transient cache first (2 hour TTL).
    $cache_key = \ERH\CacheKeys::productHasPricing( $product_id );
    $cached = get_transient( $cache_key );

    if ( $cached !== false ) {
        return $cached === '1';
    }

    global $wpdb;

    $table_name = $wpdb->prefix . 'product_data';
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT price_history FROM {$table_name} WHERE product_id = %d",
            $product_id
        ),
        ARRAY_A
    );

    if ( ! $row || empty( $row['price_history'] ) ) {
        set_transient( $cache_key, '0', 2 * HOUR_IN_SECONDS );
        return false;
    }

    $price_history = maybe_unserialize( $row['price_history'] );

    if ( ! is_array( $price_history ) || empty( $price_history ) ) {
        set_transient( $cache_key, '0', 2 * HOUR_IN_SECONDS );
        return false;
    }

    // Check if any region has a current price.
    foreach ( $price_history as $region => $data ) {
        if ( ! empty( $data['current_price'] ) && $data['current_price'] > 0 ) {
            set_transient( $cache_key, '1', 2 * HOUR_IN_SECONDS );
            return true;
        }
    }

    set_transient( $cache_key, '0', 2 * HOUR_IN_SECONDS );
    return false;
}

/**
 * Check if a product has current price data
 *
 * Uses the PriceFetcher from ERH Core to check if any retailer
 * has pricing for the product. Used to conditionally render
 * the price intelligence section.
 *
 * @param int $product_id The product ID.
 * @return bool True if product has price data.
 */
function erh_product_has_prices( int $product_id ): bool {
    // Check if ERH Core is active and PriceFetcher exists
    if ( ! class_exists( '\\ERH\\Pricing\\PriceFetcher' ) ) {
        return false;
    }

    // Use static cache to avoid repeated DB queries on same page
    static $cache = array();

    if ( ! isset( $cache[ $product_id ] ) ) {
        $fetcher = new \ERH\Pricing\PriceFetcher();
        $cache[ $product_id ] = $fetcher->has_price_data( $product_id );
    }

    return $cache[ $product_id ];
}

/**
 * Get similar products based on specs and price
 *
 * Queries the wp_product_data cache table and calculates similarity scores
 * based on key specs. Returns products of the same type that are most similar.
 *
 * Similarity factors (for e-scooters):
 * - Price (±25% = 100 points, scales down)
 * - Top speed (±5 mph = 100 points)
 * - Range (±10 miles = 100 points)
 * - Weight (±10 lbs = 100 points)
 * - Motor power (±200W = 100 points)
 *
 * @param int $product_id The product to find similar products for.
 * @param int $count Number of similar products to return.
 * @param bool $exclude_obsolete Whether to exclude obsolete products.
 * @return array Array of similar product data with similarity scores.
 */
function erh_get_similar_products( int $product_id, int $count = 4, bool $exclude_obsolete = true ): array {
    global $wpdb;

    // Get the source product data.
    $table_name = $wpdb->prefix . 'product_data';
    $source = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE product_id = %d",
            $product_id
        ),
        ARRAY_A
    );

    if ( ! $source ) {
        return array();
    }

    $source_specs = maybe_unserialize( $source['specs'] );
    $source_type  = $source['product_type'];

    // Get all products of same type (excluding source).
    $candidates = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table_name}
             WHERE product_type = %s
             AND product_id != %d
             ORDER BY popularity_score DESC",
            $source_type,
            $product_id
        ),
        ARRAY_A
    );

    if ( empty( $candidates ) ) {
        return array();
    }

    // Define spec comparison config by product type.
    $spec_config = erh_get_similarity_spec_config( $source_type );

    // Calculate similarity scores.
    $scored = array();
    foreach ( $candidates as $candidate ) {
        // Skip obsolete products if requested.
        if ( $exclude_obsolete ) {
            $obsolete = get_field( 'obsolete', $candidate['product_id'] );
            if ( ! empty( $obsolete['is_product_obsolete'] ) ) {
                continue;
            }
        }

        $candidate_specs = maybe_unserialize( $candidate['specs'] );
        $score = erh_calculate_similarity_score( $source_specs, $candidate_specs, $spec_config );

        // Add e-bike specific match bonuses (category, class, motor type, frame style).
        if ( 'Electric Bike' === $source_type ) {
            $score += erh_calculate_ebike_match_bonus( $source_specs, $candidate_specs );
        }

        // Add popularity as a tiebreaker (small weight).
        $score += ( (int) $candidate['popularity_score'] / 100 );

        // Get pricing data (geo-keyed).
        $price_history = maybe_unserialize( $candidate['price_history'] );
        $pricing       = is_array( $price_history ) ? $price_history : array();

        $scored[] = array(
            'product_id'       => (int) $candidate['product_id'],
            'name'             => $candidate['name'],
            'permalink'        => $candidate['permalink'],
            'image_url'        => $candidate['image_url'],
            'rating'           => $candidate['rating'],
            'popularity_score' => (int) $candidate['popularity_score'],
            'similarity_score' => $score,
            'specs'            => $candidate_specs,
            'pricing'          => $pricing,
        );
    }

    // Sort by similarity score (descending).
    usort( $scored, function( $a, $b ) {
        return $b['similarity_score'] <=> $a['similarity_score'];
    } );

    // Return top N.
    return array_slice( $scored, 0, $count );
}

/**
 * Get spec comparison configuration for a product type
 *
 * @param string $product_type The product type.
 * @return array Spec config with keys, ideal ranges, and weights.
 */
function erh_get_similarity_spec_config( string $product_type ): array {
    // Default config for e-scooters.
    // 'paths' is an array of alternative keys to try (flat first, then nested).
    $config = array(
        'speed' => array(
            'paths'  => array( 'manufacturer_top_speed' ),
            'range'  => 5,    // ±5 mph for 100 points.
            'weight' => 1.0,
        ),
        'range' => array(
            'paths'  => array( 'manufacturer_range' ),
            'range'  => 10,   // ±10 miles for 100 points.
            'weight' => 1.0,
        ),
        'weight' => array(
            'paths'  => array( 'weight', 'e-scooters.dimensions.weight' ),
            'range'  => 10,   // ±10 lbs for 100 points.
            'weight' => 0.8,
        ),
        'motor' => array(
            'paths'  => array( 'peak_motor_wattage', 'e-scooters.motor.power_peak', 'nominal_motor_wattage', 'e-scooters.motor.power_nominal' ),
            'range'  => 300,  // ±300W for 100 points.
            'weight' => 0.7,
        ),
        'battery' => array(
            'paths'  => array( 'battery_capacity', 'e-scooters.battery.capacity' ),
            'range'  => 150,  // ±150Wh for 100 points.
            'weight' => 0.6,
        ),
    );

    // Adjust for other product types as needed.
    if ( 'Electric Unicycle' === $product_type ) {
        $config = array(
            'motor' => array(
                'paths'  => array( 'eucs.motor.power_peak', 'eucs.motor.power_nominal' ),
                'range'  => 500,   // ±500W for 100 points.
                'weight' => 0.7,
            ),
            'battery' => array(
                'paths'  => array( 'eucs.battery.capacity' ),
                'range'  => 300,   // ±300Wh for 100 points.
                'weight' => 0.8,
            ),
            'weight' => array(
                'paths'  => array( 'eucs.dimensions.weight' ),
                'range'  => 15,    // ±15 lbs for 100 points.
                'weight' => 0.6,
            ),
            'speed' => array(
                'paths'  => array( 'manufacturer_top_speed' ),
                'range'  => 8,     // ±8 mph for 100 points.
                'weight' => 1.0,
            ),
            'range' => array(
                'paths'  => array( 'manufacturer_range' ),
                'range'  => 15,    // ±15 miles for 100 points.
                'weight' => 0.9,
            ),
        );
    }

    if ( 'Electric Bike' === $product_type ) {
        $config = array(
            // Numeric comparisons (uses range-based scoring).
            'motor' => array(
                'paths'  => array( 'e-bikes.motor.power_nominal' ),
                'range'  => 150,   // ±150W for 100 points.
                'weight' => 0.6,
            ),
            'torque' => array(
                'paths'  => array( 'e-bikes.motor.torque' ),
                'range'  => 20,    // ±20Nm for 100 points.
                'weight' => 0.5,
            ),
            'battery' => array(
                'paths'  => array( 'e-bikes.battery.battery_capacity' ),
                'range'  => 200,   // ±200Wh for 100 points.
                'weight' => 0.5,
            ),
            'weight' => array(
                'paths'  => array( 'e-bikes.weight_and_capacity.weight' ),
                'range'  => 15,    // ±15 lbs for 100 points.
                'weight' => 0.4,
            ),
        );
    }

    return $config;
}

/**
 * Calculate e-bike specific similarity bonuses for category, class, and motor type.
 *
 * @param array $source_specs    Source product specs.
 * @param array $candidate_specs Candidate product specs.
 * @return float Bonus score (0-100 scale additions).
 */
function erh_calculate_ebike_match_bonus( array $source_specs, array $candidate_specs ): float {
    $bonus = 0;

    $source_ebike    = $source_specs['e-bikes'] ?? array();
    $candidate_ebike = $candidate_specs['e-bikes'] ?? array();

    if ( empty( $source_ebike ) || empty( $candidate_ebike ) ) {
        return 0;
    }

    // Helper to get array/string value and normalize.
    $get_array = function ( array $ebike, string $path ): array {
        $keys    = explode( '.', $path );
        $current = $ebike;
        foreach ( $keys as $key ) {
            if ( ! is_array( $current ) || ! isset( $current[ $key ] ) ) {
                return array();
            }
            $current = $current[ $key ];
        }
        if ( is_array( $current ) ) {
            return array_map( 'strtolower', array_filter( $current ) );
        }
        if ( is_string( $current ) && ! empty( $current ) && strtolower( $current ) !== 'unknown' ) {
            return array( strtolower( $current ) );
        }
        return array();
    };

    // 1. Category match (CRITICAL - Fat Tire should match Fat Tire).
    // Full match = +50, partial overlap = +25, no match = 0.
    $source_cat    = $get_array( $source_ebike, 'category' );
    $candidate_cat = $get_array( $candidate_ebike, 'category' );
    if ( ! empty( $source_cat ) && ! empty( $candidate_cat ) ) {
        $overlap = array_intersect( $source_cat, $candidate_cat );
        if ( count( $overlap ) === count( $source_cat ) && count( $overlap ) === count( $candidate_cat ) ) {
            $bonus += 50; // Exact match.
        } elseif ( ! empty( $overlap ) ) {
            $bonus += 25; // Partial match.
        }
        // No match = no bonus (but not a penalty, they might still be similar).
    }

    // 2. Class match (HIGH - same legal/use implications).
    // Full match = +30, partial overlap = +15, no match = 0.
    $source_class    = $get_array( $source_ebike, 'speed_and_class.class' );
    $candidate_class = $get_array( $candidate_ebike, 'speed_and_class.class' );
    if ( ! empty( $source_class ) && ! empty( $candidate_class ) ) {
        $overlap = array_intersect( $source_class, $candidate_class );
        if ( ! empty( $overlap ) ) {
            if ( count( $overlap ) === count( $source_class ) && count( $overlap ) === count( $candidate_class ) ) {
                $bonus += 30; // Exact match.
            } else {
                $bonus += 15; // Partial match.
            }
        }
    }

    // 3. Motor type match (MEDIUM - hub vs mid-drive is big difference).
    // Match = +20, no match = 0.
    $source_motor_type    = $get_array( $source_ebike, 'motor.motor_type' );
    $candidate_motor_type = $get_array( $candidate_ebike, 'motor.motor_type' );
    if ( ! empty( $source_motor_type ) && ! empty( $candidate_motor_type ) ) {
        if ( $source_motor_type === $candidate_motor_type ) {
            $bonus += 20;
        }
    }

    // 4. Frame style match (MEDIUM - step-through vs diamond matters for accessibility).
    // Match = +10, no match = 0.
    $source_frame    = $get_array( $source_ebike, 'frame_and_geometry.frame_style' );
    $candidate_frame = $get_array( $candidate_ebike, 'frame_and_geometry.frame_style' );
    if ( ! empty( $source_frame ) && ! empty( $candidate_frame ) ) {
        $overlap = array_intersect( $source_frame, $candidate_frame );
        if ( ! empty( $overlap ) ) {
            $bonus += 10;
        }
    }

    return $bonus;
}

/**
 * Calculate similarity score between two products
 *
 * @param array $source_specs Source product specs.
 * @param array $candidate_specs Candidate product specs.
 * @param array $config Spec comparison config.
 * @return float Similarity score (higher = more similar).
 */
function erh_calculate_similarity_score( array $source_specs, array $candidate_specs, array $config ): float {
    $total_score  = 0;
    $total_weight = 0;

    // Helper to get first available value from array of paths.
    $get_first_value = function ( array $specs, array $paths ) {
        foreach ( $paths as $path ) {
            $value = erh_get_nested_value( $specs, $path );
            if ( is_numeric( $value ) ) {
                return (float) $value;
            }
        }
        return null;
    };

    foreach ( $config as $spec_name => $params ) {
        $paths = $params['paths'] ?? array( $spec_name );

        $source_val    = $get_first_value( $source_specs, $paths );
        $candidate_val = $get_first_value( $candidate_specs, $paths );

        // Skip if either value is missing.
        if ( $source_val === null || $candidate_val === null ) {
            continue;
        }

        $range  = $params['range'];
        $weight = $params['weight'];

        // Calculate how close the values are (100 = identical, 0 = at range limit or beyond).
        $diff       = abs( $source_val - $candidate_val );
        $spec_score = max( 0, 100 - ( $diff / $range ) * 100 );

        $total_score  += $spec_score * $weight;
        $total_weight += $weight;
    }

    // Return weighted average (0-100 scale).
    return $total_weight > 0 ? $total_score / $total_weight : 0;
}

/**
 * Format basic specs line for product cards
 *
 * Returns a comma-separated string of key specs matching the finder tool defaults:
 * speed, battery, motor, weight, max load, voltage, tires.
 *
 * @param array $specs Product specs array from wp_product_data.
 * @return string Formatted specs line.
 */
function erh_format_card_specs( array $specs ): string {
    // Detect product type from specs structure.
    if ( isset( $specs['e-bikes'] ) && is_array( $specs['e-bikes'] ) ) {
        return erh_format_ebike_card_specs( $specs );
    }
    if ( isset( $specs['hoverboards'] ) && is_array( $specs['hoverboards'] ) ) {
        return erh_format_hoverboard_card_specs( $specs );
    }
    if ( isset( $specs['eucs'] ) && is_array( $specs['eucs'] ) ) {
        return erh_format_euc_card_specs( $specs );
    }

    // Default: e-scooter format.
    return erh_format_escooter_card_specs( $specs );
}

/**
 * Format e-scooter specs for card display.
 *
 * @param array $specs Product specs.
 * @return string Formatted specs line.
 */
function erh_format_escooter_card_specs( array $specs ): string {
    $parts = array();

    // Helper to get value from flat key or nested e-scooters path.
    $get_value = function ( string ...$paths ) use ( $specs ) {
        foreach ( $paths as $path ) {
            if ( isset( $specs[ $path ] ) && $specs[ $path ] !== '' && $specs[ $path ] !== null ) {
                return $specs[ $path ];
            }
            if ( strpos( $path, '.' ) !== false ) {
                $value = erh_get_nested_value( $specs, $path );
                if ( $value !== null && $value !== '' ) {
                    return $value;
                }
            }
        }
        return null;
    };

    // Top speed.
    $speed = $get_value( 'manufacturer_top_speed' );
    if ( $speed && is_numeric( $speed ) ) {
        $parts[] = round( (float) $speed ) . ' mph';
    }

    // Battery capacity.
    $battery = $get_value( 'battery_capacity', 'e-scooters.battery.capacity' );
    if ( $battery && is_numeric( $battery ) ) {
        $parts[] = round( (float) $battery ) . ' Wh';
    }

    // Motor power (peak preferred, then nominal).
    $motor = $get_value(
        'peak_motor_wattage',
        'e-scooters.motor.power_peak',
        'nominal_motor_wattage',
        'e-scooters.motor.power_nominal'
    );
    if ( $motor && is_numeric( $motor ) ) {
        $parts[] = round( (float) $motor ) . 'W';
    }

    // Weight.
    $weight = $get_value( 'weight', 'e-scooters.dimensions.weight' );
    if ( $weight && is_numeric( $weight ) ) {
        $parts[] = round( (float) $weight ) . ' lbs';
    }

    // Max load (weight limit).
    $max_load = $get_value( 'max_weight_capacity', 'e-scooters.dimensions.max_load' );
    if ( $max_load && is_numeric( $max_load ) ) {
        $parts[] = round( (float) $max_load ) . ' lbs limit';
    }

    // Voltage.
    $voltage = $get_value( 'voltage', 'e-scooters.battery.voltage' );
    if ( $voltage && is_numeric( $voltage ) ) {
        $parts[] = round( (float) $voltage ) . 'V';
    }

    // Tire type.
    $tires = $get_value( 'tire_type', 'e-scooters.wheels.tire_type' );
    if ( $tires && is_string( $tires ) && ! empty( $tires ) ) {
        $parts[] = $tires;
    }

    return implode( ', ', $parts );
}

/**
 * Format e-bike specs for card display.
 *
 * Order: Category, Motor (power + type), Torque, Battery, Weight, Frame, Wheels
 *
 * @param array $specs Product specs.
 * @return string Formatted specs line.
 */
function erh_format_ebike_card_specs( array $specs ): string {
    $parts = array();
    $ebike = $specs['e-bikes'] ?? array();

    // Helper to safely get nested value.
    $get = function ( string $path ) use ( $ebike ) {
        $keys    = explode( '.', $path );
        $current = $ebike;
        foreach ( $keys as $key ) {
            if ( ! is_array( $current ) || ! isset( $current[ $key ] ) ) {
                return null;
            }
            $current = $current[ $key ];
        }
        // Return null for empty/unknown values.
        if ( $current === '' || $current === null ) {
            return null;
        }
        if ( is_string( $current ) && strtolower( $current ) === 'unknown' ) {
            return null;
        }
        return $current;
    };

    // 1. Category (Fat Tire, Commuter, etc.).
    $category = $get( 'category' );
    if ( $category ) {
        $cat_str = is_array( $category ) ? implode( '/', $category ) : $category;
        if ( ! empty( $cat_str ) ) {
            $parts[] = $cat_str;
        }
    }

    // 2. Motor power + type (750W hub, 500W mid-drive).
    $motor_power = $get( 'motor.power_nominal' );
    $motor_type  = $get( 'motor.motor_type' );
    if ( $motor_power && is_numeric( $motor_power ) ) {
        $motor_str = round( (float) $motor_power ) . 'W';
        if ( $motor_type ) {
            $type_lower = strtolower( $motor_type );
            if ( $type_lower === 'hub' ) {
                $motor_str .= ' hub';
            } elseif ( $type_lower === 'mid-drive' ) {
                $motor_str .= ' mid-drive';
            }
        }
        $parts[] = $motor_str;
    }

    // 3. Torque (90Nm).
    $torque = $get( 'motor.torque' );
    if ( $torque && is_numeric( $torque ) ) {
        $parts[] = round( (float) $torque ) . 'Nm';
    }

    // 4. Battery (960Wh).
    $battery = $get( 'battery.battery_capacity' );
    if ( $battery && is_numeric( $battery ) ) {
        $parts[] = round( (float) $battery ) . 'Wh';
    }

    // 5. Weight (79 lbs).
    $weight = $get( 'weight_and_capacity.weight' );
    if ( $weight && is_numeric( $weight ) ) {
        $parts[] = round( (float) $weight ) . ' lbs';
    }

    // 6. Frame (aluminum step-through).
    $frame_material = $get( 'frame_and_geometry.frame_material' );
    $frame_style    = $get( 'frame_and_geometry.frame_style' );
    $frame_parts    = array();
    if ( $frame_material ) {
        $mat = is_array( $frame_material ) ? implode( '/', $frame_material ) : $frame_material;
        $frame_parts[] = strtolower( $mat );
    }
    if ( $frame_style ) {
        $style = is_array( $frame_style ) ? implode( '/', $frame_style ) : $frame_style;
        $frame_parts[] = strtolower( $style );
    }
    if ( ! empty( $frame_parts ) ) {
        $parts[] = implode( ' ', $frame_parts );
    }

    // 7. Wheels/Tires (26"×4" fat).
    $wheel_front = $get( 'wheels_and_tires.wheel_size' );
    $wheel_rear  = $get( 'wheels_and_tires.wheel_size_rear' );
    $tire_width  = $get( 'wheels_and_tires.tire_width' );
    $tire_type   = $get( 'wheels_and_tires.tire_type' );
    if ( $wheel_front ) {
        // Handle front/rear size difference.
        if ( $wheel_rear && $wheel_rear != $wheel_front ) {
            $wheel_str = $wheel_front . '"/' . $wheel_rear . '"';
        } else {
            $wheel_str = $wheel_front . '"';
        }
        // Add width if available.
        if ( $tire_width ) {
            $wheel_str .= '×' . $tire_width . '"';
        }
        // Add type if available.
        if ( $tire_type ) {
            $wheel_str .= ' ' . strtolower( $tire_type );
        }
        $parts[] = $wheel_str;
    }

    return implode( ', ', $parts );
}

/**
 * Format hoverboard specs for card display.
 *
 * Order: Speed, Battery (Wh), Motor (W), Weight, Max Load
 * Matches the finder tool display format.
 *
 * @param array $specs Product specs.
 * @return string Formatted specs line.
 */
function erh_format_hoverboard_card_specs( array $specs ): string {
    $parts      = array();
    $hoverboard = $specs['hoverboards'] ?? array();

    // Helper to safely get nested value.
    $get = function ( string $path ) use ( $hoverboard, $specs ) {
        // Try nested hoverboards path first.
        $keys    = explode( '.', $path );
        $current = $hoverboard;
        foreach ( $keys as $key ) {
            if ( ! is_array( $current ) || ! isset( $current[ $key ] ) ) {
                $current = null;
                break;
            }
            $current = $current[ $key ];
        }
        if ( $current !== null && $current !== '' ) {
            return $current;
        }
        // Fallback to flat spec key.
        if ( isset( $specs[ $path ] ) && $specs[ $path ] !== '' && $specs[ $path ] !== null ) {
            return $specs[ $path ];
        }
        return null;
    };

    // 1. Top speed.
    $speed = $get( 'manufacturer_top_speed' ) ?? ( $specs['manufacturer_top_speed'] ?? null );
    if ( $speed && is_numeric( $speed ) ) {
        $parts[] = round( (float) $speed ) . ' MPH';
    }

    // 2. Battery capacity.
    $battery = $get( 'battery.capacity' );
    if ( $battery && is_numeric( $battery ) ) {
        $parts[] = round( (float) $battery ) . ' Wh battery';
    }

    // 3. Motor power.
    $motor = $get( 'motor.power_nominal' );
    if ( $motor && is_numeric( $motor ) ) {
        $parts[] = round( (float) $motor ) . 'W motor';
    }

    // 4. Weight.
    $weight = $get( 'dimensions.weight' );
    if ( $weight && is_numeric( $weight ) ) {
        $parts[] = round( (float) $weight ) . ' lbs';
    }

    // 5. Max load.
    $max_load = $get( 'dimensions.max_load' );
    if ( $max_load && is_numeric( $max_load ) ) {
        $parts[] = round( (float) $max_load ) . ' lbs max load';
    }

    return implode( ', ', $parts );
}

/**
 * Format EUC specs for card display.
 *
 * Order: Speed, Battery (Wh), Motor (W), Weight, Max Load, Wheel Size
 * Matches the finder tool display format.
 *
 * @param array $specs Product specs.
 * @return string Formatted specs line.
 */
function erh_format_euc_card_specs( array $specs ): string {
    $parts = array();
    $euc   = $specs['eucs'] ?? array();

    // Helper to safely get nested value.
    $get = function ( string $path ) use ( $euc, $specs ) {
        // Try nested eucs path first.
        $keys    = explode( '.', $path );
        $current = $euc;
        foreach ( $keys as $key ) {
            if ( ! is_array( $current ) || ! isset( $current[ $key ] ) ) {
                $current = null;
                break;
            }
            $current = $current[ $key ];
        }
        if ( $current !== null && $current !== '' ) {
            return $current;
        }
        // Fallback to flat spec key.
        if ( isset( $specs[ $path ] ) && $specs[ $path ] !== '' && $specs[ $path ] !== null ) {
            return $specs[ $path ];
        }
        return null;
    };

    // 1. Top speed.
    $speed = $get( 'manufacturer_top_speed' ) ?? ( $specs['manufacturer_top_speed'] ?? null );
    if ( $speed && is_numeric( $speed ) ) {
        $parts[] = round( (float) $speed ) . ' mph';
    }

    // 2. Battery capacity.
    $battery = $get( 'battery.capacity' );
    if ( $battery && is_numeric( $battery ) ) {
        $parts[] = round( (float) $battery ) . ' Wh';
    }

    // 3. Motor power (peak preferred, then nominal).
    $motor = $get( 'motor.power_peak' ) ?: $get( 'motor.power_nominal' );
    if ( $motor && is_numeric( $motor ) ) {
        $parts[] = round( (float) $motor ) . 'W';
    }

    // 4. Weight.
    $weight = $get( 'dimensions.weight' );
    if ( $weight && is_numeric( $weight ) ) {
        $parts[] = round( (float) $weight ) . ' lbs';
    }

    // 5. Max load.
    $max_load = $get( 'dimensions.max_load' );
    if ( $max_load && is_numeric( $max_load ) ) {
        $parts[] = round( (float) $max_load ) . ' lbs max load';
    }

    // 6. Wheel size.
    $wheel_size = $get( 'wheel.tire_size' );
    if ( $wheel_size && is_numeric( $wheel_size ) ) {
        $parts[] = $wheel_size . '" wheel';
    }

    return implode( ', ', $parts );
}

/**
 * Get product type short name for display in badges/cards
 *
 * @param string $type Full product type label
 * @return string Short display name
 */
function erh_get_product_type_short_name( string $type ): string {
    $short_names = array(
        'Electric Scooter'    => 'E-Scooter',
        'Electric Bike'       => 'E-Bike',
        'Electric Skateboard' => 'E-Skateboard',
        'Electric Unicycle'   => 'EUC',
        'Hoverboard'          => 'Hoverboard',
    );

    return $short_names[ $type ] ?? $type;
}

/**
 * Get category short name for display in badges/cards (plural form)
 *
 * Maps WordPress category names to short display names.
 *
 * @param string $category Category name
 * @return string Short display name
 */
function erh_get_category_short_name( string $category ): string {
    $short_names = array(
        // Plural forms (category names)
        'Electric Scooters'    => 'E-Scooters',
        'Electric Bikes'       => 'E-Bikes',
        'Electric Skateboards' => 'E-Skateboards',
        'Electric Unicycles'   => 'EUCs',
        'Hoverboards'          => 'Hoverboards',
        // Singular forms (in case they're used)
        'Electric Scooter'     => 'E-Scooters',
        'Electric Bike'        => 'E-Bikes',
        'Electric Skateboard'  => 'E-Skateboards',
        'Electric Unicycle'    => 'EUCs',
        'Hoverboard'           => 'Hoverboards',
        // Common variations
        'E-Scooter'            => 'E-Scooters',
        'E-Bike'               => 'E-Bikes',
        'EUC'                  => 'EUCs',
    );

    return $short_names[ $category ] ?? $category;
}

/**
 * Check if product has performance test data
 *
 * @param int $product_id Product ID.
 * @return bool True if product has tested performance data.
 */
function erh_product_has_performance_data( int $product_id ): bool {
    // Check for key tested fields that indicate performance tests were done.
    $tested_fields = array(
        'tested_top_speed',
        'tested_range_regular',
        'tested_range_fast',
        'tested_range_slow',
        'hill_climbing',
        'brake_distance',
    );

    foreach ( $tested_fields as $field ) {
        $value = get_field( $field, $product_id );
        if ( ! empty( $value ) && is_numeric( $value ) && $value > 0 ) {
            return true;
        }
    }

    return false;
}

/**
 * Get category key from product type
 *
 * Returns the lowercase key used in JS configs (compare-config.js).
 *
 * @param string $product_type Product type label (e.g., 'Electric Scooter').
 * @return string Category key (e.g., 'escooter').
 */
function erh_get_category_key( string $product_type ): string {
    return CategoryConfig::type_to_key( $product_type ) ?: 'escooter';
}

/**
 * Extract brand name from product title
 *
 * Assumes brand is the first word of the product name.
 * Handles compound brand names like "Segway Ninebot".
 *
 * @param string $title Product title.
 * @return string Brand name.
 */
function erh_extract_brand_from_title( string $title ): string {
    // Known compound brand names to check first
    $compound_brands = array(
        'Segway Ninebot',
        'Zero Motorcycles',
        'Xiaomi Mi',
        'Hiboy S',
        'Gotrax G',
        'Inokim Light',
        'Inokim Quick',
        'Inokim Ox',
        'Apollo City',
        'Apollo Ghost',
        'Apollo Phantom',
        'Niu KQi',
        'Mantis King',
        'Wolf King',
        'Wolf Warrior',
    );

    // Check for compound brands first
    foreach ( $compound_brands as $brand ) {
        if ( stripos( $title, $brand ) === 0 ) {
            return $brand;
        }
    }

    // Fall back to first word
    $parts = explode( ' ', trim( $title ) );
    return $parts[0] ?? '';
}

/**
 * Get product data from the wp_product_data cache table.
 *
 * @param int $product_id Product ID.
 * @return array|null Product data or null if not found.
 */
function erh_get_product_cache_data( int $product_id ): ?array {
    global $wpdb;

    $table = $wpdb->prefix . 'product_data';
    $row   = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE product_id = %d",
            $product_id
        ),
        ARRAY_A
    );

    if ( ! $row ) {
        return null;
    }

    // Unserialize specs and price_history
    if ( isset( $row['specs'] ) ) {
        $row['specs'] = maybe_unserialize( $row['specs'] );
    }
    if ( isset( $row['price_history'] ) ) {
        $row['price_history'] = maybe_unserialize( $row['price_history'] );
    }

    return $row;
}

/**
 * Get inline product data for JS hydration
 *
 * Returns minimal product data for use in inline scripts on product pages.
 *
 * @param int $product_id Product ID.
 * @return array|null Product data array or null.
 */
function erh_get_inline_product_data( int $product_id ): ?array {
    $data = erh_get_product_cache_data( $product_id );

    if ( ! $data ) {
        return null;
    }

    return array(
        'id'           => $data['product_id'],
        'name'         => $data['name'],
        'slug'         => get_post_field( 'post_name', $product_id ),
        'url'          => $data['permalink'],
        'thumbnail'    => $data['image_url'],
        'product_type' => $data['product_type'],
        'rating'       => $data['rating'],
        'specs'        => $data['specs'],
        'pricing'      => $data['price_history'],
    );
}

/**
 * Get product info from category term
 *
 * Extracts product information from a WP_Term object.
 *
 * @param WP_Term $category The category term.
 * @return array|null Product info or null.
 */
function erh_get_product_info_from_category( WP_Term $category ): ?array {
    // Get first product in category
    $products = get_posts( array(
        'post_type'      => 'products',
        'posts_per_page' => 1,
        'tax_query'      => array(
            array(
                'taxonomy' => $category->taxonomy,
                'terms'    => $category->term_id,
            ),
        ),
    ) );

    if ( empty( $products ) ) {
        return null;
    }

    $product = $products[0];
    $product_type = erh_get_product_type( $product->ID );

    return array(
        'id'           => $product->ID,
        'name'         => $product->post_title,
        'product_type' => $product_type,
        'category_key' => erh_get_category_key( $product_type ),
    );
}

/**
 * Get product info for current or specified post
 *
 * @param int|null $post_id Post ID (optional, uses current post).
 * @return array|null Product info or null.
 */
function erh_get_product_info_for_post( ?int $post_id = null ): ?array {
    $post_id = $post_id ?? get_the_ID();

    if ( get_post_type( $post_id ) !== 'products' ) {
        return null;
    }

    $product_type = erh_get_product_type( $post_id );

    return array(
        'id'           => $post_id,
        'name'         => get_the_title( $post_id ),
        'product_type' => $product_type,
        'category_key' => erh_get_category_key( $product_type ),
    );
}

/**
 * Get key specs for product hero display
 *
 * Returns an array of formatted spec strings for display in the hero section.
 *
 * @param int    $product_id   Product ID.
 * @param string $product_type Product type label (e.g., 'Electric Scooter').
 * @return array Array of formatted spec strings.
 */
function erh_get_hero_key_specs( int $product_id, string $product_type ): array {
    $result = array();

    // Get category key and nested wrapper
    $category_key   = erh_get_category_key( $product_type );
    $nested_wrapper = erh_get_specs_wrapper_key( $category_key );

    // Get product data from cache (more reliable than direct ACF calls)
    $product_data = erh_get_product_cache_data( $product_id );
    $specs        = array();

    if ( $product_data && ! empty( $product_data['specs'] ) ) {
        $specs = is_array( $product_data['specs'] )
            ? $product_data['specs']
            : maybe_unserialize( $product_data['specs'] );
    }

    // If no cache data, fall back to ACF for nested groups
    if ( empty( $specs ) || ! is_array( $specs ) ) {
        $specs = array();
        // Try to get the nested group directly
        $nested_data = get_field( $nested_wrapper, $product_id );
        if ( is_array( $nested_data ) ) {
            $specs[ $nested_wrapper ] = $nested_data;
        }
        // Also add root-level performance fields
        $specs['manufacturer_top_speed'] = get_field( 'manufacturer_top_speed', $product_id );
        $specs['manufacturer_range']     = get_field( 'manufacturer_range', $product_id );
    }

    // Helper to get spec
    $get = function( $key ) use ( $specs, $nested_wrapper ) {
        return erh_get_spec_from_cache( $specs, $key, $nested_wrapper );
    };

    // Define key specs per product type
    switch ( $category_key ) {
        case 'escooter':
            // 1. Product type
            $result[] = 'Electric Scooter';

            // 2. Top speed
            $speed = $get( 'manufacturer_top_speed' );
            if ( $speed ) {
                $result[] = $speed . ' mph';
            }

            // 3. Range (in parenthesis style - will be formatted in output)
            $range = $get( 'manufacturer_range' );
            if ( $range ) {
                $result[] = '(' . $range . ' mi range)';
            }

            // 4. Motor - e.g., "500W rear motor (1000W peak)" or "dual 1000W motors (2000W peak)"
            $motor_nominal  = $get( 'motor.power_nominal' );
            $motor_peak     = $get( 'motor.power_peak' );
            $motor_position = $get( 'motor.motor_position' );
            if ( $motor_nominal ) {
                if ( strtolower( $motor_position ) === 'dual' ) {
                    $motor_str = 'dual ' . $motor_nominal . 'W motors';
                } else {
                    $position  = $motor_position ? strtolower( $motor_position ) . ' ' : '';
                    $motor_str = $motor_nominal . 'W ' . $position . 'motor';
                }
                if ( $motor_peak && $motor_peak > $motor_nominal ) {
                    $motor_str .= ' (' . $motor_peak . 'W peak)';
                }
                $result[] = $motor_str;
            }

            // 5. Battery - e.g., "551Wh 48V battery"
            $battery_wh = $get( 'battery.capacity' );
            $battery_v  = $get( 'battery.voltage' );
            if ( $battery_wh ) {
                $battery_str = $battery_wh . 'Wh';
                if ( $battery_v ) {
                    $battery_str .= ' ' . $battery_v . 'V';
                }
                $result[] = $battery_str . ' battery';
            }

            // 6. Weight
            $weight = $get( 'dimensions.weight' );
            if ( $weight ) {
                $result[] = $weight . ' lbs';
            }

            // 7. Max load
            $max_load = $get( 'dimensions.max_load' );
            if ( $max_load ) {
                $result[] = $max_load . ' lbs max load';
            }

            // 8. Tires - e.g., '10" pneumatic tires', '8.5"/8" solid tires'
            $tire_front = $get( 'wheels.tire_size_front' );
            $tire_rear  = $get( 'wheels.tire_size_rear' );
            $tire_type  = $get( 'wheels.tire_type' );
            $tubeless   = $get( 'wheels.tubeless' );
            if ( $tire_front ) {
                // Build size string
                if ( $tire_rear && $tire_rear != $tire_front ) {
                    $size_str = $tire_front . '"/' . $tire_rear . '"';
                } else {
                    $size_str = $tire_front . '"';
                }
                // Build type string
                $type_str = '';
                if ( $tire_type ) {
                    $type_lower = strtolower( $tire_type );
                    if ( $type_lower === 'pneumatic' ) {
                        $type_str = $tubeless ? 'tubeless' : 'pneumatic';
                    } elseif ( $type_lower === 'solid' ) {
                        $type_str = 'solid';
                    } elseif ( $type_lower === 'mixed' ) {
                        $type_str = 'mixed';
                    }
                }
                $result[] = $size_str . ( $type_str ? ' ' . $type_str : '' ) . ' tires';
            }

            // 9. Suspension
            $suspension_type = $get( 'suspension.type' );
            if ( $suspension_type && is_array( $suspension_type ) && ! empty( $suspension_type ) ) {
                // Check if front and rear
                $has_front = false;
                $has_rear  = false;
                foreach ( $suspension_type as $susp ) {
                    if ( stripos( $susp, 'front' ) !== false ) {
                        $has_front = true;
                    }
                    if ( stripos( $susp, 'rear' ) !== false ) {
                        $has_rear = true;
                    }
                }
                if ( $has_front && $has_rear ) {
                    $result[] = 'dual suspension';
                } elseif ( $has_front ) {
                    $result[] = 'front suspension';
                } elseif ( $has_rear ) {
                    $result[] = 'rear suspension';
                }
            }

            // 10. Brakes - e.g., "dual disc brakes" or "front drum, rear disc"
            $brake_front = $get( 'brakes.front' );
            $brake_rear  = $get( 'brakes.rear' );
            if ( $brake_front && $brake_rear ) {
                $front_lower = strtolower( $brake_front );
                $rear_lower  = strtolower( $brake_rear );
                if ( $front_lower === $rear_lower && $front_lower !== 'none' ) {
                    $result[] = 'dual ' . $front_lower . ' brakes';
                } elseif ( $front_lower !== 'none' && $rear_lower !== 'none' ) {
                    $result[] = 'front ' . $front_lower . ', rear ' . $rear_lower;
                } elseif ( $front_lower !== 'none' ) {
                    $result[] = 'front ' . $front_lower . ' brake';
                } elseif ( $rear_lower !== 'none' ) {
                    $result[] = 'rear ' . $rear_lower . ' brake';
                }
            }

            // 11. IP rating (if not none/unknown)
            $ip_rating = $get( 'build.ip_rating' );
            if ( $ip_rating && ! in_array( strtolower( $ip_rating ), array( 'none', 'unknown', '' ), true ) ) {
                $result[] = $ip_rating;
            }
            break;

        case 'ebike':
            // 1. Class - "Class 2/3 E-Bike" or just "E-Bike" if unknown
            $class = $get( 'speed_and_class.class' );
            if ( $class && is_array( $class ) ) {
                $class_filtered = array_filter( $class, function( $c ) {
                    return ! empty( $c ) && strtolower( $c ) !== 'unknown';
                } );
                if ( ! empty( $class_filtered ) ) {
                    // Extract class numbers/names: "Class 1", "Class 2" -> "1/2"
                    $class_nums = array_map( function( $c ) {
                        return str_ireplace( 'class ', '', trim( $c ) );
                    }, $class_filtered );
                    $result[] = 'Class ' . implode( '/', $class_nums ) . ' E-Bike';
                } else {
                    $result[] = 'E-Bike';
                }
            } else {
                $result[] = 'E-Bike';
            }

            // 2. Weight - "79 lbs"
            $weight = $get( 'weight_and_capacity.weight' );
            if ( $weight && is_numeric( $weight ) ) {
                $result[] = $weight . ' lbs';
            }

            // 3. Motor - "750W 90Nm rear hub" (power + torque + position + type)
            $motor_power    = $get( 'motor.power_nominal' );
            $motor_torque   = $get( 'motor.torque' );
            $motor_position = $get( 'motor.motor_position' );
            $motor_type     = $get( 'motor.motor_type' );
            if ( $motor_power ) {
                $motor_str = $motor_power . 'W';
                if ( $motor_torque ) {
                    $motor_str .= ' ' . $motor_torque . 'Nm';
                }
                // Position: "Rear Hub" -> "rear", "Mid" -> "mid", "Front Hub" -> "front"
                if ( $motor_position && strtolower( $motor_position ) !== 'unknown' ) {
                    $pos_lower = strtolower( $motor_position );
                    $pos_short = str_replace( ' hub', '', $pos_lower );
                    $motor_str .= ' ' . $pos_short;
                }
                // Type: "Hub" or "Mid-Drive"
                if ( $motor_type && strtolower( $motor_type ) !== 'unknown' ) {
                    $type_lower = strtolower( $motor_type );
                    if ( $type_lower === 'hub' ) {
                        $motor_str .= ' hub';
                    } elseif ( $type_lower === 'mid-drive' ) {
                        $motor_str .= ' mid-drive';
                    }
                }
                $result[] = $motor_str;
            }

            // 4. Battery - "960Wh (48V × 20Ah) battery"
            $battery_wh = $get( 'battery.battery_capacity' );
            $battery_v  = $get( 'battery.voltage' );
            $battery_ah = $get( 'battery.amphours' );
            if ( $battery_wh ) {
                $battery_str = $battery_wh . 'Wh';
                if ( $battery_v && $battery_ah ) {
                    $battery_str .= ' (' . $battery_v . 'V × ' . $battery_ah . 'Ah)';
                }
                $battery_str .= ' battery';
                $result[] = $battery_str;
            }

            // 5. Brakes - "Gemma GA-1000 hydraulic disc" (brand + model + type)
            $brake_type  = $get( 'brakes.brake_type' );
            $brake_brand = $get( 'brakes.brake_brand' );
            $brake_model = $get( 'brakes.brake_model' );
            if ( $brake_type ) {
                $type_str = is_array( $brake_type ) ? implode( '/', $brake_type ) : $brake_type;
                $type_str = strtolower( $type_str );
                // Skip if unknown/none
                if ( $type_str && ! in_array( $type_str, array( 'unknown', 'none', '' ), true ) ) {
                    $brake_str = '';
                    if ( $brake_brand && strtolower( $brake_brand ) !== 'unknown' ) {
                        $brake_str .= $brake_brand;
                        if ( $brake_model && strtolower( $brake_model ) !== 'unknown' ) {
                            $brake_str .= ' ' . $brake_model;
                        }
                        $brake_str .= ' ';
                    }
                    $brake_str .= $type_str;
                    $result[] = trim( $brake_str );
                }
            }

            // 6. Frame - "aluminum step-through" (material + style)
            $frame_material = $get( 'frame_and_geometry.frame_material' );
            $frame_style    = $get( 'frame_and_geometry.frame_style' );
            $frame_parts    = array();
            if ( $frame_material ) {
                $mat = is_array( $frame_material ) ? implode( '/', $frame_material ) : $frame_material;
                if ( strtolower( $mat ) !== 'unknown' ) {
                    $frame_parts[] = strtolower( $mat );
                }
            }
            if ( $frame_style ) {
                $style = is_array( $frame_style ) ? implode( '/', $frame_style ) : $frame_style;
                if ( strtolower( $style ) !== 'unknown' ) {
                    $frame_parts[] = strtolower( $style );
                }
            }
            if ( ! empty( $frame_parts ) ) {
                $result[] = implode( ' ', $frame_parts ) . ' frame';
            }

            // 7. Fork/Front Suspension - "86mm air fork"
            $front_susp   = $get( 'suspension.front_suspension' );
            $front_travel = $get( 'suspension.front_travel' );
            if ( $front_susp && strtolower( $front_susp ) !== 'none' && strtolower( $front_susp ) !== 'unknown' ) {
                $fork_str = '';
                if ( $front_travel ) {
                    $fork_str .= $front_travel . 'mm ';
                }
                $fork_str .= strtolower( $front_susp ) . ' fork';
                $result[] = $fork_str;
            }

            // 8. Wheels/Tires - "26"×4" Innova fat tires"
            $wheel_front = $get( 'wheels_and_tires.wheel_size' );
            $wheel_rear  = $get( 'wheels_and_tires.wheel_size_rear' );
            $tire_width  = $get( 'wheels_and_tires.tire_width' );
            $tire_type   = $get( 'wheels_and_tires.tire_type' );
            $tire_brand  = $get( 'wheels_and_tires.tire_brand' );
            $tire_model  = $get( 'wheels_and_tires.tire_model' );
            if ( $wheel_front ) {
                // Size: handle front/rear difference
                if ( $wheel_rear && $wheel_rear != $wheel_front ) {
                    $size_str = $wheel_front . '"/' . $wheel_rear . '"';
                } else {
                    $size_str = $wheel_front . '"';
                }
                // Add width if available
                if ( $tire_width ) {
                    $size_str .= '×' . $tire_width . '"';
                }
                // Add brand/model if available
                if ( $tire_brand && strtolower( $tire_brand ) !== 'unknown' ) {
                    $size_str .= ' ' . $tire_brand;
                    if ( $tire_model && strtolower( $tire_model ) !== 'unknown' ) {
                        $size_str .= ' ' . $tire_model;
                    }
                }
                // Add type if available and not unknown
                if ( $tire_type && strtolower( $tire_type ) !== 'unknown' ) {
                    $size_str .= ' ' . strtolower( $tire_type );
                }
                $size_str .= ' tires';
                $result[] = $size_str;
            }

            // 9. Rear Shock - "100mm coil shock" (only if rear suspension exists)
            $rear_susp   = $get( 'suspension.rear_suspension' );
            $rear_travel = $get( 'suspension.rear_travel' );
            if ( $rear_susp && strtolower( $rear_susp ) !== 'none' && strtolower( $rear_susp ) !== 'unknown' ) {
                $shock_str = '';
                if ( $rear_travel ) {
                    $shock_str .= $rear_travel . 'mm ';
                }
                $shock_str .= strtolower( $rear_susp ) . ' shock';
                $result[] = $shock_str;
            }

            // 10. Seatpost Suspension - "suspension seatpost" (only if true)
            $seatpost_susp = $get( 'suspension.seatpost_suspension' );
            if ( $seatpost_susp && $seatpost_susp !== '0' && $seatpost_susp !== false ) {
                $result[] = 'suspension seatpost';
            }
            break;

        case 'euc':
            // 1. Product type.
            $result[] = 'EUC';

            // 2. Top speed.
            $speed = $get( 'manufacturer_top_speed' );
            if ( $speed ) {
                $result[] = $speed . ' mph';
            }

            // 3. Range.
            $range = $get( 'manufacturer_range' );
            if ( $range ) {
                $result[] = '(' . $range . ' mi range)';
            }

            // 4. Motor - "3500W motor (7000W peak)".
            $motor_nominal = $get( 'motor.power_nominal' );
            $motor_peak    = $get( 'motor.power_peak' );
            if ( $motor_nominal ) {
                $motor_str = $motor_nominal . 'W motor';
                if ( $motor_peak && $motor_peak > $motor_nominal ) {
                    $motor_str .= ' (' . $motor_peak . 'W peak)';
                }
                $result[] = $motor_str;
            }

            // 5. Torque.
            $torque = $get( 'motor.torque' );
            if ( $torque ) {
                $result[] = $torque . 'Nm torque';
            }

            // 6. Battery - "2400Wh 100.8V battery".
            $battery_wh = $get( 'battery.capacity' );
            $battery_v  = $get( 'battery.voltage' );
            if ( $battery_wh ) {
                $battery_str = $battery_wh . 'Wh';
                if ( $battery_v ) {
                    $battery_str .= ' ' . $battery_v . 'V';
                }
                $result[] = $battery_str . ' battery';
            }

            // 7. Weight.
            $weight = $get( 'dimensions.weight' );
            if ( $weight ) {
                $result[] = $weight . ' lbs';
            }

            // 8. Max load.
            $max_load = $get( 'dimensions.max_load' );
            if ( $max_load ) {
                $result[] = $max_load . ' lbs max load';
            }

            // 9. Wheel size + tire type.
            $wheel_size = $get( 'wheel.tire_size' );
            $tire_type  = $get( 'wheel.tire_type' );
            if ( $wheel_size ) {
                $wheel_str = $wheel_size . '"';
                if ( $tire_type && strtolower( $tire_type ) !== 'unknown' ) {
                    $wheel_str .= ' ' . strtolower( $tire_type );
                }
                $wheel_str .= ' wheel';
                $result[] = $wheel_str;
            }

            // 10. Suspension.
            $suspension_type = $get( 'suspension.suspension_type' );
            if ( $suspension_type && strtolower( $suspension_type ) !== 'none' && strtolower( $suspension_type ) !== 'unknown' ) {
                $result[] = strtolower( $suspension_type ) . ' suspension';
            }

            // 11. IP rating.
            $ip_rating = $get( 'safety.ip_rating' );
            if ( $ip_rating && ! in_array( strtolower( $ip_rating ), array( 'none', 'unknown', '' ), true ) ) {
                $result[] = $ip_rating;
            }
            break;

        case 'hoverboard':
            // 1. Product type.
            $result[] = 'Hoverboard';

            // 2. Top speed.
            $speed = $get( 'manufacturer_top_speed' );
            if ( $speed ) {
                $result[] = $speed . ' mph';
            }

            // 3. Range.
            $range = $get( 'manufacturer_range' );
            if ( $range ) {
                $result[] = '(' . $range . ' mi range)';
            }

            // 4. Motor power.
            $motor = $get( 'motor.power_nominal' );
            if ( $motor ) {
                $motor_str = $motor . 'W motor';
                $peak      = $get( 'motor.power_peak' );
                if ( $peak && $peak > $motor ) {
                    $motor_str .= ' (' . $peak . 'W peak)';
                }
                $result[] = $motor_str;
            }

            // 5. Battery.
            $battery_wh = $get( 'battery.capacity' );
            $battery_v  = $get( 'battery.voltage' );
            if ( $battery_wh ) {
                $battery_str = $battery_wh . 'Wh';
                if ( $battery_v ) {
                    $battery_str .= ' ' . $battery_v . 'V';
                }
                $result[] = $battery_str . ' battery';
            }

            // 6. Weight.
            $weight = $get( 'dimensions.weight' );
            if ( $weight ) {
                $result[] = $weight . ' lbs';
            }

            // 7. Max load.
            $max_load = $get( 'dimensions.max_load' );
            if ( $max_load ) {
                $result[] = $max_load . ' lbs max load';
            }

            // 8. Wheel size + tire type.
            $wheel_size = $get( 'wheels.wheel_size' );
            $tire_type  = $get( 'wheels.tire_type' );
            if ( $wheel_size ) {
                $wheel_str = $wheel_size . '"';
                if ( $tire_type && strtolower( $tire_type ) !== 'unknown' ) {
                    $wheel_str .= ' ' . strtolower( $tire_type );
                }
                $wheel_str .= ' wheels';
                $result[] = $wheel_str;
            }

            // 9. UL 2272 certification.
            $ul_cert = $get( 'safety.ul_2272' );
            if ( $ul_cert && $ul_cert !== '0' && $ul_cert !== false ) {
                $result[] = 'UL 2272';
            }

            // 10. IP rating.
            $ip_rating = $get( 'safety.ip_rating' );
            if ( $ip_rating && ! in_array( strtolower( $ip_rating ), array( 'none', 'unknown', '' ), true ) ) {
                $result[] = $ip_rating;
            }
            break;

        default:
            // Generic fallback
            $result[] = $product_type ?: 'Electric Vehicle';

            $motor = $get( 'motor.power_nominal' );
            if ( $motor ) {
                $result[] = $motor . 'W motor';
            }

            $range = $get( 'manufacturer_range' );
            if ( $range ) {
                $result[] = '(' . $range . ' mi range)';
            }

            $weight = $get( 'dimensions.weight' );
            if ( $weight ) {
                $result[] = $weight . ' lbs';
            }
            break;
    }

    return $result;
}

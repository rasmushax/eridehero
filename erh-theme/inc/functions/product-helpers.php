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
    if ( 'Electric Bike' === $product_type ) {
        $config = array(
            'range' => array(
                'paths'  => array( 'e-bikes.battery.range_claimed' ),
                'range'  => 15,
                'weight' => 1.0,
            ),
            'motor' => array(
                'paths'  => array( 'e-bikes.motor.power_nominal' ),
                'range'  => 100,
                'weight' => 0.8,
            ),
            'battery' => array(
                'paths'  => array( 'e-bikes.battery.capacity' ),
                'range'  => 150,
                'weight' => 0.7,
            ),
        );
    }

    return $config;
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
    $parts = array();

    // Helper to get value from flat key or nested e-scooters path.
    // Checks flat key first, then nested path.
    $get_value = function ( string ...$paths ) use ( $specs ) {
        foreach ( $paths as $path ) {
            // Check flat key first.
            if ( isset( $specs[ $path ] ) && $specs[ $path ] !== '' && $specs[ $path ] !== null ) {
                return $specs[ $path ];
            }

            // Check nested path (e.g., 'e-scooters.battery.capacity').
            if ( strpos( $path, '.' ) !== false ) {
                $value = erh_get_nested_value( $specs, $path );
                if ( $value !== null && $value !== '' ) {
                    return $value;
                }
            }
        }
        return null;
    };

    // Top speed (flat only for e-scooters).
    $speed = $get_value( 'manufacturer_top_speed' );
    if ( $speed && is_numeric( $speed ) ) {
        $parts[] = round( (float) $speed ) . ' mph';
    }

    // Battery capacity - check flat AND nested.
    $battery = $get_value( 'battery_capacity', 'e-scooters.battery.capacity' );
    if ( $battery && is_numeric( $battery ) ) {
        $parts[] = round( (float) $battery ) . ' Wh';
    }

    // Motor power (peak preferred, then nominal) - check flat AND nested.
    $motor = $get_value(
        'peak_motor_wattage',
        'e-scooters.motor.power_peak',
        'nominal_motor_wattage',
        'e-scooters.motor.power_nominal'
    );
    if ( $motor && is_numeric( $motor ) ) {
        $parts[] = round( (float) $motor ) . 'W';
    }

    // Weight - check flat AND nested.
    $weight = $get_value( 'weight', 'e-scooters.dimensions.weight' );
    if ( $weight && is_numeric( $weight ) ) {
        $parts[] = round( (float) $weight ) . ' lbs';
    }

    // Max load (weight limit) - check flat AND nested.
    $max_load = $get_value( 'max_weight_capacity', 'e-scooters.dimensions.max_load' );
    if ( $max_load && is_numeric( $max_load ) ) {
        $parts[] = round( (float) $max_load ) . ' lbs limit';
    }

    // Voltage - check flat AND nested.
    $voltage = $get_value( 'voltage', 'e-scooters.battery.voltage' );
    if ( $voltage && is_numeric( $voltage ) ) {
        $parts[] = round( (float) $voltage ) . 'V';
    }

    // Tire type - check flat AND nested (consolidated by cache-rebuild-job).
    $tires = $get_value( 'tire_type', 'e-scooters.wheels.tire_type' );
    if ( $tires && is_string( $tires ) && ! empty( $tires ) ) {
        $parts[] = $tires;
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
            // Product type
            $result[] = 'Electric Bike';

            // Motor power
            $motor = $get( 'motor.power_nominal' );
            if ( $motor ) {
                $result[] = $motor . 'W motor';
            }

            // Range
            $range = $get( 'battery.range_claimed' );
            if ( $range ) {
                $result[] = '(' . $range . ' mi range)';
            }

            // Battery capacity
            $battery = $get( 'battery.capacity' );
            if ( $battery ) {
                $result[] = $battery . 'Wh battery';
            }

            // Weight
            $weight = $get( 'frame.weight' );
            if ( $weight ) {
                $result[] = $weight . ' lbs';
            }

            // Class
            $class = $get( 'motor.class' );
            if ( $class ) {
                $result[] = 'Class ' . $class;
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

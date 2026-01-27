<?php
/**
 * Compare Helper Functions
 *
 * Head-to-head comparison utilities: product loading, spec flattening,
 * winner detection, advantage calculation, and row rendering.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ERH\CategoryConfig;
use ERH\Config\SpecConfig;

/**
 * Get currency symbol for geo region.
 *
 * @param string $geo Geo region code.
 * @return string Currency symbol.
 */
function erh_get_currency_symbol( string $geo ): string {
    $symbols = array(
        'US' => '$',
        'GB' => '£',
        'EU' => '€',
        'CA' => 'C$',
        'AU' => 'A$',
    );
    return $symbols[ $geo ] ?? '$';
}

/**
 * Get multiple products from product_data cache for comparison.
 *
 * Business rule: No currency mixing!
 * - User in supported geo (US/GB/EU/CA/AU): Show price ONLY if that geo has pricing. No fallback.
 * - User outside supported geos: Fall back to US as reference.
 *
 * @param int[]  $product_ids Product IDs.
 * @param string $geo         User's geo region.
 * @return array[] Products with enriched pricing data.
 */
function erh_get_compare_products( array $product_ids, string $geo = 'US' ): array {
    $product_cache  = new ERH\Database\ProductCache();
    $products       = array();
    $supported_geos = array( 'US', 'GB', 'EU', 'CA', 'AU' );

    foreach ( $product_ids as $id ) {
        $data = $product_cache->get( (int) $id );
        if ( ! $data ) {
            continue;
        }

        // Get pricing - NO fallback to US for users in supported geos.
        $price_history   = $data['price_history'] ?? array();
        $geo_pricing     = $price_history[ $geo ] ?? array();
        $has_geo_price   = ! empty( $geo_pricing['current_price'] );
        $is_supported    = in_array( $geo, $supported_geos, true );

        // Determine which pricing to use.
        if ( $has_geo_price ) {
            // User's geo has pricing - use it.
            $region_pricing = $geo_pricing;
            $currency       = class_exists( 'ERH\\GeoConfig' ) ? \ERH\GeoConfig::get_currency( $geo ) : 'USD';
        } elseif ( ! $is_supported && ! empty( $price_history['US']['current_price'] ) ) {
            // User outside supported geos - fall back to US.
            $region_pricing = $price_history['US'];
            $currency       = 'USD';
        } else {
            // User in supported geo but no pricing for that geo - NO price.
            $region_pricing = array();
            $currency       = class_exists( 'ERH\\GeoConfig' ) ? \ERH\GeoConfig::get_currency( $geo ) : 'USD';
        }

        $current_price = $region_pricing['current_price'] ?? null;

        // Flatten specs (move e-scooters/e-bikes nested content to top level).
        $raw_specs = $data['specs'] ?? array();
        $specs     = erh_flatten_compare_specs( $raw_specs, $data['product_type'] ?? '' );

        // Extract score from specs.scores.overall (calculated by CacheRebuildJob).
        $score = $specs['scores']['overall'] ?? null;

        $products[] = array(
            'id'            => $data['product_id'],
            'name'          => $data['name'],
            'slug'          => get_post_field( 'post_name', $data['product_id'] ),
            'url'           => $data['permalink'],
            'thumbnail'     => $data['image_url'],
            'product_type'  => $data['product_type'] ?? 'Electric Scooter',
            'specs'         => $specs,
            'rating'        => $score,
            'current_price' => $current_price,
            'currency'      => $currency,
            'retailer'      => $current_price ? ( $region_pricing['retailer'] ?? null ) : null,
            'buy_link'      => $current_price ? ( $region_pricing['tracked_url'] ?? null ) : null,
            'in_stock'      => $region_pricing['instock'] ?? false,
            'avg_3m'        => $region_pricing['avg_3m'] ?? null,
            'avg_6m'        => $region_pricing['avg_6m'] ?? null,
        );
    }

    return $products;
}

/**
 * Check if any product has pricing data in any geo region.
 *
 * Used to determine if Value Analysis section should be shown at all.
 *
 * @param array $product_ids Product IDs to check.
 * @return bool True if at least one product has pricing in any geo.
 */
function erh_any_product_has_pricing( array $product_ids ): bool {
    $product_cache = new ERH\Database\ProductCache();
    $geos          = array( 'US', 'GB', 'EU', 'CA', 'AU' );

    foreach ( $product_ids as $id ) {
        $data = $product_cache->get( (int) $id );
        if ( ! $data ) {
            continue;
        }

        $price_history = $data['price_history'] ?? array();
        foreach ( $geos as $geo ) {
            if ( ! empty( $price_history[ $geo ]['current_price'] ) ) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Flatten nested product specs for comparison.
 *
 * Moves product-type-specific nested content (e.g., e-scooters.motor) to top level
 * so spec keys like 'motor.power_nominal' work directly.
 *
 * @param array  $specs        Raw specs from product_data table.
 * @param string $product_type Product type (e.g., 'Electric Scooter').
 * @return array Flattened specs.
 */
function erh_flatten_compare_specs( array $specs, string $product_type ): array {
    // Get ACF wrapper key from CategoryConfig.
    $category   = CategoryConfig::get_by_type( $product_type );
    $nested_key = $category ? $category['acf_wrapper'] : null;

    // Fields to exclude from output.
    $exclude = array( 'product_type', 'big_thumbnail', 'gallery', 'product_video', 'coupon', 'related_products', 'is_obsolete', 'variants' );
    $output  = array();

    // If nested group exists, move its contents to top level.
    if ( $nested_key && isset( $specs[ $nested_key ] ) && is_array( $specs[ $nested_key ] ) ) {
        // Copy nested group contents to output.
        $output = $specs[ $nested_key ];

        // Also include top-level fields (tested values, computed metrics, etc.).
        foreach ( $specs as $key => $value ) {
            if ( $key === $nested_key || in_array( $key, $exclude, true ) ) {
                continue;
            }
            $output[ $key ] = $value;
        }
    } else {
        // No nested structure - just filter exclusions.
        foreach ( $specs as $key => $value ) {
            if ( in_array( $key, $exclude, true ) ) {
                continue;
            }
            $output[ $key ] = $value;
        }
    }

    return $output;
}

/**
 * Get a spec value with optional normalization.
 *
 * Some specs (like suspension.type and wheels.tire_type) need normalization
 * before comparison because the raw values are arrays or need combining with
 * other fields.
 *
 * @param array  $specs    Full specs array for the product.
 * @param string $spec_key Spec key (dot notation).
 * @param array  $spec     Spec config from COMPARISON_SPECS.
 * @return mixed Normalized value or raw value if no normalizer.
 */
function erh_get_normalized_spec_value( array $specs, string $spec_key, array $spec ) {
    $raw_value = erh_get_nested_spec( $specs, $spec_key );

    // Check for normalizer.
    $normalizer = $spec['normalizer'] ?? null;
    if ( ! $normalizer || ! function_exists( $normalizer ) ) {
        return $raw_value;
    }

    // Some normalizers need full specs (e.g., tire type combines multiple fields).
    if ( ! empty( $spec['normalizerFullSpecs'] ) ) {
        return $normalizer( $specs );
    }

    // Standard normalizer receives raw value.
    return $normalizer( $raw_value );
}

/**
 * Calculate spec-based advantages for each product (Versus.com style).
 *
 * Delegates to product-specific advantage calculators via the factory.
 * Each product type (escooter, ebike, etc.) has its own calculator with
 * type-specific logic for head-to-head and multi-product comparisons.
 *
 * @param array[] $products Products from erh_get_compare_products().
 * @return array[] Array of advantages per product index.
 */
function erh_calculate_spec_advantages( array $products ): array {
    $count = count( $products );

    // For single product, no advantages.
    if ( $count < 2 ) {
        return array_fill( 0, $count, [] );
    }

    // Detect product type from first product.
    $product_type = $products[0]['product_type'] ?? 'escooter';

    // Use the AdvantageCalculatorFactory for all product types.
    // Each type has its own calculator with type-specific logic.
    return \ERH\Comparison\AdvantageCalculatorFactory::calculate( $products, $product_type );
}

/**
 * Format a number for spec display.
 *
 * @param float $value The value.
 * @return string Formatted number.
 */
function erh_format_spec_number( float $value ): string {
    // Round to 1 decimal if needed, otherwise whole number.
    if ( $value == floor( $value ) ) {
        return number_format( $value, 0 );
    }
    return number_format( $value, 1 );
}

/**
 * Get nested spec value using dot notation.
 *
 * @param array  $specs Specs array.
 * @param string $key   Dot-notation key (e.g., 'motor.power_nominal').
 * @return mixed|null Value or null if not found.
 */
function erh_get_nested_spec( array $specs, string $key ) {
    if ( empty( $key ) ) {
        return null;
    }

    // Direct key access if no dot.
    if ( strpos( $key, '.' ) === false ) {
        return $specs[ $key ] ?? null;
    }

    // Navigate nested path.
    $parts   = explode( '.', $key );
    $current = $specs;

    foreach ( $parts as $part ) {
        if ( ! is_array( $current ) || ! isset( $current[ $part ] ) ) {
            return null;
        }
        $current = $current[ $part ];
    }

    return $current;
}

/**
 * Check if all values in array are empty/null.
 *
 * @param array $values Values to check.
 * @return bool True if all empty.
 */
function erh_all_values_empty( array $values ): bool {
    foreach ( $values as $val ) {
        if ( $val !== null && $val !== '' && $val !== array() ) {
            return false;
        }
    }
    return true;
}

/**
 * Check if all values are the same (for diff toggle).
 *
 * @param array $values Values to check.
 * @return bool True if all same.
 */
function erh_values_are_same( array $values ): bool {
    $valid_values = array_filter( $values, function ( $v ) {
        return $v !== null && $v !== '';
    } );

    if ( count( $valid_values ) <= 1 ) {
        return true;
    }

    $first = null;
    foreach ( $valid_values as $val ) {
        $normalized = erh_normalize_spec_value( $val );
        if ( $first === null ) {
            $first = $normalized;
        } elseif ( $normalized !== $first ) {
            return false;
        }
    }

    return true;
}

/**
 * Normalize spec value for comparison.
 *
 * @param mixed $value Value to normalize.
 * @return string Normalized string.
 */
function erh_normalize_spec_value( $value ): string {
    if ( is_array( $value ) ) {
        sort( $value );
        return wp_json_encode( $value );
    }
    if ( is_bool( $value ) ) {
        return $value ? 'true' : 'false';
    }
    return strtolower( trim( (string) $value ) );
}

/**
 * Find winner indices for a spec row.
 *
 * @param array $values Spec values from each product.
 * @param array $spec   Spec definition with higherBetter.
 * @return int[] Indices of winning products (can be multiple for ties).
 */
function erh_find_spec_winners( array $values, array $spec ): array {
    // Skip specs explicitly marked as noWinner or without higherBetter.
    if ( ! empty( $spec['noWinner'] ) || ! isset( $spec['higherBetter'] ) ) {
        return array();
    }

    $format = $spec['format'] ?? '';

    // IP rating comparison - use normalized scores.
    if ( $format === 'ip' ) {
        $scores = array();
        foreach ( $values as $idx => $val ) {
            if ( $val !== null && $val !== '' ) {
                $scores[ $idx ] = erh_normalize_ip_rating( $val );
            }
        }

        if ( count( $scores ) < 2 ) {
            return array();
        }

        // Find highest score (higher is always better for IP).
        $best_score = max( $scores );
        if ( $best_score === 0 ) {
            return array(); // All have no/invalid ratings.
        }

        $winners = array();
        foreach ( $scores as $idx => $score ) {
            if ( $score === $best_score ) {
                $winners[] = $idx;
            }
        }

        // Only return winners if not all tied.
        if ( count( $winners ) === count( $scores ) ) {
            return array();
        }

        return $winners;
    }

    // Extract numeric values.
    $numeric_values = array();
    foreach ( $values as $idx => $val ) {
        if ( is_numeric( $val ) ) {
            $numeric_values[ $idx ] = (float) $val;
        }
    }

    if ( count( $numeric_values ) < 2 ) {
        return array();
    }

    // Find best value.
    $higher_better = $spec['higherBetter'];
    $best_value    = $higher_better ? max( $numeric_values ) : min( $numeric_values );

    // No tie threshold - a winner is a winner.
    $tie_threshold = 0;
    $winners       = array();

    foreach ( $numeric_values as $idx => $val ) {
        if ( $best_value == 0 ) {
            if ( $val == 0 ) {
                $winners[] = $idx;
            }
        } else {
            $diff = abs( $val - $best_value ) / abs( $best_value );
            if ( $diff <= $tie_threshold ) {
                $winners[] = $idx;
            }
        }
    }

    // Only return winners if not all tied.
    if ( count( $winners ) === count( $numeric_values ) ) {
        return array();
    }

    return $winners;
}

/**
 * Format spec value for display.
 *
 * @param mixed  $value  Raw value.
 * @param array  $spec   Spec definition.
 * @param string $symbol Currency symbol.
 * @return string Formatted value.
 */
function erh_format_compare_spec_value( $value, array $spec, string $symbol = '$' ): string {
    if ( $value === null || $value === '' ) {
        return '—';
    }

    $format = $spec['format'] ?? '';
    $unit   = $spec['unit'] ?? '';

    // Boolean format.
    if ( $format === 'boolean' ) {
        $is_yes = is_bool( $value ) ? $value : ( $value === 'Yes' || $value === 'yes' || $value === '1' || $value === true );
        return $is_yes ? 'Yes' : 'No';
    }

    // Currency format (with optional unit suffix like "/Wh").
    if ( $format === 'currency' ) {
        if ( ! is_numeric( $value ) ) {
            return '—';
        }
        $formatted = $symbol . number_format( (float) $value, 2 );
        // Append valueUnit if specified (e.g., "/Wh", "/mph").
        if ( ! empty( $spec['valueUnit'] ) ) {
            $formatted .= $spec['valueUnit'];
        }
        return $formatted;
    }

    // Decimal format (with optional unit suffix like "mph/lb").
    if ( $format === 'decimal' ) {
        if ( ! is_numeric( $value ) ) {
            return '—';
        }
        $formatted = number_format( (float) $value, 2 );
        // Append valueUnit if specified (e.g., "mph/lb", "Wh/lb").
        if ( ! empty( $spec['valueUnit'] ) ) {
            $formatted .= ' ' . $spec['valueUnit'];
        }
        return $formatted;
    }

    // Array format.
    if ( $format === 'array' || $format === 'suspensionArray' ) {
        if ( is_array( $value ) ) {
            return implode( ', ', array_filter( $value ) );
        }
        return (string) $value;
    }

    // Feature array - special handling (expanded in template).
    if ( $format === 'featureArray' ) {
        if ( is_array( $value ) ) {
            return implode( ', ', array_filter( $value ) );
        }
        return (string) $value;
    }

    // IP rating format.
    if ( $format === 'ip' ) {
        return (string) $value;
    }

    // Default: value with unit.
    $formatted = is_numeric( $value ) ? number_format( (float) $value, is_float( $value + 0 ) && $value != floor( $value ) ? 1 : 0 ) : (string) $value;

    return $unit ? $formatted . ' ' . $unit : $formatted;
}

/**
 * Build compare spec rows with winner detection and diff tracking.
 *
 * @param array[] $products Products from erh_get_compare_products().
 * @param array   $specs    Spec definitions.
 * @param string  $geo      Geo region.
 * @param string  $symbol   Currency symbol.
 * @return array[] Rows with label, values, winners, all_same.
 */
function erh_build_compare_spec_rows( array $products, array $specs, string $geo, string $symbol ): array {
    $rows = array();

    foreach ( $specs as $spec ) {
        // Resolve geo placeholders.
        $key   = str_replace( '{geo}', $geo, $spec['key'] );
        $label = str_replace( '{symbol}', $symbol, $spec['label'] );

        // Skip feature arrays - they're expanded separately.
        if ( ( $spec['format'] ?? '' ) === 'featureArray' ) {
            // Get all unique features across products.
            $all_features = array();
            foreach ( $products as $product ) {
                $features = erh_get_nested_spec( $product['specs'], $key );
                if ( is_array( $features ) ) {
                    $all_features = array_merge( $all_features, $features );
                }
            }
            $all_features = array_unique( $all_features );
            sort( $all_features );

            // Create a row for each feature.
            foreach ( $all_features as $feature ) {
                $feature_values = array();
                foreach ( $products as $product ) {
                    $product_features = erh_get_nested_spec( $product['specs'], $key );
                    $has_feature      = is_array( $product_features ) && in_array( $feature, $product_features, true );
                    $feature_values[] = $has_feature;
                }

                $rows[] = array(
                    'label'    => $feature,
                    'key'      => $key . '.' . sanitize_title( $feature ),
                    'spec'     => array( 'format' => 'boolean', 'higherBetter' => true ),
                    'values'   => $feature_values,
                    'winners'  => array(), // Don't show winners for features.
                    'all_same' => erh_values_are_same( $feature_values ),
                    'is_bool'  => true,
                );
            }
            continue;
        }

        // Get values from each product.
        $values = array_map( function ( $p ) use ( $key ) {
            return erh_get_nested_spec( $p['specs'], $key );
        }, $products );

        // Skip if all values missing.
        if ( erh_all_values_empty( $values ) ) {
            continue;
        }

        // Detect winners.
        $winners = erh_find_spec_winners( $values, $spec );

        // Check if all values are the same.
        $all_same = erh_values_are_same( $values );

        $rows[] = array(
            'label'    => $label,
            'key'      => $key,
            'spec'     => $spec,
            'values'   => $values,
            'winners'  => $winners,
            'all_same' => $all_same,
            'is_bool'  => ( $spec['format'] ?? '' ) === 'boolean',
        );
    }

    return $rows;
}

/**
 * Render a single compare spec row.
 *
 * @param array   $row      Row data from erh_build_compare_spec_rows().
 * @param array[] $products Products.
 * @param string  $symbol   Currency symbol.
 */
function erh_render_compare_spec_row( array $row, array $products, string $symbol = '$' ): void {
    $same_attr = $row['all_same'] ? ' data-same-values' : '';
    $is_bool   = $row['is_bool'] ?? false;
    $spec      = $row['spec'];
    ?>
    <tr<?php echo $same_attr; ?>>
        <td>
            <div class="compare-spec-label">
                <?php echo esc_html( $row['label'] ); ?>
                <?php if ( ! empty( $spec['tooltip'] ) ) : ?>
                    <span class="info-trigger" data-tooltip="<?php echo esc_attr( $spec['tooltip'] ); ?>" data-tooltip-trigger="click">
                        <?php erh_the_icon( 'info', '', [ 'width' => '14', 'height' => '14' ] ); ?>
                    </span>
                <?php endif; ?>
            </div>
        </td>
        <?php foreach ( $row['values'] as $idx => $value ) : ?>
            <?php
            $is_winner = in_array( $idx, $row['winners'], true );
            $formatted = erh_format_compare_spec_value( $value, $spec, $symbol );

            // Handle boolean specs with green/red badges.
            if ( $is_bool ) :
                if ( $value === null || $value === '' ) :
                    ?>
                    <td>—</td>
                    <?php
                else :
                    $is_yes = is_bool( $value ) ? $value : ( $value === 'Yes' || $value === 'yes' || $value === '1' || $value === true );
                    $class  = $is_yes ? 'feature-yes' : 'feature-no';
                    $icon   = $is_yes ? 'check' : 'x';
                    $text   = $is_yes ? 'Yes' : 'No';
                    ?>
                    <td class="<?php echo esc_attr( $class ); ?>">
                        <div class="compare-spec-value-inner">
                            <span class="compare-feature-badge">
                                <?php erh_the_icon( $icon ); ?>
                            </span>
                            <span class="compare-feature-text"><?php echo esc_html( $text ); ?></span>
                        </div>
                    </td>
                    <?php
                endif;
            // Handle winner cells with purple badge.
            elseif ( $is_winner ) :
                ?>
                <td class="is-winner">
                    <div class="compare-spec-value-inner">
                        <span class="compare-spec-badge">
                            <?php erh_the_icon( 'check' ); ?>
                        </span>
                        <span class="compare-spec-value-text"><?php echo esc_html( $formatted ); ?></span>
                    </div>
                </td>
                <?php
            // Non-winner cells - plain text, no wrapper.
            else :
                ?>
                <td><?php echo esc_html( $formatted ); ?></td>
                <?php
            endif;
            ?>
        <?php endforeach; ?>
    </tr>
    <?php
}

/**
 * Render a mobile spec card for comparison.
 *
 * Uses shared component from product-thumb.php for consistent rendering with JS.
 *
 * @param array   $row      Row data from erh_build_compare_spec_rows().
 * @param array[] $products Products array.
 * @param string  $symbol   Currency symbol.
 */
function erh_render_mobile_spec_card( array $row, array $products, string $symbol = '$' ): void {
    $spec = $row['spec'];
    ?>
    <div class="compare-spec-card">
        <div class="compare-spec-card-label">
            <?php echo esc_html( $row['label'] ); ?>
            <?php if ( ! empty( $spec['tooltip'] ) ) : ?>
                <span class="info-trigger" data-tooltip="<?php echo esc_attr( $spec['tooltip'] ); ?>" data-tooltip-trigger="click">
                    <?php erh_the_icon( 'info', '', [ 'width' => '14', 'height' => '14' ] ); ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="compare-spec-card-values">
            <?php foreach ( $row['values'] as $idx => $value ) :
                $product   = $products[ $idx ] ?? null;
                $is_winner = in_array( $idx, $row['winners'], true );

                // Use shared component for consistent rendering with JS.
                erh_the_mobile_spec_value( $product, $value, $spec, $is_winner );
            endforeach; ?>
        </div>
    </div>
    <?php
}

/**
 * Calculate suspension score for comparison (matches scorer algorithm).
 *
 * Scoring (same as ProductScorer):
 * - Front hydraulic: +15, Front spring/fork: +10, Front rubber: +7
 * - Rear hydraulic: +15, Rear spring/fork: +10, Rear rubber: +7
 * - Dual entries count for both front and rear
 *
 * @param mixed $suspension_type Raw suspension.type value (array or null).
 * @return int Suspension score (0-30 range, without adjustable bonus).
 */
function erh_normalize_suspension_level( $suspension_type ): int {
    if ( ! is_array( $suspension_type ) || empty( $suspension_type ) ) {
        return 0;
    }

    $types = array_map( fn( $s ) => strtolower( (string) $s ), $suspension_type );

    // Check for "None" only.
    if ( count( $types ) === 1 && ( $types[0] === 'none' || $types[0] === '' ) ) {
        return 0;
    }

    $score = 0;

    // Helper to check if any type matches a pattern.
    $has_match = fn( $position, $type ) => array_reduce(
        $types,
        fn( $carry, $t ) => $carry || ( strpos( $t, $position ) !== false && strpos( $t, $type ) !== false ),
        false
    );

    // Front suspension scoring.
    if ( $has_match( 'front', 'hydraulic' ) || $has_match( 'dual', 'hydraulic' ) ) {
        $score += 15;
    } elseif ( $has_match( 'front', 'spring' ) || $has_match( 'front', 'fork' ) || $has_match( 'dual', 'spring' ) || $has_match( 'dual', 'fork' ) ) {
        $score += 10;
    } elseif ( $has_match( 'front', 'rubber' ) || $has_match( 'dual', 'rubber' ) ) {
        $score += 7;
    }

    // Rear suspension scoring.
    if ( $has_match( 'rear', 'hydraulic' ) || $has_match( 'dual', 'hydraulic' ) ) {
        $score += 15;
    } elseif ( $has_match( 'rear', 'spring' ) || $has_match( 'rear', 'fork' ) || $has_match( 'dual', 'spring' ) || $has_match( 'dual', 'fork' ) ) {
        $score += 10;
    } elseif ( $has_match( 'rear', 'rubber' ) || $has_match( 'dual', 'rubber' ) ) {
        $score += 7;
    }

    return $score;
}

/**
 * Format suspension array for display.
 *
 * @param mixed $suspension_type Raw suspension.type value (array or null).
 * @return string Human-readable suspension description.
 */
function erh_format_suspension_display( $suspension_type ): string {
    if ( ! is_array( $suspension_type ) || empty( $suspension_type ) ) {
        return 'None';
    }

    $types = array_filter( $suspension_type, fn( $t ) => strtolower( (string) $t ) !== 'none' && $t !== '' );

    if ( empty( $types ) ) {
        return 'None';
    }

    // Join multiple types with " + " for readability.
    return implode( ' + ', array_map( fn( $t ) => strtolower( (string) $t ), $types ) );
}

/**
 * Normalize tire type to a comparable level.
 *
 * Combines tire_type, pneumatic_type, and self_healing into a single comparable string.
 *
 * @param array $specs Product specs array.
 * @return string Normalized tire type: 'Tubeless', 'Pneumatic', 'Self-healing', 'Mixed', 'Solid'.
 */
function erh_normalize_tire_type( array $specs ): string {
    $tire_type      = strtolower( (string) ( erh_get_nested_spec( $specs, 'wheels.tire_type' ) ?? '' ) );
    $pneumatic_type = strtolower( (string) ( erh_get_nested_spec( $specs, 'wheels.pneumatic_type' ) ?? '' ) );
    $self_healing   = (bool) erh_get_nested_spec( $specs, 'wheels.self_healing' );

    // Check for tubeless in either field (tire_type or pneumatic_type).
    $is_tubeless = strpos( $tire_type, 'tubeless' ) !== false || strpos( $pneumatic_type, 'tubeless' ) !== false;

    // Pneumatic tires (including tubeless which is a type of pneumatic).
    if ( $is_tubeless || ( strpos( $tire_type, 'pneumatic' ) !== false && strpos( $tire_type, 'semi' ) === false ) ) {
        if ( $is_tubeless ) {
            return 'Tubeless';
        }
        if ( $self_healing ) {
            return 'Self-healing';
        }
        return 'Pneumatic';
    }

    // Mixed/semi-pneumatic.
    if ( strpos( $tire_type, 'mixed' ) !== false || strpos( $tire_type, 'semi' ) !== false ) {
        return 'Mixed';
    }

    // Solid/honeycomb.
    return 'Solid';
}

/**
 * Normalize tire type for SAFETY comparison.
 *
 * For safety: Pneumatic > Mixed > Solid
 * - Pneumatic/tubeless have best grip and braking (both are pneumatic)
 * - Self-healing doesn't matter for safety (it's about grip, not flats)
 * - Solid tires are unsafe (poor grip, longer braking)
 *
 * @param array $specs Product specs array.
 * @return string Normalized tire type: 'Pneumatic', 'Mixed', 'Solid'.
 */
function erh_normalize_tire_type_for_safety( array $specs ): string {
    $tire_type      = strtolower( (string) ( erh_get_nested_spec( $specs, 'wheels.tire_type' ) ?? '' ) );
    $pneumatic_type = strtolower( (string) ( erh_get_nested_spec( $specs, 'wheels.pneumatic_type' ) ?? '' ) );

    // Check for tubeless in either field.
    $is_tubeless = strpos( $tire_type, 'tubeless' ) !== false || strpos( $pneumatic_type, 'tubeless' ) !== false;

    // Pneumatic tires (tubed or tubeless) - best safety.
    if ( $is_tubeless || ( strpos( $tire_type, 'pneumatic' ) !== false && strpos( $tire_type, 'semi' ) === false ) ) {
        return 'Pneumatic'; // Both tubed and tubeless are "Pneumatic" for safety ranking.
    }

    // Mixed/semi-pneumatic - moderate safety.
    if ( strpos( $tire_type, 'mixed' ) !== false || strpos( $tire_type, 'semi' ) !== false ) {
        return 'Mixed';
    }

    // Solid/honeycomb - worst safety.
    return 'Solid';
}

/**
 * Get average tire size for safety comparison.
 *
 * Larger tires = more stable, better handling, safer.
 *
 * @param array $specs Product specs array.
 * @return float|null Average tire size in inches, or null if no sizes available.
 */
function erh_get_avg_tire_size( array $specs ): ?float {
    $front_size = erh_get_nested_spec( $specs, 'wheels.tire_size_front' );
    $rear_size  = erh_get_nested_spec( $specs, 'wheels.tire_size_rear' );

    $sizes = array_filter(
        [ $front_size, $rear_size ],
        fn( $s ) => $s !== null && is_numeric( $s ) && (float) $s > 0
    );

    if ( empty( $sizes ) ) {
        return null;
    }

    return array_sum( array_map( 'floatval', $sizes ) ) / count( $sizes );
}

/**
 * Calculate folded footprint (floor area) from dimensions.
 *
 * Uses length × width only (2D) to show how much floor space the scooter
 * takes up when folded. More practical than volume since height varies
 * based on handlebar position.
 *
 * @param array $specs Product specs array.
 * @return float|null Folded area in square inches, or null if dimensions missing.
 */
function erh_calculate_folded_footprint( array $specs ): ?float {
    $length = erh_get_nested_spec( $specs, 'dimensions.folded_length' );
    $width  = erh_get_nested_spec( $specs, 'dimensions.folded_width' );

    // Need length and width for footprint.
    if ( ! is_numeric( $length ) || ! is_numeric( $width ) ) {
        return null;
    }

    return (float) $length * (float) $width;
}

/**
 * Format folded footprint for display.
 *
 * @param float $footprint Footprint in square inches.
 * @return string Formatted footprint (e.g., "748 sq in").
 */
function erh_format_folded_footprint( float $footprint ): string {
    return number_format( $footprint, 0 ) . ' sq in';
}

/**
 * Format IP rating for display.
 *
 * Simply returns the raw IP rating string (e.g., "IP55", "IPX5").
 * Used as displayFormatter when normalizer converts to numeric score.
 *
 * @param string|null $ip_rating IP rating string.
 * @return string Formatted IP rating or empty string.
 */
function erh_format_ip_rating_display( ?string $ip_rating ): string {
    if ( empty( $ip_rating ) ) {
        return '';
    }
    return strtoupper( trim( $ip_rating ) );
}

/**
 * Normalize IP rating to numeric score for comparison.
 *
 * IP rating format: IP[dust][water] where:
 * - Dust: 0-6 (X means untested)
 * - Water: 0-9 (X means untested)
 *
 * Comparison rules:
 * 1. Water rating (second digit) is primary - higher wins
 * 2. If water ratings equal, having dust rating (IP) beats no dust (IPX)
 *
 * Returns composite score: water*10 + (has_dust ? 1 : 0)
 *
 * Examples:
 * - IPX5 (50) > IP54 (41) — water 5 > water 4
 * - IP55 (51) > IPX5 (50) — both water 5, but IP55 has dust rating
 * - IP67 (71) > IP55 (51) — water 7 > water 5
 *
 * @param string|null $ip_rating IP rating string (e.g., "IP54", "IPX5", "IP67").
 * @return int Composite score (0-81), or 0 if no rating.
 */
function erh_normalize_ip_rating( ?string $ip_rating ): int {
    if ( empty( $ip_rating ) ) {
        return 0;
    }

    $ip = strtoupper( trim( $ip_rating ) );

    // Match IP followed by two characters (digits or X).
    if ( ! preg_match( '/IP([0-6X])([0-9X])/i', $ip, $matches ) ) {
        return 0;
    }

    // Water rating (second digit) is primary comparison.
    // Dust rating (first digit) is tiebreaker when water ratings are equal.
    //
    // Scoring: water * 10 + (has_dust ? 1 : 0)
    // Examples:
    // - IPX5 = 5*10 + 0 = 50
    // - IP55 = 5*10 + 1 = 51  (beats IPX5 due to dust rating)
    // - IP54 = 4*10 + 1 = 41  (loses to IPX5 because water 4 < water 5)
    // - IPX4 = 4*10 + 0 = 40
    $water = $matches[2] === 'X' ? 0 : (int) $matches[2];
    $has_dust = $matches[1] !== 'X';

    return ( $water * 10 ) + ( $has_dust ? 1 : 0 );
}

/**
 * Normalize brake type for maintenance comparison.
 *
 * Based on maintenance scoring:
 * - Drum: 25 pts (sealed, maintenance-free)
 * - Electronic/Foot/None: 10 pts (minimal/no physical wear)
 * - Disc (hydraulic or mechanical): 15 pts (needs pad replacement, adjustment)
 *
 * Returns normalized category for ranking comparison.
 *
 * @param array $specs Product specs array.
 * @return string Normalized brake category: 'Drum', 'Electronic', 'Foot', or 'Disc'.
 */
function erh_normalize_brake_maintenance( array $specs ): string {
    $front = strtolower( (string) ( erh_get_nested_spec( $specs, 'brakes.front' ) ?? '' ) );
    $rear  = strtolower( (string) ( erh_get_nested_spec( $specs, 'brakes.rear' ) ?? '' ) );

    // Helper to categorize a single brake.
    $categorize = function ( string $brake ): string {
        if ( strpos( $brake, 'drum' ) !== false ) {
            return 'Drum';
        }
        if ( strpos( $brake, 'disc' ) !== false || strpos( $brake, 'hydraulic' ) !== false || strpos( $brake, 'mechanical' ) !== false ) {
            return 'Disc';
        }
        if ( strpos( $brake, 'electronic' ) !== false || strpos( $brake, 'regenerative' ) !== false || strpos( $brake, 'regen' ) !== false ) {
            return 'Electronic';
        }
        if ( strpos( $brake, 'foot' ) !== false || $brake === 'none' || $brake === '' ) {
            return 'Foot';
        }
        return 'Disc'; // Default to disc (most common, worst maintenance).
    };

    $front_cat = $categorize( $front );
    $rear_cat  = $categorize( $rear );

    // Maintenance ranking: Drum > Electronic > Foot > Disc.
    // Return the WORSE of the two brakes (bottleneck for maintenance).
    $ranking = [ 'Disc' => 0, 'Foot' => 1, 'Electronic' => 2, 'Drum' => 3 ];
    $front_rank = $ranking[ $front_cat ] ?? 0;
    $rear_rank  = $ranking[ $rear_cat ] ?? 0;

    // Return the one with lower rank (worse for maintenance).
    return $front_rank <= $rear_rank ? $front_cat : $rear_cat;
}

/**
 * Get tire comparison values for advantage display.
 *
 * @param array $specs_a Product A specs.
 * @param array $specs_b Product B specs.
 * @return array{val_a: string, val_b: string} Normalized tire types.
 */
function erh_get_tire_comparison( array $specs_a, array $specs_b ): array {
    return [
        'val_a' => erh_normalize_tire_type( $specs_a ),
        'val_b' => erh_normalize_tire_type( $specs_b ),
    ];
}

/**
 * Get suspension comparison values for advantage display.
 *
 * @param array $specs_a Product A specs.
 * @param array $specs_b Product B specs.
 * @return array{val_a: string, val_b: string} Normalized suspension levels.
 */
function erh_get_suspension_comparison( array $specs_a, array $specs_b ): array {
    $type_a = erh_get_nested_spec( $specs_a, 'suspension.type' );
    $type_b = erh_get_nested_spec( $specs_b, 'suspension.type' );

    return [
        'val_a' => erh_normalize_suspension_level( $type_a ),
        'val_b' => erh_normalize_suspension_level( $type_b ),
    ];
}

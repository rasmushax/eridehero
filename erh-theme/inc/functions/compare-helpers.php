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
 * @param int[]  $product_ids Product IDs.
 * @param string $geo         User's geo region.
 * @return array[] Products with enriched pricing data.
 */
function erh_get_compare_products( array $product_ids, string $geo = 'US' ): array {
    $product_cache = new ERH\Database\ProductCache();
    $products      = array();

    foreach ( $product_ids as $id ) {
        $data = $product_cache->get( (int) $id );
        if ( ! $data ) {
            continue;
        }

        // Enrich with geo-specific pricing.
        $price_history  = $data['price_history'] ?? array();
        $region_pricing = $price_history[ $geo ] ?? $price_history['US'] ?? array();

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
            'specs'         => $specs,
            'rating'        => $score,
            'current_price' => $region_pricing['current_price'] ?? null,
            'currency'      => class_exists( 'ERH\\GeoConfig' ) ? \ERH\GeoConfig::get_currency( $geo ) : 'USD',
            'retailer'      => $region_pricing['retailer'] ?? null,
            'buy_link'      => $region_pricing['tracked_url'] ?? null,
            'in_stock'      => $region_pricing['instock'] ?? false,
            'avg_3m'        => $region_pricing['avg_3m'] ?? null,
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
 * Calculate advantages for each product in a comparison.
 *
 * Determines which categories each product leads in based on scores.
 *
 * @param array[] $products   Products from erh_get_compare_products().
 * @param array   $categories Category score keys to label mapping.
 * @return array[] Array of advantages per product index.
 */
function erh_calculate_product_advantages( array $products, array $categories ): array {
    $advantages = array_fill( 0, count( $products ), array() );
    $threshold  = 5; // Minimum % difference to count as advantage.
    $max_adv    = 4; // Max advantages per product.

    foreach ( $categories as $key => $label ) {
        // Get scores for this category from all products.
        $scores = array();
        foreach ( $products as $idx => $product ) {
            $scores[ $idx ] = $product['specs']['scores'][ $key ] ?? 0;
        }

        // Find the best score.
        $max_score = max( $scores );
        if ( $max_score <= 0 ) {
            continue;
        }

        // Find winner(s) - products with the best score.
        $winners = array_keys( array_filter( $scores, fn( $s ) => $s === $max_score ) );

        // Only award advantage if there's a single winner.
        if ( count( $winners ) !== 1 ) {
            continue;
        }

        $winner_idx   = $winners[0];
        $winner_score = $scores[ $winner_idx ];

        // Calculate best runner-up score.
        $runner_up_scores = $scores;
        unset( $runner_up_scores[ $winner_idx ] );
        $runner_up_max = count( $runner_up_scores ) > 0 ? max( $runner_up_scores ) : 0;

        // Calculate percentage difference.
        if ( $runner_up_max > 0 ) {
            $diff = round( ( ( $winner_score - $runner_up_max ) / $runner_up_max ) * 100 );
        } else {
            $diff = 100;
        }

        // Only count as advantage if above threshold.
        if ( $diff >= $threshold && count( $advantages[ $winner_idx ] ) < $max_adv ) {
            $advantages[ $winner_idx ][] = array(
                'label'     => $label,
                'diff'      => $diff,
                'direction' => 'higher',
            );
        }
    }

    // Sort each product's advantages by diff (biggest first).
    foreach ( $advantages as &$adv_list ) {
        usort( $adv_list, fn( $a, $b ) => $b['diff'] - $a['diff'] );
    }

    return $advantages;
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

    // Tie threshold (3%).
    $tie_threshold = 0.03;
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

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
 * Each spec is compared independently - different products can win different specs.
 * Format: "1.6 mph faster top speed" not "23 mph vs 22 mph"
 *
 * Supports composite specs (e.g., "Better ride quality") that consolidate
 * multiple individual specs when one product wins the category decisively.
 *
 * @param array[] $products Products from erh_get_compare_products().
 * @return array[] Array of advantages per product index.
 */
function erh_calculate_spec_advantages( array $products ): array {
    $count = count( $products );

    // For 3+ products, use the AdvantageCalculatorFactory.
    if ( $count >= 3 ) {
        $product_type = $products[0]['product_type'] ?? 'escooter';
        return \ERH\Comparison\AdvantageCalculatorFactory::calculate( $products, $product_type );
    }

    // For single product, no advantages.
    if ( $count < 2 ) {
        return array_fill( 0, $count, [] );
    }

    // 2-product comparison uses the detailed logic below.

    $spec_config = \ERH\Config\SpecConfig::COMPARISON_SPECS;
    $threshold   = \ERH\Config\SpecConfig::SPEC_ADVANTAGE_THRESHOLD;
    $max_adv     = \ERH\Config\SpecConfig::SPEC_ADVANTAGE_MAX;

    // Sort specs by priority.
    uasort( $spec_config, fn( $a, $b ) => ( $a['priority'] ?? 99 ) - ( $b['priority'] ?? 99 ) );

    $advantages     = [ [], [] ]; // Indexed by product position.
    $processed_keys = [];         // Track spec keys that were processed (for fallback logic).
    $composite_winners = [];      // Track composite category winners (spec_key => winner_idx).
    $composite_specs   = [];      // Track specs that belong to composites.
    $handled_specs     = [];      // Track specs already added as advantages (prevent duplicates).

    foreach ( $spec_config as $spec_key => $spec ) {
        // Check max advantages reached for both products.
        if ( count( $advantages[0] ) >= $max_adv && count( $advantages[1] ) >= $max_adv ) {
            break;
        }

        // Skip specs that were already handled by a composite.
        if ( in_array( $spec_key, $handled_specs, true ) ) {
            $processed_keys[] = $spec_key;
            continue;
        }

        // Handle composite specs (e.g., "_ride_quality").
        if ( ( $spec['type'] ?? '' ) === 'composite' ) {
            $result = erh_process_composite_advantage( $spec_key, $spec, $products, $advantages, $max_adv );
            if ( $result ) {
                $advantages = $result['advantages'];
                if ( $result['winner'] !== null ) {
                    $composite_winners[ $spec_key ] = $result['winner'];
                }
                // Mark child specs as belonging to this composite.
                foreach ( $spec['specs'] ?? [] as $child_key ) {
                    $composite_specs[ $child_key ] = [
                        'composite'  => $spec_key,
                        'winner'     => $result['winner'],
                        'close'      => $result['close'] ?? false,
                    ];
                }
                // Track specs that were already added as advantages.
                foreach ( $result['handled_specs'] ?? [] as $handled_key ) {
                    $handled_specs[] = $handled_key;
                }
            }
            $processed_keys[] = $spec_key;
            continue;
        }

        // Check if this spec belongs to a composite.
        if ( isset( $composite_specs[ $spec_key ] ) ) {
            $comp_info = $composite_specs[ $spec_key ];
            // If composite had a clear winner and this product won, skip (already in consolidated).
            if ( ! $comp_info['close'] && $comp_info['winner'] !== null ) {
                // Only process for the loser (to show their individual wins).
                $result = erh_process_individual_spec_for_loser(
                    $spec_key,
                    $spec,
                    $products,
                    $comp_info['winner'],
                    $advantages,
                    $max_adv
                );
                if ( $result ) {
                    $advantages = $result;
                }
                $processed_keys[] = $spec_key;
                continue;
            }
            // If close, fall through to normal processing.
        }

        // Handle fallbackFor - skip if primary spec was already processed.
        if ( ! empty( $spec['fallbackFor'] ) ) {
            if ( in_array( $spec['fallbackFor'], $processed_keys, true ) ) {
                continue;
            }
        }

        // Get values from both products (with optional normalization).
        $val_a = erh_get_normalized_spec_value( $products[0]['specs'], $spec_key, $spec );
        $val_b = erh_get_normalized_spec_value( $products[1]['specs'], $spec_key, $spec );

        // Skip if either value is missing.
        if ( $val_a === null || $val_a === '' || $val_b === null || $val_b === '' ) {
            continue;
        }

        // For specs with displayFormatter, also check raw values (normalizer might return 0 for "no value").
        if ( ! empty( $spec['displayFormatter'] ) ) {
            $raw_a = erh_get_nested_spec( $products[0]['specs'], $spec_key );
            $raw_b = erh_get_nested_spec( $products[1]['specs'], $spec_key );
            if ( empty( $raw_a ) || empty( $raw_b ) ) {
                continue;
            }
        }

        // Mark this spec as processed (for fallback logic).
        $processed_keys[] = $spec_key;

        // Handle special formats.
        if ( ( $spec['diffFormat'] ?? '' ) === 'dual_vs_single' ) {
            $adv = erh_format_motor_count_advantage( $val_a, $val_b, $spec );
            if ( $adv ) {
                $winner_idx = $adv['winner'];
                if ( count( $advantages[ $winner_idx ] ) < $max_adv ) {
                    $advantages[ $winner_idx ][] = $adv;
                }
            }
            continue;
        }

        // Handle feature count comparison (features array).
        if ( ( $spec['diffFormat'] ?? '' ) === 'feature_count' ) {
            $features_a = erh_get_nested_spec( $products[0]['specs'], $spec_key );
            $features_b = erh_get_nested_spec( $products[1]['specs'], $spec_key );
            $adv = erh_format_feature_count_advantage( $features_a, $features_b, $spec );
            if ( $adv ) {
                $winner_idx = $adv['winner'];
                if ( count( $advantages[ $winner_idx ] ) < $max_adv ) {
                    $advantages[ $winner_idx ][] = $adv;
                }
            }
            continue;
        }

        // Handle ranked/categorical specs (suspension, tire type).
        if ( ! empty( $spec['ranking'] ) ) {
            $adv = erh_format_ranked_advantage( $spec_key, $spec, $val_a, $val_b );
            if ( $adv ) {
                $winner_idx = $adv['winner'];
                if ( count( $advantages[ $winner_idx ] ) < $max_adv ) {
                    $advantages[ $winner_idx ][] = $adv;
                }
            }
            continue;
        }

        // Numeric comparison.
        if ( ! is_numeric( $val_a ) || ! is_numeric( $val_b ) ) {
            continue;
        }

        $a = (float) $val_a;
        $b = (float) $val_b;

        // Skip if values are the same.
        if ( $a === $b ) {
            continue;
        }

        // Skip if requireValidPair is set and either value is 0 (e.g., IP rating: none/unknown).
        if ( ! empty( $spec['requireValidPair'] ) && ( $a === 0.0 || $b === 0.0 ) ) {
            continue;
        }

        // Calculate difference.
        $higher_better = $spec['higherBetter'] ?? true;
        $diff          = abs( $a - $b );
        $base          = $higher_better ? min( $a, $b ) : max( $a, $b );
        $pct_diff      = $base > 0 ? ( $diff / $base ) * 100 : 0;

        // Check threshold (minDiff or percentage).
        $min_diff = $spec['minDiff'] ?? null;
        if ( $min_diff !== null ) {
            if ( $diff < $min_diff ) {
                continue;
            }
        } elseif ( $pct_diff < $threshold ) {
            continue;
        }

        // Determine winner.
        if ( $higher_better ) {
            $winner_idx = $a > $b ? 0 : 1;
        } else {
            $winner_idx = $a < $b ? 0 : 1;
        }

        // Skip if this product already has max advantages.
        if ( count( $advantages[ $winner_idx ] ) >= $max_adv ) {
            continue;
        }

        // Format the advantage text.
        // For specs with displayFormatter (e.g., suspension), pass raw values for nice display.
        if ( ! empty( $spec['displayFormatter'] ) ) {
            $raw_a = erh_get_nested_spec( $products[0]['specs'], $spec_key );
            $raw_b = erh_get_nested_spec( $products[1]['specs'], $spec_key );
            $adv = erh_format_display_formatter_advantage( $spec_key, $spec, $raw_a, $raw_b, $winner_idx );
        } else {
            $adv = erh_format_spec_advantage( $spec_key, $spec, $a, $b, $winner_idx );
        }
        if ( $adv ) {
            $advantages[ $winner_idx ][] = $adv;
        }
    }

    return $advantages;
}

/**
 * Process a composite advantage (e.g., "Better ride quality").
 *
 * If one product wins the category score by >= threshold, they get consolidated bullet.
 * Individual spec wins are collected for the comparison line.
 *
 * @param string  $spec_key   Composite spec key.
 * @param array   $spec       Composite spec config.
 * @param array[] $products   Products.
 * @param array[] $advantages Current advantages.
 * @param int     $max_adv    Max advantages per product.
 * @return array|null Result with updated advantages, winner, and close flag.
 */
function erh_process_composite_advantage( string $spec_key, array $spec, array $products, array $advantages, int $max_adv ): ?array {
    // Special handling for portability composite.
    if ( $spec_key === '_portability' ) {
        return erh_process_portability_composite( $spec, $products, $advantages, $max_adv );
    }

    $score_key  = $spec['scoreKey'] ?? '';
    $threshold  = $spec['threshold'] ?? 5;
    $child_specs = $spec['specs'] ?? [];

    // Get category scores.
    $score_a = $products[0]['specs']['scores'][ $score_key ] ?? 0;
    $score_b = $products[1]['specs']['scores'][ $score_key ] ?? 0;

    $diff = abs( $score_a - $score_b );
    $close = $diff < $threshold;

    // If scores are close, skip consolidated - let individual specs be processed normally.
    if ( $close ) {
        return [
            'advantages' => $advantages,
            'winner'     => null,
            'close'      => true,
        ];
    }

    // Determine category winner.
    $winner_idx = $score_a > $score_b ? 0 : 1;
    $loser_idx  = $winner_idx === 0 ? 1 : 0;

    // Skip if winner already has max advantages.
    if ( count( $advantages[ $winner_idx ] ) >= $max_adv ) {
        return [
            'advantages' => $advantages,
            'winner'     => $winner_idx,
            'close'      => false,
        ];
    }

    // Collect individual spec wins for the comparison line.
    $spec_config = \ERH\Config\SpecConfig::COMPARISON_SPECS;
    $winner_details = [];

    foreach ( $child_specs as $child_key ) {
        $child_spec = $spec_config[ $child_key ] ?? null;
        if ( ! $child_spec ) {
            continue;
        }

        // Get normalized values for comparison.
        $val_a = erh_get_normalized_spec_value( $products[0]['specs'], $child_key, $child_spec );
        $val_b = erh_get_normalized_spec_value( $products[1]['specs'], $child_key, $child_spec );

        if ( $val_a === null || $val_b === null || $val_a === '' || $val_b === '' ) {
            continue;
        }

        $child_winner = erh_compare_spec_values( $val_a, $val_b, $child_spec );
        if ( $child_winner === $winner_idx ) {
            // For specs with displayFormatter, pass raw values for nice display.
            if ( ! empty( $child_spec['displayFormatter'] ) ) {
                $raw_a = erh_get_nested_spec( $products[0]['specs'], $child_key );
                $raw_b = erh_get_nested_spec( $products[1]['specs'], $child_key );
                $detail = erh_format_composite_detail( $child_key, $child_spec, $raw_a, $raw_b, $winner_idx );
            } else {
                $detail = erh_format_composite_detail( $child_key, $child_spec, $val_a, $val_b, $winner_idx );
            }
            if ( $detail ) {
                $winner_details[] = $detail;
            }
        }
    }

    // Create consolidated advantage for the winner.
    if ( ! empty( $winner_details ) ) {
        // Capitalize first letter of the comparison line.
        $comparison_line = implode( ', ', $winner_details );
        $comparison_line = ucfirst( $comparison_line );

        // Use diffFormat to determine prefix (e.g., "Lower maintenance" vs "Better ride quality").
        $diff_format = $spec['diffFormat'] ?? 'better';
        $label       = $spec['label'] ?? 'specs';
        switch ( $diff_format ) {
            case 'lower':
                $text = 'Lower ' . $label;
                break;
            case 'safer':
                $text = 'Safer';  // Just "Safer" as headline
                break;
            default:
                $text = 'Better ' . $label;
        }

        $advantages[ $winner_idx ][] = [
            'text'       => $text,
            'comparison' => $comparison_line,
            'winner'     => $winner_idx,
            'spec_key'   => $spec_key,
            'tooltip'    => $spec['tooltip'] ?? null,
        ];
    }

    return [
        'advantages'    => $advantages,
        'winner'        => $winner_idx,
        'close'         => false,
        'handled_specs' => $child_specs,  // Prevent duplicate processing of child specs
    ];
}

/**
 * Process portability composite advantage.
 *
 * Logic:
 * - If one scooter wins BOTH weight AND footprint → "More Portable" with "lighter, smaller when folded"
 * - If one wins weight and other wins footprint → each gets their individual advantage
 *
 * @param array   $spec       Composite spec config.
 * @param array[] $products   Products.
 * @param array[] $advantages Current advantages.
 * @param int     $max_adv    Max advantages per product.
 * @return array Result with updated advantages, winner (or null if split), and close flag.
 */
function erh_process_portability_composite( array $spec, array $products, array $advantages, int $max_adv ): array {
    // Get weight values (lower is better).
    $weight_a = erh_get_nested_spec( $products[0]['specs'], 'dimensions.weight' );
    $weight_b = erh_get_nested_spec( $products[1]['specs'], 'dimensions.weight' );

    // Get footprint values (lower is better).
    $footprint_a = erh_calculate_folded_footprint( $products[0]['specs'] );
    $footprint_b = erh_calculate_folded_footprint( $products[1]['specs'] );

    // Determine winners for each (null if can't compare or tied).
    $weight_winner = null;
    $footprint_winner = null;

    if ( is_numeric( $weight_a ) && is_numeric( $weight_b ) && $weight_a !== $weight_b ) {
        $weight_winner = (float) $weight_a < (float) $weight_b ? 0 : 1;
    }

    if ( $footprint_a !== null && $footprint_b !== null && $footprint_a !== $footprint_b ) {
        $footprint_winner = $footprint_a < $footprint_b ? 0 : 1;
    }

    // Case 1: Same product wins both → consolidated "More Portable".
    if ( $weight_winner !== null && $footprint_winner !== null && $weight_winner === $footprint_winner ) {
        $winner_idx = $weight_winner;

        if ( count( $advantages[ $winner_idx ] ) < $max_adv ) {
            $weight_diff = abs( (float) $weight_a - (float) $weight_b );
            $winner_weight = $winner_idx === 0 ? $weight_a : $weight_b;
            $loser_weight  = $winner_idx === 0 ? $weight_b : $weight_a;
            $winner_footprint = $winner_idx === 0 ? $footprint_a : $footprint_b;
            $loser_footprint  = $winner_idx === 0 ? $footprint_b : $footprint_a;

            $details = [];
            $details[] = erh_format_spec_number( $weight_diff ) . ' lbs lighter';
            $details[] = 'smaller when folded (' . erh_format_folded_footprint( $winner_footprint ) . ' vs ' . erh_format_folded_footprint( $loser_footprint ) . ')';

            $advantages[ $winner_idx ][] = [
                'text'       => 'More portable',
                'comparison' => ucfirst( implode( ', ', $details ) ),
                'winner'     => $winner_idx,
                'spec_key'   => '_portability',
                'tooltip'    => $spec['tooltip'] ?? null,
            ];
        }

        return [
            'advantages'    => $advantages,
            'winner'        => $winner_idx,
            'close'         => false,
            'handled_specs' => [ 'dimensions.weight', 'folded_footprint' ],
        ];
    }

    // Case 2: Split wins or can't compare → add granular advantages.
    $handled_specs = [];

    // Weight winner gets "X lbs lighter".
    if ( $weight_winner !== null && count( $advantages[ $weight_winner ] ) < $max_adv ) {
        $weight_diff = abs( (float) $weight_a - (float) $weight_b );
        $winner_weight = $weight_winner === 0 ? $weight_a : $weight_b;
        $loser_weight  = $weight_winner === 0 ? $weight_b : $weight_a;

        $advantages[ $weight_winner ][] = [
            'text'       => erh_format_spec_number( $weight_diff ) . ' lbs lighter',
            'comparison' => erh_format_spec_number( $winner_weight ) . ' lbs vs ' . erh_format_spec_number( $loser_weight ) . ' lbs',
            'winner'     => $weight_winner,
            'spec_key'   => 'dimensions.weight',
            'tooltip'    => 'Lighter scooters are easier to carry upstairs, onto public transit, or into your office.',
        ];
        $handled_specs[] = 'dimensions.weight';
    }

    // Footprint winner gets "Smaller when folded".
    if ( $footprint_winner !== null && count( $advantages[ $footprint_winner ] ) < $max_adv ) {
        $winner_footprint = $footprint_winner === 0 ? $footprint_a : $footprint_b;
        $loser_footprint  = $footprint_winner === 0 ? $footprint_b : $footprint_a;

        $advantages[ $footprint_winner ][] = [
            'text'       => 'Smaller when folded',
            'comparison' => erh_format_folded_footprint( $winner_footprint ) . ' vs ' . erh_format_folded_footprint( $loser_footprint ),
            'winner'     => $footprint_winner,
            'spec_key'   => 'folded_footprint',
            'tooltip'    => 'Smaller folded size makes it easier to fit in car trunks, closets, or under desks.',
        ];
        $handled_specs[] = 'folded_footprint';
    }

    return [
        'advantages'    => $advantages,
        'winner'        => null, // Split wins, no clear portability winner
        'close'         => true,
        'handled_specs' => $handled_specs,
    ];
}

/**
 * Process individual spec for the loser of a composite category.
 *
 * Only adds advantage if the loser wins this specific spec.
 *
 * @param string  $spec_key   Spec key.
 * @param array   $spec       Spec config.
 * @param array[] $products   Products.
 * @param int     $comp_winner Composite category winner index.
 * @param array[] $advantages Current advantages.
 * @param int     $max_adv    Max advantages.
 * @return array[]|null Updated advantages or null.
 */
function erh_process_individual_spec_for_loser( string $spec_key, array $spec, array $products, int $comp_winner, array $advantages, int $max_adv ): ?array {
    $loser_idx = $comp_winner === 0 ? 1 : 0;

    // Skip if loser already has max advantages.
    if ( count( $advantages[ $loser_idx ] ) >= $max_adv ) {
        return $advantages;
    }

    // Get normalized values for comparison.
    $val_a = erh_get_normalized_spec_value( $products[0]['specs'], $spec_key, $spec );
    $val_b = erh_get_normalized_spec_value( $products[1]['specs'], $spec_key, $spec );

    if ( $val_a === null || $val_b === null || $val_a === '' || $val_b === '' ) {
        return $advantages;
    }

    // Check if loser wins this spec.
    $spec_winner = erh_compare_spec_values( $val_a, $val_b, $spec );
    if ( $spec_winner !== $loser_idx ) {
        return $advantages;
    }

    // Format the advantage.
    // For specs with displayFormatter (e.g., suspension), use raw values for display.
    if ( ! empty( $spec['displayFormatter'] ) ) {
        $raw_a = erh_get_nested_spec( $products[0]['specs'], $spec_key );
        $raw_b = erh_get_nested_spec( $products[1]['specs'], $spec_key );
        $adv = erh_format_display_formatter_advantage( $spec_key, $spec, $raw_a, $raw_b, $loser_idx );
    } elseif ( ! empty( $spec['ranking'] ) ) {
        $adv = erh_format_ranked_advantage( $spec_key, $spec, $val_a, $val_b );
    } elseif ( is_numeric( $val_a ) && is_numeric( $val_b ) ) {
        $adv = erh_format_spec_advantage( $spec_key, $spec, (float) $val_a, (float) $val_b, $loser_idx );
    } else {
        return $advantages;
    }

    if ( $adv ) {
        $advantages[ $loser_idx ][] = $adv;
    }

    return $advantages;
}

/**
 * Compare two spec values and return winner index.
 *
 * @param mixed $val_a Product A value.
 * @param mixed $val_b Product B value.
 * @param array $spec  Spec config.
 * @return int|null Winner index (0 or 1) or null if tie/incomparable.
 */
function erh_compare_spec_values( $val_a, $val_b, array $spec ): ?int {
    // Handle ranked values.
    if ( ! empty( $spec['ranking'] ) ) {
        $ranking = $spec['ranking'];
        $rank_a  = array_search( $val_a, $ranking, true );
        $rank_b  = array_search( $val_b, $ranking, true );

        if ( $rank_a === false || $rank_b === false ) {
            return null;
        }
        if ( $rank_a === $rank_b ) {
            return null;
        }

        return $rank_a > $rank_b ? 0 : 1;
    }

    // Handle numeric values.
    if ( is_numeric( $val_a ) && is_numeric( $val_b ) ) {
        $a = (float) $val_a;
        $b = (float) $val_b;

        if ( $a === $b ) {
            return null;
        }

        $higher_better = $spec['higherBetter'] ?? true;
        if ( $higher_better ) {
            return $a > $b ? 0 : 1;
        }
        return $a < $b ? 0 : 1;
    }

    return null;
}

/**
 * Format a detail string for composite comparison line.
 *
 * @param string $spec_key   Spec key.
 * @param array  $spec       Spec config.
 * @param mixed  $val_a      Product A value (raw for displayFormatter, normalized otherwise).
 * @param mixed  $val_b      Product B value.
 * @param int    $winner_idx Winner index.
 * @return string|null Detail string or null.
 */
function erh_format_composite_detail( string $spec_key, array $spec, $val_a, $val_b, int $winner_idx ): ?string {
    $winner_val = $winner_idx === 0 ? $val_a : $val_b;
    $loser_val  = $winner_idx === 0 ? $val_b : $val_a;
    $unit       = $spec['unit'] ?? '';
    $label      = $spec['label'] ?? '';

    // Special handling for brake_distance - show difference as "shorter stopping distance".
    if ( $spec_key === 'brake_distance' && is_numeric( $winner_val ) && is_numeric( $loser_val ) ) {
        $diff = abs( (float) $loser_val - (float) $winner_val );
        if ( $diff >= 0.5 ) { // Only show if meaningful difference.
            $diff_fmt = number_format( $diff, 1 );
            return "{$diff_fmt} ft shorter stopping distance";
        }
        return null;
    }

    // Special handling for safety tire type - show as "pneumatic vs solid tires".
    if ( $spec_key === 'safety.tire_type' ) {
        $winner_str = strtolower( (string) $winner_val );
        $loser_str  = strtolower( (string) $loser_val );
        if ( $winner_str === $loser_str ) {
            return null;
        }
        return "{$winner_str} vs {$loser_str} tires";
    }

    // Special handling for tire size safety - show as "larger 10.5" vs 8.5"".
    // Avoid "tires" since tire type also says "tires".
    if ( $spec_key === 'wheels.tire_size_safety' && is_numeric( $winner_val ) && is_numeric( $loser_val ) ) {
        $w_fmt = number_format( (float) $winner_val, 1 );
        $l_fmt = number_format( (float) $loser_val, 1 );
        return "larger {$w_fmt}\" vs {$l_fmt}\"";
    }

    // Handle specs with displayFormatter (e.g., suspension with array values).
    if ( ! empty( $spec['displayFormatter'] ) ) {
        $formatter  = $spec['displayFormatter'];
        if ( function_exists( $formatter ) ) {
            $winner_str = strtolower( $formatter( $winner_val ) );
            $loser_str  = strtolower( $formatter( $loser_val ) );
            // Handle "none" gracefully (e.g., "no suspension" instead of "none suspension").
            if ( $loser_str === 'none' ) {
                return "{$winner_str} vs no {$label}";
            }
            return "{$winner_str} vs {$loser_str} {$label}";
        }
    }

    // Handle ranked values (tire type, etc.).
    if ( ! empty( $spec['ranking'] ) ) {
        // Format: "tubeless vs solid tire type"
        $winner_str = strtolower( (string) $winner_val );
        $loser_str  = strtolower( (string) $loser_val );
        // Handle "none" gracefully.
        if ( $loser_str === 'none' ) {
            return "{$winner_str} vs no {$label}";
        }
        return "{$winner_str} vs {$loser_str} {$label}";
    }

    // Handle numeric values.
    if ( is_numeric( $winner_val ) && is_numeric( $loser_val ) ) {
        $w_fmt = erh_format_spec_number( (float) $winner_val );
        $l_fmt = erh_format_spec_number( (float) $loser_val );
        return "{$w_fmt}{$unit} vs {$l_fmt}{$unit} {$label}";
    }

    return null;
}

/**
 * Format a ranked/categorical advantage (suspension, tire type).
 *
 * @param string $spec_key Spec key.
 * @param array  $spec     Spec config.
 * @param mixed  $val_a    Product A value.
 * @param mixed  $val_b    Product B value.
 * @return array|null Advantage data or null.
 */
function erh_format_ranked_advantage( string $spec_key, array $spec, $val_a, $val_b ): ?array {
    $ranking = $spec['ranking'] ?? [];
    $rank_a  = array_search( $val_a, $ranking, true );
    $rank_b  = array_search( $val_b, $ranking, true );

    if ( $rank_a === false || $rank_b === false || $rank_a === $rank_b ) {
        return null;
    }

    $winner_idx = $rank_a > $rank_b ? 0 : 1;
    $winner_val = $winner_idx === 0 ? $val_a : $val_b;
    $loser_val  = $winner_idx === 0 ? $val_b : $val_a;

    $label      = $spec['label'] ?? $spec_key;
    $diff_format = $spec['diffFormat'] ?? '';

    // Build text based on format.
    // Handle "none" gracefully (e.g., "no suspension" instead of "none suspension").
    $loser_display = strtolower( $loser_val );
    $loser_is_none = $loser_display === 'none';

    switch ( $diff_format ) {
        case 'suspension':
            $text = $loser_is_none
                ? ucfirst( $winner_val ) . ' vs no suspension'
                : ucfirst( $winner_val ) . ' vs ' . $loser_display . ' suspension';
            break;
        case 'tire_type':
            $text = ucfirst( $winner_val ) . ' vs ' . $loser_display . ' tires';
            break;
        default:
            $text = ucfirst( $winner_val ) . ' vs ' . $loser_display . ' ' . $label;
    }

    return [
        'text'       => $text,
        'comparison' => null,
        'winner'     => $winner_idx,
        'spec_key'   => $spec_key,
        'winner_val' => $winner_val,
        'loser_val'  => $loser_val,
        'tooltip'    => $spec['tooltip'] ?? null,
    ];
}

/**
 * Format a spec advantage in Versus.com style.
 *
 * @param string $spec_key   Spec key.
 * @param array  $spec       Spec configuration.
 * @param float  $val_a      Product A value.
 * @param float  $val_b      Product B value.
 * @param int    $winner_idx Winner index (0 or 1).
 * @return array|null Advantage data.
 */
function erh_format_spec_advantage( string $spec_key, array $spec, float $val_a, float $val_b, int $winner_idx ): ?array {
    $winner_val = $winner_idx === 0 ? $val_a : $val_b;
    $loser_val  = $winner_idx === 0 ? $val_b : $val_a;
    $diff       = abs( $winner_val - $loser_val );

    $label       = $spec['label'] ?? $spec_key;
    $unit        = $spec['unit'] ?? '';
    $diff_format = $spec['diffFormat'] ?? 'more';
    $tooltip     = $spec['tooltip'] ?? null;

    // Format values.
    $diff_fmt   = erh_format_spec_number( $diff );
    $winner_fmt = erh_format_spec_number( $winner_val );
    $loser_fmt  = erh_format_spec_number( $loser_val );

    // Build the text based on diffFormat.
    switch ( $diff_format ) {
        case 'faster':
            $text = "{$diff_fmt} {$unit} faster {$label}";
            break;
        case 'more':
            $text = "{$diff_fmt}{$unit} more {$label}";
            break;
        case 'higher':
            $text = "{$diff_fmt}{$unit} higher {$label}";
            break;
        case 'larger':
            $text = "{$diff_fmt}{$unit} larger {$label}";
            break;
        case 'lighter':
            $text = "{$diff_fmt} {$unit} lighter";
            break;
        case 'longer':
            $text = "{$diff_fmt} {$unit} longer {$label}";
            break;
        case 'shorter':
            $text = "{$diff_fmt} {$unit} shorter {$label}";
            break;
        case 'foldable_bars':
            // Binary comparison - winner has foldable handlebars, loser doesn't.
            $text = "foldable {$label}";
            break;
        case 'has_feature':
            // Binary comparison - winner has the feature, loser doesn't.
            $text = "{$label}";
            break;
        case 'lower':
            // For maintenance - "lower maintenance tires" or similar.
            $text = "lower maintenance {$label}";
            break;
        case 'water_resistance':
            // For IP rating - "higher water resistance".
            $text = "higher {$label}";
            break;
        case 'safer_tires':
            // For safety tire type - "safer tires".
            $text = 'safer tires';
            break;
        case 'larger_tires':
            // For safety tire size - "larger tires".
            $text = 'larger tires';
            break;
        case 'better':
            // Generic "better X" format.
            $text = "better {$label}";
            break;
        default:
            $text = "{$diff_fmt}{$unit} {$diff_format} {$label}";
    }

    // Clean up double spaces.
    $text = preg_replace( '/\s+/', ' ', trim( $text ) );

    // Build comparison string: "23.6 mph vs 18.9 mph"
    // Binary specs show "yes vs no" instead of "1 vs 0".
    if ( in_array( $diff_format, [ 'foldable_bars', 'has_feature' ], true ) ) {
        $comparison = 'yes vs no';
    } else {
        $unit_str   = $unit ? " {$unit}" : '';
        $comparison = "{$winner_fmt}{$unit_str} vs {$loser_fmt}{$unit_str}";
    }

    return [
        'text'       => $text,
        'comparison' => $comparison,
        'winner'     => $winner_idx,
        'spec_key'   => $spec_key,
        'winner_val' => $winner_val,
        'loser_val'  => $loser_val,
        'diff'       => $diff,
        'tooltip'    => $tooltip,
    ];
}

/**
 * Format advantage for specs with displayFormatter (e.g., suspension).
 *
 * Uses the displayFormatter function to create human-readable output
 * from raw values (which may be arrays or complex types).
 *
 * @param string $spec_key   Spec key.
 * @param array  $spec       Spec configuration (must have displayFormatter).
 * @param mixed  $raw_a      Product A raw value.
 * @param mixed  $raw_b      Product B raw value.
 * @param int    $winner_idx Winner index (0 or 1).
 * @return array|null Advantage data.
 */
function erh_format_display_formatter_advantage( string $spec_key, array $spec, $raw_a, $raw_b, int $winner_idx ): ?array {
    $formatter = $spec['displayFormatter'] ?? null;
    if ( ! $formatter || ! function_exists( $formatter ) ) {
        return null;
    }

    $label      = $spec['label'] ?? $spec_key;
    $tooltip    = $spec['tooltip'] ?? null;
    $diff_format = $spec['diffFormat'] ?? 'better';

    // Format both values using the display formatter.
    $display_a = $formatter( $raw_a );
    $display_b = $formatter( $raw_b );

    $winner_display = $winner_idx === 0 ? $display_a : $display_b;
    $loser_display  = $winner_idx === 0 ? $display_b : $display_a;

    // Handle water_resistance format: "Higher water resistance" + "IPX5 vs IP44"
    if ( $diff_format === 'water_resistance' ) {
        return [
            'text'       => 'Higher ' . $label,
            'comparison' => strtoupper( $winner_display ) . ' vs ' . strtoupper( $loser_display ),
            'winner'     => $winner_idx,
            'spec_key'   => $spec_key,
            'winner_val' => $winner_display,
            'loser_val'  => $loser_display,
            'tooltip'    => $tooltip,
        ];
    }

    // Build headline and comparison.
    // Handle "none" gracefully (e.g., "no suspension" instead of "none suspension").
    $loser_lower   = strtolower( $loser_display );
    $loser_is_none = $loser_lower === 'none';

    // Build comparison line: "Front hydraulic + rear spring vs front hydraulic"
    $comparison = $loser_is_none
        ? ucfirst( $winner_display ) . ' vs none'
        : ucfirst( $winner_display ) . ' vs ' . $loser_lower;

    // Build headline based on diffFormat.
    switch ( $diff_format ) {
        case 'suspension':
            $text = 'Better ' . $label; // "Better suspension"
            break;
        default:
            $text = 'Better ' . $label;
    }

    return [
        'text'       => $text,
        'comparison' => $comparison,
        'winner'     => $winner_idx,
        'spec_key'   => $spec_key,
        'winner_val' => $winner_display,
        'loser_val'  => $loser_display,
        'tooltip'    => $tooltip,
    ];
}

/**
 * Format motor count advantage (dual vs single).
 *
 * @param mixed $val_a Product A motor count.
 * @param mixed $val_b Product B motor count.
 * @param array $spec  Spec configuration.
 * @return array|null Advantage data or null.
 */
function erh_format_motor_count_advantage( $val_a, $val_b, array $spec ): ?array {
    $count_a = is_numeric( $val_a ) ? (int) $val_a : 1;
    $count_b = is_numeric( $val_b ) ? (int) $val_b : 1;

    // Only show if one has dual and other has single.
    if ( $count_a === $count_b ) {
        return null;
    }

    $winner_idx   = $count_a > $count_b ? 0 : 1;
    $winner_count = $winner_idx === 0 ? $count_a : $count_b;
    $loser_count  = $winner_idx === 0 ? $count_b : $count_a;

    $winner_label = $winner_count > 1 ? 'Dual motor' : 'Single motor';
    $loser_label  = $loser_count > 1 ? 'dual motor' : 'single motor';

    return [
        'text'       => "{$winner_label} vs {$loser_label}",
        'comparison' => null, // No separate comparison line for this one
        'winner'     => $winner_idx,
        'spec_key'   => 'motor.motor_count',
        'winner_val' => $winner_count,
        'loser_val'  => $loser_count,
        'diff'       => abs( $count_a - $count_b ),
        'tooltip'    => $spec['tooltip'] ?? null,
    ];
}

/**
 * Format feature count advantage.
 *
 * Compares features arrays and returns advantage if one product has
 * significantly more features (minDiff, default 2).
 *
 * @param mixed $features_a Product A features array.
 * @param mixed $features_b Product B features array.
 * @param array $spec       Spec configuration.
 * @return array|null Advantage data or null if difference < minDiff.
 */
function erh_format_feature_count_advantage( $features_a, $features_b, array $spec ): ?array {
    $arr_a = is_array( $features_a ) ? $features_a : [];
    $arr_b = is_array( $features_b ) ? $features_b : [];

    $count_a = count( $arr_a );
    $count_b = count( $arr_b );

    // Check minimum difference threshold (default 2).
    $min_diff = $spec['minDiff'] ?? 2;
    $diff     = abs( $count_a - $count_b );

    if ( $diff < $min_diff ) {
        return null;
    }

    // Determine winner (more features).
    $winner_idx      = $count_a > $count_b ? 0 : 1;
    $winner_features = $winner_idx === 0 ? $arr_a : $arr_b;
    $loser_features  = $winner_idx === 0 ? $arr_b : $arr_a;
    $winner_count    = $winner_idx === 0 ? $count_a : $count_b;
    $loser_count     = $winner_idx === 0 ? $count_b : $count_a;

    // Find features the winner has that the loser doesn't.
    $loser_lower = array_map( 'strtolower', $loser_features );
    $unique_features = array_filter( $winner_features, function( $feature ) use ( $loser_lower ) {
        return ! in_array( strtolower( $feature ), $loser_lower, true );
    } );

    // Build comparison string: list unique features (max 4).
    $unique_list = array_values( $unique_features );
    if ( count( $unique_list ) > 4 ) {
        $comparison = implode( ', ', array_slice( $unique_list, 0, 4 ) ) . ' +' . ( count( $unique_list ) - 4 ) . ' more';
    } else {
        $comparison = implode( ', ', $unique_list );
    }

    return [
        'text'       => 'More features',
        'comparison' => $comparison ?: "{$winner_count} vs {$loser_count}",
        'winner'     => $winner_idx,
        'spec_key'   => 'features',
        'winner_val' => $winner_count,
        'loser_val'  => $loser_count,
        'diff'       => $diff,
        'tooltip'    => $spec['tooltip'] ?? null,
    ];
}

/**
 * Calculate advantages for each product in a comparison.
 *
 * @deprecated Use erh_calculate_spec_advantages() for spec-based format.
 *
 * Determines which categories each product leads in based on scores.
 * Returns detailed advantage data with concrete spec comparisons.
 *
 * @param array[] $products   Products from erh_get_compare_products().
 * @param array   $categories Category score keys to label mapping.
 * @return array[] Array of advantages per product index.
 */
function erh_calculate_product_advantages( array $products, array $categories ): array {
    $advantages = array_fill( 0, count( $products ), array() );
    $threshold  = \ERH\Config\SpecConfig::ADVANTAGE_THRESHOLD;
    $max_adv    = \ERH\Config\SpecConfig::MAX_ADVANTAGES;

    foreach ( $categories as $key => $label ) {
        // Get scores for this category from all products.
        $scores = array();
        foreach ( $products as $idx => $product ) {
            $scores[ $idx ] = $product['specs']['scores'][ $key ] ?? 0;
        }

        // Find the best score.
        if ( empty( $scores ) ) {
            continue;
        }
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
            // Get detailed advantage data for 2-product comparisons.
            $details = null;
            if ( count( $products ) === 2 ) {
                $loser_idx = $winner_idx === 0 ? 1 : 0;
                $details   = erh_get_advantage_details(
                    $key,
                    $products[ $winner_idx ],
                    $products[ $loser_idx ]
                );
            }

            $advantages[ $winner_idx ][] = array(
                'label'      => $label,
                'diff'       => $diff,
                'direction'  => 'higher',
                'category'   => $key,
                'details'    => $details,
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
 * Get detailed advantage data for a category comparison.
 *
 * Returns concrete spec comparisons instead of abstract percentages.
 * For 2-product comparisons only.
 *
 * New format (specOrder): Priority-based comma-separated list.
 * Legacy format (primary/secondary): Headline + stacked primary/supporting.
 *
 * @param string $category_key Score category key (e.g., 'motor_performance').
 * @param array  $winner       Winning product data.
 * @param array  $loser        Losing product data.
 * @return array|null Detailed advantage data or null if no concrete data.
 */
function erh_get_advantage_details( string $category_key, array $winner, array $loser ): ?array {
    $adv_specs = \ERH\Config\SpecConfig::ADVANTAGE_SPECS[ $category_key ] ?? null;
    if ( ! $adv_specs ) {
        return null;
    }

    $headlines    = $adv_specs['headlines'] ?? [];
    $lower_better = $adv_specs['lowerBetter'] ?? [];
    $units        = \ERH\Config\SpecConfig::ADVANTAGE_UNITS;

    // Check if category uses composite approach (consolidated advantage).
    if ( ! empty( $adv_specs['composite'] ) ) {
        return erh_get_composite_advantage_details(
            $adv_specs,
            $winner,
            $loser,
            $headlines
        );
    }

    // Check if using new specOrder format (priority-based).
    if ( ! empty( $adv_specs['specOrder'] ) ) {
        return erh_get_priority_advantage_details(
            $adv_specs,
            $winner,
            $loser,
            $headlines,
            $lower_better,
            $units
        );
    }

    // Legacy format: primary + secondary.
    $primary_key = $adv_specs['primary'] ?? null;
    $secondary   = $adv_specs['secondary'] ?? [];

    // Get primary spec values.
    $winner_primary = erh_get_nested_spec( $winner['specs'], $primary_key );
    $loser_primary  = erh_get_nested_spec( $loser['specs'], $primary_key );

    // Format primary comparison.
    $primary_comparison = erh_format_advantage_comparison(
        $primary_key,
        $winner_primary,
        $loser_primary,
        $units[ $primary_key ] ?? '',
        in_array( $primary_key, $lower_better, true )
    );

    // Determine headline.
    $headline = $headlines[ $primary_key ] ?? ucfirst( str_replace( '_', ' ', $category_key ) );

    // Check secondary specs for notable differences.
    $supporting = null;
    foreach ( $secondary as $sec_key ) {
        $winner_sec = erh_get_nested_spec( $winner['specs'], $sec_key );
        $loser_sec  = erh_get_nested_spec( $loser['specs'], $sec_key );

        $sec_comparison = erh_format_supporting_comparison(
            $sec_key,
            $winner_sec,
            $loser_sec,
            $units[ $sec_key ] ?? '',
            $headlines[ $sec_key ] ?? null,
            in_array( $sec_key, $lower_better, true )
        );

        if ( $sec_comparison ) {
            $supporting = $sec_comparison;
            break; // Only show one supporting detail.
        }
    }

    return array(
        'headline'   => $headline,
        'primary'    => $primary_comparison,
        'supporting' => $supporting,
    );
}

/**
 * Get composite advantage details (consolidated headline + supporting specs).
 *
 * For categories like "safety" that consolidate multiple specs into one
 * headline (e.g., "Safer" with "pneumatic vs solid tires, larger 10.5" vs 8.5", shorter stopping").
 *
 * @param array $adv_specs  Category advantage config (must have 'composite' key).
 * @param array $winner     Winning product data.
 * @param array $loser      Losing product data.
 * @param array $headlines  Headline mapping.
 * @return array|null Composite advantage data or null.
 */
function erh_get_composite_advantage_details( array $adv_specs, array $winner, array $loser, array $headlines ): ?array {
    $composite_key = $adv_specs['composite'];
    $max_items     = $adv_specs['maxItems'] ?? 4;

    // Get the composite spec config from COMPARISON_SPECS.
    $composite_spec = \ERH\Config\SpecConfig::COMPARISON_SPECS[ $composite_key ] ?? null;
    if ( ! $composite_spec ) {
        return null;
    }

    // Use specs from composite definition, NOT the full specOrder.
    // This keeps features like turn_signals separate from the consolidated message.
    $composite_specs = $composite_spec['specs'] ?? [];

    // Get headline from composite or fallback.
    $headline = $headlines[ $composite_key ] ?? ucfirst( $composite_spec['label'] ?? 'Better' );

    // Collect comparison details from individual specs.
    $comparisons = [];

    foreach ( $composite_specs as $spec_key ) {
        if ( count( $comparisons ) >= $max_items ) {
            break;
        }

        $spec_config = \ERH\Config\SpecConfig::COMPARISON_SPECS[ $spec_key ] ?? null;
        if ( ! $spec_config ) {
            continue;
        }

        // Get normalized values for comparison.
        $val_winner = erh_get_normalized_spec_value( $winner['specs'], $spec_key, $spec_config );
        $val_loser  = erh_get_normalized_spec_value( $loser['specs'], $spec_key, $spec_config );

        // Skip if either value is missing.
        if ( $val_winner === null || $val_winner === '' || $val_loser === null || $val_loser === '' ) {
            continue;
        }

        // Determine who wins this spec.
        $spec_winner = erh_compare_spec_values( $val_winner, $val_loser, $spec_config );

        // Only include specs where the category winner (product 0) wins.
        if ( $spec_winner !== 0 ) {
            continue;
        }

        // For specs with displayFormatter (like suspension), pass raw values for nice display.
        if ( ! empty( $spec_config['displayFormatter'] ) ) {
            $raw_winner = erh_get_nested_spec( $winner['specs'], $spec_key );
            $raw_loser  = erh_get_nested_spec( $loser['specs'], $spec_key );
            $detail = erh_format_composite_detail( $spec_key, $spec_config, $raw_winner, $raw_loser, 0 );
        } else {
            $detail = erh_format_composite_detail( $spec_key, $spec_config, $val_winner, $val_loser, 0 );
        }
        if ( $detail ) {
            $comparisons[] = $detail;
        }
    }

    if ( empty( $comparisons ) ) {
        return null;
    }

    // Capitalize first letter of the comparison line.
    $comparison_line = implode( ', ', $comparisons );
    $comparison_line = ucfirst( $comparison_line );

    return [
        'headline'   => $headline,
        'primary'    => $comparison_line,
        'supporting' => null,
    ];
}

/**
 * Get advantage details using priority-ordered spec list.
 *
 * Iterates through specs in priority order, only including specs where
 * winner is actually better. Builds comma-separated comparison string.
 * Selects headline based on first winning spec.
 *
 * @param array $adv_specs    Category advantage config.
 * @param array $winner       Winning product data.
 * @param array $loser        Losing product data.
 * @param array $headlines    Headline mapping per spec.
 * @param array $lower_better Specs where lower is better.
 * @param array $units        Unit mapping per spec.
 * @return array|null Advantage details or null.
 */
function erh_get_priority_advantage_details(
    array $adv_specs,
    array $winner,
    array $loser,
    array $headlines,
    array $lower_better,
    array $units
): ?array {
    $spec_order  = $adv_specs['specOrder'];
    $max_items   = $adv_specs['maxItems'] ?? 3;
    $comparisons = [];
    $headline    = null;
    $percent     = null;

    foreach ( $spec_order as $spec_key ) {
        // Limit items.
        if ( count( $comparisons ) >= $max_items ) {
            break;
        }

        $winner_val = erh_get_nested_spec( $winner['specs'], $spec_key );
        $loser_val  = erh_get_nested_spec( $loser['specs'], $spec_key );

        // Skip if winner doesn't have value.
        if ( $winner_val === null || $winner_val === '' ) {
            continue;
        }

        // Handle motor_count specially.
        if ( $spec_key === 'motor.motor_count' ) {
            $comparison = erh_format_motor_count_comparison( $winner_val, $loser_val );
            if ( $comparison ) {
                $comparisons[] = $comparison;
                if ( ! $headline ) {
                    $headline = $headlines[ $spec_key ] ?? 'Dual Motors';
                }
            }
            continue;
        }

        // Handle voltage (only show if winner has higher voltage).
        if ( $spec_key === 'battery.voltage' ) {
            // Skip if loser value is missing or same.
            if ( empty( $loser_val ) || $winner_val <= $loser_val ) {
                continue;
            }
            $comparison = erh_format_single_spec_comparison( $spec_key, $winner_val, $loser_val, $units[ $spec_key ] ?? 'V', false );
            if ( $comparison ) {
                $comparisons[] = $comparison;
                if ( ! $headline ) {
                    $headline = $headlines[ $spec_key ] ?? 'Higher Voltage';
                }
            }
            continue;
        }

        // Handle foldable handlebars (boolean: winner has them, loser doesn't).
        if ( $spec_key === 'dimensions.foldable_handlebars' ) {
            // Only show if winner has foldable bars and loser doesn't.
            $winner_has = ! empty( $winner_val ) && $winner_val !== 'no' && $winner_val !== 'No';
            $loser_has  = ! empty( $loser_val ) && $loser_val !== 'no' && $loser_val !== 'No';
            if ( $winner_has && ! $loser_has ) {
                $comparisons[] = 'foldable handlebars';
                if ( ! $headline ) {
                    $headline = $headlines[ $spec_key ] ?? 'Foldable Handlebars';
                }
            }
            continue;
        }

        // Handle turn signals (boolean: winner has them, loser doesn't).
        if ( $spec_key === 'lighting.turn_signals' ) {
            $winner_has = ! empty( $winner_val ) && $winner_val !== 'no' && $winner_val !== 'No';
            $loser_has  = ! empty( $loser_val ) && $loser_val !== 'no' && $loser_val !== 'No';
            if ( $winner_has && ! $loser_has ) {
                $comparisons[] = 'turn signals';
                if ( ! $headline ) {
                    $headline = $headlines[ $spec_key ] ?? 'Turn Signals';
                }
            }
            continue;
        }

        // Handle IP rating (compare normalized scores, display raw values).
        if ( $spec_key === 'other.ip_rating' ) {
            if ( empty( $winner_val ) || empty( $loser_val ) ) {
                continue;
            }
            $winner_score = erh_normalize_ip_rating( $winner_val );
            $loser_score  = erh_normalize_ip_rating( $loser_val );
            // Only show if winner has better IP rating.
            if ( $winner_score > $loser_score ) {
                $comparisons[] = "{$winner_val} vs {$loser_val} water resistance";
                if ( ! $headline ) {
                    $headline = $headlines[ $spec_key ] ?? 'Better Protected';
                }
            }
            continue;
        }

        // Handle display type (categorical ranking comparison).
        if ( $spec_key === 'other.display_type' ) {
            if ( empty( $winner_val ) ) {
                continue;
            }
            // Ranking: None < Basic LED < LED < LCD < Color LCD.
            $ranking = [ 'none' => 0, 'basic led' => 1, 'led' => 2, 'lcd' => 3, 'color lcd' => 4 ];
            $winner_rank = $ranking[ strtolower( $winner_val ) ] ?? 0;
            $loser_rank  = $ranking[ strtolower( $loser_val ?? '' ) ] ?? 0;
            // Only show if winner has better display.
            if ( $winner_rank > $loser_rank ) {
                $loser_display = ! empty( $loser_val ) ? $loser_val : 'none';
                $comparisons[] = "{$winner_val} vs {$loser_display} display";
                if ( ! $headline ) {
                    $headline = $headlines[ $spec_key ] ?? 'Better Display';
                }
            }
            continue;
        }

        // Handle kickstand (boolean: winner has it, loser doesn't).
        if ( $spec_key === 'other.kickstand' ) {
            $winner_has = ! empty( $winner_val ) && $winner_val !== 'no' && $winner_val !== 'No';
            $loser_has  = ! empty( $loser_val ) && $loser_val !== 'no' && $loser_val !== 'No';
            if ( $winner_has && ! $loser_has ) {
                $comparisons[] = 'kickstand';
                if ( ! $headline ) {
                    $headline = $headlines[ $spec_key ] ?? 'Kickstand';
                }
            }
            continue;
        }

        // Handle maintenance tire type (ranking: solid > tubeless > pneumatic for maintenance).
        if ( $spec_key === 'maintenance.tire_type' ) {
            // Get tire type from winner and loser specs.
            $winner_tire = erh_normalize_tire_type( $winner['specs'] );
            $loser_tire  = erh_normalize_tire_type( $loser['specs'] );
            // Maintenance ranking based on scoring: Solid (45) > Mixed (30) > Tubeless (25) > Self-healing (20) > Pneumatic (15).
            $ranking = [ 'Pneumatic' => 0, 'Self-healing' => 1, 'Tubeless' => 2, 'Mixed' => 3, 'Solid' => 4 ];
            $winner_rank = $ranking[ $winner_tire ] ?? 0;
            $loser_rank  = $ranking[ $loser_tire ] ?? 0;
            // Only show if winner has better maintenance tire type.
            if ( $winner_rank > $loser_rank ) {
                $comparisons[] = strtolower( $winner_tire ) . ' vs ' . strtolower( $loser_tire ) . ' tires';
                if ( ! $headline ) {
                    $headline = $headlines[ $spec_key ] ?? 'Easier Maintenance';
                }
            }
            continue;
        }

        // Handle self-healing tires (boolean: winner has them, loser doesn't).
        if ( $spec_key === 'wheels.self_healing' ) {
            $winner_has = ! empty( $winner_val );
            $loser_has  = ! empty( $loser_val );
            if ( $winner_has && ! $loser_has ) {
                $comparisons[] = 'self-healing tires';
                if ( ! $headline ) {
                    $headline = $headlines[ $spec_key ] ?? 'Self-Healing Tires';
                }
            }
            continue;
        }

        // Handle maintenance brake type (ranking: drum > electronic > foot > disc for maintenance).
        if ( $spec_key === 'maintenance.brake_type' ) {
            // Get brake type from winner and loser specs.
            $winner_brake = erh_normalize_brake_maintenance( $winner['specs'] );
            $loser_brake  = erh_normalize_brake_maintenance( $loser['specs'] );
            // Maintenance ranking: Drum > Electronic > Foot > Disc.
            $ranking = [ 'Disc' => 0, 'Foot' => 1, 'Electronic' => 2, 'Drum' => 3 ];
            $winner_rank = $ranking[ $winner_brake ] ?? 0;
            $loser_rank  = $ranking[ $loser_brake ] ?? 0;
            // Only show if winner has better maintenance brakes AND they're different.
            if ( $winner_rank > $loser_rank ) {
                $comparisons[] = strtolower( $winner_brake ) . ' vs ' . strtolower( $loser_brake ) . ' brakes';
                if ( ! $headline ) {
                    $headline = $headlines[ $spec_key ] ?? 'Lower Maintenance Brakes';
                }
            }
            continue;
        }

        // Handle safety tire type (ranking: pneumatic > mixed > solid for SAFETY).
        // NOTE: This is OPPOSITE of maintenance ranking - pneumatic = safest, solid = most dangerous.
        if ( $spec_key === 'safety.tire_type' ) {
            $winner_tire = erh_normalize_tire_type_for_safety( $winner['specs'] );
            $loser_tire  = erh_normalize_tire_type_for_safety( $loser['specs'] );
            // Safety ranking: Pneumatic > Mixed > Solid (pneumatic = best grip, solid = worst).
            $ranking = [ 'Solid' => 0, 'Mixed' => 1, 'Pneumatic' => 2 ];
            $winner_rank = $ranking[ $winner_tire ] ?? 0;
            $loser_rank  = $ranking[ $loser_tire ] ?? 0;
            // Only show if winner has safer tires AND they're different.
            if ( $winner_rank > $loser_rank && $winner_tire !== $loser_tire ) {
                $comparisons[] = strtolower( $winner_tire ) . ' vs ' . strtolower( $loser_tire ) . ' tires';
                if ( ! $headline ) {
                    $headline = $headlines[ $spec_key ] ?? 'Safer Tires';
                }
            }
            continue;
        }

        // Handle safety tire size (larger tires = safer).
        if ( $spec_key === 'wheels.tire_size_safety' ) {
            $winner_size = erh_get_avg_tire_size( $winner['specs'] );
            $loser_size  = erh_get_avg_tire_size( $loser['specs'] );
            // Skip if either has no tire size data.
            if ( $winner_size === null || $loser_size === null ) {
                continue;
            }
            // Only show if winner has larger tires by at least 0.5".
            $diff = $winner_size - $loser_size;
            if ( $diff >= 0.5 ) {
                $winner_fmt = number_format( $winner_size, 1 );
                $loser_fmt  = number_format( $loser_size, 1 );
                $comparisons[] = "{$winner_fmt}\" vs {$loser_fmt}\" tires";
                if ( ! $headline ) {
                    $headline = $headlines[ $spec_key ] ?? 'Larger Tires';
                }
            }
            continue;
        }

        // Handle brake distance (only show if both have tested data).
        if ( $spec_key === 'brake_distance' ) {
            $winner_brake_dist = erh_get_nested_spec( $winner['specs'], 'brake_distance' );
            $loser_brake_dist  = erh_get_nested_spec( $loser['specs'], 'brake_distance' );
            // Skip if either is missing - only compare when both have tested brake data.
            if ( empty( $winner_brake_dist ) || empty( $loser_brake_dist ) ) {
                continue;
            }
            // Shorter is better for brake distance.
            if ( (float) $winner_brake_dist < (float) $loser_brake_dist ) {
                $diff = (float) $loser_brake_dist - (float) $winner_brake_dist;
                $diff_fmt = number_format( $diff, 1 );
                $comparisons[] = "{$diff_fmt} ft shorter stopping distance";
                if ( ! $headline ) {
                    $headline = $headlines[ $spec_key ] ?? 'Shorter Stopping';
                }
            }
            continue;
        }

        // Skip manufacturer_top_speed if we already have tested_top_speed (don't show both).
        if ( $spec_key === 'manufacturer_top_speed' ) {
            $has_tested_speed = false;
            foreach ( $comparisons as $c ) {
                if ( strpos( $c, 'mph' ) !== false ) {
                    $has_tested_speed = true;
                    break;
                }
            }
            if ( $has_tested_speed ) {
                continue;
            }
        }

        // Skip range_claimed if we already have tested_range_regular (don't show both).
        if ( $spec_key === 'range_claimed' ) {
            $has_tested_range = false;
            foreach ( $comparisons as $c ) {
                if ( strpos( $c, ' mi' ) !== false ) {
                    $has_tested_range = true;
                    break;
                }
            }
            if ( $has_tested_range ) {
                continue;
            }
        }

        // Numeric comparison: only include if winner is actually better.
        $is_lower_better = in_array( $spec_key, $lower_better, true );
        $winner_is_better = erh_winner_is_better( $winner_val, $loser_val, $is_lower_better );

        if ( ! $winner_is_better ) {
            continue; // Skip: winner has worse value for this spec.
        }

        // Format the comparison.
        $comparison = erh_format_single_spec_comparison(
            $spec_key,
            $winner_val,
            $loser_val,
            $units[ $spec_key ] ?? '',
            $is_lower_better
        );

        if ( $comparison ) {
            $comparisons[] = $comparison;

            // Calculate percentage for headline (first spec only).
            if ( ! $headline ) {
                $headline = $headlines[ $spec_key ] ?? ucfirst( str_replace( [ '_', '.' ], ' ', $spec_key ) );
                $percent  = erh_calculate_spec_percent( $winner_val, $loser_val, $is_lower_better );
            }
        }
    }

    if ( empty( $comparisons ) ) {
        return null;
    }

    // Build headline with percentage (e.g., "14% Faster").
    $headline_with_pct = $headline;
    if ( $percent !== null && $percent >= 3 ) {
        $headline_with_pct = round( $percent ) . '% ' . $headline;
    }

    return array(
        'headline'   => $headline_with_pct,
        'primary'    => implode( ', ', $comparisons ),
        'supporting' => null, // Not used in specOrder format.
    );
}

/**
 * Format motor count comparison (dual vs single).
 *
 * @param mixed $winner_val Winner's motor count.
 * @param mixed $loser_val  Loser's motor count.
 * @return string|null Formatted comparison or null if no advantage.
 */
function erh_format_motor_count_comparison( $winner_val, $loser_val ): ?string {
    $winner_count = is_numeric( $winner_val ) ? (int) $winner_val : 1;
    $loser_count  = is_numeric( $loser_val ) ? (int) $loser_val : 1;

    // Only show if winner has more motors.
    if ( $winner_count <= $loser_count ) {
        return null;
    }

    $winner_label = $winner_count > 1 ? 'dual motor' : 'single motor';
    $loser_label  = $loser_count > 1 ? 'dual motor' : 'single motor';

    return "{$winner_label} vs {$loser_label}";
}

/**
 * Check if winner value is actually better than loser value.
 *
 * @param mixed $winner_val    Winner's value.
 * @param mixed $loser_val     Loser's value.
 * @param bool  $lower_better  Whether lower is better.
 * @return bool True if winner is genuinely better.
 */
function erh_winner_is_better( $winner_val, $loser_val, bool $lower_better ): bool {
    // Skip if loser value is missing.
    if ( $loser_val === null || $loser_val === '' ) {
        return true; // Winner has value, loser doesn't.
    }

    // Non-numeric comparison.
    if ( ! is_numeric( $winner_val ) || ! is_numeric( $loser_val ) ) {
        return true; // Can't determine, assume winner is better.
    }

    $w = (float) $winner_val;
    $l = (float) $loser_val;

    if ( $lower_better ) {
        return $w < $l; // Winner should be lower.
    }
    return $w > $l; // Winner should be higher.
}

/**
 * Format single spec comparison string.
 *
 * @param string $spec_key     Spec key.
 * @param mixed  $winner_val   Winner's value.
 * @param mixed  $loser_val    Loser's value.
 * @param string $unit         Unit string.
 * @param bool   $lower_better Whether lower is better.
 * @return string|null Formatted comparison (e.g., "23 mph vs 20 mph").
 */
function erh_format_single_spec_comparison(
    string $spec_key,
    $winner_val,
    $loser_val,
    string $unit,
    bool $lower_better
): ?string {
    // Non-numeric values.
    if ( ! is_numeric( $winner_val ) ) {
        if ( $winner_val && $loser_val && $winner_val !== $loser_val ) {
            return "{$winner_val} vs {$loser_val}";
        }
        return null;
    }

    // Both numeric.
    $w = (float) $winner_val;
    $l = (float) $loser_val;

    // Skip if exactly the same value.
    if ( abs( $w - $l ) < 0.01 ) {
        return null;
    }

    $w_fmt    = erh_format_spec_number( $w );
    $l_fmt    = is_numeric( $loser_val ) ? erh_format_spec_number( $l ) : '—';
    $unit_str = $unit ? ' ' . $unit : '';

    // Handle loser having no value.
    if ( $loser_val === null || $loser_val === '' || ! is_numeric( $loser_val ) ) {
        return "{$w_fmt}{$unit_str}";
    }

    return "{$w_fmt}{$unit_str} vs {$l_fmt}{$unit_str}";
}

/**
 * Calculate percentage difference for headline.
 *
 * @param mixed $winner_val   Winner's value.
 * @param mixed $loser_val    Loser's value.
 * @param bool  $lower_better Whether lower is better.
 * @return float|null Percentage difference or null.
 */
function erh_calculate_spec_percent( $winner_val, $loser_val, bool $lower_better ): ?float {
    if ( ! is_numeric( $winner_val ) || ! is_numeric( $loser_val ) ) {
        return null;
    }

    $w = (float) $winner_val;
    $l = (float) $loser_val;

    if ( $l <= 0 ) {
        return null;
    }

    if ( $lower_better ) {
        return ( ( $l - $w ) / $l ) * 100; // % less.
    }
    return ( ( $w - $l ) / $l ) * 100; // % more.
}

/**
 * Format primary spec comparison for advantage display.
 *
 * @param string     $key         Spec key.
 * @param mixed      $winner_val  Winner's value.
 * @param mixed      $loser_val   Loser's value.
 * @param string     $unit        Unit string.
 * @param bool       $lower_better Whether lower is better.
 * @return string|null Formatted comparison or null.
 */
function erh_format_advantage_comparison( string $key, $winner_val, $loser_val, string $unit = '', bool $lower_better = false ): ?string {
    // Handle arrays (suspension types, features).
    if ( is_array( $winner_val ) || is_array( $loser_val ) ) {
        return erh_format_array_comparison( $key, $winner_val, $loser_val );
    }

    // Handle non-numeric values.
    if ( ! is_numeric( $winner_val ) || ! is_numeric( $loser_val ) ) {
        // IP ratings, tire types, etc.
        if ( $winner_val && $loser_val && $winner_val !== $loser_val ) {
            return sprintf( '%s vs %s', $winner_val, $loser_val );
        }
        if ( $winner_val && ! $loser_val ) {
            return (string) $winner_val;
        }
        return null;
    }

    // Both values are numeric.
    $w = (float) $winner_val;
    $l = (float) $loser_val;

    // Skip if values are essentially the same (<3% diff).
    if ( $l > 0 ) {
        $diff_pct = abs( $w - $l ) / $l * 100;
        if ( $diff_pct < 3 ) {
            return null;
        }
    }

    // Format with appropriate precision.
    $w_fmt = erh_format_spec_number( $w );
    $l_fmt = erh_format_spec_number( $l );

    // Build comparison string: "23.6 mph vs 22 mph".
    $unit_str = $unit ? ' ' . $unit : '';
    return sprintf( '%s%s vs %s%s', $w_fmt, $unit_str, $l_fmt, $unit_str );
}

/**
 * Format supporting comparison for advantage display.
 *
 * Only returns something if there's a notable difference (>15%).
 *
 * @param string      $key          Spec key.
 * @param mixed       $winner_val   Winner's value.
 * @param mixed       $loser_val    Loser's value.
 * @param string      $unit         Unit string.
 * @param string|null $headline     Optional headline override.
 * @param bool        $lower_better Whether lower is better.
 * @return string|null Formatted supporting text or null.
 */
function erh_format_supporting_comparison( string $key, $winner_val, $loser_val, string $unit = '', ?string $headline = null, bool $lower_better = false ): ?string {
    // Handle arrays.
    if ( is_array( $winner_val ) || is_array( $loser_val ) ) {
        $comparison = erh_format_array_comparison( $key, $winner_val, $loser_val );
        return $comparison ? '+ ' . $comparison : null;
    }

    // Handle booleans (e.g., turn_signals, self_healing).
    if ( is_bool( $winner_val ) && $winner_val === true && ( ! $loser_val || $loser_val === false ) ) {
        return $headline ? '+ ' . $headline : null;
    }

    // Handle non-numeric.
    if ( ! is_numeric( $winner_val ) || ! is_numeric( $loser_val ) ) {
        if ( $winner_val && $loser_val && $winner_val !== $loser_val ) {
            return sprintf( '+ %s vs %s', $winner_val, $loser_val );
        }
        return null;
    }

    // Numeric comparison - only show if >15% difference.
    $w = (float) $winner_val;
    $l = (float) $loser_val;

    if ( $l <= 0 ) {
        return null;
    }

    $diff_pct = abs( $w - $l ) / $l * 100;
    if ( $diff_pct < 15 ) {
        return null;
    }

    $w_fmt    = erh_format_spec_number( $w );
    $l_fmt    = erh_format_spec_number( $l );
    $unit_str = $unit ? ' ' . $unit : '';

    return sprintf( '+ %s%s vs %s%s', $w_fmt, $unit_str, $l_fmt, $unit_str );
}

/**
 * Format array comparison (suspension types, features).
 *
 * @param string     $key        Spec key.
 * @param mixed      $winner_val Winner's array value.
 * @param mixed      $loser_val  Loser's array value.
 * @return string|null Formatted comparison or null.
 */
function erh_format_array_comparison( string $key, $winner_val, $loser_val ): ?string {
    // Handle suspension type arrays.
    if ( strpos( $key, 'suspension' ) !== false ) {
        $w_str = is_array( $winner_val ) ? implode( ' + ', array_filter( $winner_val ) ) : (string) $winner_val;
        $l_str = is_array( $loser_val ) ? implode( ' + ', array_filter( $loser_val ) ) : (string) $loser_val;

        // Simplify "None" comparisons.
        if ( ! $w_str || strtolower( $w_str ) === 'none' ) {
            return null;
        }
        if ( ! $l_str || strtolower( $l_str ) === 'none' ) {
            $l_str = 'None';
        }

        return sprintf( '%s vs %s', $w_str, $l_str );
    }

    // Handle features array (count comparison).
    if ( $key === 'features' ) {
        $w_count = is_array( $winner_val ) ? count( $winner_val ) : 0;
        $l_count = is_array( $loser_val ) ? count( $loser_val ) : 0;

        if ( $w_count > $l_count && $l_count > 0 ) {
            return sprintf( '%d features vs %d', $w_count, $l_count );
        }
        if ( $w_count > 0 && $l_count === 0 ) {
            return sprintf( '%d features included', $w_count );
        }
        return null;
    }

    return null;
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

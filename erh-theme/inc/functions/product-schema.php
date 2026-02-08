<?php
/**
 * Product Schema Functions
 *
 * Generates Product schema.org markup for single product pages.
 * Hooks into Rank Math to add Product type schema with offers,
 * positiveNotes/negativeNotes, brand, manufacturer, and weight.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get US pricing offers for a product.
 *
 * @param int $product_id The product post ID.
 * @return array Array of offer data for schema.
 */
function erh_get_product_offers_for_schema( int $product_id ): array {
	// Check if HFT plugin is available.
	if ( ! defined( 'HFT_VERSION' ) || ! class_exists( 'ERH\Pricing\PriceFetcher' ) ) {
		return [];
	}

	$fetcher = new \ERH\Pricing\PriceFetcher();
	$prices  = $fetcher->get_prices( $product_id, 'US' );

	if ( empty( $prices ) || ! is_array( $prices ) ) {
		return [];
	}

	$offers = [];

	foreach ( $prices as $price_data ) {
		// Skip if no valid price data.
		if ( ! is_array( $price_data ) || empty( $price_data['price'] ) ) {
			continue;
		}

		$offer = [
			'@type'         => 'Offer',
			'price'         => (float) $price_data['price'],
			'priceCurrency' => $price_data['currency'] ?? 'USD',
			'url'           => esc_url( $price_data['tracked_url'] ?? '' ),
			'availability'  => erh_map_stock_to_schema( $price_data['status'] ?? '' ),
		];

		// Add seller if available.
		if ( ! empty( $price_data['retailer'] ) ) {
			$offer['seller'] = [
				'@type' => 'Organization',
				'name'  => $price_data['retailer'],
			];
		}

		$offers[] = $offer;
	}

	return $offers;
}

/**
 * Map stock status to schema.org ItemAvailability.
 *
 * @param string $status Stock status from HFT.
 * @return string Schema.org ItemAvailability URL.
 */
function erh_map_stock_to_schema( string $status ): string {
	$status = strtolower( $status );

	if ( in_array( $status, [ 'in_stock', 'in stock', 'available' ], true ) ) {
		return 'https://schema.org/InStock';
	}

	if ( in_array( $status, [ 'out_of_stock', 'out of stock', 'unavailable' ], true ) ) {
		return 'https://schema.org/OutOfStock';
	}

	if ( in_array( $status, [ 'preorder', 'pre-order' ], true ) ) {
		return 'https://schema.org/PreOrder';
	}

	// Default to InStock if status is unknown but price exists.
	return 'https://schema.org/InStock';
}

/**
 * Get product analysis (advantages/weaknesses) for schema.
 *
 * @param int    $product_id   The product post ID.
 * @param string $product_type Product type (e.g., 'Electric Scooter').
 * @return array { advantages: array, weaknesses: array }
 */
function erh_get_product_analysis_for_schema( int $product_id, string $product_type ): array {
	$result = [
		'advantages'  => [],
		'weaknesses'  => [],
	];

	// Check if calculator factory is available.
	if ( ! class_exists( 'ERH\Comparison\AdvantageCalculatorFactory' ) ||
		 ! class_exists( 'ERH\Database\ProductCache' ) ) {
		return $result;
	}

	// Check if product type has an advantage calculator.
	if ( ! \ERH\Comparison\AdvantageCalculatorFactory::supports( $product_type ) ) {
		return $result;
	}

	// Get product from cache.
	$cache   = new \ERH\Database\ProductCache();
	$product = $cache->get( $product_id );

	if ( ! $product ) {
		return $result;
	}

	// Get calculator.
	$calculator = \ERH\Comparison\AdvantageCalculatorFactory::get( $product_type );
	if ( ! $calculator ) {
		return $result;
	}

	// Calculate analysis for US geo.
	$analysis = $calculator->calculate_single( $product, 'US' );

	if ( ! $analysis ) {
		return $result;
	}

	// Extract text from advantages/weaknesses.
	if ( ! empty( $analysis['advantages'] ) ) {
		foreach ( $analysis['advantages'] as $adv ) {
			if ( ! empty( $adv['text'] ) ) {
				$result['advantages'][] = $adv['text'];
			} elseif ( ! empty( $adv['label'] ) ) {
				$result['advantages'][] = $adv['label'];
			}
		}
	}

	if ( ! empty( $analysis['weaknesses'] ) ) {
		foreach ( $analysis['weaknesses'] as $weak ) {
			if ( ! empty( $weak['text'] ) ) {
				$result['weaknesses'][] = $weak['text'];
			} elseif ( ! empty( $weak['label'] ) ) {
				$result['weaknesses'][] = $weak['label'];
			}
		}
	}

	return $result;
}

/**
 * Get weight in pounds for a product from ProductCache.
 *
 * Reads from wp_product_data cache table for consistency with analysis data.
 *
 * @param int    $product_id   The product post ID.
 * @param string $product_type Product type.
 * @return float|null Weight in pounds or null if not available.
 */
function erh_get_product_weight_for_schema( int $product_id, string $product_type ): ?float {
	// Check if ProductCache is available.
	if ( ! class_exists( 'ERH\Database\ProductCache' ) ) {
		return null;
	}

	$cache   = new \ERH\Database\ProductCache();
	$product = $cache->get( $product_id );

	if ( ! $product || empty( $product['specs'] ) ) {
		return null;
	}

	$specs  = $product['specs'];
	$weight = null;

	// Path depends on product type (matches cache rebuild structure).
	if ( $product_type === 'Electric Scooter' ) {
		$weight = erh_get_nested_spec( $specs, 'e-scooters.dimensions.weight' );
	} elseif ( $product_type === 'Electric Bike' ) {
		$weight = erh_get_nested_spec( $specs, 'e-bikes.weight_and_capacity.weight' );
	} else {
		// EUC, Skateboard, Hoverboard - use dimensions.weight like escooters.
		$weight = erh_get_nested_spec( $specs, 'dimensions.weight' );
	}

	if ( $weight && is_numeric( $weight ) ) {
		return (float) $weight;
	}

	return null;
}

/**
 * Add Product schema to Rank Math JSON-LD output on single product pages.
 *
 * @param array $data The Rank Math JSON-LD data.
 * @return array Modified JSON-LD data.
 */
add_filter( 'rank_math/json_ld', function( array $data ): array {
	// Only on single product pages.
	if ( ! is_singular( 'products' ) ) {
		return $data;
	}

	$product_id   = get_the_ID();
	$product_name = get_the_title();
	$product_type = erh_get_product_type( $product_id );
	$permalink    = get_permalink( $product_id );
	$excerpt      = get_the_excerpt( $product_id );

	// Get brand from taxonomy.
	$brand       = '';
	$brand_terms = get_the_terms( $product_id, 'brand' );
	if ( $brand_terms && ! is_wp_error( $brand_terms ) ) {
		$brand = $brand_terms[0]->name;
	}

	// Get product image.
	$big_thumbnail  = get_field( 'big_thumbnail', $product_id );
	$featured_image = get_post_thumbnail_id( $product_id );
	$image_id       = $big_thumbnail ?: $featured_image;
	$image_url      = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';

	// Build Product schema.
	$product_schema = [
		'@type'       => 'Product',
		'@id'         => $permalink . '#product',
		'name'        => $product_name,
		'url'         => $permalink,
		'description' => $excerpt ?: wp_trim_words( get_the_content(), 50 ),
	];

	// Add image.
	if ( $image_url ) {
		$product_schema['image'] = $image_url;
	}

	// Add brand.
	if ( $brand ) {
		$product_schema['brand'] = [
			'@type' => 'Brand',
			'name'  => $brand,
		];
		// Use brand as manufacturer too (most products are manufactured by the brand).
		$product_schema['manufacturer'] = [
			'@type' => 'Organization',
			'name'  => $brand,
		];
	}

	// Add product category.
	if ( $product_type ) {
		$product_schema['category'] = $product_type;
	}

	// Add weight.
	$weight = erh_get_product_weight_for_schema( $product_id, $product_type );
	if ( $weight ) {
		$product_schema['weight'] = [
			'@type'    => 'QuantitativeValue',
			'value'    => $weight,
			'unitCode' => 'LBR', // Pounds.
		];
	}

	// Add offers (US pricing only).
	$offers = erh_get_product_offers_for_schema( $product_id );
	if ( ! empty( $offers ) ) {
		if ( count( $offers ) === 1 ) {
			$product_schema['offers'] = $offers[0];
		} else {
			// Multiple offers - use AggregateOffer.
			$prices = array_column( $offers, 'price' );
			$product_schema['offers'] = [
				'@type'      => 'AggregateOffer',
				'lowPrice'   => min( $prices ),
				'highPrice'  => max( $prices ),
				'priceCurrency' => 'USD',
				'offerCount' => count( $offers ),
				'offers'     => $offers,
			];
		}
	}

	// Add positiveNotes/negativeNotes from analysis.
	$analysis = erh_get_product_analysis_for_schema( $product_id, $product_type );

	if ( ! empty( $analysis['advantages'] ) ) {
		$product_schema['positiveNotes'] = [
			'@type'            => 'ItemList',
			'itemListElement'  => array_map( function( $text, $index ) {
				return [
					'@type'    => 'ListItem',
					'position' => $index + 1,
					'name'     => $text,
				];
			}, $analysis['advantages'], array_keys( $analysis['advantages'] ) ),
		];
	}

	if ( ! empty( $analysis['weaknesses'] ) ) {
		$product_schema['negativeNotes'] = [
			'@type'            => 'ItemList',
			'itemListElement'  => array_map( function( $text, $index ) {
				return [
					'@type'    => 'ListItem',
					'position' => $index + 1,
					'name'     => $text,
				];
			}, $analysis['weaknesses'], array_keys( $analysis['weaknesses'] ) ),
		];
	}

	// Add to Rank Math data.
	$data['product'] = $product_schema;

	return $data;
}, 20 );

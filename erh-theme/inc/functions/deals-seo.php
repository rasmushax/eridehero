<?php
/**
 * Deals SEO Functions
 *
 * RankMath integration for deals pages: dynamic titles, meta descriptions,
 * and JSON-LD schema (CollectionPage + BreadcrumbList).
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ERH\CategoryConfig;
use ERH\Pricing\DealsFinder;

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

/**
 * Check if the current page uses the Deals Hub template.
 *
 * @return bool True if the current page uses page-deals.php.
 */
function erh_is_deals_hub(): bool {
	return is_page_template( 'page-deals.php' );
}

/**
 * Check if the current page uses the Deals Category template.
 *
 * @return bool True if the current page uses page-deals-category.php.
 */
function erh_is_deals_category(): bool {
	return is_page_template( 'page-deals-category.php' );
}

/**
 * Check if the current page is any deals page (hub or category).
 *
 * @return bool True if current page is a deals hub or category page.
 */
function erh_is_deals_page(): bool {
	return erh_is_deals_hub() || erh_is_deals_category();
}

/**
 * Get CategoryConfig for the current deals category page.
 *
 * Reads the page slug and resolves it via CategoryConfig::get_by_slug().
 *
 * @return array<string, mixed>|null Category config array or null if not found.
 */
function erh_get_deals_category_config(): ?array {
	if ( ! erh_is_deals_category() ) {
		return null;
	}

	if ( ! class_exists( 'ERH\CategoryConfig' ) ) {
		return null;
	}

	$slug = get_post_field( 'post_name', get_the_ID() );
	if ( empty( $slug ) ) {
		return null;
	}

	return CategoryConfig::get_by_slug( $slug );
}

/**
 * Get US deal counts via DealsFinder (statically cached per request).
 *
 * Returns an array keyed by product type name, e.g.:
 * ['Electric Scooter' => 47, 'Electric Bike' => 12, ...]
 *
 * @return array<string, int> Deal counts per product type.
 */
function erh_get_deals_counts_for_seo(): array {
	static $counts = null;

	if ( $counts !== null ) {
		return $counts;
	}

	if ( ! class_exists( 'ERH\Pricing\DealsFinder' ) ) {
		$counts = [];
		return $counts;
	}

	$finder = new DealsFinder();
	$counts = $finder->get_deal_counts(
		DealsFinder::DEFAULT_THRESHOLD,
		DealsFinder::DEFAULT_GEO,
		DealsFinder::DEFAULT_PERIOD
	);

	return $counts;
}

/**
 * Get the total deal count across all categories.
 *
 * @return int Total number of active deals.
 */
function erh_get_total_deal_count(): int {
	$counts = erh_get_deals_counts_for_seo();
	return array_sum( $counts );
}

/**
 * Get the deal count for a specific product type.
 *
 * @param string $product_type Product type label (e.g., 'Electric Scooter').
 * @return int Number of active deals for this product type.
 */
function erh_get_category_deal_count( string $product_type ): int {
	$counts = erh_get_deals_counts_for_seo();
	return (int) ( $counts[ $product_type ] ?? 0 );
}

/**
 * Get tracked product count for a product type (from wp_product_data cache table).
 *
 * @param string $product_type Product type (e.g., 'Electric Scooter'), or empty for all.
 * @return int
 */
function erh_get_tracked_product_count( string $product_type = '' ): int {
	static $counts = [];

	if ( isset( $counts[ $product_type ] ) ) {
		return $counts[ $product_type ];
	}

	if ( ! class_exists( 'ERH\Database\ProductCache' ) ) {
		return 0;
	}

	$cache = new \ERH\Database\ProductCache();
	$filters = $product_type ? [ 'product_type' => $product_type ] : [];
	$counts[ $product_type ] = $cache->count_filtered( $filters );

	return $counts[ $product_type ];
}

/**
 * Get retailer count (HFT scrapers + Amazon).
 *
 * @return int
 */
function erh_get_retailer_count(): int {
	static $count = null;

	if ( $count !== null ) {
		return $count;
	}

	if ( ! class_exists( 'HFT_Scraper_Repository' ) ) {
		$count = 0;
		return $count;
	}

	$repo  = new \HFT_Scraper_Repository();
	$count = $repo->count() + 1; // +1 for Amazon (PA-API, not a scraper).

	return $count;
}

/**
 * Round a count down to a clean number for display.
 *
 * 23 → 20, 69 → 65, 127 → 120, 344 → 340, 1247 → 1,200
 *
 * @param int $n Raw count.
 * @return string Formatted rounded number (e.g., "340").
 */
function erh_round_count( int $n ): string {
	if ( $n < 20 ) {
		return (string) $n;
	}
	if ( $n < 100 ) {
		$rounded = (int) floor( $n / 5 ) * 5;
	} elseif ( $n < 1000 ) {
		$rounded = (int) floor( $n / 10 ) * 10;
	} else {
		$rounded = (int) floor( $n / 100 ) * 100;
	}
	return number_format( $rounded );
}

// =============================================================================
// RANKMATH TITLE FILTER
// =============================================================================

/**
 * Override RankMath title for deals pages.
 *
 * Hub:      "Electric Ride Deals ({total}) - Updated Daily | ERideHero"
 * Category: "{name_short} Deals ({count}) - Updated Daily | ERideHero"
 * Count is omitted from parentheses when 0.
 */
add_filter( 'rank_math/frontend/title', function( string $title ): string {
	if ( ! erh_is_deals_page() ) {
		return $title;
	}

	if ( erh_is_deals_hub() ) {
		$total = erh_get_total_deal_count();
		if ( $total > 0 ) {
			return sprintf( 'All Deals (%d on Sale) - Tracked Daily', $total );
		}
		return 'All Deals - Tracked Daily';
	}

	// Category page.
	$config = erh_get_deals_category_config();
	if ( ! $config ) {
		return $title;
	}

	$name  = $config['type'] ?? '';
	$count = erh_get_category_deal_count( $name );

	if ( $count > 0 ) {
		return sprintf( '%s Deals (%d on Sale) - Tracked Daily', $name, $count );
	}
	return sprintf( '%s Deals - Tracked Daily', $name );
}, 20 );

// =============================================================================
// RANKMATH DESCRIPTION FILTER
// =============================================================================

/**
 * Override RankMath meta description for deals pages.
 *
 * Hub: "Browse {total} deals on electric scooters, e-bikes, skateboards & more..."
 * Category: "Browse {count} {name_plural lowercase} deals currently priced below..."
 * Fallback versions without counts if count is 0.
 */
add_filter( 'rank_math/frontend/description', function( string $description ): string {
	if ( ! erh_is_deals_page() ) {
		return $description;
	}

	$retailers = erh_get_retailer_count();

	if ( erh_is_deals_hub() ) {
		$total    = erh_get_total_deal_count();
		$products = erh_get_tracked_product_count();

		if ( $total > 0 && $products > 0 && $retailers > 0 ) {
			return sprintf(
				'Browse %d deals on electric scooters, e-bikes, skateboards and more, all priced below their average selling price. We track prices on %s models across %s retailers daily.',
				$total,
				erh_round_count( $products ),
				erh_round_count( $retailers )
			);
		}
		return 'Find deals on electric scooters, e-bikes, skateboards and more, all priced below their average selling price. Click any product to view in-depth price history.';
	}

	// Category page.
	$config = erh_get_deals_category_config();
	if ( ! $config ) {
		return $description;
	}

	$name_plural = strtolower( $config['name_plural'] ?? '' );
	$count       = erh_get_category_deal_count( $config['type'] ?? '' );
	$products    = erh_get_tracked_product_count( $config['type'] ?? '' );

	if ( $count > 0 && $products > 0 && $retailers > 0 ) {
		return sprintf(
			'Browse %d %s priced below their 6-month average. We track prices on %s models across %s retailers daily. Click any product to view in-depth price history.',
			$count,
			$name_plural . ' deals',
			erh_round_count( $products ),
			erh_round_count( $retailers )
		);
	}
	return sprintf(
		'Find %s currently priced below their average selling price. We track actual prices daily, not inflated list prices. Click any product to view in-depth price history.',
		$name_plural . ' deals'
	);
}, 20 );

// =============================================================================
// RANKMATH JSON-LD FILTER
// =============================================================================

/**
 * Modify JSON-LD schema for deals pages.
 *
 * - Changes Article @type to CollectionPage (top-level entries AND @graph nodes).
 * - For category pages: replaces BreadcrumbList with Home > Deals > {category}.
 */
add_filter( 'rank_math/json_ld', function( array $data ): array {
	if ( ! erh_is_deals_page() ) {
		return $data;
	}

	// Replace Article @type with CollectionPage throughout the data structure.
	foreach ( $data as $key => $entity ) {
		if ( ! is_array( $entity ) ) {
			continue;
		}

		// Handle top-level entries.
		if ( isset( $entity['@type'] ) && $entity['@type'] === 'Article' ) {
			$data[ $key ]['@type'] = 'CollectionPage';
		}

		// Handle @graph nodes.
		if ( isset( $entity['@graph'] ) && is_array( $entity['@graph'] ) ) {
			foreach ( $entity['@graph'] as $graph_key => $node ) {
				if ( isset( $node['@type'] ) && $node['@type'] === 'Article' ) {
					$data[ $key ]['@graph'][ $graph_key ]['@type'] = 'CollectionPage';
				}
			}
		}
	}

	// For category pages: replace BreadcrumbList with a 3-level version.
	if ( erh_is_deals_category() ) {
		$config = erh_get_deals_category_config();
		if ( $config ) {
			// Remove all existing BreadcrumbList entries (top-level and @graph).
			foreach ( $data as $key => $entity ) {
				if ( isset( $entity['@type'] ) && $entity['@type'] === 'BreadcrumbList' ) {
					unset( $data[ $key ] );
				}
				if ( isset( $entity['@graph'] ) && is_array( $entity['@graph'] ) ) {
					foreach ( $entity['@graph'] as $graph_key => $node ) {
						if ( isset( $node['@type'] ) && $node['@type'] === 'BreadcrumbList' ) {
							unset( $data[ $key ]['@graph'][ $graph_key ] );
						}
					}
				}
			}

			$name_plural  = $config['name_plural'] ?? '';
			$current_url  = get_permalink();

			$data['breadcrumb'] = [
				'@type'           => 'BreadcrumbList',
				'@id'             => ( $current_url ?: home_url( '/' ) ) . '#breadcrumb',
				'itemListElement' => [
					[
						'@type'    => 'ListItem',
						'position' => 1,
						'item'     => [
							'@id'  => home_url( '/' ),
							'name' => 'Home',
						],
					],
					[
						'@type'    => 'ListItem',
						'position' => 2,
						'item'     => [
							'@id'  => home_url( '/deals/' ),
							'name' => 'Deals',
						],
					],
					[
						'@type'    => 'ListItem',
						'position' => 3,
						'item'     => [
							'@id'  => $current_url ?: '',
							'name' => $name_plural,
						],
					],
				],
			];
		}
	}

	// Add ItemList schema for category pages (SSR deals).
	if ( erh_is_deals_category() ) {
		$config = erh_get_deals_category_config();
		if ( $config && class_exists( 'ERH\Pricing\DealsFinder' ) ) {
			$finder = new DealsFinder();
			$deals  = $finder->get_deals(
				$config['type'],
				DealsFinder::DEFAULT_THRESHOLD,
				100,
				'US',
				DealsFinder::DEFAULT_PERIOD
			);

			if ( ! empty( $deals ) ) {
				$data['itemList'] = erh_build_deals_itemlist_schema( $deals );
			}
		}
	}

	return $data;
}, 20 );

/**
 * Build ItemList schema from SSR deals.
 *
 * @param array  $deals SSR deals array from DealsFinder.
 * @return array Schema.org ItemList.
 */
function erh_build_deals_itemlist_schema( array $deals ): array {
	$items = [];

	foreach ( $deals as $index => $deal ) {
		$product_id    = (int) ( $deal['product_id'] ?? $deal['id'] ?? 0 );
		$analysis      = $deal['deal_analysis'] ?? [];
		$current_price = (float) ( $analysis['current_price'] ?? 0 );
		$currency      = $analysis['currency'] ?? 'USD';
		$permalink     = $deal['permalink'] ?? '';
		$name          = $deal['name'] ?? '';

		if ( ! $product_id || $current_price <= 0 || ! $permalink ) {
			continue;
		}

		$item = [
			'@type'    => 'ListItem',
			'position' => $index + 1,
			'item'     => [
				'@type'  => 'Product',
				'name'   => $name,
				'url'    => $permalink,
				'offers' => [
					'@type'         => 'Offer',
					'price'         => $current_price,
					'priceCurrency' => $currency,
					'availability'  => 'https://schema.org/InStock',
				],
			],
		];

		// Add seller if available.
		if ( ! empty( $analysis['retailer'] ) ) {
			$item['item']['offers']['seller'] = [
				'@type' => 'Organization',
				'name'  => $analysis['retailer'],
			];
		}

		// Add image.
		if ( ! empty( $deal['image_url'] ) ) {
			$item['item']['image'] = $deal['image_url'];
		} elseif ( $product_id ) {
			$thumb_id = get_post_thumbnail_id( $product_id );
			if ( $thumb_id ) {
				$thumb_url = wp_get_attachment_image_url( $thumb_id, 'medium' );
				if ( $thumb_url ) {
					$item['item']['image'] = $thumb_url;
				}
			}
		}

		$items[] = $item;
	}

	return [
		'@type'           => 'ItemList',
		'numberOfItems'   => count( $items ),
		'itemListElement' => $items,
	];
}

// =============================================================================
// ACF FIELD GROUP: DEALS FAQ
// =============================================================================

/**
 * Register ACF field group for Deals FAQ repeater.
 *
 * Adds a repeater field (question + answer) to pages using the
 * Deals Hub or Deals Category templates.
 */
add_action( 'acf/init', function (): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( [
		'key'      => 'group_deals_faq',
		'title'    => 'Deals FAQ',
		'fields'   => [
			[
				'key'          => 'field_deals_faq_repeater',
				'label'        => 'FAQ Items',
				'name'         => 'deals_faq',
				'type'         => 'repeater',
				'instructions' => 'Add frequently asked questions. Displayed as a two-column accordion at the bottom of the page.',
				'layout'       => 'block',
				'button_label' => 'Add Question',
				'min'          => 0,
				'max'          => 0,
				'sub_fields'   => [
					[
						'key'         => 'field_deals_faq_question',
						'label'       => 'Question',
						'name'        => 'question',
						'type'        => 'text',
						'required'    => 1,
						'placeholder' => 'Enter question...',
					],
					[
						'key'          => 'field_deals_faq_answer',
						'label'        => 'Answer',
						'name'         => 'answer',
						'type'         => 'wysiwyg',
						'required'     => 1,
						'tabs'         => 'all',
						'toolbar'      => 'full',
						'media_upload' => 0,
					],
				],
			],
		],
		'location' => [
			[
				[
					'param'    => 'page_template',
					'operator' => '==',
					'value'    => 'page-deals.php',
				],
			],
			[
				[
					'param'    => 'page_template',
					'operator' => '==',
					'value'    => 'page-deals-category.php',
				],
			],
		],
		'menu_order' => 10,
		'position'   => 'normal',
		'style'      => 'default',
	] );
} );

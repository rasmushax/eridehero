<?php
/**
 * Hub Page Layout
 *
 * Main orchestrator for category hub pages (e-scooters, e-bikes, etc.).
 * Assembles all hub sections in order.
 *
 * Called from category.php which handles get_header(), get_footer(), and <main>.
 *
 * Expected args:
 * - category (WP_Term): The category term object
 * - product_type (string): Display name (e.g., "Electric Scooter")
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get args passed from category.php.
$category     = $args['category'] ?? null;
$product_type = $args['product_type'] ?? '';

if ( ! $category ) {
	return;
}

$category_slug = $category->slug;

// Map category slugs to product type identifiers for deals/finder.
$product_type_map = array(
	'electric-scooters'    => 'escooter',
	'electric-bikes'       => 'ebike',
	'electric-unicycles'   => 'euc',
	'electric-skateboards' => 'eskate',
	'hoverboards'          => 'hoverboard',
);

$product_type_key = $product_type_map[ $category_slug ] ?? 'escooter';

// Hub Header - Title + navigation pills.
get_template_part( 'template-parts/hub/header', null, array(
	'category'     => $category,
	'product_type' => $product_type,
) );

// Buying Guides - Featured (2) + grid (4).
get_template_part( 'template-parts/hub/guides', null, array(
	'category'      => $category,
	'category_slug' => $category_slug,
) );

// Tools Section - Finder + H2H Comparison.
get_template_part( 'template-parts/hub/tools', null, array(
	'category'         => $category,
	'product_type'     => $product_type,
	'product_type_key' => $product_type_key,
) );

// Deals Section - Reuse home deals with category filter.
get_template_part( 'template-parts/home/deals', null, array(
	'category'  => $product_type_key,
	'show_tabs' => false,
	'limit'     => 12,
) );

// Latest Reviews - Reuse home reviews with category filter.
get_template_part( 'template-parts/home/reviews', null, array(
	'category'     => $category_slug,
	'limit'        => 4,
	'show_sidebar' => true,
) );

// Latest Articles - Reuse home articles with category filter.
get_template_part( 'template-parts/home/articles', null, array(
	'category'     => $category_slug,
	'limit'        => 4,
	'show_sidebar' => true,
) );

// CTA Section - Sign up prompt.
get_template_part( 'template-parts/sections/cta' );

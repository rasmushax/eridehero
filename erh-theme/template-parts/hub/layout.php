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
 * - hub_context (array): Hub context from erh_get_hub_context()
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get args passed from category.php.
$category    = $args['category'] ?? null;
$hub_context = $args['hub_context'] ?? null;

if ( ! $category || ! $hub_context ) {
	return;
}

$category_slug    = $category->slug;
$product_type     = $hub_context['product_type'];
$product_type_key = $hub_context['product_type_key'];

// Hub Header - Title + description + navigation pills.
get_template_part( 'template-parts/hub/header', null, array(
	'category'     => $category,
	'product_type' => $product_type,
	'description'  => $hub_context['description'],
) );

// Buying Guides - All guides, featured sorted first.
get_template_part( 'template-parts/hub/guides', null, array(
	'category'      => $category,
	'category_slug' => $category_slug,
) );

// Tools Section - Finder + H2H Comparison.
get_template_part( 'template-parts/hub/tools', null, array(
	'category'         => $category,
	'product_type'     => $product_type,
	'product_type_key' => $product_type_key,
	'short_name'       => $hub_context['short_name'],
	'finder_url'       => $hub_context['finder_url'],
	'product_count'    => $hub_context['product_count'],
) );

// Deals Section - Reuse home deals with category filter.
get_template_part( 'template-parts/home/deals', null, array(
	'category'  => $product_type_key,
	'show_tabs' => false,
	'limit'     => 12,
	'deals_url' => $hub_context['deals_url'],
) );

// Latest Reviews - Reuse home reviews with category filter.
get_template_part( 'template-parts/home/reviews', null, array(
	'category'      => $category_slug,
	'limit'         => 4,
	'show_sidebar'  => true,
	'view_all_url'  => $hub_context['reviews_url'],
) );

// Latest Articles - Reuse home articles with category filter.
get_template_part( 'template-parts/home/articles', null, array(
	'category'      => $category_slug,
	'limit'         => 4,
	'show_sidebar'  => true,
	'view_all_url'  => $hub_context['articles_url'],
) );

// CTA Section - Sign up prompt.
get_template_part( 'template-parts/sections/cta' );

<?php
/**
 * Coupon Page Routing
 *
 * Handles virtual routes for coupon category pages.
 * E.g., /coupons/electric-scooters/ → coupon listing for e-scooters.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register rewrite rules for coupon category pages.
 */
function erh_coupon_rewrite_rules(): void {
	// /coupons/electric-scooters/ → escooter coupons page
	add_rewrite_rule(
		'^coupons/electric-scooters/?$',
		'index.php?erh_coupon_category=escooter',
		'top'
	);

	// Future: Add more categories as needed
	// add_rewrite_rule( '^coupons/electric-bikes/?$', 'index.php?erh_coupon_category=ebike', 'top' );
}
add_action( 'init', 'erh_coupon_rewrite_rules' );

/**
 * Register custom query vars.
 *
 * @param array $vars Existing query vars.
 * @return array Modified query vars.
 */
function erh_coupon_query_vars( array $vars ): array {
	$vars[] = 'erh_coupon_category';
	return $vars;
}
add_filter( 'query_vars', 'erh_coupon_query_vars' );

/**
 * Load coupon category template.
 *
 * @param string $template The template to load.
 * @return string Modified template path.
 */
function erh_coupon_template_include( string $template ): string {
	$category = get_query_var( 'erh_coupon_category' );

	if ( ! $category ) {
		return $template;
	}

	$custom_template = get_template_directory() . '/template-parts/coupons/category.php';
	if ( file_exists( $custom_template ) ) {
		return $custom_template;
	}

	return $template;
}
add_filter( 'template_include', 'erh_coupon_template_include' );

/**
 * Handle coupon URLs before WordPress processes them.
 * Ensures routing works even before permalinks are flushed.
 *
 * @param WP $wp The WordPress environment instance.
 */
function erh_coupon_parse_request( $wp ): void {
	$request = trim( $wp->request, '/' );

	if ( preg_match( '#^coupons/electric-scooters/?$#', $request ) ) {
		$wp->query_vars['erh_coupon_category'] = 'escooter';
	}

	// Future: Add more category patterns as needed
}
add_action( 'parse_request', 'erh_coupon_parse_request' );

/**
 * Fix 404 for coupon virtual pages.
 */
function erh_coupon_fix_404(): void {
	$category = get_query_var( 'erh_coupon_category' );

	if ( ! $category ) {
		return;
	}

	global $wp_query;
	$wp_query->is_404  = false;
	$wp_query->is_page = true;
	status_header( 200 );
}
add_action( 'wp', 'erh_coupon_fix_404' );

/**
 * Check if current request is a coupon category page.
 *
 * @param string $category Optional. Check for specific category.
 * @return bool True if on coupon category page.
 */
function erh_is_coupon_page( string $category = '' ): bool {
	$current = get_query_var( 'erh_coupon_category' );

	if ( empty( $category ) ) {
		return ! empty( $current );
	}

	return $current === $category;
}

/**
 * Get coupon category data for the current page.
 *
 * @return array|null Category config data or null.
 */
function erh_get_coupon_category(): ?array {
	$category_key = get_query_var( 'erh_coupon_category' );

	if ( ! $category_key ) {
		return null;
	}

	return \ERH\CategoryConfig::get_by_key( $category_key );
}

/**
 * Get URL for a coupon category page.
 *
 * @param string $category_key Category key (e.g. 'escooter').
 * @return string The coupon page URL.
 */
function erh_get_coupon_url( string $category_key ): string {
	$category = \ERH\CategoryConfig::get_by_key( $category_key );
	if ( ! $category ) {
		return home_url( '/coupons/' );
	}

	return home_url( '/coupons/' . $category['slug'] . '/' );
}

// ─── RankMath SEO Integration ───

/**
 * Override RankMath title for coupon pages.
 *
 * @param string $title The page title.
 * @return string Modified title.
 */
function erh_coupon_rankmath_title( string $title ): string {
	if ( ! erh_is_coupon_page() ) {
		return $title;
	}

	$category = erh_get_coupon_category();
	if ( ! $category ) {
		return $title;
	}

	$month_year = date_i18n( 'F Y' );

	return sprintf(
		'%s Coupon Codes for %s | ERideHero',
		$category['type'],
		$month_year
	);
}
add_filter( 'rank_math/frontend/title', 'erh_coupon_rankmath_title', 20 );

/**
 * Override RankMath description for coupon pages.
 *
 * @param string $desc The meta description.
 * @return string Modified description.
 */
function erh_coupon_rankmath_description( string $desc ): string {
	if ( ! erh_is_coupon_page() ) {
		return $desc;
	}

	$category = erh_get_coupon_category();
	if ( ! $category ) {
		return $desc;
	}

	$month_year = date_i18n( 'F Y' );

	return sprintf(
		'Exclusive %s coupon codes and discounts for %s. Save on top electric scooter brands with verified promo codes from ERideHero.',
		strtolower( $category['name_short'] ),
		$month_year
	);
}
add_filter( 'rank_math/frontend/description', 'erh_coupon_rankmath_description', 20 );

/**
 * Set robots directives for coupon pages (index, follow).
 *
 * @param array $robots The robots directives.
 * @return array Modified robots.
 */
function erh_coupon_rankmath_robots( array $robots ): array {
	if ( ! erh_is_coupon_page() ) {
		return $robots;
	}

	return [
		'index'  => 'index',
		'follow' => 'follow',
	];
}
add_filter( 'rank_math/frontend/robots', 'erh_coupon_rankmath_robots', 20 );

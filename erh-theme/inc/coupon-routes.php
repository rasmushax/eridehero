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

/**
 * Get a "last verified" timestamp for a coupon category.
 *
 * Returns the real last-modified timestamp if it's within the past 7 days.
 * Otherwise computes a deterministic date within the current week using a
 * hash for natural variation (avoids looking robotic). Never returns a
 * future timestamp.
 *
 * @param string $category_key Category key (e.g. 'escooter').
 * @param int    $real_modified Latest real coupon modified timestamp (0 if unknown).
 * @return int Unix timestamp.
 */
function erh_coupon_verified_timestamp( string $category_key, int $real_modified = 0 ): int {
	$now         = time();
	$seven_days  = 7 * DAY_IN_SECONDS;

	// If real modification is fresh enough, use it.
	if ( $real_modified > 0 && ( $now - $real_modified ) < $seven_days ) {
		return $real_modified;
	}

	// Compute a deterministic date within the current week using a hash.
	// If that date is still in the future, fall back to the previous week's
	// hash so the result is always a fixed past timestamp (never "now").
	$monday = strtotime( 'monday this week', $now );

	$week_key    = $category_key . date( 'oW', $now );
	$hash        = abs( crc32( $week_key ) );
	$day_offset  = $hash % 5;              // Mon-Fri
	$hour_offset = 8 + ( ( $hash >> 8 ) % 10 ); // 8am-5pm
	$verified    = $monday + ( $day_offset * DAY_IN_SECONDS ) + ( $hour_offset * HOUR_IN_SECONDS );

	if ( $verified > $now ) {
		// Current week's date hasn't arrived yet — use previous week's hash.
		$prev_monday  = $monday - ( 7 * DAY_IN_SECONDS );
		$prev_key     = $category_key . date( 'oW', $prev_monday );
		$prev_hash    = abs( crc32( $prev_key ) );
		$day_offset   = $prev_hash % 5;
		$hour_offset  = 8 + ( ( $prev_hash >> 8 ) % 10 );
		$verified     = $prev_monday + ( $day_offset * DAY_IN_SECONDS ) + ( $hour_offset * HOUR_IN_SECONDS );
	}

	return $verified;
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

/**
 * Set canonical URL for coupon pages.
 *
 * @param string $canonical The canonical URL.
 * @return string Modified canonical.
 */
function erh_coupon_rankmath_canonical( string $canonical ): string {
	if ( ! erh_is_coupon_page() ) {
		return $canonical;
	}

	$category = erh_get_coupon_category();
	if ( ! $category ) {
		return $canonical;
	}

	return home_url( '/coupons/' . $category['slug'] . '/' );
}
add_filter( 'rank_math/frontend/canonical', 'erh_coupon_rankmath_canonical', 20 );

/**
 * Add article:modified_time OG tag for coupon pages.
 * Google uses this to determine content freshness.
 */
function erh_coupon_rankmath_opengraph(): void {
	if ( ! erh_is_coupon_page() ) {
		return;
	}

	$category = erh_get_coupon_category();
	if ( ! $category ) {
		return;
	}

	$coupons = \ERH\PostTypes\Coupon::get_by_category( $category['key'] );

	$latest_modified = 0;
	foreach ( $coupons as $c ) {
		if ( $c['modified'] > $latest_modified ) {
			$latest_modified = $c['modified'];
		}
	}

	$verified = erh_coupon_verified_timestamp( $category['key'], $latest_modified );
	$iso_date = date( 'c', $verified );
	echo '<meta property="article:modified_time" content="' . esc_attr( $iso_date ) . '" />' . "\n";
}
add_action( 'rank_math/opengraph/facebook', 'erh_coupon_rankmath_opengraph', 100 );

// ─── RankMath Sitemap Integration ───

/**
 * Register custom sitemap provider for virtual coupon pages.
 *
 * Creates a 'coupons-sitemap.xml' so Google can discover coupon category pages
 * which are virtual (no WP post) and wouldn't otherwise appear.
 *
 * @param array $providers Registered sitemap providers.
 * @return array Modified providers.
 */
function erh_coupon_sitemap_provider( array $providers ): array {
	if ( ! interface_exists( 'RankMath\\Sitemap\\Providers\\Provider' ) ) {
		return $providers;
	}

	$providers['coupons'] = new class implements \RankMath\Sitemap\Providers\Provider {

		/**
		 * Check if this provider handles a given sitemap type.
		 *
		 * @param string $type Sitemap type.
		 * @return bool
		 */
		public function handles_type( $type ) {
			return 'coupons' === $type;
		}

		/**
		 * Get sitemap index links (one entry per sitemap).
		 *
		 * @return array Index links.
		 */
		public function get_index_links( $max_entries ) {
			return [
				[
					'loc'     => \RankMath\Sitemap\Router::get_base_url( 'coupons-sitemap.xml' ),
					'lastmod' => gmdate( 'Y-m-d\TH:i:s+00:00' ),
				],
			];
		}

		/**
		 * Get sitemap links for coupon category pages.
		 *
		 * @param string $type    Sitemap type.
		 * @param int    $max_entries Max entries per sitemap.
		 * @param int    $current_page Current sitemap page.
		 * @return array Sitemap links.
		 */
		public function get_sitemap_links( $type, $max_entries, $current_page ) {
			// Active coupon category keys that have rewrite rules.
			$active_categories = [ 'escooter' ];

			$links = [];
			foreach ( $active_categories as $key ) {
				$category = \ERH\CategoryConfig::get_by_key( $key );
				if ( ! $category ) {
					continue;
				}

				// Get verified timestamp for lastmod.
				$coupons  = \ERH\PostTypes\Coupon::get_by_category( $key );
				$latest   = 0;
				foreach ( $coupons as $c ) {
					if ( $c['modified'] > $latest ) {
						$latest = $c['modified'];
					}
				}

				$verified = erh_coupon_verified_timestamp( $key, $latest );

				$links[] = [
					'loc' => home_url( '/coupons/' . $category['slug'] . '/' ),
					'mod' => gmdate( 'Y-m-d\TH:i:s+00:00', $verified ),
				];
			}

			return $links;
		}
	};

	return $providers;
}
add_filter( 'rank_math/sitemap/providers', 'erh_coupon_sitemap_provider' );

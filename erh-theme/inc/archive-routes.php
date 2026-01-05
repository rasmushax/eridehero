<?php
/**
 * Custom Archive URL Routing
 *
 * Handles custom archive URLs that live under category archives.
 * For example: /electric-scooters/reviews/ while /electric-scooters/ remains a category.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register rewrite rules for custom archive URLs.
 */
function erh_archive_rewrite_rules() {
	// /electric-scooters/reviews/ → e-scooter reviews archive
	add_rewrite_rule(
		'^electric-scooters/reviews/?$',
		'index.php?erh_archive=escooter-reviews',
		'top'
	);

	// Pagination: /electric-scooters/reviews/page/2/
	add_rewrite_rule(
		'^electric-scooters/reviews/page/([0-9]+)/?$',
		'index.php?erh_archive=escooter-reviews&paged=$matches[1]',
		'top'
	);

	// Future: Add more category-specific archives as needed
	// add_rewrite_rule('^electric-bikes/reviews/?$', 'index.php?erh_archive=ebike-reviews', 'top');
}
add_action( 'init', 'erh_archive_rewrite_rules' );

/**
 * Register custom query vars.
 *
 * @param array $vars Existing query vars.
 * @return array Modified query vars.
 */
function erh_archive_query_vars( $vars ) {
	$vars[] = 'erh_archive';
	return $vars;
}
add_filter( 'query_vars', 'erh_archive_query_vars' );

/**
 * Load custom archive templates.
 *
 * @param string $template The template to load.
 * @return string Modified template path.
 */
function erh_archive_template_include( $template ) {
	$archive = get_query_var( 'erh_archive' );

	if ( ! $archive ) {
		return $template;
	}

	$templates = array(
		'escooter-reviews' => 'page-escooter-reviews.php',
		// Future: 'ebike-reviews' => 'page-ebike-reviews.php',
	);

	if ( isset( $templates[ $archive ] ) ) {
		$custom_template = get_template_directory() . '/' . $templates[ $archive ];
		if ( file_exists( $custom_template ) ) {
			return $custom_template;
		}
	}

	return $template;
}
add_filter( 'template_include', 'erh_archive_template_include' );

/**
 * Handle custom archive URLs before WordPress processes them.
 * This works even before permalinks are flushed.
 *
 * @param WP $wp The WordPress environment instance.
 */
function erh_archive_parse_request( $wp ) {
	$request = trim( $wp->request, '/' );

	// Match /electric-scooters/reviews/ or /electric-scooters/reviews/page/N/
	if ( preg_match( '#^electric-scooters/reviews(?:/page/([0-9]+))?/?$#', $request, $matches ) ) {
		$wp->query_vars['erh_archive'] = 'escooter-reviews';
		if ( ! empty( $matches[1] ) ) {
			$wp->query_vars['paged'] = (int) $matches[1];
		}
	}

	// Future: Add more patterns as needed
}
add_action( 'parse_request', 'erh_archive_parse_request' );

/**
 * Check if current request is a custom archive page.
 *
 * @param string $archive Optional. Check for specific archive type.
 * @return bool True if on custom archive page.
 */
function erh_is_custom_archive( $archive = '' ) {
	$current = get_query_var( 'erh_archive' );

	if ( empty( $archive ) ) {
		return ! empty( $current );
	}

	return $current === $archive;
}

/**
 * Set page title for custom archive pages.
 *
 * @param array $title The page title parts.
 * @return array Modified title.
 */
function erh_archive_document_title( $title ) {
	$archive = get_query_var( 'erh_archive' );

	if ( ! $archive ) {
		return $title;
	}

	$titles = array(
		'escooter-reviews' => 'Electric Scooter Reviews - Hands-On Tested',
	);

	if ( isset( $titles[ $archive ] ) ) {
		$title['title'] = $titles[ $archive ];

		// Add page number for paginated archives
		$paged = get_query_var( 'paged', 1 );
		if ( $paged > 1 ) {
			$title['title'] .= ' - Page ' . $paged;
		}
	}

	return $title;
}
add_filter( 'document_title_parts', 'erh_archive_document_title' );

/**
 * Get the URL for a custom archive page.
 *
 * @param string $archive The archive type (e.g., 'escooter-reviews').
 * @param int    $page    Optional. Page number for pagination.
 * @return string The archive URL.
 */
function erh_get_custom_archive_url( $archive, $page = 1 ) {
	$urls = array(
		'escooter-reviews' => '/electric-scooters/reviews/',
	);

	if ( ! isset( $urls[ $archive ] ) ) {
		return home_url( '/' );
	}

	$url = home_url( $urls[ $archive ] );

	if ( $page > 1 ) {
		$url = trailingslashit( $url ) . 'page/' . $page . '/';
	}

	return $url;
}

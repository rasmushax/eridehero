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

// ─── RankMath SEO Integration for /electric-scooters/reviews/ ───

/**
 * Get the latest modified date from e-scooter review posts.
 *
 * @return string|false ISO 8601 date string or false if no reviews found.
 */
function erh_escooter_reviews_latest_modified() {
	static $cached = null;

	if ( null !== $cached ) {
		return $cached;
	}

	$query = new WP_Query( [
		'tag'            => 'review',
		'category_name'  => 'electric-scooters',
		'posts_per_page' => 1,
		'orderby'        => 'modified',
		'order'          => 'DESC',
		'post_status'    => 'publish',
		'no_found_rows'  => true,
		'fields'         => 'ids',
	] );

	if ( $query->have_posts() ) {
		$cached = get_post_modified_time( 'c', true, $query->posts[0] );
	} else {
		$cached = false;
	}

	wp_reset_postdata();

	return $cached;
}

/**
 * Override RankMath title for e-scooter reviews archive.
 *
 * @param string $title The page title.
 * @return string Modified title.
 */
function erh_escooter_reviews_rankmath_title( string $title ): string {
	if ( ! erh_is_custom_archive( 'escooter-reviews' ) ) {
		return $title;
	}

	$base = 'Electric Scooter Reviews – Hands-On Tested | ERideHero';

	$paged = get_query_var( 'paged', 1 );
	if ( $paged > 1 ) {
		$base = 'Electric Scooter Reviews – Hands-On Tested - Page ' . $paged . ' | ERideHero';
	}

	return $base;
}
add_filter( 'rank_math/frontend/title', 'erh_escooter_reviews_rankmath_title', 20 );

/**
 * Override RankMath description for e-scooter reviews archive.
 *
 * @param string $desc The meta description.
 * @return string Modified description.
 */
function erh_escooter_reviews_rankmath_description( string $desc ): string {
	if ( ! erh_is_custom_archive( 'escooter-reviews' ) ) {
		return $desc;
	}

	return 'Hands-on electric scooter reviews from scooters we\'ve actually tested. Independent ratings, performance data, and honest verdicts from ERideHero.';
}
add_filter( 'rank_math/frontend/description', 'erh_escooter_reviews_rankmath_description', 20 );

/**
 * Set robots directives for e-scooter reviews archive.
 *
 * Page 1: index, follow (main archive page).
 * Paginated pages: noindex, follow (prevent thin paginated pages from indexing).
 *
 * @param array $robots The robots directives.
 * @return array Modified robots.
 */
function erh_escooter_reviews_rankmath_robots( array $robots ): array {
	if ( ! erh_is_custom_archive( 'escooter-reviews' ) ) {
		return $robots;
	}

	$paged = get_query_var( 'paged', 1 );

	if ( $paged > 1 ) {
		return [
			'index'  => 'noindex',
			'follow' => 'follow',
		];
	}

	return [
		'index'  => 'index',
		'follow' => 'follow',
	];
}
add_filter( 'rank_math/frontend/robots', 'erh_escooter_reviews_rankmath_robots', 20 );

/**
 * Set canonical URL for e-scooter reviews archive.
 *
 * Always points to page 1 (paginated pages canonical to page 1).
 *
 * @param string $canonical The canonical URL.
 * @return string Modified canonical.
 */
function erh_escooter_reviews_rankmath_canonical( string $canonical ): string {
	if ( ! erh_is_custom_archive( 'escooter-reviews' ) ) {
		return $canonical;
	}

	return home_url( '/electric-scooters/reviews/' );
}
add_filter( 'rank_math/frontend/canonical', 'erh_escooter_reviews_rankmath_canonical', 20 );

/**
 * Add article:modified_time OG tag for e-scooter reviews archive.
 * Google uses this to determine content freshness.
 */
function erh_escooter_reviews_rankmath_opengraph(): void {
	if ( ! erh_is_custom_archive( 'escooter-reviews' ) ) {
		return;
	}

	$modified = erh_escooter_reviews_latest_modified();
	if ( $modified ) {
		echo '<meta property="article:modified_time" content="' . esc_attr( $modified ) . '" />' . "\n";
	}
}
add_action( 'rank_math/opengraph/facebook', 'erh_escooter_reviews_rankmath_opengraph', 100 );

/**
 * Enhance RankMath JSON-LD schema for e-scooter reviews archive.
 *
 * - Fixes URL and @id on WebPage/CollectionPage.
 * - Adds description.
 * - Replaces BreadcrumbList with correct hierarchy: Home → Electric Scooters → Reviews.
 *
 * @param array $data The JSON-LD data from RankMath.
 * @return array Modified data.
 */
function erh_escooter_reviews_enhance_schema( array $data ): array {
	if ( ! erh_is_custom_archive( 'escooter-reviews' ) ) {
		return $data;
	}

	$canonical   = home_url( '/electric-scooters/reviews/' );
	$description = erh_escooter_reviews_rankmath_description( '' );

	$breadcrumb = [
		'@type'           => 'BreadcrumbList',
		'itemListElement' => [
			[
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => 'Home',
				'item'     => home_url( '/' ),
			],
			[
				'@type'    => 'ListItem',
				'position' => 2,
				'name'     => 'Electric Scooters',
				'item'     => home_url( '/electric-scooters/' ),
			],
			[
				'@type'    => 'ListItem',
				'position' => 3,
				'name'     => 'Reviews',
			],
		],
	];

	// Handle @graph structure (newer RankMath versions).
	if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
		foreach ( $data['@graph'] as $idx => &$item ) {
			$type = $item['@type'] ?? '';

			// Remove existing BreadcrumbList (we add our own).
			if ( $type === 'BreadcrumbList' ) {
				unset( $data['@graph'][ $idx ] );
				continue;
			}

			// Fix CollectionPage or WebPage.
			if ( in_array( $type, [ 'CollectionPage', 'WebPage' ], true ) ) {
				$item['url']         = $canonical;
				$item['@id']         = $canonical . '#webpage';
				$item['description'] = $description;
			}
		}
		unset( $item );
		$data['@graph']   = array_values( $data['@graph'] );
		$data['@graph'][] = $breadcrumb;

		return $data;
	}

	// Handle flat structure (older RankMath versions).
	unset( $data['BreadcrumbList'] );

	foreach ( [ 'CollectionPage', 'WebPage' ] as $page_type ) {
		if ( isset( $data[ $page_type ] ) ) {
			$data[ $page_type ]['url']         = $canonical;
			$data[ $page_type ]['@id']         = $canonical . '#webpage';
			$data[ $page_type ]['description'] = $description;
		}
	}

	$data['BreadcrumbList'] = $breadcrumb;

	return $data;
}
add_filter( 'rank_math/json_ld', 'erh_escooter_reviews_enhance_schema', 20 );

// ─── RankMath Sitemap Integration ───

/**
 * Register custom sitemap provider for the e-scooter reviews virtual page.
 *
 * Creates an 'escooter-reviews-sitemap.xml' so Google can discover this virtual
 * page which has no WP post and wouldn't otherwise appear in sitemaps.
 *
 * @param array $providers Registered sitemap providers.
 * @return array Modified providers.
 */
function erh_escooter_reviews_sitemap_provider( array $providers ): array {
	if ( ! interface_exists( 'RankMath\\Sitemap\\Providers\\Provider' ) ) {
		return $providers;
	}

	$providers['escooter-reviews'] = new class implements \RankMath\Sitemap\Providers\Provider {

		/**
		 * Check if this provider handles a given sitemap type.
		 *
		 * @param string $type Sitemap type.
		 * @return bool
		 */
		public function handles_type( $type ) {
			return 'escooter-reviews' === $type;
		}

		/**
		 * Get sitemap index links (one entry per sitemap).
		 *
		 * @param int $max_entries Maximum entries per sitemap.
		 * @return array Index links.
		 */
		public function get_index_links( $max_entries ) {
			$lastmod = erh_escooter_reviews_latest_modified();

			return [
				[
					'loc'     => \RankMath\Sitemap\Router::get_base_url( 'escooter-reviews-sitemap.xml' ),
					'lastmod' => $lastmod ?: gmdate( 'Y-m-d\TH:i:s+00:00' ),
				],
			];
		}

		/**
		 * Get sitemap links for the e-scooter reviews page.
		 *
		 * @param string $type         Sitemap type.
		 * @param int    $max_entries  Maximum entries per sitemap.
		 * @param int    $current_page Current sitemap page.
		 * @return array Sitemap links.
		 */
		public function get_sitemap_links( $type, $max_entries, $current_page ) {
			$lastmod = erh_escooter_reviews_latest_modified();

			return [
				[
					'loc' => home_url( '/electric-scooters/reviews/' ),
					'mod' => $lastmod ?: gmdate( 'Y-m-d\TH:i:s+00:00' ),
				],
			];
		}
	};

	return $providers;
}
add_filter( 'rank_math/sitemap/providers', 'erh_escooter_reviews_sitemap_provider' );

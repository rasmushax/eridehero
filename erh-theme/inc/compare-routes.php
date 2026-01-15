<?php
/**
 * Compare page URL routing.
 *
 * Handles SEO-friendly URLs like /compare/product-a-vs-product-b/
 * and query string URLs like /compare?products=123,456
 *
 * URL Priority:
 * 1. /compare/                           → Hub page
 * 2. /compare/{category-slug}/           → Category landing (electric-scooters, etc.)
 * 3. /compare/{comparison-cpt-slug}/     → Curated comparison (if CPT exists)
 * 4. /compare/{slug-vs-slug}/            → Dynamic comparison (fallback)
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ERH\CategoryConfig;

/**
 * Category slug mapping for compare landing pages.
 *
 * @return array<string, array<string, string>>
 */
function erh_compare_category_map(): array {
    return CategoryConfig::get_compare_map();
}

/**
 * Register rewrite rules for comparison URLs.
 */
function erh_compare_rewrite_rules() {
    // Base compare page (hub).
    add_rewrite_rule(
        '^compare/?$',
        'index.php?erh_compare=1',
        'top'
    );

    // Category landing pages: /compare/electric-scooters/, /compare/e-bikes/, etc.
    $category_slugs = implode( '|', array_keys( erh_compare_category_map() ) );
    add_rewrite_rule(
        '^compare/(' . $category_slugs . ')/?$',
        'index.php?erh_compare=1&compare_category=$matches[1]',
        'top'
    );

    // Match /compare/product-a-vs-product-b/ (2 products).
    add_rewrite_rule(
        '^compare/([a-z0-9-]+)-vs-([a-z0-9-]+)/?$',
        'index.php?erh_compare=1&compare_slugs=$matches[1],$matches[2]',
        'top'
    );

    // Match /compare/a-vs-b-vs-c/ (3 products).
    add_rewrite_rule(
        '^compare/([a-z0-9-]+)-vs-([a-z0-9-]+)-vs-([a-z0-9-]+)/?$',
        'index.php?erh_compare=1&compare_slugs=$matches[1],$matches[2],$matches[3]',
        'top'
    );

    // Match /compare/a-vs-b-vs-c-vs-d/ (4 products).
    add_rewrite_rule(
        '^compare/([a-z0-9-]+)-vs-([a-z0-9-]+)-vs-([a-z0-9-]+)-vs-([a-z0-9-]+)/?$',
        'index.php?erh_compare=1&compare_slugs=$matches[1],$matches[2],$matches[3],$matches[4]',
        'top'
    );
}
add_action( 'init', 'erh_compare_rewrite_rules' );

/**
 * Register custom query vars.
 *
 * @param array $vars Existing query vars.
 * @return array Modified query vars.
 */
function erh_compare_query_vars( $vars ) {
    $vars[] = 'erh_compare';
    $vars[] = 'compare_slugs';
    $vars[] = 'compare_category';
    $vars[] = 'products';
    return $vars;
}
add_filter( 'query_vars', 'erh_compare_query_vars' );

/**
 * Load compare template when erh_compare query var is set.
 *
 * @param string $template The template to load.
 * @return string Modified template path.
 */
function erh_compare_template_include( $template ) {
    if ( get_query_var( 'erh_compare' ) ) {
        // Check for category landing page.
        $category = get_query_var( 'compare_category', '' );
        if ( ! empty( $category ) ) {
            $category_template = get_template_directory() . '/template-parts/compare/category.php';
            if ( file_exists( $category_template ) ) {
                return $category_template;
            }
        }

        // Default compare page template.
        $compare_template = get_template_directory() . '/page-compare.php';
        if ( file_exists( $compare_template ) ) {
            return $compare_template;
        }
    }
    return $template;
}
add_filter( 'template_include', 'erh_compare_template_include' );

/**
 * Fix 404 status for valid compare pages.
 *
 * WordPress sets 404 because there's no matching post, but our compare pages
 * are valid virtual pages. This resets the 404 status after the main query runs.
 */
function erh_compare_fix_404() {
    global $wp_query;

    // Check if this is a compare page (via query var or URL parameter).
    $is_compare = get_query_var( 'erh_compare' );
    $has_products_param = isset( $_GET['products'] ) && ! empty( $_GET['products'] );

    // Also check if URL path is /compare/ (for query string URLs before parse_request runs).
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
    $is_compare_url = preg_match( '#/compare/?(\?|$)#', $request_uri );

    if ( $is_compare || $has_products_param || ( $is_compare_url && $has_products_param ) ) {
        // This is a valid compare page, not a 404.
        $wp_query->is_404 = false;
        $wp_query->is_page = true;
        $wp_query->is_singular = false;
        $wp_query->is_archive = false;

        // Reset 404 status header.
        status_header( 200 );

        // Set erh_compare if not already set (for ?products= URLs).
        if ( ! get_query_var( 'erh_compare' ) && $has_products_param && $is_compare_url ) {
            set_query_var( 'erh_compare', 1 );
        }
    }
}
add_action( 'wp', 'erh_compare_fix_404' );

/**
 * Handle compare URLs before WordPress processes them.
 * This works even before permalinks are flushed.
 *
 * Handles:
 * - Hub page with ?products= query string
 * - Category landing pages
 * - Curated comparison redirects
 * - Dynamic comparison parsing
 *
 * @param WP $wp The WordPress environment instance.
 */
function erh_compare_parse_request( $wp ) {
    // Check if this is a compare URL.
    $request = trim( $wp->request, '/' );

    // Match /compare or /compare/...
    if ( $request === 'compare' || strpos( $request, 'compare/' ) === 0 ) {
        // Extract the path after /compare/.
        $path = str_replace( 'compare/', '', $request );
        $path = trim( $path, '/' );

        // Hub page: /compare/ (with or without ?products= query string)
        if ( empty( $path ) ) {
            $wp->query_vars['erh_compare'] = 1;

            // If ?products= is present, ensure it's captured as a query var.
            if ( isset( $_GET['products'] ) && ! empty( $_GET['products'] ) ) {
                $wp->query_vars['products'] = sanitize_text_field( wp_unslash( $_GET['products'] ) );
            }
            return;
        }

        // Check for category landing page: /compare/electric-scooters/.
        $category_map = erh_compare_category_map();
        if ( isset( $category_map[ $path ] ) ) {
            $wp->query_vars['erh_compare'] = 1;
            $wp->query_vars['compare_category'] = $path;
            return;
        }

        // Check if this is a curated comparison CPT slug.
        // The comparison CPT uses 'compare' as rewrite base, so the full slug would match.
        $curated = get_page_by_path( $path, OBJECT, 'comparison' );

        if ( $curated && $curated->post_status === 'publish' ) {
            // It's a curated comparison - let WordPress handle it via CPT.
            // UNSET erh_compare in case the rewrite rule already set it.
            unset( $wp->query_vars['erh_compare'] );
            unset( $wp->query_vars['compare_slugs'] );

            // Set up the query for the comparison CPT post.
            $wp->query_vars['comparison'] = $curated->post_name;
            $wp->query_vars['post_type'] = 'comparison';
            $wp->query_vars['name'] = $curated->post_name;
            return;
        }

        // Check for SEO slug pattern: /compare/product-a-vs-product-b/.
        if ( preg_match( '#^([a-z0-9-]+(?:-vs-[a-z0-9-]+)+)$#', $path, $matches ) ) {
            $slugs = explode( '-vs-', $matches[1] );

            // Only process 2-product comparisons for curated redirect check.
            if ( count( $slugs ) === 2 ) {
                // Check if a curated comparison exists for this pair (by product slugs).
                $curated_redirect = erh_check_curated_comparison( $slugs[0], $slugs[1] );
                if ( $curated_redirect ) {
                    // Prevent redirect loop: only redirect if URL is different.
                    $current_url = home_url( '/' . $request . '/' );
                    if ( trailingslashit( $curated_redirect ) !== trailingslashit( $current_url ) ) {
                        wp_redirect( $curated_redirect, 301 );
                        exit;
                    }
                    // Same URL means this IS the curated comparison, let CPT handle it.
                    return;
                }

                // No curated - check if URL order matches canonical order.
                $canonical_redirect = erh_check_canonical_order( $slugs[0], $slugs[1] );
                if ( $canonical_redirect ) {
                    $current_url = home_url( '/' . $request . '/' );
                    if ( trailingslashit( $canonical_redirect ) !== trailingslashit( $current_url ) ) {
                        wp_redirect( $canonical_redirect, 301 );
                        exit;
                    }
                }
            }

            // No curated comparison - use dynamic comparison.
            $wp->query_vars['erh_compare'] = 1;
            $wp->query_vars['compare_slugs'] = implode( ',', $slugs );
        }
    }
}
add_action( 'parse_request', 'erh_compare_parse_request' );

/**
 * Check if a curated comparison exists for a product pair.
 * If found, return the URL to redirect to.
 *
 * @param string $slug_1 First product slug.
 * @param string $slug_2 Second product slug.
 * @return string|null Curated comparison URL or null.
 */
function erh_check_curated_comparison( string $slug_1, string $slug_2 ): ?string {
    global $wpdb;

    // Get product IDs from slugs.
    $product_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'products'
             AND post_status = 'publish'
             AND post_name IN (%s, %s)",
            $slug_1,
            $slug_2
        )
    );

    if ( count( $product_ids ) !== 2 ) {
        return null;
    }

    $product_1_id = (int) $product_ids[0];
    $product_2_id = (int) $product_ids[1];

    // Check for curated comparison (either order).
    $curated_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'product_1'
             INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'product_2'
             WHERE p.post_type = 'comparison'
             AND p.post_status = 'publish'
             AND (
                 (pm1.meta_value = %d AND pm2.meta_value = %d)
                 OR (pm1.meta_value = %d AND pm2.meta_value = %d)
             )
             LIMIT 1",
            $product_1_id,
            $product_2_id,
            $product_2_id,
            $product_1_id
        )
    );

    if ( $curated_id ) {
        return get_permalink( $curated_id );
    }

    return null;
}

/**
 * Check if URL order matches canonical order for a product pair.
 * Returns canonical URL if redirect needed, null if order is already correct.
 *
 * Canonical order: lower product ID first (consistent, simple).
 *
 * @param string $slug_1 First product slug in URL.
 * @param string $slug_2 Second product slug in URL.
 * @return string|null Canonical URL if redirect needed, null otherwise.
 */
function erh_check_canonical_order( string $slug_1, string $slug_2 ): ?string {
    global $wpdb;

    // Get product IDs and slugs.
    $products = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT ID, post_name FROM {$wpdb->posts}
             WHERE post_type = 'products'
             AND post_status = 'publish'
             AND post_name IN (%s, %s)",
            $slug_1,
            $slug_2
        ),
        OBJECT_K
    );

    if ( count( $products ) !== 2 ) {
        return null;
    }

    // Get IDs for each slug.
    $id_1 = null;
    $id_2 = null;
    foreach ( $products as $id => $product ) {
        if ( $product->post_name === $slug_1 ) {
            $id_1 = (int) $id;
        } elseif ( $product->post_name === $slug_2 ) {
            $id_2 = (int) $id;
        }
    }

    if ( ! $id_1 || ! $id_2 ) {
        return null;
    }

    // Canonical order: lower ID first.
    if ( $id_1 < $id_2 ) {
        // Already in canonical order.
        return null;
    }

    // Needs redirect: swap order.
    return home_url( '/compare/' . $slug_2 . '-vs-' . $slug_1 . '/' );
}

/**
 * Check if current request is the compare page.
 *
 * @return bool True if on compare page.
 */
function erh_is_compare_page() {
    return (bool) get_query_var( 'erh_compare' );
}

/**
 * Check if current request is a compare category page.
 *
 * @return bool True if on compare category page.
 */
function erh_is_compare_category_page() {
    return ! empty( get_query_var( 'compare_category' ) );
}

/**
 * Get compare category data for current page.
 *
 * @return array|null Category data or null.
 */
function erh_get_compare_category(): ?array {
    $category_slug = get_query_var( 'compare_category', '' );
    if ( empty( $category_slug ) ) {
        return null;
    }

    $map = erh_compare_category_map();
    return $map[ $category_slug ] ?? null;
}

/**
 * Generate SEO-friendly compare URL from product IDs.
 *
 * @param array $product_ids Array of product post IDs.
 * @return string The comparison URL.
 */
function erh_get_compare_url( $product_ids ) {
    if ( empty( $product_ids ) || count( $product_ids ) < 2 ) {
        return home_url( '/compare/' );
    }

    // For 2-4 products, use SEO-friendly slug format.
    if ( count( $product_ids ) <= 4 ) {
        $slugs = [];
        foreach ( $product_ids as $id ) {
            $slug = get_post_field( 'post_name', $id );
            if ( $slug ) {
                $slugs[] = $slug;
            }
        }

        if ( count( $slugs ) === count( $product_ids ) ) {
            return home_url( '/compare/' . implode( '-vs-', $slugs ) . '/' );
        }
    }

    // Fallback to query string for 5+ products or if slugs can't be resolved.
    return home_url( '/compare/?products=' . implode( ',', array_map( 'intval', $product_ids ) ) );
}

/**
 * Generate canonical URL for compare page.
 * Used for SEO to normalize comparison order.
 *
 * @param array $product_ids Array of product post IDs.
 * @return string The canonical comparison URL.
 */
function erh_get_compare_canonical_url( $product_ids ) {
    if ( empty( $product_ids ) ) {
        return home_url( '/compare/' );
    }

    // Sort by popularity (or ID as fallback) for consistent canonical.
    $products_with_pop = [];
    foreach ( $product_ids as $id ) {
        $pop = get_post_meta( $id, '_popularity_score', true ) ?: 0;
        $products_with_pop[] = [ 'id' => $id, 'pop' => (int) $pop ];
    }

    // Sort by popularity descending, then by ID ascending.
    usort( $products_with_pop, function ( $a, $b ) {
        if ( $a['pop'] === $b['pop'] ) {
            return $a['id'] - $b['id'];
        }
        return $b['pop'] - $a['pop'];
    } );

    $sorted_ids = array_column( $products_with_pop, 'id' );

    return erh_get_compare_url( $sorted_ids );
}

/**
 * Get compare category URL.
 *
 * @param string $category_key Category key (escooter, ebike, etc.).
 * @return string Category compare URL.
 */
function erh_get_compare_category_url( string $category_key ): string {
    $slug = CategoryConfig::key_to_slug( $category_key );
    if ( $slug ) {
        return home_url( '/compare/' . $slug . '/' );
    }
    return home_url( '/compare/' );
}

/**
 * Set page title for compare page.
 *
 * @param array $title The page title parts.
 * @return array Modified title.
 */
function erh_compare_document_title( $title ) {
    if ( erh_is_compare_page() ) {
        // Category landing page title.
        $category = erh_get_compare_category();
        if ( $category ) {
            $title['title'] = 'Compare ' . $category['name'];
            return $title;
        }

        // Dynamic comparison title.
        $compare_slugs  = get_query_var( 'compare_slugs', '' );
        $products_param = isset( $_GET['products'] ) ? sanitize_text_field( wp_unslash( $_GET['products'] ) ) : '';

        $product_ids = [];
        if ( ! empty( $compare_slugs ) ) {
            $slugs = array_filter( explode( ',', $compare_slugs ) );
            if ( ! empty( $slugs ) ) {
                global $wpdb;
                $placeholders = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $product_ids = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT ID FROM {$wpdb->posts}
                        WHERE post_type = 'products'
                        AND post_status = 'publish'
                        AND post_name IN ({$placeholders})",
                        ...$slugs
                    )
                );
            }
        } elseif ( ! empty( $products_param ) ) {
            $product_ids = array_filter( array_map( 'absint', explode( ',', $products_param ) ) );
        }

        if ( ! empty( $product_ids ) ) {
            $names = [];
            foreach ( array_slice( $product_ids, 0, 3 ) as $pid ) {
                $names[] = get_the_title( $pid );
            }
            $vs_title = implode( ' vs ', $names );
            if ( count( $product_ids ) > 3 ) {
                $vs_title .= ' & more';
            }
            $title['title'] = $vs_title . ' - Comparison';
        } else {
            $title['title'] = 'Compare Electric Rides';
        }
    }

    return $title;
}
add_filter( 'document_title_parts', 'erh_compare_document_title' );

/**
 * Add Open Graph meta tags for compare page.
 */
function erh_compare_og_meta() {
    if ( ! erh_is_compare_page() ) {
        return;
    }

    // Category landing page OG.
    $category = erh_get_compare_category();
    if ( $category ) {
        echo '<meta property="og:title" content="Compare ' . esc_attr( $category['name'] ) . '">' . "\n";
        echo '<meta property="og:description" content="Compare the best ' . esc_attr( strtolower( $category['name'] ) ) . ' side-by-side.">' . "\n";
        echo '<meta property="og:type" content="website">' . "\n";
        return;
    }

    // Dynamic comparison OG.
    $compare_slugs  = get_query_var( 'compare_slugs', '' );
    $products_param = isset( $_GET['products'] ) ? sanitize_text_field( wp_unslash( $_GET['products'] ) ) : '';

    $product_ids = [];
    if ( ! empty( $compare_slugs ) ) {
        $slugs = array_filter( explode( ',', $compare_slugs ) );
        if ( ! empty( $slugs ) ) {
            global $wpdb;
            $placeholders = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $product_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts}
                    WHERE post_type = 'products'
                    AND post_status = 'publish'
                    AND post_name IN ({$placeholders})",
                    ...$slugs
                )
            );
        }
    } elseif ( ! empty( $products_param ) ) {
        $product_ids = array_filter( array_map( 'absint', explode( ',', $products_param ) ) );
    }

    if ( empty( $product_ids ) ) {
        return;
    }

    // Build title.
    $names = [];
    foreach ( array_slice( $product_ids, 0, 3 ) as $pid ) {
        $names[] = get_the_title( $pid );
    }
    $title = implode( ' vs ', $names );
    if ( count( $product_ids ) > 3 ) {
        $title .= ' & more';
    }

    // Get first product's thumbnail for OG image.
    $og_image = get_the_post_thumbnail_url( $product_ids[0], 'large' );

    echo '<meta property="og:title" content="' . esc_attr( $title . ' - Comparison' ) . '">' . "\n";
    echo '<meta property="og:description" content="Compare specs, prices, and features side-by-side.">' . "\n";
    echo '<meta property="og:type" content="website">' . "\n";
    if ( $og_image ) {
        echo '<meta property="og:image" content="' . esc_url( $og_image ) . '">' . "\n";
    }
}
add_action( 'wp_head', 'erh_compare_og_meta', 5 );

/**
 * Get compare page title data for SSR and JS.
 *
 * Returns an array with title string and components for dynamic updates.
 *
 * @return array Title data: ['title' => string, 'count' => int, 'category' => string, 'products' => array]
 */
function erh_get_compare_title_data(): array {
	$data = [
		'title'    => 'Compare Electric Rides',
		'count'    => 0,
		'category' => '',
		'products' => [],
	];

	if ( ! erh_is_compare_page() ) {
		return $data;
	}

	// Category landing page.
	$category = erh_get_compare_category();
	if ( $category ) {
		$count       = erh_get_compare_category_count( $category['key'] );
		$name_plural = $category['name_plural'] ?? $category['name'];
		$data['count']    = $count;
		$data['category'] = $name_plural;
		$data['title']    = sprintf( 'Compare %d %s', $count, $name_plural );
		return $data;
	}

	// Main hub (no category, no products selected).
	$compare_slugs  = get_query_var( 'compare_slugs', '' );
	$products_param = isset( $_GET['products'] ) ? sanitize_text_field( wp_unslash( $_GET['products'] ) ) : '';

	if ( empty( $compare_slugs ) && empty( $products_param ) ) {
		$count = erh_get_compare_total_count();
		$data['count'] = $count;
		$data['title'] = sprintf( 'Compare %d Electric Rides', $count );
		return $data;
	}

	// Product comparison - get product IDs.
	$product_ids = [];
	if ( ! empty( $compare_slugs ) ) {
		$slugs = array_filter( explode( ',', $compare_slugs ) );
		if ( ! empty( $slugs ) ) {
			global $wpdb;
			$placeholders = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$product_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					WHERE post_type = 'products'
					AND post_status = 'publish'
					AND post_name IN ({$placeholders})",
					...$slugs
				)
			);
		}
	} elseif ( ! empty( $products_param ) ) {
		$product_ids = array_filter( array_map( 'absint', explode( ',', $products_param ) ) );
	}

	if ( empty( $product_ids ) ) {
		return $data;
	}

	// Get product names and category.
	$names        = [];
	$product_type = '';
	foreach ( $product_ids as $pid ) {
		$names[] = get_the_title( $pid );
		if ( empty( $product_type ) ) {
			$product_type = get_field( 'product_type', $pid );
		}
	}

	$data['products'] = $names;
	$data['count']    = count( $names );

	// Determine category name from product type.
	if ( $product_type ) {
		$cat_config = CategoryConfig::get_by_type( $product_type );
		if ( $cat_config ) {
			$data['category'] = $cat_config['name'];
		}
	}

	// Build title based on product count.
	if ( count( $names ) <= 3 ) {
		// "Product A vs Product B [vs Product C]"
		$data['title'] = implode( ' vs ', $names );
	} else {
		// "Comparing X electric scooters"
		$cat_name = $data['category'] ?: 'Electric Rides';
		$data['title'] = sprintf( 'Comparing %d %s', count( $names ), $cat_name );
	}

	return $data;
}

/**
 * Get total product count for compare hub.
 *
 * @return int Total count of published products.
 */
function erh_get_compare_total_count(): int {
	$cache_key = 'erh_compare_total_count';
	$count     = get_transient( $cache_key );

	if ( false === $count ) {
		$count = (int) wp_count_posts( 'products' )->publish;
		set_transient( $cache_key, $count, HOUR_IN_SECONDS );
	}

	return $count;
}

/**
 * Get product count for a specific category.
 *
 * Uses product_type taxonomy (not meta field).
 * Taxonomy term slugs are singular: electric-scooter, electric-bike, etc.
 *
 * @param string $category_key Category key (escooter, ebike, etc.).
 * @return int Count of products in category.
 */
function erh_get_compare_category_count( string $category_key ): int {
	$cache_key = 'erh_compare_count_' . $category_key;
	$count     = get_transient( $cache_key );

	if ( false === $count ) {
		// Get product type (e.g., "Electric Scooter") and convert to taxonomy slug.
		$product_type = CategoryConfig::key_to_type( $category_key );
		if ( ! $product_type ) {
			return 0;
		}

		// Convert "Electric Scooter" → "electric-scooter" (taxonomy term slug format).
		$tax_slug = sanitize_title( $product_type );

		// Get term by slug.
		$term = get_term_by( 'slug', $tax_slug, 'product_type' );
		if ( ! $term || is_wp_error( $term ) ) {
			return 0;
		}

		$count = (int) $term->count;
		set_transient( $cache_key, $count, HOUR_IN_SECONDS );
	}

	return (int) $count;
}

/**
 * Override RankMath title for compare pages.
 *
 * @param string $title The title.
 * @return string Modified title.
 */
function erh_compare_rankmath_title( string $title ): string {
	if ( ! erh_is_compare_page() ) {
		return $title;
	}

	$data = erh_get_compare_title_data();
	return $data['title'];
}
add_filter( 'rank_math/frontend/title', 'erh_compare_rankmath_title', 20 );

/**
 * Override RankMath description for compare pages.
 *
 * @param string $description The description.
 * @return string Modified description.
 */
function erh_compare_rankmath_description( string $description ): string {
	if ( ! erh_is_compare_page() ) {
		return $description;
	}

	$data     = erh_get_compare_title_data();
	$category = erh_get_compare_category();

	if ( $category ) {
		return sprintf( 'Compare the best %s side-by-side. View specs, prices, and features to find the perfect ride.', strtolower( $category['name'] ) );
	}

	if ( ! empty( $data['products'] ) ) {
		if ( count( $data['products'] ) <= 3 ) {
			return sprintf( 'Compare %s side-by-side. View detailed specs, prices, and features.', implode( ', ', $data['products'] ) );
		}
		$cat_name = $data['category'] ? strtolower( $data['category'] ) : 'electric rides';
		return sprintf( 'Compare %d %s side-by-side. View detailed specs, prices, and features.', $data['count'], $cat_name );
	}

	return 'Compare electric scooters, e-bikes, and more side-by-side. Find the perfect electric ride with detailed specs and prices.';
}
add_filter( 'rank_math/frontend/description', 'erh_compare_rankmath_description', 20 );

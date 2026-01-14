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

/**
 * Category slug mapping for compare landing pages.
 *
 * @var array<string, array<string, string>>
 */
function erh_compare_category_map(): array {
    return [
        'electric-scooters'    => [ 'key' => 'escooter', 'name' => 'Electric Scooters', 'type' => 'Electric Scooter' ],
        'e-bikes'              => [ 'key' => 'ebike', 'name' => 'E-Bikes', 'type' => 'Electric Bike' ],
        'e-skateboards'        => [ 'key' => 'eskateboard', 'name' => 'E-Skateboards', 'type' => 'Electric Skateboard' ],
        'electric-unicycles'   => [ 'key' => 'euc', 'name' => 'Electric Unicycles', 'type' => 'Electric Unicycle' ],
        'hoverboards'          => [ 'key' => 'hoverboard', 'name' => 'Hoverboards', 'type' => 'Hoverboard' ],
    ];
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
 * Handle compare URLs before WordPress processes them.
 * This works even before permalinks are flushed.
 *
 * Handles:
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

        // Hub page: /compare/
        if ( empty( $path ) ) {
            $wp->query_vars['erh_compare'] = 1;
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
    $map = erh_compare_category_map();

    // Find the URL slug for this category key.
    foreach ( $map as $slug => $data ) {
        if ( $data['key'] === $category_key ) {
            return home_url( '/compare/' . $slug . '/' );
        }
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

<?php
/**
 * Compare page URL routing.
 *
 * Handles SEO-friendly URLs like /compare/product-a-vs-product-b/
 * and query string URLs like /compare?products=123,456
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register rewrite rules for comparison URLs.
 */
function erh_compare_rewrite_rules() {
    // Base compare page (with query string)
    add_rewrite_rule(
        '^compare/?$',
        'index.php?erh_compare=1',
        'top'
    );

    // Match /compare/product-a-vs-product-b/ (2 products)
    add_rewrite_rule(
        '^compare/([a-z0-9-]+)-vs-([a-z0-9-]+)/?$',
        'index.php?erh_compare=1&compare_slugs=$matches[1],$matches[2]',
        'top'
    );

    // Match /compare/a-vs-b-vs-c/ (3 products)
    add_rewrite_rule(
        '^compare/([a-z0-9-]+)-vs-([a-z0-9-]+)-vs-([a-z0-9-]+)/?$',
        'index.php?erh_compare=1&compare_slugs=$matches[1],$matches[2],$matches[3]',
        'top'
    );

    // Match /compare/a-vs-b-vs-c-vs-d/ (4 products)
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
 * @param WP $wp The WordPress environment instance.
 */
function erh_compare_parse_request( $wp ) {
    // Check if this is a compare URL
    $request = trim( $wp->request, '/' );

    // Match /compare or /compare/...
    if ( $request === 'compare' || strpos( $request, 'compare/' ) === 0 ) {
        $wp->query_vars['erh_compare'] = 1;

        // Check for SEO slug pattern: /compare/product-a-vs-product-b/
        if ( preg_match( '#^compare/([a-z0-9-]+(?:-vs-[a-z0-9-]+)+)/?$#', $request, $matches ) ) {
            $slugs = explode( '-vs-', $matches[1] );
            $wp->query_vars['compare_slugs'] = implode( ',', $slugs );
        }
    }
}
add_action( 'parse_request', 'erh_compare_parse_request' );

/**
 * Check if current request is the compare page.
 *
 * @return bool True if on compare page.
 */
function erh_is_compare_page() {
    return (bool) get_query_var( 'erh_compare' );
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
 * Set page title for compare page.
 *
 * @param array $title The page title parts.
 * @return array Modified title.
 */
function erh_compare_document_title( $title ) {
    if ( erh_is_compare_page() ) {
        $compare_slugs = get_query_var( 'compare_slugs', '' );
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

    $compare_slugs = get_query_var( 'compare_slugs', '' );
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

<?php
/**
 * Breadcrumb Helper Functions
 *
 * Breadcrumb navigation utilities.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ERH\CategoryConfig;

/**
 * Render breadcrumb navigation
 *
 * Single reusable function for all breadcrumbs across the site.
 * Takes an array of items, each with 'label' and optional 'url'.
 * By default, last item is treated as current page (no link).
 * Use 'is_link' => true to force an item to render as a link even if it's the last item.
 *
 * Items may include 'schema' => false to exclude from BreadcrumbList schema
 * (e.g., links to non-canonical ?category= filtered pages).
 *
 * @param array $items Array of breadcrumb items: [ ['label' => 'Home', 'url' => '/'], ... ]
 */
function erh_breadcrumb( array $items ): void {
    if ( empty( $items ) ) {
        return;
    }

    $count = count( $items );
    $output = '<nav class="breadcrumb" aria-label="Breadcrumb">';

    foreach ( $items as $index => $item ) {
        $is_last = ( $index === $count - 1 );
        $force_link = ! empty( $item['is_link'] );
        $has_url = ! empty( $item['url'] );

        // Render as link if: (not last AND has URL) OR (force_link AND has URL)
        if ( $has_url && ( ! $is_last || $force_link ) ) {
            $output .= sprintf(
                '<a href="%s">%s</a>',
                esc_url( $item['url'] ),
                esc_html( $item['label'] )
            );
            // Add separator only if not the last item
            if ( ! $is_last ) {
                $output .= '<span class="breadcrumb-sep" aria-hidden="true">/</span>';
            }
        } else {
            $output .= sprintf(
                '<span class="breadcrumb-current">%s</span>',
                esc_html( $item['label'] )
            );
        }
    }

    $output .= '</nav>';

    echo $output;
}

/**
 * Output BreadcrumbList JSON-LD schema from breadcrumb items.
 *
 * Only includes items that have a URL and are not excluded via 'schema' => false.
 * Call this after get_footer() alongside other page schemas.
 *
 * @param array $items Same items array passed to erh_breadcrumb().
 */
function erh_breadcrumb_schema( array $items ): void {
    $list_items = [];
    $position   = 1;

    foreach ( $items as $item ) {
        // Skip items without URL or explicitly excluded from schema.
        if ( empty( $item['url'] ) || ( isset( $item['schema'] ) && $item['schema'] === false ) ) {
            continue;
        }

        $list_items[] = [
            '@type'    => 'ListItem',
            'position' => $position,
            'name'     => $item['label'],
            'item'     => $item['url'],
        ];
        $position++;
    }

    if ( empty( $list_items ) ) {
        return;
    }

    $schema = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $list_items,
    ];

    echo '<script type="application/ld+json">' . "\n";
    echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES );
    echo "\n" . '</script>' . "\n";
}

/**
 * Auto-detect and render breadcrumbs for current post.
 *
 * Detects content type (review, buying guide, article) and product category
 * from WP categories, then builds: [Category] > [Content Type].
 * No current page title — the H1 handles that.
 *
 * For custom breadcrumbs (coupons, compare, tools, products),
 * use erh_breadcrumb() directly.
 */
function erh_the_breadcrumbs(): void {
    if ( ! is_singular( 'post' ) ) {
        // Fallback for pages/archives (unchanged).
        $items = [];

        if ( is_page() ) {
            $parent_id = wp_get_post_parent_id( get_the_ID() );
            if ( $parent_id ) {
                $items[] = [ 'label' => get_the_title( $parent_id ), 'url' => get_permalink( $parent_id ) ];
            }
            $items[] = [ 'label' => get_the_title() ];
        } elseif ( is_archive() ) {
            $items[] = [ 'label' => get_the_archive_title() ];
        }

        if ( ! empty( $items ) ) {
            erh_breadcrumb( $items );
        }
        return;
    }

    // --- Single post: detect category and content type ---

    $post_id  = get_the_ID();
    $items    = [];

    // Determine product category from WP category.
    $category_config = erh_get_post_category_config( $post_id );

    if ( $category_config ) {
        // First item: product category linking to its hub page.
        $items[] = [
            'label' => $category_config['name_plural'],
            'url'   => home_url( '/' . $category_config['slug'] . '/' ),
        ];
    }

    // Determine content type from tags.
    $tag_slugs    = wp_get_post_tags( $post_id, [ 'fields' => 'slugs' ] );
    $is_review    = is_array( $tag_slugs ) && in_array( 'review', $tag_slugs, true );
    $is_guide     = is_array( $tag_slugs ) && in_array( 'buying-guide', $tag_slugs, true );

    if ( $is_review ) {
        $reviews_url = erh_get_reviews_page_url( $category_config );
        $items[] = [
            'label'   => 'Reviews',
            'url'     => $reviews_url,
            'is_link' => true,
            'schema'  => erh_is_canonical_reviews_url( $category_config ),
        ];
    } elseif ( $is_guide ) {
        $guides_url = erh_get_guides_page_url( $category_config );
        $items[] = [
            'label'   => 'Buying Guides',
            'url'     => $guides_url,
            'is_link' => true,
            'schema'  => false, // No dedicated canonical guide pages yet.
        ];
    } else {
        $articles_url = erh_get_articles_page_url( $category_config );
        $items[] = [
            'label'   => 'Articles',
            'url'     => $articles_url,
            'is_link' => true,
            'schema'  => false, // Filtered view, not canonical.
        ];
    }

    if ( ! empty( $items ) ) {
        erh_breadcrumb( $items );
    }
}

/**
 * Get CategoryConfig for a post based on its WP category.
 *
 * @param int $post_id Post ID.
 * @return array|null CategoryConfig data or null.
 */
function erh_get_post_category_config( int $post_id ): ?array {
    $categories = get_the_category( $post_id );

    if ( empty( $categories ) || is_wp_error( $categories ) ) {
        return null;
    }

    // Try each category — find the first that matches a CategoryConfig slug.
    foreach ( $categories as $cat ) {
        $config = CategoryConfig::get_by_slug( $cat->slug );
        if ( $config ) {
            return $config;
        }
    }

    return null;
}

/**
 * Get the reviews page URL for a category.
 *
 * E-scooters has a dedicated canonical page; others use ?category= filter.
 *
 * @param array|null $category_config CategoryConfig data.
 * @return string Reviews page URL.
 */
function erh_get_reviews_page_url( ?array $category_config ): string {
    if ( ! $category_config ) {
        return home_url( '/reviews/' );
    }

    // E-scooters has a dedicated reviews page.
    if ( $category_config['key'] === 'escooter' ) {
        return home_url( '/electric-scooters/reviews/' );
    }

    return home_url( '/reviews/?category=' . $category_config['slug'] );
}

/**
 * Check if a category has a canonical (non-filtered) reviews URL.
 *
 * @param array|null $category_config CategoryConfig data.
 * @return bool True if the reviews URL is canonical.
 */
function erh_is_canonical_reviews_url( ?array $category_config ): bool {
    if ( ! $category_config ) {
        return false;
    }

    // Only e-scooters currently has a dedicated reviews page.
    return $category_config['key'] === 'escooter';
}

/**
 * Get the buying guides page URL for a category.
 *
 * @param array|null $category_config CategoryConfig data.
 * @return string Guides page URL.
 */
function erh_get_guides_page_url( ?array $category_config ): string {
    if ( ! $category_config ) {
        return home_url( '/articles/' );
    }

    return home_url( '/articles/?category=' . $category_config['slug'] . '&type=buying-guide' );
}

/**
 * Get the articles page URL for a category.
 *
 * @param array|null $category_config CategoryConfig data.
 * @return string Articles page URL.
 */
function erh_get_articles_page_url( ?array $category_config ): string {
    if ( ! $category_config ) {
        return home_url( '/articles/' );
    }

    return home_url( '/articles/?category=' . $category_config['slug'] );
}

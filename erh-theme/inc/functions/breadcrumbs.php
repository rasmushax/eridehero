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

/**
 * Render breadcrumb navigation
 *
 * Single reusable function for all breadcrumbs across the site.
 * Takes an array of items, each with 'label' and optional 'url'.
 * By default, last item is treated as current page (no link).
 * Use 'is_link' => true to force an item to render as a link even if it's the last item.
 *
 * @param array $items Array of breadcrumb items: [ ['label' => 'Home', 'url' => '/'], ['label' => 'Category', 'url' => '...', 'is_link' => true] ]
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
 * Auto-detect and render breadcrumbs for current page
 *
 * Convenience wrapper for standard post types.
 * For custom breadcrumbs, use erh_breadcrumb() directly.
 */
function erh_the_breadcrumbs(): void {
    $items = array();

    if ( is_singular( 'post' ) ) {
        $items[] = [ 'label' => 'Articles', 'url' => home_url( '/articles/' ) ];
        $items[] = [ 'label' => get_the_title() ];
    } elseif ( is_page() ) {
        // Check for parent page.
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
}

/**
 * Render review breadcrumb
 *
 * Custom breadcrumb for review posts: Category > Reviews > Post Title
 *
 * @param string $category_slug Category slug (e.g., 'e-scooters')
 * @param string $category_name Category display name (e.g., 'E-Scooters')
 */
function erh_review_breadcrumb( string $category_slug, string $category_name ): void {
    erh_breadcrumb( [
        [ 'label' => $category_name, 'url' => home_url( '/' . $category_slug . '/' ) ],
        [ 'label' => 'Reviews', 'url' => home_url( '/' . $category_slug . '-reviews/' ) ],
        [ 'label' => get_the_title() ],
    ] );
}

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
 * Last item is automatically treated as current page (no link).
 *
 * @param array $items Array of breadcrumb items: [ ['label' => 'Home', 'url' => '/'], ['label' => 'Current'] ]
 */
function erh_breadcrumb( array $items ): void {
    if ( empty( $items ) ) {
        return;
    }

    $count = count( $items );
    $output = '<nav class="breadcrumb" aria-label="Breadcrumb">';

    foreach ( $items as $index => $item ) {
        $is_last = ( $index === $count - 1 );

        if ( ! $is_last && ! empty( $item['url'] ) ) {
            $output .= sprintf(
                '<a href="%s">%s</a>',
                esc_url( $item['url'] ),
                esc_html( $item['label'] )
            );
            $output .= '<span class="breadcrumb-sep" aria-hidden="true">/</span>';
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

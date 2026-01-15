<?php
/**
 * Table of Contents Helper Functions
 *
 * TOC generation and content heading utilities.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get TOC items for product review pages
 *
 * Generates a structured array of TOC items including:
 * - Static sections (Quick take, Pros/cons, Prices, Tested performance, Full specs)
 * - Dynamic content headings (H2s with nested H3s from post content)
 *
 * @param int   $product_id      The product ID (for checking performance data, etc.).
 * @param array $options         Options for conditional sections.
 *                               'has_prices'      => bool - Show "Where to buy" section
 *                               'has_performance' => bool - Show "Tested performance" section
 *                               'content_post_id' => int  - Post ID to extract headings from (defaults to current post)
 * @return array Array of TOC items with 'id', 'label', and optional 'children'.
 */
function erh_get_toc_items( int $product_id, array $options = array() ): array {
    $has_prices       = $options['has_prices'] ?? false;
    $has_performance  = $options['has_performance'] ?? false;
    $content_post_id  = $options['content_post_id'] ?? get_the_ID();

    $items = array();

    // Static section: Quick take
    $items[] = array(
        'id'    => 'quick-take',
        'label' => 'Quick take',
    );

    // Static section: Pros & cons
    $items[] = array(
        'id'    => 'pros-cons',
        'label' => 'Pros & cons',
    );

    // Conditional section: Where to buy (only if product has prices)
    if ( $has_prices ) {
        $items[] = array(
            'id'    => 'prices',
            'label' => 'Where to buy',
        );
    }

    // Conditional section: Tested performance
    if ( $has_performance ) {
        $items[] = array(
            'id'    => 'tested-performance',
            'label' => 'Tested performance',
        );
    }

    // Dynamic sections: H2s from content become top-level items (with H3s as children)
    // Use content_post_id (the review post) not product_id
    $content_headings = erh_extract_content_headings( $content_post_id );
    foreach ( $content_headings as $heading ) {
        $items[] = $heading;
    }

    // Static section: Full specifications
    $items[] = array(
        'id'    => 'full-specs',
        'label' => 'Full specifications',
    );

    return $items;
}

/**
 * Extract headings from post content
 *
 * Parses H2 and H3 headings from post content.
 * H3s are nested as children of the preceding H2.
 * Handles duplicate IDs by appending -2, -3, etc.
 *
 * @param int $post_id The post ID.
 * @return array Array of heading items with 'id', 'label', and optional 'children'.
 */
function erh_extract_content_headings( int $post_id ): array {
    $post = get_post( $post_id );
    if ( ! $post || empty( $post->post_content ) ) {
        return array();
    }

    $content  = $post->post_content;
    $items    = array();
    $used_ids = array(); // Track used IDs to handle duplicates

    // Match all H2 and H3 headings
    // Pattern matches: <h2...>text</h2> or <h3...>text</h3>
    // Captures: 1=heading level (2|3), 2=attributes, 3=heading text
    $pattern = '/<h([23])([^>]*)>(.+?)<\/h\1>/is';

    if ( ! preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
        return array();
    }

    $current_h2 = null;

    foreach ( $matches as $match ) {
        $level = (int) $match[1];
        $attrs = $match[2];
        $text  = wp_strip_all_tags( $match[3] );

        // Extract existing ID from attributes or generate from text
        $base_id = '';
        if ( preg_match( '/id=["\']([^"\']+)["\']/', $attrs, $id_match ) ) {
            $base_id = $id_match[1];
        } else {
            $base_id = sanitize_title( $text );
        }

        if ( empty( $base_id ) || empty( $text ) ) {
            continue;
        }

        // Handle duplicate IDs
        $id = $base_id;
        if ( isset( $used_ids[ $base_id ] ) ) {
            $used_ids[ $base_id ]++;
            $id = $base_id . '-' . $used_ids[ $base_id ];
        } else {
            $used_ids[ $base_id ] = 1;
        }

        if ( $level === 2 ) {
            // Save previous H2 if it exists
            if ( $current_h2 !== null ) {
                $items[] = $current_h2;
            }

            // Start new H2
            $current_h2 = array(
                'id'       => $id,
                'label'    => $text,
                'children' => array(),
            );
        } elseif ( $level === 3 && $current_h2 !== null ) {
            // Add H3 as child of current H2
            $current_h2['children'][] = array(
                'id'    => $id,
                'label' => $text,
            );
        }
    }

    // Add last H2
    if ( $current_h2 !== null ) {
        $items[] = $current_h2;
    }

    // Clean up empty children arrays
    foreach ( $items as &$item ) {
        if ( empty( $item['children'] ) ) {
            unset( $item['children'] );
        }
    }

    return $items;
}

/**
 * Add IDs to headings in content that don't have them
 *
 * Filter for 'the_content' to ensure all H2/H3 headings have IDs for TOC linking.
 * Runs on all singular posts/pages/products for consistent TOC support.
 * Handles duplicates by appending -2, -3, etc.
 *
 * @param string $content The post content.
 * @return string Content with IDs added to headings.
 */
function erh_add_heading_ids( string $content ): string {
    // Only process on singular pages (posts, pages, products, etc.)
    if ( ! is_singular() ) {
        return $content;
    }

    // Track used IDs to handle duplicates
    $used_ids = array();

    // First pass: collect existing IDs
    if ( preg_match_all( '/<h[23][^>]*\bid=["\']([^"\']+)["\'][^>]*>/is', $content, $existing_ids ) ) {
        foreach ( $existing_ids[1] as $existing_id ) {
            $used_ids[ $existing_id ] = 1;
        }
    }

    // Pattern matches headings without an id attribute
    $pattern = '/<h([23])(?![^>]*\bid=)([^>]*)>(.+?)<\/h\1>/is';

    $content = preg_replace_callback( $pattern, function( $match ) use ( &$used_ids ) {
        $level   = $match[1];
        $attrs   = $match[2];
        $text    = $match[3];
        $base_id = sanitize_title( wp_strip_all_tags( $text ) );

        // Skip if we couldn't generate an ID
        if ( empty( $base_id ) ) {
            return $match[0];
        }

        // Handle duplicate IDs
        $id = $base_id;
        if ( isset( $used_ids[ $base_id ] ) ) {
            $used_ids[ $base_id ]++;
            $id = $base_id . '-' . $used_ids[ $base_id ];
        } else {
            $used_ids[ $base_id ] = 1;
        }

        return sprintf( '<h%s id="%s"%s>%s</h%s>', $level, esc_attr( $id ), $attrs, $text, $level );
    }, $content );

    return $content;
}
add_filter( 'the_content', 'erh_add_heading_ids', 5 ); // Early priority so IDs exist for other filters

/**
 * Estimate reading time for a post
 *
 * @param int $post_id Post ID.
 * @return int Minutes to read.
 */
function erh_get_reading_time( int $post_id ): int {
    $content    = get_post_field( 'post_content', $post_id );
    $word_count = str_word_count( wp_strip_all_tags( $content ) );
    $reading_time = ceil( $word_count / 200 ); // 200 words per minute

    return max( 1, (int) $reading_time );
}

/**
 * Get TOC from post content
 *
 * Extracts table of contents from post content by parsing H2/H3 headings.
 * Used for standard article posts (not product reviews).
 *
 * @param int|null $post_id Post ID (optional, uses current post).
 * @return array Array of TOC items.
 */
function erh_get_toc_from_content( ?int $post_id = null ): array {
    $post_id = $post_id ?? get_the_ID();
    $post    = get_post( $post_id );

    if ( ! $post || empty( $post->post_content ) ) {
        return array();
    }

    $content = apply_filters( 'the_content', $post->post_content );
    $items   = array();

    // Match h2 and h3 headings with IDs.
    if ( ! preg_match_all( '/<h([23])[^>]*\bid=["\']([^"\']+)["\'][^>]*>(.*?)<\/h\1>/is', $content, $matches, PREG_SET_ORDER ) ) {
        return array();
    }

    $current_h2 = null;

    foreach ( $matches as $match ) {
        $level = (int) $match[1];
        $id    = $match[2];
        $label = wp_strip_all_tags( $match[3] );

        // Skip empty labels.
        if ( empty( trim( $label ) ) ) {
            continue;
        }

        $item = array(
            'id'    => $id,
            'label' => $label,
        );

        if ( $level === 2 ) {
            // H2 - top level item.
            $item['children'] = array();
            $items[]          = $item;
            $current_h2       = count( $items ) - 1;
        } elseif ( $level === 3 && $current_h2 !== null ) {
            // H3 - child of current H2.
            $items[ $current_h2 ]['children'][] = $item;
        } else {
            // H3 without a parent H2 - add as top level.
            $items[] = $item;
        }
    }

    // Clean up empty children arrays.
    foreach ( $items as &$item ) {
        if ( isset( $item['children'] ) && empty( $item['children'] ) ) {
            unset( $item['children'] );
        }
    }

    return $items;
}

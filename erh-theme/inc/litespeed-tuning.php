<?php
/**
 * LiteSpeed Cache Tuning
 *
 * UCSS per-pagetype grouping and selector allowlist for dynamic components.
 * Ensures JS-rendered/conditional CSS is never stripped by UCSS optimization.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Generate UCSS per post type for consistent-layout types.
 *
 * Post types with consistent layouts across all entries get grouped
 * (one UCSS per type) to save QUIC.cloud quota:
 *   - products: all share the same single-product template
 *   - tool: all share the same calculator layout
 *   - comparison: all share the same compare template
 *
 * Post types with varied layouts stay per-URL for accuracy:
 *   - post: articles vs buying guides vs reviews use different templates
 *   - page: homepage vs compare hub vs search vs account are all different
 */
add_filter( 'litespeed_ucss_per_pagetype', function () {
    $type = get_post_type();

    return in_array( $type, [ 'products', 'tool', 'comparison' ], true );
} );

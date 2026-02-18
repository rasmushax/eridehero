<?php
/**
 * One-time migration: Copy old review ACF fields to new field names.
 *
 * Old → New:
 *   relationship  → review_product     (array → single ID, take first)
 *   bottomline    → review_quick_take
 *   pros          → review_pros
 *   cons          → review_cons
 *
 * Run with: wp eval-file scripts/migrate-review-fields.php
 * Dry run:  wp eval-file scripts/migrate-review-fields.php -- --dry-run
 */

$dry_run = in_array( '--dry-run', $args ?? [], true );

if ( $dry_run ) {
    WP_CLI::log( '=== DRY RUN — no changes will be made ===' );
}

// Get the "review" tag
$review_tag = get_term_by( 'slug', 'review', 'post_tag' );
if ( ! $review_tag ) {
    WP_CLI::error( 'Tag "review" not found.' );
}

// Get all posts with the "review" tag
$posts = get_posts( [
    'post_type'      => 'post',
    'tag_id'         => $review_tag->term_id,
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'fields'         => 'ids',
] );

WP_CLI::log( sprintf( 'Found %d review posts.', count( $posts ) ) );

$stats = [
    'product_set'    => 0,
    'quick_take_set' => 0,
    'pros_set'       => 0,
    'cons_set'       => 0,
    'skipped_empty'  => 0,
    'skipped_exists' => 0,
];

foreach ( $posts as $post_id ) {
    $title = get_the_title( $post_id );

    // --- review_product (from relationship) ---
    $old_relationship = get_post_meta( $post_id, 'relationship', true );
    $new_product      = get_post_meta( $post_id, 'review_product', true );

    if ( ! empty( $old_relationship ) && empty( $new_product ) ) {
        // relationship field stores serialized array — ACF returns array of IDs
        $product_ids = is_array( $old_relationship ) ? $old_relationship : maybe_unserialize( $old_relationship );
        $product_id  = is_array( $product_ids ) ? (int) reset( $product_ids ) : (int) $product_ids;

        if ( $product_id > 0 ) {
            if ( ! $dry_run ) {
                update_field( 'review_product', $product_id, $post_id );
            }
            WP_CLI::log( sprintf( '  [%d] %s → review_product = %d', $post_id, $title, $product_id ) );
            $stats['product_set']++;
        }
    } elseif ( ! empty( $new_product ) ) {
        $stats['skipped_exists']++;
    } else {
        $stats['skipped_empty']++;
    }

    // --- review_quick_take (from bottomline) ---
    $old_bottomline = get_post_meta( $post_id, 'bottomline', true );
    $new_quick_take = get_post_meta( $post_id, 'review_quick_take', true );

    if ( ! empty( $old_bottomline ) && empty( $new_quick_take ) ) {
        if ( ! $dry_run ) {
            update_field( 'review_quick_take', $old_bottomline, $post_id );
        }
        WP_CLI::log( sprintf( '  [%d] %s → review_quick_take set', $post_id, $title ) );
        $stats['quick_take_set']++;
    }

    // --- review_pros (from pros) ---
    $old_pros = get_post_meta( $post_id, 'pros', true );
    $new_pros = get_post_meta( $post_id, 'review_pros', true );

    if ( ! empty( $old_pros ) && empty( $new_pros ) ) {
        if ( ! $dry_run ) {
            update_field( 'review_pros', $old_pros, $post_id );
        }
        WP_CLI::log( sprintf( '  [%d] %s → review_pros set', $post_id, $title ) );
        $stats['pros_set']++;
    }

    // --- review_cons (from cons) ---
    $old_cons = get_post_meta( $post_id, 'cons', true );
    $new_cons = get_post_meta( $post_id, 'review_cons', true );

    if ( ! empty( $old_cons ) && empty( $new_cons ) ) {
        if ( ! $dry_run ) {
            update_field( 'review_cons', $old_cons, $post_id );
        }
        WP_CLI::log( sprintf( '  [%d] %s → review_cons set', $post_id, $title ) );
        $stats['cons_set']++;
    }
}

WP_CLI::log( '' );
WP_CLI::log( '=== Summary ===' );
WP_CLI::log( sprintf( 'review_product set:    %d', $stats['product_set'] ) );
WP_CLI::log( sprintf( 'review_quick_take set: %d', $stats['quick_take_set'] ) );
WP_CLI::log( sprintf( 'review_pros set:       %d', $stats['pros_set'] ) );
WP_CLI::log( sprintf( 'review_cons set:       %d', $stats['cons_set'] ) );
WP_CLI::log( sprintf( 'Skipped (already set): %d', $stats['skipped_exists'] ) );
WP_CLI::log( sprintf( 'Skipped (no old data): %d', $stats['skipped_empty'] ) );

if ( $dry_run ) {
    WP_CLI::log( '' );
    WP_CLI::warning( 'Dry run complete. Run without --dry-run to apply changes.' );
} else {
    WP_CLI::success( 'Migration complete.' );
}

<?php
/**
 * Fix Image Srcset - Replace orphan crop sizes with registered ones.
 *
 * Scans all published posts for images referencing crop sizes that no longer
 * exist in attachment metadata (old Oxygen sizes). Replaces with the closest
 * registered size so WordPress can generate proper srcset attributes.
 *
 * Usage: wp eval-file scripts/fix-image-srcset.php [dry-run]
 *
 * @package ERH
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'Run via WP-CLI: wp eval-file scripts/fix-image-srcset.php [dry-run]' );
}

$dry_run = in_array( 'dry-run', $args ?? [], true );

if ( $dry_run ) {
    WP_CLI::log( '=== DRY RUN — no changes will be made ===' );
}

// =========================================================================
// Phase 1: Audit — find all orphan image references
// =========================================================================

WP_CLI::log( 'Phase 1: Scanning posts for orphan image sizes...' );

global $wpdb;
$table = $wpdb->prefix . 'posts';

$posts = $wpdb->get_results(
    "SELECT ID, post_content FROM {$table}
     WHERE post_type = 'post'
       AND post_status = 'publish'
       AND post_content LIKE '%wp-content/uploads/%'",
    ARRAY_A
);

WP_CLI::log( sprintf( 'Found %d posts with image references.', count( $posts ) ) );

// Collect all orphan images: [ post_id => [ [ 'old_src' => ..., 'att_id' => ..., 'dims' => ... ], ... ] ]
$orphans_by_post = [];
$orphan_count    = 0;
$ok_count        = 0;
$no_id_count     = 0;

// Cache attachment metadata to avoid repeated lookups.
$meta_cache = [];

foreach ( $posts as $post ) {
    $content = $post['post_content'];
    $post_id = (int) $post['ID'];

    // Find all img tags with wp-image-{ID} class and a cropped src.
    // Pattern: <img ... src="...uploads/path/name-WIDTHxHEIGHT.ext" ... class="...wp-image-NNN..."
    if ( ! preg_match_all(
        '/<img\s[^>]*?src=["\']([^"\']*wp-content\/uploads\/[^"\']*-(\d+x\d+)\.(jpg|jpeg|png|gif))["\'][^>]*?class=["\'][^"\']*wp-image-(\d+)[^"\']*["\'][^>]*>/i',
        $content,
        $matches,
        PREG_SET_ORDER
    ) ) {
        // Also try reverse order: class before src.
        preg_match_all(
            '/<img\s[^>]*?class=["\'][^"\']*wp-image-(\d+)[^"\']*["\'][^>]*?src=["\']([^"\']*wp-content\/uploads\/[^"\']*-(\d+x\d+)\.(jpg|jpeg|png|gif))["\'][^>]*>/i',
            $content,
            $matches2,
            PREG_SET_ORDER
        );

        // Normalize matches2 to same format: [0=full, 1=src, 2=dims, 3=ext, 4=att_id]
        $matches = [];
        foreach ( $matches2 as $m ) {
            $matches[] = [ $m[0], $m[2], $m[3], $m[4], $m[1] ];
        }
    }

    if ( empty( $matches ) ) {
        continue;
    }

    foreach ( $matches as $m ) {
        $src    = $m[1];
        $dims   = $m[2];
        $ext    = $m[3];
        $att_id = (int) $m[4];

        if ( ! $att_id ) {
            $no_id_count++;
            continue;
        }

        // Get attachment metadata (cached).
        if ( ! isset( $meta_cache[ $att_id ] ) ) {
            $meta_cache[ $att_id ] = wp_get_attachment_metadata( $att_id );
        }
        $meta = $meta_cache[ $att_id ];

        if ( ! $meta || empty( $meta['sizes'] ) ) {
            continue;
        }

        // Check if this exact dimension exists in metadata.
        $found = false;
        foreach ( $meta['sizes'] as $size_name => $size_data ) {
            $size_dims = $size_data['width'] . 'x' . $size_data['height'];
            if ( $size_dims === $dims ) {
                $found = true;
                break;
            }
        }

        // Also check the full/original size.
        if ( ! $found && isset( $meta['width'], $meta['height'] ) ) {
            $orig_dims = $meta['width'] . 'x' . $meta['height'];
            if ( $orig_dims === $dims ) {
                $found = true;
            }
        }

        if ( $found ) {
            $ok_count++;
            continue;
        }

        // Orphan! Find the best replacement.
        list( $old_w, $old_h ) = explode( 'x', $dims );
        $old_w = (int) $old_w;
        $old_h = (int) $old_h;

        $best_size   = null;
        $best_file   = null;
        $best_score  = PHP_INT_MAX;

        foreach ( $meta['sizes'] as $size_name => $size_data ) {
            $sw = (int) $size_data['width'];
            $sh = (int) $size_data['height'];

            // Only consider sizes with the same extension.
            $size_ext = pathinfo( $size_data['file'], PATHINFO_EXTENSION );
            if ( strtolower( $size_ext ) !== strtolower( $ext ) ) {
                continue;
            }

            // Score: prefer same width, then closest width, then closest area.
            $w_diff = abs( $sw - $old_w );
            $h_diff = abs( $sh - $old_h );

            // Prefer exact width match, then close width, penalize big differences.
            $score = $w_diff * 10 + $h_diff;

            // Strong preference for same width.
            if ( $sw === $old_w ) {
                $score = $h_diff;
            }

            // Prefer not to upscale (smaller is ok, bigger is slightly penalized).
            if ( $sw > $old_w * 1.5 ) {
                $score += 5000;
            }

            // Avoid tiny thumbnails.
            if ( $sw < 200 && $old_w > 300 ) {
                $score += 10000;
            }

            if ( $score < $best_score ) {
                $best_score = $score;
                $best_size  = $size_name;
                $best_file  = $size_data['file'];
            }
        }

        if ( ! $best_file ) {
            WP_CLI::warning( "  No replacement found for {$dims} (att #{$att_id}) in post #{$post_id}" );
            continue;
        }

        if ( ! isset( $orphans_by_post[ $post_id ] ) ) {
            $orphans_by_post[ $post_id ] = [];
        }

        // Build the new src by replacing the filename.
        $old_filename = basename( $src );
        $new_filename = $best_file;
        $new_src      = str_replace( $old_filename, $new_filename, $src );

        $orphans_by_post[ $post_id ][] = [
            'old_src' => $src,
            'new_src' => $new_src,
            'att_id'  => $att_id,
            'old_dim' => $dims,
            'new_size' => $best_size,
        ];
        $orphan_count++;
    }
}

WP_CLI::log( '' );
WP_CLI::log( '=== Audit Results ===' );
WP_CLI::log( "Images with valid srcset:    {$ok_count}" );
WP_CLI::log( "Orphan images (no srcset):   {$orphan_count}" );
WP_CLI::log( "Posts to update:             " . count( $orphans_by_post ) );
if ( $no_id_count > 0 ) {
    WP_CLI::log( "Skipped (no wp-image-ID):    {$no_id_count}" );
}

if ( $orphan_count === 0 ) {
    WP_CLI::success( 'No orphan images found. All images have proper srcset potential.' );
    return;
}

// =========================================================================
// Phase 2: Replace orphan src references in post content
// =========================================================================

WP_CLI::log( '' );
WP_CLI::log( 'Phase 2: Replacing orphan image references...' );
WP_CLI::log( '' );

$updated_posts = 0;
$replaced      = 0;
$errors        = 0;

foreach ( $orphans_by_post as $post_id => $replacements ) {
    $content = $wpdb->get_var(
        $wpdb->prepare( "SELECT post_content FROM {$table} WHERE ID = %d", $post_id )
    );

    $original = $content;
    $post_replaced = 0;

    foreach ( $replacements as $r ) {
        $old = $r['old_src'];
        $new = $r['new_src'];

        if ( strpos( $content, $old ) !== false ) {
            $content = str_replace( $old, $new, $content );
            $post_replaced++;

            if ( $dry_run ) {
                WP_CLI::log( "  [DRY] Post #{$post_id}: {$r['old_dim']} → {$r['new_size']} (att #{$r['att_id']})" );
                WP_CLI::log( "         " . basename( $old ) );
                WP_CLI::log( "       → " . basename( $new ) );
            }
        }
    }

    if ( $content === $original ) {
        continue;
    }

    if ( ! $dry_run ) {
        $result = $wpdb->update(
            $table,
            [ 'post_content' => $content ],
            [ 'ID' => $post_id ],
            [ '%s' ],
            [ '%d' ]
        );

        if ( $result === false ) {
            WP_CLI::warning( "Failed to update post #{$post_id}" );
            $errors++;
            continue;
        }

        // Clear post cache.
        clean_post_cache( $post_id );
    }

    $updated_posts++;
    $replaced += $post_replaced;

    if ( ! $dry_run ) {
        WP_CLI::log( "  Updated post #{$post_id}: {$post_replaced} images replaced" );
    }
}

WP_CLI::log( '' );
WP_CLI::log( '=== Summary ===' );
WP_CLI::log( "Posts updated:     {$updated_posts}" );
WP_CLI::log( "Images replaced:   {$replaced}" );
if ( $errors > 0 ) {
    WP_CLI::warning( "Errors: {$errors}" );
}

if ( $dry_run ) {
    WP_CLI::log( '' );
    WP_CLI::log( 'Run without dry-run to apply changes.' );
} else {
    WP_CLI::success( "Replaced {$replaced} orphan image references across {$updated_posts} posts." );
}

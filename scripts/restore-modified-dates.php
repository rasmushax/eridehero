<?php
/**
 * Restore Post Modified Dates from Live Site
 *
 * Pulls modified dates from the production site (eridehero.com) via REST API
 * and updates staging posts to match. Only affects post_type=post.
 *
 * Usage: wp eval-file scripts/restore-modified-dates.php [dry-run]
 *
 * The Oxygen block migrations updated modified dates on all affected posts.
 * This script restores them to the real values from the live site.
 *
 * @package ERH
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script must be run via WP-CLI: wp eval-file scripts/restore-modified-dates.php' );
}

$dry_run = in_array( 'dry-run', $args ?? [], true );

if ( $dry_run ) {
    WP_CLI::log( '=== DRY RUN — no changes will be made ===' );
}

WP_CLI::log( 'Fetching post modified dates from live site...' );

// Fetch all posts from live site REST API (paginated).
$live_posts = [];
$page       = 1;
$per_page   = 100;
$api_base   = 'https://eridehero.com/wp-json/wp/v2/posts';

while ( true ) {
    $url = add_query_arg(
        [
            'per_page' => $per_page,
            'page'     => $page,
            '_fields'  => 'id,modified,modified_gmt',
            'status'   => 'publish',
        ],
        $api_base
    );

    $response = wp_remote_get( $url, [
        'timeout' => 30,
        'headers' => [
            'User-Agent' => 'ERH-Migration/1.0',
        ],
    ] );

    if ( is_wp_error( $response ) ) {
        WP_CLI::error( 'API request failed: ' . $response->get_error_message() );
    }

    $status_code = wp_remote_retrieve_response_code( $response );
    if ( $status_code !== 200 ) {
        // 400 = page beyond total, we're done.
        if ( $status_code === 400 && $page > 1 ) {
            break;
        }
        WP_CLI::error( "API returned status {$status_code} on page {$page}" );
    }

    $body  = wp_remote_retrieve_body( $response );
    $posts = json_decode( $body, true );

    if ( empty( $posts ) || ! is_array( $posts ) ) {
        break;
    }

    foreach ( $posts as $post ) {
        $live_posts[ $post['id'] ] = [
            'modified'     => $post['modified'],
            'modified_gmt' => $post['modified_gmt'],
        ];
    }

    $total_pages = (int) wp_remote_retrieve_header( $response, 'x-wp-totalpages' );
    $total_posts = (int) wp_remote_retrieve_header( $response, 'x-wp-total' );

    if ( $page === 1 ) {
        WP_CLI::log( "Live site has {$total_posts} posts across {$total_pages} pages." );
    }

    WP_CLI::log( "  Fetched page {$page}/{$total_pages} (" . count( $posts ) . ' posts)' );

    if ( $page >= $total_pages ) {
        break;
    }

    $page++;
}

WP_CLI::log( 'Fetched ' . count( $live_posts ) . ' posts from live site.' );

if ( empty( $live_posts ) ) {
    WP_CLI::error( 'No posts fetched. Aborting.' );
}

// Now compare with staging and update where different.
global $wpdb;

$updated  = 0;
$skipped  = 0;
$notfound = 0;

foreach ( $live_posts as $post_id => $live_dates ) {
    // Check if post exists on staging.
    $staging_post = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT post_modified, post_modified_gmt FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'post'",
            $post_id
        ),
        ARRAY_A
    );

    if ( ! $staging_post ) {
        $notfound++;
        continue;
    }

    // Convert REST API format (ISO 8601) to MySQL format.
    // REST returns: 2024-01-15T10:30:00 — MySQL needs: 2024-01-15 10:30:00
    $live_modified     = str_replace( 'T', ' ', $live_dates['modified'] );
    $live_modified_gmt = str_replace( 'T', ' ', $live_dates['modified_gmt'] );

    // Check if dates already match.
    if ( $staging_post['post_modified'] === $live_modified && $staging_post['post_modified_gmt'] === $live_modified_gmt ) {
        $skipped++;
        continue;
    }

    if ( $dry_run ) {
        WP_CLI::log( "  [DRY] Post {$post_id}: {$staging_post['post_modified']} → {$live_modified}" );
    } else {
        $result = $wpdb->update(
            $wpdb->posts,
            [
                'post_modified'     => $live_modified,
                'post_modified_gmt' => $live_modified_gmt,
            ],
            [ 'ID' => $post_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        if ( $result === false ) {
            WP_CLI::warning( "Failed to update post {$post_id}" );
            continue;
        }
    }

    $updated++;
}

WP_CLI::log( '' );
WP_CLI::log( '=== Summary ===' );
WP_CLI::log( "Updated:   {$updated}" );
WP_CLI::log( "Unchanged: {$skipped}" );
WP_CLI::log( "Not found: {$notfound} (exist on live but not staging)" );

if ( ! $dry_run && $updated > 0 ) {
    // Clear object cache so WP picks up the new dates.
    wp_cache_flush();
    WP_CLI::success( "Restored modified dates for {$updated} posts." );
} elseif ( $dry_run ) {
    WP_CLI::log( '' );
    WP_CLI::log( 'Run without dry-run to apply changes.' );
} else {
    WP_CLI::success( 'All dates already match — nothing to update.' );
}

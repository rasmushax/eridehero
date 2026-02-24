<?php
/**
 * Find all posts containing WPForms references.
 *
 * Usage: wp eval-file scripts/find-wpforms.php
 */

if (!defined('ABSPATH')) {
    exit('Run via WP-CLI: wp eval-file scripts/find-wpforms.php');
}

global $wpdb;

$posts = $wpdb->get_results(
    "SELECT ID, post_title, post_name, post_type, post_status
     FROM {$wpdb->posts}
     WHERE (post_content LIKE '%wpforms%' OR post_content LIKE '%wp:wpforms%')
     ORDER BY post_type, ID"
);

if (empty($posts)) {
    WP_CLI::log('No posts with WPForms references found.');
    return;
}

WP_CLI::log(sprintf('Found %d posts with WPForms references:', count($posts)));
WP_CLI::log('');

foreach ($posts as $p) {
    // Get the actual shortcode/block to show context.
    $content = $wpdb->get_var($wpdb->prepare(
        "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d",
        $p->ID
    ));

    $matches = [];
    // Find wpforms shortcodes.
    preg_match_all('/\[wpforms[^\]]*\]/', $content, $shortcode_matches);
    // Find wpforms blocks.
    preg_match_all('/<!-- wp:wpforms[^-].*?\/-->/', $content, $block_matches);

    $refs = array_merge($shortcode_matches[0] ?? [], $block_matches[0] ?? []);

    WP_CLI::log(sprintf(
        '%d | %s | %s | %s (/%s/)',
        $p->ID,
        $p->post_type,
        $p->post_status,
        $p->post_title,
        $p->post_name
    ));

    foreach ($refs as $ref) {
        WP_CLI::log('  → ' . trim($ref));
    }

    WP_CLI::log('');
}

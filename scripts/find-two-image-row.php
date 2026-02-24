<?php
/**
 * Find all posts containing ovsb-two-image-row blocks and show their content.
 *
 * Usage: wp eval-file scripts/find-two-image-row.php
 */

if (!defined('ABSPATH')) {
    exit('Run via WP-CLI: wp eval-file scripts/find-two-image-row.php');
}

global $wpdb;

$posts = $wpdb->get_results(
    "SELECT ID, post_title, post_name, post_content
     FROM {$wpdb->posts}
     WHERE post_content LIKE '%ovsb-two-image-row%'
       AND post_status = 'publish'
     ORDER BY ID ASC"
);

if (empty($posts)) {
    WP_CLI::log('No published posts with ovsb-two-image-row blocks found.');
    return;
}

WP_CLI::log(sprintf('Found %d posts:', count($posts)));
WP_CLI::log('');

foreach ($posts as $p) {
    preg_match_all('/<!-- wp:oxygen-vsb\/ovsb-two-image-row\s+(\{.*?\})\s*\/-->/s', $p->post_content, $matches);

    WP_CLI::log(sprintf(
        '%d | %s (/%s/) — %d block(s)',
        $p->ID,
        $p->post_title,
        $p->post_name,
        count($matches[0])
    ));

    foreach ($matches[1] as $i => $json) {
        $attrs = json_decode($json, true);
        if (!is_array($attrs)) {
            WP_CLI::log('  Block ' . ($i + 1) . ': (invalid JSON)');
            continue;
        }

        // Show image URLs or IDs from attributes.
        $keys = array_keys($attrs);
        WP_CLI::log('  Block ' . ($i + 1) . ' keys: ' . implode(', ', $keys));
    }

    WP_CLI::log('');
}

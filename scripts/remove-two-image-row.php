<?php
/**
 * Remove ovsb-two-image-row blocks from published posts.
 *
 * Usage: wp eval-file scripts/remove-two-image-row.php
 */

if (!defined('ABSPATH')) {
    exit('Run via WP-CLI: wp eval-file scripts/remove-two-image-row.php');
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
    WP_CLI::success('No posts with ovsb-two-image-row blocks found.');
    return;
}

$total = 0;

foreach ($posts as $p) {
    $content = $p->post_content;
    $count = preg_match_all('/<!-- wp:oxygen-vsb\/ovsb-two-image-row\s+\{.*?\}\s*\/-->/s', $content);
    $content = preg_replace('/<!-- wp:oxygen-vsb\/ovsb-two-image-row\s+\{.*?\}\s*\/-->/s', '', $content);
    $content = preg_replace('/\n{3,}/', "\n\n", $content);

    $wpdb->update(
        $wpdb->posts,
        ['post_content' => $content],
        ['ID' => $p->ID],
        ['%s'],
        ['%d']
    );
    clean_post_cache($p->ID);

    WP_CLI::log(sprintf('%d | %s — %d block(s) removed', $p->ID, $p->post_title, $count));
    $total += $count;
}

WP_CLI::success(sprintf('Removed %d blocks from %d posts.', $total, count($posts)));

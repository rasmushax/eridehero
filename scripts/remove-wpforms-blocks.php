<?php
/**
 * Remove all WPForms blocks from published posts.
 *
 * Usage: wp eval-file scripts/remove-wpforms-blocks.php [dry-run]
 *
 * @package ERH
 */

if (!defined('ABSPATH')) {
    exit('Run via WP-CLI: wp eval-file scripts/remove-wpforms-blocks.php');
}

$dry_run = in_array('dry-run', $args ?? [], true);

if ($dry_run) {
    WP_CLI::log('=== DRY RUN MODE — no changes will be saved ===');
}

WP_CLI::log('');
WP_CLI::log('WPForms Block Removal');
WP_CLI::log('=====================');
WP_CLI::log('');

global $wpdb;

$posts = $wpdb->get_results(
    "SELECT ID, post_title, post_name, post_content
     FROM {$wpdb->posts}
     WHERE post_status = 'publish'
       AND post_type IN ('post', 'page', 'products')
       AND post_content LIKE '%wp:wpforms%'
     ORDER BY ID ASC"
);

if (empty($posts)) {
    WP_CLI::success('No published posts with WPForms blocks found.');
    return;
}

WP_CLI::log(sprintf('Found %d posts to process.', count($posts)));
WP_CLI::log('');

$total_removed = 0;
$total_posts   = 0;
$errors        = [];

foreach ($posts as $post) {
    $content  = $post->post_content;
    $original = $content;
    $removed  = 0;

    // Count wpforms blocks before removal.
    $removed = preg_match_all('/<!-- wp:wpforms\/form-selector\s+\{.*?\}\s*\/-->/s', $content);

    // Remove wpforms blocks.
    $content = preg_replace('/<!-- wp:wpforms\/form-selector\s+\{.*?\}\s*\/-->/s', '', $content);

    // Clean up double blank lines.
    $content = preg_replace('/\n{3,}/', "\n\n", $content);

    if ($content === $original) {
        continue;
    }

    $total_removed += $removed;
    $total_posts++;

    WP_CLI::log(sprintf(
        '[%d] %s (/%s/) — %d block(s) removed',
        $post->ID,
        $post->post_title,
        $post->post_name,
        $removed
    ));

    if (!$dry_run) {
        $result = $wpdb->update(
            $wpdb->posts,
            ['post_content' => $content],
            ['ID' => $post->ID],
            ['%s'],
            ['%d']
        );

        if ($result === false) {
            WP_CLI::warning(sprintf('  FAILED to save post %d', $post->ID));
            $errors[] = $post->ID;
        } else {
            clean_post_cache($post->ID);
            WP_CLI::log('  Saved');
        }
    }
}

WP_CLI::log('');
WP_CLI::log('=====================');
WP_CLI::log('Summary');
WP_CLI::log('=====================');
WP_CLI::log(sprintf('Posts modified:  %d', $total_posts));
WP_CLI::log(sprintf('Blocks removed: %d', $total_removed));

if (!empty($errors)) {
    WP_CLI::warning(sprintf('Errors in %d posts: %s', count($errors), implode(', ', $errors)));
}

if ($dry_run) {
    WP_CLI::log('');
    WP_CLI::warning('DRY RUN — no changes were saved. Run without dry-run arg to apply.');
} else {
    WP_CLI::success('All WPForms blocks removed.');
}

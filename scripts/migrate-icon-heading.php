<?php
/**
 * Icon Heading Block Migration Script
 *
 * Converts Oxygen ovsb-icon-h3 blocks to acf/icon-heading blocks.
 * All 8 instances are in post 10447 (Electric Scooter Throttles).
 *
 * Usage: wp eval-file scripts/migrate-icon-heading.php [dry-run]
 *
 * Oxygen field patterns (numeric ID is 10487 for this post):
 *   headline-4-{N}_string           → icon_heading_title
 *   fancy_icon-3-{N}_icon           → icon_heading_icon (Lineariconsicon-cross-circle → x, absent → check)
 *
 * Key lessons applied from OXYGEN_MIGRATION.md:
 *   - Uses $wpdb->update() instead of wp_update_post() to avoid slashing
 *   - Verifies with json_decode() after save
 *   - Positional dry-run arg (not --dry-run)
 *
 * @package ERH
 */

if (!defined('ABSPATH')) {
    exit('This script must be run via WP-CLI: wp eval-file scripts/migrate-icon-heading.php');
}

// WP-CLI eats --flags, so use positional arg.
$dry_run = in_array('dry-run', $args ?? [], true);

if ($dry_run) {
    WP_CLI::log('=== DRY RUN MODE — no changes will be saved ===');
}

WP_CLI::log('');
WP_CLI::log('Icon Heading Block Migration');
WP_CLI::log('============================');
WP_CLI::log('');

global $wpdb;

// Find posts containing ovsb-icon-h3 blocks.
$posts = $wpdb->get_results(
    "SELECT ID, post_title, post_name, post_content
     FROM {$wpdb->posts}
     WHERE post_status = 'publish'
       AND post_type IN ('post', 'page', 'products')
       AND post_content LIKE '%ovsb-icon-h3%'
     ORDER BY ID ASC"
);

if (empty($posts)) {
    WP_CLI::success('No posts found with ovsb-icon-h3 blocks.');
    return;
}

WP_CLI::log(sprintf('Found %d posts to process.', count($posts)));
WP_CLI::log('');

$total_replaced = 0;
$total_posts    = 0;
$errors         = [];

// Icon mapping from Oxygen Linearicons to our SVG sprite names.
$icon_map = [
    'Lineariconsicon-cross-circle' => 'x',
];

foreach ($posts as $post) {
    $content      = $post->post_content;
    $original     = $content;
    $replaced     = 0;
    $block_report = [];

    // Match ovsb-icon-h3 blocks.
    $content = preg_replace_callback(
        '/<!-- wp:oxygen-vsb\/ovsb-icon-h3\s+(\{.*?\})\s*\/-->/s',
        function ($match) use (&$replaced, &$block_report, $icon_map) {
            $attrs = json_decode($match[1], true);

            if (!is_array($attrs)) {
                $block_report[] = 'ovsb-icon-h3 (FAILED to parse JSON)';
                return $match[0]; // Leave unchanged.
            }

            // Extract title (headline-4-{N}_string).
            $title = '';
            foreach ($attrs as $key => $val) {
                if (preg_match('/^headline-4-\d+_string$/', $key) && !empty($val)) {
                    $title = trim(html_entity_decode($val, ENT_QUOTES, 'UTF-8'));
                    break;
                }
            }

            if (empty($title)) {
                $block_report[] = 'ovsb-icon-h3 (empty title, removed)';
                $replaced++;
                return '';
            }

            // Extract icon (fancy_icon-3-{N}_icon). Default = check.
            $icon = 'check';
            foreach ($attrs as $key => $val) {
                if (preg_match('/^fancy_icon-3-\d+_icon$/', $key) && !empty($val)) {
                    $icon = $icon_map[$val] ?? 'check';
                    break;
                }
            }

            $replaced++;
            $block_report[] = sprintf(
                'ovsb-icon-h3 → icon-heading ("%s", icon: %s)',
                $title,
                $icon
            );

            return build_icon_heading_block($icon, 'h3', $title);
        },
        $content
    );

    // Skip if nothing changed.
    if ($content === $original) {
        continue;
    }

    // Clean up double blank lines left by removed blocks.
    $content = preg_replace('/\n{3,}/', "\n\n", $content);

    $total_replaced += $replaced;
    $total_posts++;

    // Report.
    WP_CLI::log(sprintf(
        '[%d] %s (%s)',
        $post->ID,
        $post->post_title,
        $post->post_name
    ));

    foreach ($block_report as $line) {
        WP_CLI::log("  → {$line}");
    }

    WP_CLI::log(sprintf('  Replaced: %d blocks', $replaced));

    // Save unless dry run.
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

            // Verify blocks are valid JSON.
            verify_icon_heading_blocks($post->ID, $content, $errors);
        }
    }

    WP_CLI::log('');
}

// Summary.
WP_CLI::log('============================');
WP_CLI::log('Migration Summary');
WP_CLI::log('============================');
WP_CLI::log(sprintf('Posts modified:   %d', $total_posts));
WP_CLI::log(sprintf('Blocks replaced: %d', $total_replaced));

if (!empty($errors)) {
    WP_CLI::warning(sprintf('Errors in %d posts: %s', count($errors), implode(', ', $errors)));
}

if ($dry_run) {
    WP_CLI::log('');
    WP_CLI::warning('DRY RUN — no changes were saved. Run without dry-run arg to apply.');
} else {
    WP_CLI::success('Migration complete.');
}


// ═══════════════════════════════════════════
// Helper Functions
// ═══════════════════════════════════════════

/**
 * Build ACF icon-heading block markup.
 *
 * @param string $icon  Icon name (check, x, etc.).
 * @param string $level Heading level (h2, h3, h4, h5).
 * @param string $title Heading text.
 * @return string Gutenberg block comment.
 */
function build_icon_heading_block(string $icon, string $level, string $title): string {
    $block_data = [
        'name' => 'acf/icon-heading',
        'data' => [
            'icon_heading_icon'   => $icon,
            '_icon_heading_icon'  => 'field_erh_icon_heading_icon',
            'icon_heading_level'  => $level,
            '_icon_heading_level' => 'field_erh_icon_heading_level',
            'icon_heading_title'  => $title,
            '_icon_heading_title' => 'field_erh_icon_heading_title',
        ],
        'mode' => 'preview',
    ];

    $json = wp_json_encode($block_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return '<!-- wp:acf/icon-heading ' . $json . ' /-->';
}

/**
 * Verify icon-heading blocks in saved content have valid JSON.
 *
 * @param int    $post_id The post ID.
 * @param string $content The saved content.
 * @param array  &$errors Array to append error post IDs.
 * @return void
 */
function verify_icon_heading_blocks(int $post_id, string $content, array &$errors): void {
    $blocks = parse_blocks($content);

    foreach ($blocks as $b) {
        if (($b['blockName'] ?? '') !== 'acf/icon-heading') {
            continue;
        }

        $title = $b['attrs']['data']['icon_heading_title'] ?? '';

        if (empty($title)) {
            WP_CLI::warning(sprintf('  VERIFY: Post %d has empty icon-heading block', $post_id));
            $errors[] = $post_id;
        }
    }
}

<?php
/**
 * Spec Group Block Migration Script
 *
 * Converts Oxygen ovsb-commuting-scooter-specs blocks to acf/spec-group blocks.
 * Known instances: 3 blocks in post 8102 ("Electric Scooter Air Pumps").
 *
 * Usage: wp eval-file scripts/migrate-spec-group.php [dry-run]
 *
 * Oxygen field patterns (numeric ID varies per block instance):
 *   text_block-{N}-{ID}_string → spec value as "<b>Label:</b> Value"
 *
 * Key lessons applied from OXYGEN_MIGRATION.md:
 *   - Uses $wpdb->update() instead of wp_update_post() to avoid slashing
 *   - Strips newlines before JSON encoding
 *   - Runs html_entity_decode() on extracted text
 *   - Verifies with json_decode() after save
 *   - Positional dry-run arg (not --dry-run)
 *
 * @package ERH
 */

if (!defined('ABSPATH')) {
    exit('This script must be run via WP-CLI: wp eval-file scripts/migrate-spec-group.php');
}

// WP-CLI eats --flags, so use positional arg.
$dry_run = in_array('dry-run', $args ?? [], true);

if ($dry_run) {
    WP_CLI::log('=== DRY RUN MODE — no changes will be saved ===');
}

WP_CLI::log('');
WP_CLI::log('Spec Group Block Migration');
WP_CLI::log('==========================');
WP_CLI::log('');

global $wpdb;

// Find posts containing ovsb-commuting-scooter-specs blocks.
$posts = $wpdb->get_results(
    "SELECT ID, post_title, post_name, post_content
     FROM {$wpdb->posts}
     WHERE post_status = 'publish'
       AND post_type IN ('post', 'page', 'products')
       AND post_content LIKE '%ovsb-commuting-scooter-specs%'
     ORDER BY ID ASC"
);

if (empty($posts)) {
    WP_CLI::success('No posts found with ovsb-commuting-scooter-specs blocks.');
    return;
}

WP_CLI::log(sprintf('Found %d posts to process.', count($posts)));
WP_CLI::log('');

$total_replaced = 0;
$total_posts    = 0;
$errors         = [];

foreach ($posts as $post) {
    $content      = $post->post_content;
    $original     = $content;
    $replaced     = 0;
    $block_report = [];

    // Match ovsb-commuting-scooter-specs blocks.
    $content = preg_replace_callback(
        '/<!-- wp:oxygen-vsb\/ovsb-commuting-scooter-specs\s+(\{.*?\})\s*\/-->/s',
        function ($match) use (&$replaced, &$block_report, $post) {
            $attrs = json_decode($match[1], true);

            if (!is_array($attrs)) {
                $block_report[] = 'ovsb-commuting-scooter-specs (FAILED to parse JSON)';
                return $match[0]; // Leave unchanged.
            }

            // Extract spec values from text_block-{N}-{ID}_string fields.
            // Each value is formatted as "<b>Label:</b> Value" or "<b>Label</b>: Value".
            $specs = [];
            foreach ($attrs as $key => $val) {
                if (preg_match('/^text_block-\d+-\d+_string$/', $key) && !empty($val)) {
                    $parsed = parse_spec_value($val);
                    if ($parsed) {
                        $specs[] = $parsed;
                    }
                }
            }

            if (empty($specs)) {
                $replaced++;
                $block_report[] = 'ovsb-commuting-scooter-specs (empty, removed)';
                return '';
            }

            $replaced++;
            $spec_labels = array_map(fn($s) => $s['title'], $specs);
            $block_report[] = sprintf(
                'ovsb-commuting-scooter-specs → spec-group (%d specs: %s)',
                count($specs),
                implode(', ', $spec_labels)
            );

            return build_spec_group_block($specs, '2');
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
            verify_spec_group_blocks($post->ID, $content, $errors);
        }
    }

    WP_CLI::log('');
}

// ─── Summary ───
WP_CLI::log('==========================');
WP_CLI::log('Migration Summary');
WP_CLI::log('==========================');
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
 * Parse a spec value from Oxygen format.
 *
 * Handles formats like:
 * - "<b>Weight:</b> 16.9 oz (0.48 kg)"
 * - "<b>Weight</b>: 16.9 oz"
 * - "<strong>Weight:</strong> 16.9 oz"
 *
 * @param string $html Raw HTML from Oxygen text_block field.
 * @return array{title: string, value: string}|null Parsed spec or null if unparseable.
 */
function parse_spec_value(string $html): ?array {
    // Decode HTML entities first.
    $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');

    // Try to split on </b> or </strong>.
    if (preg_match('/<(?:b|strong)>(.*?)<\/(?:b|strong)>\s*:?\s*(.*)/si', $html, $m)) {
        $title = trim(strip_tags($m[1]));
        $value = trim(strip_tags($m[2]));

        // Strip trailing colon from title if present.
        $title = rtrim($title, ':');
        // Strip leading colon from value if present.
        $value = ltrim($value, ':');
        $value = trim($value);

        if (!empty($title)) {
            return ['title' => $title, 'value' => $value];
        }
    }

    // Fallback: try splitting on first colon.
    $plain = trim(strip_tags($html));
    $colon_pos = strpos($plain, ':');
    if ($colon_pos !== false && $colon_pos < 50) {
        $title = trim(substr($plain, 0, $colon_pos));
        $value = trim(substr($plain, $colon_pos + 1));
        if (!empty($title)) {
            return ['title' => $title, 'value' => $value];
        }
    }

    return null;
}

/**
 * Build ACF spec-group block markup.
 *
 * @param array  $specs   Array of ['title' => ..., 'value' => ...] pairs.
 * @param string $columns Number of columns ('1' or '2').
 * @return string Gutenberg block comment.
 */
function build_spec_group_block(array $specs, string $columns): string {
    $data = [
        'spec_group_columns'  => $columns,
        '_spec_group_columns' => 'field_erh_spec_group_columns',
        'spec_group_specs'    => count($specs),
        '_spec_group_specs'   => 'field_erh_spec_group_specs',
    ];

    // Add each spec as repeater sub-fields.
    foreach ($specs as $i => $spec) {
        $data["spec_group_specs_{$i}_spec_title"]  = str_replace(["\n", "\r"], '', $spec['title']);
        $data["_spec_group_specs_{$i}_spec_title"]  = 'field_erh_spec_group_title';
        $data["spec_group_specs_{$i}_spec_value"]  = str_replace(["\n", "\r"], '', $spec['value']);
        $data["_spec_group_specs_{$i}_spec_value"]  = 'field_erh_spec_group_value';
    }

    $block_data = [
        'name' => 'acf/spec-group',
        'data' => $data,
        'mode' => 'preview',
    ];

    $json = wp_json_encode($block_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return '<!-- wp:acf/spec-group ' . $json . ' /-->';
}

/**
 * Verify spec-group blocks in saved content have valid JSON.
 *
 * @param int    $post_id The post ID.
 * @param string $content The saved content.
 * @param array  &$errors Array to append error post IDs.
 * @return void
 */
function verify_spec_group_blocks(int $post_id, string $content, array &$errors): void {
    $blocks = parse_blocks($content);

    foreach ($blocks as $b) {
        if (($b['blockName'] ?? '') !== 'acf/spec-group') {
            continue;
        }

        $specs_count = $b['attrs']['data']['spec_group_specs'] ?? 0;

        if (empty($specs_count)) {
            WP_CLI::warning(sprintf('  VERIFY: Post %d has empty spec-group block', $post_id));
            $errors[] = $post_id;
        } else {
            WP_CLI::log(sprintf('  VERIFY: Post %d spec-group OK (%d specs)', $post_id, $specs_count));
        }
    }
}

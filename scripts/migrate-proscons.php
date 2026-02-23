<?php
/**
 * Pros & Cons Block Migration Script
 *
 * Converts Oxygen ovsb-pros-cons blocks to acf/proscons blocks.
 *
 * Usage: wp eval-file scripts/migrate-proscons.php [dry-run]
 *
 * Oxygen field patterns (numeric ID is typically 2751 but may vary):
 *   headline-7-{N}_string           → proscons_pro_header (default "Pros")
 *   _rich_text-4-{N}_richtext       → proscons_pros (strip HTML to plain lines)
 *   headline-10-{N}_string          → proscons_con_header (default "Cons")
 *   _rich_text-6-{N}_richtext       → proscons_cons (strip HTML to plain lines)
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
    exit('This script must be run via WP-CLI: wp eval-file scripts/migrate-proscons.php');
}

// WP-CLI eats --flags, so use positional arg.
$dry_run = in_array('dry-run', $args ?? [], true);

if ($dry_run) {
    WP_CLI::log('=== DRY RUN MODE — no changes will be saved ===');
}

WP_CLI::log('');
WP_CLI::log('Pros & Cons Block Migration');
WP_CLI::log('===========================');
WP_CLI::log('');

global $wpdb;

// Find posts containing ovsb-pros-cons blocks.
$posts = $wpdb->get_results(
    "SELECT ID, post_title, post_name, post_content
     FROM {$wpdb->posts}
     WHERE post_status = 'publish'
       AND post_type IN ('post', 'page', 'products')
       AND post_content LIKE '%ovsb-pros-cons%'
     ORDER BY ID ASC"
);

if (empty($posts)) {
    WP_CLI::success('No posts found with ovsb-pros-cons blocks.');
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

    // Match ovsb-pros-cons blocks.
    $content = preg_replace_callback(
        '/<!-- wp:oxygen-vsb\/ovsb-pros-cons\s+(\{.*?\})\s*\/-->/s',
        function ($match) use (&$replaced, &$block_report, $post) {
            $attrs = json_decode($match[1], true);

            if (!is_array($attrs)) {
                $block_report[] = 'ovsb-pros-cons (FAILED to parse JSON)';
                return $match[0]; // Leave unchanged.
            }

            // Extract pros header (headline-7-{N}_string).
            $pro_header = '';
            foreach ($attrs as $key => $val) {
                if (preg_match('/^headline-7-\d+_string$/', $key) && !empty($val)) {
                    $pro_header = trim(strip_tags(html_entity_decode($val, ENT_QUOTES, 'UTF-8')));
                    break;
                }
            }

            // Extract pros body (_rich_text-4-{N}_richtext).
            $pros_html = '';
            foreach ($attrs as $key => $val) {
                if (preg_match('/^_rich_text-4-\d+_richtext$/', $key) && !empty($val)) {
                    $pros_html = $val;
                    break;
                }
            }

            // Extract cons header (headline-10-{N}_string).
            $con_header = '';
            foreach ($attrs as $key => $val) {
                if (preg_match('/^headline-10-\d+_string$/', $key) && !empty($val)) {
                    $con_header = trim(strip_tags(html_entity_decode($val, ENT_QUOTES, 'UTF-8')));
                    break;
                }
            }

            // Extract cons body (_rich_text-6-{N}_richtext).
            $cons_html = '';
            foreach ($attrs as $key => $val) {
                if (preg_match('/^_rich_text-6-\d+_richtext$/', $key) && !empty($val)) {
                    $cons_html = $val;
                    break;
                }
            }

            // Convert HTML lists to plain text lines.
            $pros_lines = extract_list_items($pros_html);
            $cons_lines = extract_list_items($cons_html);

            if (empty($pros_lines) && empty($cons_lines)) {
                $replaced++;
                $block_report[] = 'ovsb-pros-cons (empty, removed)';
                return '';
            }

            $header_info = '';
            if (!empty($pro_header) && strtolower($pro_header) !== 'pros') {
                $header_info = " [{$pro_header} / {$con_header}]";
            }

            $replaced++;
            $block_report[] = sprintf(
                'ovsb-pros-cons → proscons (%d pros, %d cons)%s',
                count($pros_lines),
                count($cons_lines),
                $header_info
            );

            return build_proscons_block($pro_header, $pros_lines, $con_header, $cons_lines);
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
            verify_proscons_blocks($post->ID, $content, $errors);
        }
    }

    WP_CLI::log('');
}

// ─── Summary ───
WP_CLI::log('===========================');
WP_CLI::log('Migration Summary');
WP_CLI::log('===========================');
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
 * Extract plain text items from HTML list markup.
 *
 * Handles various formats found in Oxygen data:
 * - Simple: <ul><li>Item text</li></ul>
 * - Nested: <ul><li><p><strong>Label:</strong> Description</p></li></ul>
 * - With spans: <ul><li><span ...>Text</span></li></ul>
 * - With embedded Gutenberg comments: <!-- wp:list-item -->
 *
 * @param string $html Raw HTML from Oxygen rich text field.
 * @return array Array of plain text strings.
 */
function extract_list_items(string $html): array {
    if (empty($html)) {
        return [];
    }

    // Remove embedded Gutenberg block comments.
    $html = preg_replace('/<!--\s*\/?wp:\S+.*?-->/s', '', $html);

    // Extract content from <li> tags.
    if (!preg_match_all('/<li[^>]*>(.*?)<\/li>/si', $html, $matches)) {
        return [];
    }

    $items = [];
    foreach ($matches[1] as $item) {
        // Strip all HTML tags.
        $text = strip_tags($item);

        // Decode HTML entities.
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Normalize whitespace (collapse multiple spaces, trim).
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (!empty($text)) {
            $items[] = $text;
        }
    }

    return $items;
}

/**
 * Build ACF proscons block markup.
 *
 * @param string $pro_header Custom pros header (empty = default "Pros").
 * @param array  $pros       Array of pros text strings.
 * @param string $con_header Custom cons header (empty = default "Cons").
 * @param array  $cons       Array of cons text strings.
 * @return string Gutenberg block comment.
 */
function build_proscons_block(string $pro_header, array $pros, string $con_header, array $cons): string {
    $pros_text = implode("\n", $pros);
    $cons_text = implode("\n", $cons);

    // Strip newlines from field values for JSON safety.
    // Use a unique separator that won't appear in content, then restore after encoding.
    $pros_text = str_replace(["\r\n", "\r"], "\n", $pros_text);
    $cons_text = str_replace(["\r\n", "\r"], "\n", $cons_text);

    $block_data = [
        'name' => 'acf/proscons',
        'data' => [
            'proscons_pros'     => $pros_text,
            '_proscons_pros'    => 'field_erh_proscons_pros',
            'proscons_cons'     => $cons_text,
            '_proscons_cons'    => 'field_erh_proscons_cons',
            'proscons_heading'  => 'h3',
            '_proscons_heading' => 'field_erh_proscons_heading',
        ],
        'mode' => 'preview',
    ];

    // Only include custom headers if they differ from defaults.
    if (!empty($pro_header) && strtolower($pro_header) !== 'pros') {
        // Strip trailing colon for consistency (e.g. "Buy It If:" → "Buy It If").
        $pro_header = rtrim($pro_header, ':');
        $block_data['data']['proscons_pro_header']  = $pro_header;
        $block_data['data']['_proscons_pro_header'] = 'field_erh_proscons_pro_header';
    }

    if (!empty($con_header) && strtolower($con_header) !== 'cons') {
        $con_header = rtrim($con_header, ':');
        $block_data['data']['proscons_con_header']  = $con_header;
        $block_data['data']['_proscons_con_header'] = 'field_erh_proscons_con_header';
    }

    // Use wp_json_encode with newlines escaped properly.
    // ACF textareas store newlines as literal \n in JSON.
    $json = wp_json_encode($block_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return '<!-- wp:acf/proscons ' . $json . ' /-->';
}

/**
 * Verify proscons blocks in saved content have valid JSON.
 *
 * @param int    $post_id The post ID.
 * @param string $content The saved content.
 * @param array  &$errors Array to append error post IDs.
 * @return void
 */
function verify_proscons_blocks(int $post_id, string $content, array &$errors): void {
    $blocks = parse_blocks($content);

    foreach ($blocks as $b) {
        if (($b['blockName'] ?? '') !== 'acf/proscons') {
            continue;
        }

        $pros = $b['attrs']['data']['proscons_pros'] ?? '';
        $cons = $b['attrs']['data']['proscons_cons'] ?? '';

        if (empty($pros) && empty($cons)) {
            WP_CLI::warning(sprintf('  VERIFY: Post %d has empty proscons block', $post_id));
            $errors[] = $post_id;
        }
    }
}

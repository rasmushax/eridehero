<?php
/**
 * Oxygen Block Migration Script
 *
 * Converts Oxygen Builder blocks to ACF blocks and removes discount codes.
 *
 * Usage: wp eval-file scripts/migrate-oxygen-blocks.php [dry-run]
 *
 * Converts:
 *   ovsb-tip            → acf/callout
 *   ovsb-tip-rich-text  → acf/callout
 *   ovsb-icon-rich-text → acf/callout
 *   ovsb-greybox-with-icon → acf/greybox
 *
 * Removes:
 *   ovsb-discount-code  → deleted (no replacement)
 *
 * @package ERH
 */

if (!defined('ABSPATH')) {
    exit('This script must be run via WP-CLI: wp eval-file scripts/migrate-oxygen-blocks.php');
}

// WP-CLI eats --flags, so use positional arg: wp eval-file script.php dry-run
$dry_run = in_array('dry-run', $args ?? [], true);

if ($dry_run) {
    WP_CLI::log('=== DRY RUN MODE — no changes will be saved ===');
}

WP_CLI::log('');
WP_CLI::log('Oxygen Block Migration');
WP_CLI::log('======================');
WP_CLI::log('');

// Block types to migrate.
$migrate_types = [
    'ovsb-tip',
    'ovsb-tip-rich-text',
    'ovsb-icon-rich-text',
    'ovsb-greybox-with-icon',
];

// Block types to remove entirely.
$remove_types = [
    'ovsb-discount-code',
];

$all_types = array_merge($migrate_types, $remove_types);

// Build LIKE clauses for the query.
$like_clauses = [];
foreach ($all_types as $type) {
    $like_clauses[] = "post_content LIKE '%" . esc_sql($type) . "%'";
}

global $wpdb;
$where = implode(' OR ', $like_clauses);
$posts = $wpdb->get_results(
    "SELECT ID, post_title, post_name, post_content
     FROM {$wpdb->posts}
     WHERE post_status = 'publish'
       AND post_type IN ('post', 'page', 'products')
       AND ({$where})
     ORDER BY ID ASC"
);

if (empty($posts)) {
    WP_CLI::success('No posts found with Oxygen blocks to migrate.');
    return;
}

WP_CLI::log(sprintf('Found %d posts to process.', count($posts)));
WP_CLI::log('');

$total_replaced = 0;
$total_removed  = 0;
$total_posts    = 0;

foreach ($posts as $post) {
    $content      = $post->post_content;
    $original     = $content;
    $replaced     = 0;
    $removed      = 0;
    $block_report = [];

    // ─── 1. Migrate ovsb-tip ───
    $content = preg_replace_callback(
        '/<!-- wp:oxygen-vsb\/ovsb-tip\s+(\{.*?\})\s*\/-->/s',
        function ($match) use (&$replaced, &$block_report) {
            $attrs = json_decode($match[1], true);
            $data  = $attrs ?? [];

            // Extract title (field key: text_block-4-85_string).
            $title = '';
            foreach ($data as $key => $val) {
                if (preg_match('/^text_block-4-\d+_string$/', $key) && !empty($val)) {
                    $title = trim(strip_tags($val));
                    break;
                }
            }

            // Extract body (field key: text_block-7-85_string).
            $body = '';
            foreach ($data as $key => $val) {
                if (preg_match('/^text_block-7-\d+_string$/', $key) && !empty($val)) {
                    $body = trim($val);
                    break;
                }
            }

            if (empty($body)) {
                $replaced++;
                $block_report[] = 'ovsb-tip (empty, removed)';
                return '';
            }

            // Wrap plain text in <p> if not already HTML.
            if (strpos($body, '<') === false) {
                $body = '<p>' . $body . '</p>';
            } elseif (!preg_match('/^<[a-z]/i', trim($body))) {
                $body = '<p>' . $body . '</p>';
            }

            $style = determine_callout_style($title, $data);

            $replaced++;
            $block_report[] = "ovsb-tip → callout ({$style})";
            return build_callout_block($style, $title, $body);
        },
        $content
    );

    // ─── 2. Migrate ovsb-tip-rich-text ───
    $content = preg_replace_callback(
        '/<!-- wp:oxygen-vsb\/ovsb-tip-rich-text\s+(\{.*?\})\s*\/-->/s',
        function ($match) use (&$replaced, &$block_report) {
            $attrs = json_decode($match[1], true);
            $data  = $attrs ?? [];

            // Extract title (field key: text_block-4-3629_string).
            $title = '';
            foreach ($data as $key => $val) {
                if (preg_match('/^text_block-4-\d+_string$/', $key) && !empty($val)) {
                    $title = trim(strip_tags($val));
                    break;
                }
            }

            // Extract body (field key: _rich_text-7-3629_richtext).
            $body = '';
            foreach ($data as $key => $val) {
                if (preg_match('/^_rich_text-7-\d+_richtext$/', $key) && !empty($val)) {
                    $body = trim($val);
                    break;
                }
            }

            if (empty($body)) {
                $replaced++;
                $block_report[] = 'ovsb-tip-rich-text (empty, removed)';
                return '';
            }

            // Extract icon for style hint.
            $icon = '';
            foreach ($data as $key => $val) {
                if (preg_match('/^fancy_icon-\d+-\d+_icon$/', $key) && !empty($val)) {
                    $icon = $val;
                    break;
                }
            }

            $style = determine_callout_style($title, $data, $icon);

            $replaced++;
            $block_report[] = "ovsb-tip-rich-text → callout ({$style})";
            return build_callout_block($style, $title, $body);
        },
        $content
    );

    // ─── 3. Migrate ovsb-icon-rich-text ───
    $content = preg_replace_callback(
        '/<!-- wp:oxygen-vsb\/ovsb-icon-rich-text\s+(\{.*?\})\s*\/-->/s',
        function ($match) use (&$replaced, &$block_report) {
            $attrs = json_decode($match[1], true);
            $data  = $attrs ?? [];

            // Extract title (field key: text_block-5-15371_string).
            $title = '';
            foreach ($data as $key => $val) {
                if (preg_match('/^text_block-5-\d+_string$/', $key) && !empty($val)) {
                    $title = trim(strip_tags($val));
                    break;
                }
            }

            // Extract body (field key: _rich_text-7-15371_richtext).
            $body = '';
            foreach ($data as $key => $val) {
                if (preg_match('/^_rich_text-7-\d+_richtext$/', $key) && !empty($val)) {
                    $body = trim($val);
                    break;
                }
            }

            if (empty($body)) {
                $replaced++;
                $block_report[] = 'ovsb-icon-rich-text (empty, removed)';
                return '';
            }

            // Extract icon for style hint.
            $icon = '';
            foreach ($data as $key => $val) {
                if (preg_match('/^fancy_icon-\d+-\d+_icon$/', $key) && !empty($val)) {
                    $icon = $val;
                    break;
                }
            }

            $style = determine_callout_style($title, $data, $icon);

            $replaced++;
            $block_report[] = "ovsb-icon-rich-text → callout ({$style})";
            return build_callout_block($style, $title, $body);
        },
        $content
    );

    // ─── 4. Migrate ovsb-greybox-with-icon ───
    $content = preg_replace_callback(
        '/<!-- wp:oxygen-vsb\/ovsb-greybox-with-icon\s+(\{.*?\})\s*\/-->/s',
        function ($match) use (&$replaced, &$block_report) {
            $attrs = json_decode($match[1], true);
            $data  = $attrs ?? [];

            // Extract heading (field key: headline-9-6929_string).
            $heading = '';
            foreach ($data as $key => $val) {
                if (preg_match('/^headline-\d+-\d+_string$/', $key) && !empty($val)) {
                    $heading = trim(strip_tags($val));
                    break;
                }
            }

            // Extract body (field key: _rich_text-11-6929_richtext).
            $body = '';
            foreach ($data as $key => $val) {
                if (preg_match('/^_rich_text-\d+-\d+_richtext$/', $key) && !empty($val)) {
                    $body = trim($val);
                    break;
                }
            }

            if (empty($heading) && empty($body)) {
                $replaced++;
                $block_report[] = 'ovsb-greybox-with-icon (empty, removed)';
                return '';
            }

            // Extract icon for mapping.
            $icon_raw = '';
            foreach ($data as $key => $val) {
                if (preg_match('/^fancy_icon-\d+-\d+_icon$/', $key) && !empty($val)) {
                    $icon_raw = $val;
                    break;
                }
            }

            $icon = determine_greybox_icon($icon_raw);

            $replaced++;
            $block_report[] = "ovsb-greybox-with-icon → greybox (icon: {$icon})";
            return build_greybox_block($icon, $heading, $body);
        },
        $content
    );

    // ─── 5. Remove ovsb-discount-code ───
    $content = preg_replace_callback(
        '/<!-- wp:oxygen-vsb\/ovsb-discount-code\s+(\{.*?\})\s*\/-->/s',
        function ($match) use (&$removed, &$block_report) {
            $removed++;
            $block_report[] = 'ovsb-discount-code (removed)';
            return '';
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
    $total_removed  += $removed;
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

    if ($replaced > 0) {
        WP_CLI::log(sprintf('  Replaced: %d blocks', $replaced));
    }
    if ($removed > 0) {
        WP_CLI::log(sprintf('  Removed: %d blocks', $removed));
    }

    // Save unless dry run.
    if (!$dry_run) {
        $result = wp_update_post([
            'ID'           => $post->ID,
            'post_content' => $content,
        ], true);

        if (is_wp_error($result)) {
            WP_CLI::warning(sprintf('  FAILED to save post %d: %s', $post->ID, $result->get_error_message()));
        } else {
            WP_CLI::log('  ✓ Saved');
        }
    }

    WP_CLI::log('');
}

// ─── Summary ───
WP_CLI::log('======================');
WP_CLI::log('Migration Summary');
WP_CLI::log('======================');
WP_CLI::log(sprintf('Posts modified:   %d', $total_posts));
WP_CLI::log(sprintf('Blocks replaced: %d', $total_replaced));
WP_CLI::log(sprintf('Blocks removed:  %d', $total_removed));

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
 * Determine the callout style based on title and icon hints.
 *
 * @param string $title    The callout title text.
 * @param array  $data     Full block data attributes.
 * @param string $icon_raw Raw icon string from Oxygen.
 * @return string One of: tip, note, warning, summary.
 */
function determine_callout_style(string $title, array $data, string $icon_raw = ''): string {
    $title_lower = strtolower(trim($title));

    // Title-based mapping.
    if ($title_lower === 'note') {
        return 'note';
    }
    if ($title_lower === 'summary') {
        return 'summary';
    }
    if ($title_lower === 'warning' || $title_lower === 'check') {
        return 'warning';
    }

    // Icon-based mapping.
    $icon_lower = strtolower($icon_raw);

    if (strpos($icon_lower, 'battery') !== false) {
        return 'note';
    }
    if (strpos($icon_lower, 'alert-triangle') !== false) {
        return 'note';
    }
    if (strpos($icon_lower, 'info') !== false) {
        return 'note';
    }
    if (strpos($icon_lower, 'bar-chart') !== false || strpos($icon_lower, 'dashboard') !== false) {
        return 'summary';
    }
    if (strpos($icon_lower, 'lightbulb') !== false) {
        return 'tip';
    }

    // Default: tip (covers "Tip", "Pro tip", "Pro Tip", empty title).
    return 'tip';
}

/**
 * Determine the greybox icon from the raw Oxygen icon string.
 *
 * @param string $icon_raw Raw icon string from Oxygen.
 * @return string One of: x, info, zap, check.
 */
function determine_greybox_icon(string $icon_raw): string {
    $icon_lower = strtolower($icon_raw);

    if (strpos($icon_lower, 'times') !== false || strpos($icon_lower, 'x-') !== false) {
        return 'x';
    }
    if (strpos($icon_lower, 'info') !== false) {
        return 'info';
    }
    if (strpos($icon_lower, 'zap') !== false || strpos($icon_lower, 'bolt') !== false) {
        return 'zap';
    }
    if (strpos($icon_lower, 'check') !== false) {
        return 'check';
    }

    // Default for greybox (most are "Cons of..." with X icon).
    return 'x';
}

/**
 * Build ACF callout block markup.
 *
 * @param string $style Callout style (tip, note, warning, summary).
 * @param string $title Callout title (use default if empty).
 * @param string $body  Callout body HTML.
 * @return string Gutenberg block comment.
 */
function build_callout_block(string $style, string $title, string $body): string {
    $body = sanitize_block_body($body);

    $block_data = [
        'name' => 'acf/callout',
        'data' => [
            'callout_style'              => $style,
            '_callout_style'             => 'field_erh_callout_style',
            'callout_body'               => $body,
            '_callout_body'              => 'field_erh_callout_body',
        ],
        'mode' => 'preview',
    ];

    // Only include title if non-default.
    $defaults = [
        'tip'     => 'Tip',
        'note'    => 'Note',
        'warning' => 'Warning',
        'summary' => 'Summary',
    ];

    $title_lower   = strtolower(trim($title));
    $default_lower = strtolower($defaults[$style] ?? '');

    // Include title if it's custom (not the default for the style).
    if (!empty($title) && $title_lower !== $default_lower && $title_lower !== 'pro tip' && $title_lower !== 'pro tip') {
        $block_data['data']['callout_title']  = $title;
        $block_data['data']['_callout_title'] = 'field_erh_callout_title';
    }

    $json = wp_json_encode($block_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return '<!-- wp:acf/callout ' . $json . ' /-->';
}

/**
 * Build ACF greybox block markup.
 *
 * @param string $icon    Icon name (x, info, zap, check).
 * @param string $heading Heading text.
 * @param string $body    Body HTML.
 * @return string Gutenberg block comment.
 */
function build_greybox_block(string $icon, string $heading, string $body): string {
    $body = sanitize_block_body($body);

    $block_data = [
        'name' => 'acf/greybox',
        'data' => [
            'greybox_icon'     => $icon,
            '_greybox_icon'    => 'field_erh_greybox_icon',
            'greybox_heading'  => $heading,
            '_greybox_heading' => 'field_erh_greybox_heading',
            'greybox_body'     => $body,
            '_greybox_body'    => 'field_erh_greybox_body',
        ],
        'mode' => 'preview',
    ];

    $json = wp_json_encode($block_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return '<!-- wp:acf/greybox ' . $json . ' /-->';
}

/**
 * Sanitize body HTML for safe embedding in block comment JSON.
 *
 * Removes newlines (which cause literal "n" in stored JSON),
 * strips embedded Gutenberg block comments, and cleans whitespace.
 *
 * @param string $body Raw HTML body.
 * @return string Cleaned HTML.
 */
function sanitize_block_body(string $body): string {
    // Remove embedded Gutenberg block comments (leftover from Oxygen).
    $body = preg_replace('/<!--\s*\/?wp:\S+.*?-->/s', '', $body);

    // Replace newlines with nothing (they're just whitespace between HTML tags).
    $body = str_replace(["\r\n", "\r", "\n"], '', $body);

    // Collapse multiple spaces.
    $body = preg_replace('/\s{2,}/', ' ', $body);

    return trim($body);
}

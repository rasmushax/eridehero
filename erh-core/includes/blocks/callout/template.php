<?php
/**
 * Callout Block Template
 *
 * Displays a styled callout box with icon, title, and body.
 * Consolidates ovsb-tip, ovsb-tip-rich-text, and ovsb-icon-rich-text.
 *
 * @package ERH\Blocks
 *
 * @var array  $block      The block settings and attributes.
 * @var string $content    The block inner HTML (empty for ACF blocks).
 * @var bool   $is_preview True during AJAX preview in editor.
 * @var int    $post_id    The post ID this block is saved to.
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

// Get block data.
$style = get_field('callout_style') ?: 'tip';
$title = get_field('callout_title');
$body  = get_field('callout_body');

// Early return if no body content.
if (empty($body)) {
    if ($is_preview) {
        echo '<div class="erh-callout-empty">';
        echo '<p>' . esc_html__('Add callout content to see the preview.', 'erh-core') . '</p>';
        echo '</div>';
    }
    return;
}

// Style configuration.
$style_map = [
    'tip'     => ['icon' => 'lightbulb',       'default_title' => 'Tip'],
    'note'    => ['icon' => 'info',             'default_title' => 'Note'],
    'warning' => ['icon' => 'alert-triangle',   'default_title' => 'Warning'],
    'summary' => ['icon' => 'clipboard-check',  'default_title' => 'Summary'],
];

$config = $style_map[$style] ?? $style_map['tip'];
$icon   = $config['icon'];
$title  = $title ?: $config['default_title'];

// Build class list.
$classes = ['erh-callout', 'erh-callout--' . $style];
if (!empty($block['className'])) {
    $classes[] = $block['className'];
}
if (!empty($block['align'])) {
    $classes[] = 'align' . $block['align'];
}

// Generate unique ID.
$block_id = 'callout-' . ($block['id'] ?? uniqid());
if (!empty($block['anchor'])) {
    $block_id = $block['anchor'];
}
?>
<div id="<?php echo esc_attr($block_id); ?>" class="<?php echo esc_attr(implode(' ', $classes)); ?>">
    <div class="erh-callout-header">
        <svg class="erh-callout-icon icon" aria-hidden="true" focusable="false">
            <use href="#icon-<?php echo esc_attr($icon); ?>"></use>
        </svg>
        <span class="erh-callout-title"><?php echo esc_html($title); ?></span>
    </div>
    <div class="erh-callout-body">
        <?php echo wp_kses_post($body); ?>
    </div>
</div>

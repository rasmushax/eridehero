<?php
/**
 * Icon Heading Block Template
 *
 * Displays a heading with an SVG icon prefix (e.g., checkmark + "Pros").
 * Replaces ovsb-icon-h3.
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
$icon  = get_field('icon_heading_icon') ?: 'check';
$level = get_field('icon_heading_level') ?: 'h3';
$title = get_field('icon_heading_title');

// Early return if no title.
if (empty($title)) {
    if ($is_preview) {
        echo '<div class="erh-icon-heading-empty">';
        echo '<p>' . esc_html__('Enter a title to see the preview.', 'erh-core') . '</p>';
        echo '</div>';
    }
    return;
}

// Sanitize heading level.
$allowed_levels = ['h2', 'h3', 'h4', 'h5'];
if (!in_array($level, $allowed_levels, true)) {
    $level = 'h3';
}

// Build class list.
$classes = ['erh-icon-heading'];
if (!empty($block['className'])) {
    $classes[] = $block['className'];
}

// Generate unique ID.
$block_id = 'icon-heading-' . ($block['id'] ?? uniqid());
if (!empty($block['anchor'])) {
    $block_id = $block['anchor'];
}
?>
<<?php echo esc_attr($level); ?> id="<?php echo esc_attr($block_id); ?>" class="<?php echo esc_attr(implode(' ', $classes)); ?>">
    <svg class="erh-icon-heading-icon icon" aria-hidden="true" focusable="false">
        <use href="#icon-<?php echo esc_attr($icon); ?>"></use>
    </svg>
    <?php echo esc_html($title); ?>
</<?php echo esc_attr($level); ?>>

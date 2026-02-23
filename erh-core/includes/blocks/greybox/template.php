<?php
/**
 * Greybox Block Template
 *
 * Displays a grey box with icon, heading, and rich text body.
 * Replaces ovsb-greybox-with-icon.
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
$icon    = get_field('greybox_icon') ?: 'x';
$color   = get_field('greybox_color') ?: 'default';
$heading = get_field('greybox_heading');
$body    = get_field('greybox_body');

// Early return if no content.
if (empty($heading) && empty($body)) {
    if ($is_preview) {
        echo '<div class="erh-greybox-empty">';
        echo '<p>' . esc_html__('Add a heading and body to see the preview.', 'erh-core') . '</p>';
        echo '</div>';
    }
    return;
}

// Build class list.
$classes = ['erh-greybox'];
if ($color !== 'default') {
    $classes[] = 'erh-greybox--' . $color;
}
if (!empty($block['className'])) {
    $classes[] = $block['className'];
}
if (!empty($block['align'])) {
    $classes[] = 'align' . $block['align'];
}

// Generate unique ID.
$block_id = 'greybox-' . ($block['id'] ?? uniqid());
if (!empty($block['anchor'])) {
    $block_id = $block['anchor'];
}
?>
<div id="<?php echo esc_attr($block_id); ?>" class="<?php echo esc_attr(implode(' ', $classes)); ?>">
    <?php if ($heading) : ?>
        <div class="erh-greybox-header">
            <svg class="erh-greybox-icon icon" aria-hidden="true" focusable="false">
                <use href="#icon-<?php echo esc_attr($icon); ?>"></use>
            </svg>
            <h4 class="erh-greybox-heading"><?php echo esc_html($heading); ?></h4>
        </div>
    <?php endif; ?>

    <?php if ($body) : ?>
        <div class="erh-greybox-body">
            <?php echo wp_kses_post($body); ?>
        </div>
    <?php endif; ?>
</div>

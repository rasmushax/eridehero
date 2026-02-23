<?php
/**
 * Pros & Cons Block Template
 *
 * Displays a two-column pros/cons list with customizable headers.
 * Reuses existing .pros-cons classes from _pros-cons.css.
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
$pro_header = get_field('proscons_pro_header') ?: 'Pros';
$pros_raw   = get_field('proscons_pros');
$con_header = get_field('proscons_con_header') ?: 'Cons';
$cons_raw   = get_field('proscons_cons');
$heading    = get_field('proscons_heading') ?: 'h3';

// Parse textarea lines into arrays.
$pros = !empty($pros_raw) ? array_filter(array_map('trim', explode("\n", $pros_raw))) : [];
$cons = !empty($cons_raw) ? array_filter(array_map('trim', explode("\n", $cons_raw))) : [];

// Early return if no content.
if (empty($pros) && empty($cons)) {
    if ($is_preview) {
        echo '<div class="erh-proscons-empty">';
        echo '<p>' . esc_html__('Add pros and cons to see the preview.', 'erh-core') . '</p>';
        echo '</div>';
    }
    return;
}

// Sanitize heading level.
$allowed_headings = ['h2', 'h3', 'h4', 'h5'];
if (!in_array($heading, $allowed_headings, true)) {
    $heading = 'h3';
}

// Build class list.
$classes = ['erh-proscons', 'pros-cons'];
if (!empty($block['className'])) {
    $classes[] = $block['className'];
}

// Generate unique ID.
$block_id = 'proscons-' . ($block['id'] ?? uniqid());
if (!empty($block['anchor'])) {
    $block_id = $block['anchor'];
}
?>
<div id="<?php echo esc_attr($block_id); ?>" class="<?php echo esc_attr(implode(' ', $classes)); ?>">
    <?php if (!empty($pros)) : ?>
        <div class="pros">
            <<?php echo esc_attr($heading); ?> class="pros-title">
                <?php echo esc_html($pro_header); ?>
            </<?php echo esc_attr($heading); ?>>
            <ul class="pros-list">
                <?php foreach ($pros as $item) : ?>
                    <li>
                        <?php erh_the_icon('check'); ?>
                        <span><?php echo esc_html($item); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($cons)) : ?>
        <div class="cons">
            <<?php echo esc_attr($heading); ?> class="cons-title">
                <?php echo esc_html($con_header); ?>
            </<?php echo esc_attr($heading); ?>>
            <ul class="cons-list">
                <?php foreach ($cons as $item) : ?>
                    <li>
                        <?php erh_the_icon('x'); ?>
                        <span><?php echo esc_html($item); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php
/**
 * Checklist Block Template
 *
 * Displays a checklist with optional title and description.
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
$title = get_field('checklist_title');
$description = get_field('checklist_description');
$items = get_field('checklist_items') ?: [];

// Filter out empty items.
$items = array_filter($items, function($item) {
    return !empty($item['item_text']);
});

// Early return if no items.
if (empty($items)) {
    if ($is_preview) {
        echo '<div class="erh-checklist-empty">';
        echo '<p>' . esc_html__('Add checklist items to see the preview.', 'erh-core') . '</p>';
        echo '</div>';
    }
    return;
}

// Build class list.
$classes = ['erh-checklist'];
if (!empty($block['className'])) {
    $classes[] = $block['className'];
}
if (!empty($block['align'])) {
    $classes[] = 'align' . $block['align'];
}

// Generate unique ID.
$block_id = 'checklist-' . ($block['id'] ?? uniqid());
if (!empty($block['anchor'])) {
    $block_id = $block['anchor'];
}
?>
<div id="<?php echo esc_attr($block_id); ?>" class="<?php echo esc_attr(implode(' ', $classes)); ?>">
    <?php if ($title) : ?>
        <div class="erh-checklist-title"><?php echo esc_html($title); ?></div>
    <?php endif; ?>

    <?php if ($description) : ?>
        <p class="erh-checklist-description"><?php echo wp_kses_post($description); ?></p>
    <?php endif; ?>

    <ul class="erh-checklist-items">
        <?php foreach ($items as $item) : ?>
            <li>
                <svg class="erh-checklist-icon icon" aria-hidden="true" focusable="false">
                    <use href="#icon-check"></use>
                </svg>
                <span><?php echo esc_html($item['item_text']); ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

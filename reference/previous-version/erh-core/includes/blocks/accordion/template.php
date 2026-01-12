<?php
/**
 * Accordion Block Template
 *
 * Accessible accordion with ARIA attributes and keyboard navigation.
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
$items = get_field('item') ?: [];

// Early return if no items.
if (empty($items)) {
    if ($is_preview) {
        echo '<div class="erh-accordion-empty">';
        echo '<p>' . esc_html__('Add accordion items to see the preview.', 'erh-core') . '</p>';
        echo '</div>';
    }
    return;
}

// Build class list.
$classes = ['erh-accordion'];
if (!empty($block['className'])) {
    $classes[] = $block['className'];
}
if (!empty($block['align'])) {
    $classes[] = 'align' . $block['align'];
}

// Generate unique ID for this accordion instance.
$accordion_id = 'accordion-' . ($block['id'] ?? uniqid());
?>
<div
    class="<?php echo esc_attr(implode(' ', $classes)); ?>"
    id="<?php echo esc_attr($accordion_id); ?>"
    data-erh-accordion
>
    <?php foreach ($items as $index => $item) :
        // Skip items without both title and text.
        if (empty($item['title']) || empty($item['text'])) {
            continue;
        }

        $item_id = $accordion_id . '-item-' . $index;
        $header_id = $item_id . '-header';
        $panel_id = $item_id . '-panel';
        $is_open = !empty($item['opened']);
    ?>
        <div class="erh-accordion-item" data-accordion-item>
            <button
                type="button"
                id="<?php echo esc_attr($header_id); ?>"
                class="erh-accordion-header<?php echo $is_open ? ' is-active' : ''; ?>"
                aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>"
                aria-controls="<?php echo esc_attr($panel_id); ?>"
                data-accordion-trigger
            >
                <span class="erh-accordion-title">
                    <?php echo esc_html($item['title']); ?>
                </span>
                <svg class="erh-accordion-icon icon" aria-hidden="true" focusable="false">
                    <use href="#icon-chevron-down"></use>
                </svg>
            </button>
            <div
                id="<?php echo esc_attr($panel_id); ?>"
                class="erh-accordion-panel<?php echo $is_open ? ' is-open' : ''; ?>"
                role="region"
                aria-labelledby="<?php echo esc_attr($header_id); ?>"
            >
                <?php echo wp_kses_post($item['text'] ?? ''); ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

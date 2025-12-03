<?php
/**
 * Accessible Accordion Block Template.
 *
 * @param   array $block The block settings and attributes.
 * @param   string $content The block inner HTML (empty).
 * @param   bool $is_preview True during AJAX preview.
 * @param   (int|string) $post_id The post ID this block is saved to.
 */
// Create class attribute allowing for custom "className" and "align" values.
$classes = '';
if( !empty($block['className']) ) {
    $classes .= sprintf( ' %s', $block['className'] );
}
if( !empty($block['align']) ) {
    $classes .= sprintf( ' align%s', $block['align'] );
}
// Load values and assign defaults.
$accordion_items = get_fields()['item'] ?: [];
$accordion_id = 'accordion-' . $block['id'];
?>
<div class="accordion<?php echo esc_attr($classes); ?>" id="<?php echo esc_attr($accordion_id); ?>">
    <?php foreach($accordion_items as $index => $item): 
        $item_id = $accordion_id . '-item-' . $index;
        $header_id = $item_id . '-header';
        $panel_id = $item_id . '-panel';
    ?>
        <div class="accordion-item">
            <button 
                id="<?php echo esc_attr($header_id); ?>"
                class="accordion-header<?php echo $item['opened'] ? ' accordion-active' : ''; ?>"
                aria-expanded="<?php echo $item['opened'] ? 'true' : 'false'; ?>"
                aria-controls="<?php echo esc_attr($panel_id); ?>"
            >
                <div class="accordion-title"><?php echo esc_html($item['title']); ?></div>
                <svg class="accordion-chevron" aria-hidden="true" focusable="false"><use xlink:href="#icon-chevron-down"></use></svg>
            </button>
            <div 
                id="<?php echo esc_attr($panel_id); ?>"
                class="accordion-panel<?php echo $item['opened'] ? ' open' : ''; ?>"
                role="region"
                aria-labelledby="<?php echo esc_attr($header_id); ?>"
            >
                <?php echo wp_kses_post($item['text']); ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
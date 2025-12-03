<?php
/**
 * Super Accordion Block Template.
 *
 * @param   array $block The block settings and attributes.
 * @param   string $content The block inner HTML (empty).
 * @param   bool $is_preview True during backend preview render.
 * @param   int $post_id The post ID the block is rendering content against.
 * This is either the post ID currently being displayed inside a query loop,
 * or the post ID of the post hosting this block.
 * @param   array $context The context provided to the block by the post or it's parent block.
 */

// Settings
$visible_title = get_field('visible_title') ?: 'Default Visible Title'; // Add default text
$visible_text = get_field('visible_text') ?: 'Default summary text goes here. Click below to see more.'; // Add default text
$expand_prompt = get_field('expand_prompt') ?: 'Learn more ▼'; // Add default text
$expanded_content = get_field('expanded_content');

// Unique ID for ARIA controls
$block_id = 'super-accordion-' . $block['id'];
$panel_id = $block_id . '-panel';
$header_id = $block_id . '-header';

// Create class attribute allowing for custom "className" and "align" values.
$classes = ['super-accordion-block'];
if (!empty($block['className'])) {
    $classes = array_merge($classes, explode(' ', $block['className']));
}
if (!empty($block['align'])) {
    $classes[] = 'align' . $block['align'];
}

// Anchor
$anchor = '';
if (!empty($block['anchor'])) {
    $anchor = ' id="' . esc_attr($block['anchor']) . '"';
}

?>
<div <?php echo $anchor; ?> class="<?php echo esc_attr(implode(' ', $classes)); ?>">
    <div class="accordion">
        <div class="accordion-item">
            <div class="visible-summary">
                <h3 class="visible-title"><?php echo esc_html($visible_title); ?></h3>
                <div class="visible-text">
                    <?php echo wp_kses_post($visible_text); // Use wp_kses_post for basic HTML in text area if needed, or esc_html if plain text only ?>
                </div>
            </div>
            <button class="accordion-header" aria-expanded="false" aria-controls="<?php echo esc_attr($panel_id); ?>" id="<?php echo esc_attr($header_id); ?>">
                <span class="accordion-prompt"><?php echo esc_html($expand_prompt); ?></span>
                <svg class="accordion-chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
                    <path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"></path>
                </svg>
            </button>
            <div class="accordion-panel" id="<?php echo esc_attr($panel_id); ?>" role="region" aria-labelledby="<?php echo esc_attr($header_id); ?>">
                <div class="accordion-content">
                    <?php echo wp_kses_post($expanded_content); // Allows standard WYSIWYG content ?>
                </div>
            </div>
        </div>
    </div>
</div>
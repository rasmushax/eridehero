<?php
/**
 * Checklist Block Template.
 *
 * @param   array $block The block settings and attributes.
 * @param   string $content The block inner content (empty unless using innerBlocks).
 * @param   bool $is_preview True during backend preview render.
 * @param   int $post_id The post ID the block is rendering content against.
 * This is either the post ID currently being displayedfrontend,
 * or the post ID being editedcand includes post revisions.
 * @param   array $context The context provided to the block by the post or it's parent block.
 */

// Get ACF fields
$title       = get_field('checklist_title');
$description = get_field('checklist_description');
$items       = get_field('checklist_items'); // This is the repeater field

// Block alignment
$alignment = !empty($block['align']) ? ' align' . $block['align'] : '';

// Block ID
$block_id = 'checklist-' . $block['id'];
if (!empty($block['anchor'])) {
    $block_id = $block['anchor'];
}

// Basic SVG Checkmark Icon
$checkmark_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="1.2em" height="1.2em" aria-hidden="true" focusable="false" class="checklist-icon"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"></path></svg>';

?>

<div id="<?php echo esc_attr($block_id); ?>" class="checklist-block<?php echo esc_attr($alignment); ?>">
    <?php if ($title) : ?>
        <div class="checklist-title"><?php echo esc_html($title); ?></div>
    <?php endif; ?>

    <?php if ($description) : ?>
        <p class="checklist-description"><?php echo wp_kses_post($description); // Use wp_kses_post for potential basic HTML in description ?></p>
    <?php endif; ?>

    <?php if (have_rows('checklist_items')) : ?>
        <ul class="checklist-items">
            <?php while (have_rows('checklist_items')) : the_row(); ?>
                <?php
                $item_text = get_sub_field('item_text');
                if ($item_text) :
                ?>
                    <li>
                        <?php echo $checkmark_svg; // Output the SVG icon ?>
                        <span><?php echo esc_html($item_text); ?></span>
                    </li>
                <?php endif; ?>
            <?php endwhile; ?>
        </ul>
    <?php elseif ($is_preview) : ?>
        <p><em><?php _e('Add items to the checklist.', 'your-text-domain'); ?></em></p>
    <?php endif; ?>
</div>
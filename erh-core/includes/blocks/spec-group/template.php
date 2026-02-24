<?php
/**
 * Spec Group Block Template
 *
 * Displays a grey bordered box with a definition list of spec label/value pairs.
 * Supports 1 or 2 column layouts. Replaces ovsb-commuting-scooter-specs.
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
$columns = get_field('spec_group_columns') ?: '2';
$specs   = get_field('spec_group_specs');

// Early return if no specs.
if (empty($specs)) {
    if ($is_preview) {
        echo '<div class="erh-spec-group-empty">';
        echo '<p>' . esc_html__('Add specs to see the preview.', 'erh-core') . '</p>';
        echo '</div>';
    }
    return;
}

// Build class list.
$classes = ['erh-spec-group', 'erh-spec-group--' . $columns . 'col'];
if (!empty($block['className'])) {
    $classes[] = $block['className'];
}

// Generate unique ID.
$block_id = 'spec-group-' . ($block['id'] ?? uniqid());
if (!empty($block['anchor'])) {
    $block_id = $block['anchor'];
}
?>
<div id="<?php echo esc_attr($block_id); ?>" class="<?php echo esc_attr(implode(' ', $classes)); ?>">
    <dl class="erh-spec-group-list">
        <?php foreach ($specs as $spec) :
            $title = trim($spec['spec_title'] ?? '');
            $value = trim($spec['spec_value'] ?? '');
            if (empty($title) && empty($value)) {
                continue;
            }
        ?>
            <div class="erh-spec-group-item">
                <dt class="erh-spec-group-label"><?php echo esc_html($title); ?></dt>
                <dd class="erh-spec-group-value"><?php echo esc_html($value); ?></dd>
            </div>
        <?php endforeach; ?>
    </dl>
</div>

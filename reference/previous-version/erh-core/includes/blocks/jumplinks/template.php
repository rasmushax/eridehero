<?php
/**
 * Jumplinks Block Template
 *
 * Quick navigation links to page sections.
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
$title = get_field('title') ?: __('Jump to', 'erh-core');
$jumplinks = get_field('jumplinks') ?: [];

// Filter out invalid links (need both title and anchor).
$jumplinks = array_filter($jumplinks, function($link) {
    return !empty($link['title']) && !empty($link['anchor']);
});

// Early return if no valid links.
if (empty($jumplinks)) {
    if ($is_preview) {
        echo '<div class="erh-jumplinks-empty">';
        echo '<p>' . esc_html__('Add jumplinks to see the preview.', 'erh-core') . '</p>';
        echo '</div>';
    }
    return;
}

// Build class list.
$classes = ['erh-jumplinks'];
if (!empty($block['className'])) {
    $classes[] = $block['className'];
}
if (!empty($block['align'])) {
    $classes[] = 'align' . $block['align'];
}
?>
<nav class="<?php echo esc_attr(implode(' ', $classes)); ?>" aria-label="<?php echo esc_attr($title); ?>">
    <span class="erh-jumplinks-label"><?php echo esc_html($title); ?>:</span>
    <ul class="erh-jumplinks-list">
        <?php foreach ($jumplinks as $link) :
            $href = $link['anchor'];
            // Add # if it's just an anchor ID without it.
            if (!empty($href) && $href[0] !== '#' && strpos($href, 'http') !== 0 && strpos($href, '/') !== 0) {
                $href = '#' . $href;
            }
        ?>
            <li>
                <a href="<?php echo esc_url($href); ?>" class="erh-jumplinks-link">
                    <?php echo esc_html($link['title']); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>

<?php
/**
 * Video Block Template
 *
 * Lazy-loaded video player for better pagespeed.
 * Video only loads when user clicks play.
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
$video_url = get_field('video');
$thumbnail_id = get_field('thumbnail');

// Early return if no video.
if (empty($video_url)) {
    if ($is_preview) {
        echo '<div class="erh-video-empty">';
        echo '<p>' . esc_html__('Add a video file to see the preview.', 'erh-core') . '</p>';
        echo '</div>';
    }
    return;
}

// Build class list.
$classes = ['erh-video'];
if (!empty($block['className'])) {
    $classes[] = $block['className'];
}
if (!empty($block['align'])) {
    $classes[] = 'align' . $block['align'];
}

// Generate unique ID.
$block_id = 'video-' . ($block['id'] ?? uniqid());
if (!empty($block['anchor'])) {
    $block_id = $block['anchor'];
}

// Get thumbnail alt for accessibility.
$thumbnail_alt = $thumbnail_id ? get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true) : '';
$video_label = $thumbnail_alt ?: __('Video', 'erh-core');
?>
<div
    id="<?php echo esc_attr($block_id); ?>"
    class="<?php echo esc_attr(implode(' ', $classes)); ?>"
    data-erh-video
    data-src="<?php echo esc_url($video_url); ?>"
    role="button"
    tabindex="0"
    aria-label="<?php echo esc_attr(sprintf(__('Play video: %s', 'erh-core'), $video_label)); ?>"
>
    <video muted loop playsinline preload="none"></video>

    <?php if ($thumbnail_id) : ?>
        <?php echo wp_get_attachment_image($thumbnail_id, 'large', false, [
            'class'   => 'erh-video-thumbnail',
            'loading' => 'lazy',
        ]); ?>
    <?php else : ?>
        <div class="erh-video-placeholder"></div>
    <?php endif; ?>

    <div class="erh-video-controls" aria-hidden="true">
        <svg class="erh-video-play icon" focusable="false">
            <use href="#icon-play"></use>
        </svg>
        <svg class="erh-video-pause icon" focusable="false">
            <use href="#icon-pause"></use>
        </svg>
        <svg class="erh-video-loader icon" focusable="false">
            <use href="#icon-loader"></use>
        </svg>
    </div>
</div>

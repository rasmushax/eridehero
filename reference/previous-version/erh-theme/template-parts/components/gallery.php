<?php
/**
 * Gallery Component
 *
 * Reusable image gallery with thumbnails, scroll-snap, and optional video lightbox.
 * Works with featured image + ACF gallery field combination.
 *
 * @package ERideHero
 *
 * Expected $args:
 *   'post_id'       => int    - Post ID to get featured image from
 *   'gallery'       => array  - ACF gallery array (optional, from review_gallery field)
 *   'product_id'    => int    - Product ID to get YouTube video from (optional)
 *   'youtube_video' => string - Direct YouTube URL (optional, overrides product lookup)
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get arguments with defaults
$post_id       = $args['post_id'] ?? get_the_ID();
$acf_gallery   = $args['gallery'] ?? array();
$product_id    = $args['product_id'] ?? null;
$youtube_video = $args['youtube_video'] ?? null;

// Build images array: featured image first, then gallery images
$images = array();

// Get featured image
$featured_id = get_post_thumbnail_id( $post_id );
if ( $featured_id ) {
    $featured_large = wp_get_attachment_image_src( $featured_id, 'large' );
    $featured_thumb = wp_get_attachment_image_src( $featured_id, 'erh-thumbnail' );
    $featured_alt   = get_post_meta( $featured_id, '_wp_attachment_image_alt', true );

    if ( $featured_large ) {
        $images[] = array(
            'id'    => $featured_id,
            'large' => $featured_large[0],
            'thumb' => $featured_thumb ? $featured_thumb[0] : $featured_large[0],
            'alt'   => $featured_alt ?: get_the_title( $post_id ),
        );
    }
}

// Add ACF gallery images
if ( ! empty( $acf_gallery ) && is_array( $acf_gallery ) ) {
    foreach ( $acf_gallery as $image ) {
        // ACF gallery returns array with 'sizes' sub-array
        $large_url = $image['sizes']['large'] ?? $image['url'];
        $thumb_url = $image['sizes']['erh-thumbnail'] ?? $image['sizes']['thumbnail'] ?? $large_url;

        $images[] = array(
            'id'    => $image['ID'],
            'large' => $large_url,
            'thumb' => $thumb_url,
            'alt'   => $image['alt'] ?: $image['title'],
        );
    }
}

// Bail if no images
if ( empty( $images ) ) {
    return;
}

// Get YouTube video
$video_id = null;

// If direct YouTube URL provided, use it
if ( $youtube_video ) {
    $video_id = erh_extract_youtube_id( $youtube_video );
} elseif ( $product_id ) {
    // Otherwise check product's review.youtube_video field
    $product_youtube = get_field( 'review', $product_id );
    if ( ! empty( $product_youtube['youtube_video'] ) ) {
        $video_id = erh_extract_youtube_id( $product_youtube['youtube_video'] );
    }
}

// Get video thumbnail if we have a video
$video_thumb = null;
if ( $video_id ) {
    // YouTube thumbnail URLs
    $video_thumb = "https://img.youtube.com/vi/{$video_id}/mqdefault.jpg";
}

// Main image (first in array)
$main_image = $images[0];
?>

<div class="gallery" data-gallery tabindex="0">
    <div class="gallery-main">
        <img
            src="<?php echo esc_url( $main_image['large'] ); ?>"
            alt="<?php echo esc_attr( $main_image['alt'] ); ?>"
            id="gallery-main-img"
        >

        <?php if ( $video_id ) : ?>
            <!-- Video Card Overlay -->
            <button class="gallery-video-card" data-video="<?php echo esc_attr( $video_id ); ?>">
                <div class="gallery-video-card-thumb">
                    <img src="<?php echo esc_url( $video_thumb ); ?>" alt="">
                    <svg class="icon icon-play" aria-hidden="true"><use href="#icon-play"></use></svg>
                    <svg class="icon icon-yt" aria-hidden="true"><use href="#icon-youtube-logo"></use></svg>
                </div>
                <div class="gallery-video-card-text">
                    <span><?php esc_html_e( 'Watch review', 'erh' ); ?></span>
                    <span class="gallery-video-card-yt">
                        <?php esc_html_e( 'on', 'erh' ); ?>
                        <svg class="icon" aria-hidden="true"><use href="#icon-youtube-logo"></use></svg>
                        <strong>YouTube</strong>
                    </span>
                </div>
            </button>
        <?php endif; ?>
    </div>

    <?php if ( count( $images ) > 1 ) : ?>
        <div class="gallery-thumbs-wrapper">
            <div class="gallery-thumbs-scroll">
                <button class="gallery-arrow gallery-arrow--prev" aria-label="<?php esc_attr_e( 'Previous images', 'erh' ); ?>">
                    <svg class="icon" aria-hidden="true"><use href="#icon-chevron-left"></use></svg>
                </button>

                <div class="gallery-thumbs">
                    <?php foreach ( $images as $index => $image ) : ?>
                        <button
                            class="gallery-thumb<?php echo 0 === $index ? ' is-active' : ''; ?>"
                            data-img="<?php echo esc_url( $image['large'] ); ?>"
                        >
                            <img
                                src="<?php echo esc_url( $image['thumb'] ); ?>"
                                alt="<?php echo esc_attr( $image['alt'] ); ?>"
                            >
                        </button>
                    <?php endforeach; ?>
                </div>

                <button class="gallery-arrow gallery-arrow--next" aria-label="<?php esc_attr_e( 'More images', 'erh' ); ?>">
                    <svg class="icon" aria-hidden="true"><use href="#icon-chevron-right"></use></svg>
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

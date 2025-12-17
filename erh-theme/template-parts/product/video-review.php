<?php
/**
 * Product Video Review
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$video_url = get_field( 'video_review' );

if ( ! $video_url ) {
    return;
}
?>
<section class="video-review">
    <h2 class="video-review-title"><?php esc_html_e( 'Video review', 'erh' ); ?></h2>
    <div class="video-review-embed">
        <?php echo wp_oembed_get( $video_url ); ?>
    </div>
</section>

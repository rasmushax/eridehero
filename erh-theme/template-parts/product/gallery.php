<?php
/**
 * Product Gallery
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$product_id = get_the_ID();
$thumbnail  = get_the_post_thumbnail_url( $product_id, 'erh-gallery' );
?>
<div class="gallery">
    <div class="gallery-main">
        <?php if ( $thumbnail ) : ?>
            <img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php the_title_attribute(); ?>" class="gallery-main-img">
        <?php else : ?>
            <div class="gallery-placeholder"><?php esc_html_e( 'No image', 'erh' ); ?></div>
        <?php endif; ?>
    </div>
    <!-- Gallery thumbnails and lightbox will be enhanced with JS -->
</div>

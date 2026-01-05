<?php
/**
 * Product Card Component
 *
 * Simple product card for product pages (not reviews).
 * Shows product image, name, brand, and price.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$product_id   = get_the_ID();
$product_name = get_the_title();
$product_type = erh_get_product_type( $product_id );
$brand        = erh_extract_brand_from_title( $product_name );

// Get thumbnail.
$big_thumbnail = get_field( 'big_thumbnail', $product_id );
$thumbnail     = $big_thumbnail
    ? wp_get_attachment_image_url( $big_thumbnail, 'erh-card' )
    : get_the_post_thumbnail_url( $product_id, 'erh-card' );

// Get score if available.
$score = get_field( 'editor_rating', $product_id );
?>
<article class="content-card product-card">
    <a href="<?php the_permalink(); ?>" class="content-card-link">
        <div class="content-card-img">
            <?php if ( $thumbnail ) : ?>
                <img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
            <?php else : ?>
                <div class="content-card-placeholder">
                    <?php erh_the_icon( 'image' ); ?>
                </div>
            <?php endif; ?>
            <?php if ( $score ) : ?>
                <span class="card-score"><?php echo esc_html( number_format( $score, 1 ) ); ?></span>
            <?php endif; ?>
        </div>
        <div class="content-card-body">
            <?php if ( $brand ) : ?>
                <span class="content-card-brand"><?php echo esc_html( $brand ); ?></span>
            <?php endif; ?>
            <h3 class="content-card-title"><?php the_title(); ?></h3>
            <div class="content-card-price" data-product-price="<?php echo esc_attr( $product_id ); ?>">
                <!-- Price populated by JS (geo-aware) -->
                <span class="skeleton skeleton-text" style="width: 60px;"></span>
            </div>
        </div>
    </a>
</article>

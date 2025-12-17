<?php
/**
 * Related Products
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$product_type = erh_get_product_type();
$current_id   = get_the_ID();

$related_query = new WP_Query( array(
    'post_type'      => 'products',
    'posts_per_page' => 4,
    'post__not_in'   => array( $current_id ),
    'meta_query'     => array(
        array(
            'key'   => 'product_type',
            'value' => $product_type,
        ),
    ),
) );

if ( ! $related_query->have_posts() ) {
    return;
}
?>
<section class="related-reviews">
    <h2 class="related-reviews-title"><?php esc_html_e( 'Related reviews', 'erh' ); ?></h2>
    <div class="related-grid">
        <?php
        while ( $related_query->have_posts() ) :
            $related_query->the_post();
            get_template_part( 'template-parts/components/review-card' );
        endwhile;
        wp_reset_postdata();
        ?>
    </div>
</section>

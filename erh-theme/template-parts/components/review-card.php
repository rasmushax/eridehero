<?php
/**
 * Review Card Component
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$product_id   = get_the_ID();
$product_type = erh_get_product_type( $product_id );
$thumbnail    = get_the_post_thumbnail_url( $product_id, 'erh-card' );
$score        = get_field( 'overall_score', $product_id );
?>
<article class="content-card review-card">
    <a href="<?php the_permalink(); ?>" class="content-card-link">
        <div class="content-card-img">
            <?php if ( $thumbnail ) : ?>
                <img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
            <?php endif; ?>
            <?php if ( $product_type ) : ?>
                <span class="card-category"><?php echo esc_html( $product_type ); ?></span>
            <?php endif; ?>
            <?php if ( $score ) : ?>
                <span class="card-score"><?php echo esc_html( number_format( $score, 1 ) ); ?></span>
            <?php endif; ?>
        </div>
        <div class="content-card-body">
            <h3 class="content-card-title"><?php the_title(); ?></h3>
            <span class="content-card-meta"><?php echo esc_html( get_the_modified_date() ); ?></span>
        </div>
    </a>
</article>

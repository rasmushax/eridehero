<?php
/**
 * Article Card Component
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$thumbnail = get_the_post_thumbnail_url( get_the_ID(), 'erh-card' );
$category  = get_the_category();
$cat_name  = ! empty( $category ) ? $category[0]->name : '';
?>
<article class="content-card article-card">
    <a href="<?php the_permalink(); ?>" class="content-card-link">
        <div class="content-card-img">
            <?php if ( $thumbnail ) : ?>
                <img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
            <?php endif; ?>
            <?php if ( $cat_name ) : ?>
                <span class="card-category"><?php echo esc_html( $cat_name ); ?></span>
            <?php endif; ?>
        </div>
        <div class="content-card-body">
            <h3 class="content-card-title"><?php the_title(); ?></h3>
            <span class="content-card-meta"><?php echo esc_html( get_the_date() ); ?></span>
        </div>
    </a>
</article>

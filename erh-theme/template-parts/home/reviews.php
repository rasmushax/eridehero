<?php
/**
 * Homepage Latest Reviews Section
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<section class="latest-reviews-section">
    <div class="container">
        <div class="content-with-sidebar">
            <div>
                <div class="section-header">
                    <h2 class="section-title"><?php esc_html_e( 'Latest reviews', 'erh' ); ?></h2>
                    <a href="<?php echo esc_url( home_url( '/reviews/' ) ); ?>" class="section-link">
                        <?php esc_html_e( 'View all reviews', 'erh' ); ?>
                        <?php erh_the_icon( 'arrow-right' ); ?>
                    </a>
                </div>

                <div class="reviews-grid">
                    <?php
                    $reviews_query = new WP_Query( array(
                        'post_type'      => 'products',
                        'posts_per_page' => 4,
                        'orderby'        => 'date',
                        'order'          => 'DESC',
                    ) );

                    if ( $reviews_query->have_posts() ) :
                        while ( $reviews_query->have_posts() ) :
                            $reviews_query->the_post();
                            get_template_part( 'template-parts/components/review-card' );
                        endwhile;
                        wp_reset_postdata();
                    else :
                        ?>
                        <p class="empty-state"><?php esc_html_e( 'No reviews found.', 'erh' ); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="home-sidebar">
                <?php get_template_part( 'template-parts/components/sidebar-how-we-test' ); ?>
            </aside>
        </div>
    </div>
</section>

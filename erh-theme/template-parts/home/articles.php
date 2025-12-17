<?php
/**
 * Homepage Latest Articles Section
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<section class="articles-section">
    <div class="container">
        <div class="content-with-sidebar">
            <div>
                <div class="section-header">
                    <h2 class="section-title"><?php esc_html_e( 'Latest articles', 'erh' ); ?></h2>
                    <a href="<?php echo esc_url( home_url( '/articles/' ) ); ?>" class="section-link">
                        <?php esc_html_e( 'View all articles', 'erh' ); ?>
                        <?php erh_the_icon( 'arrow-right' ); ?>
                    </a>
                </div>

                <div class="articles-grid">
                    <?php
                    $articles_query = new WP_Query( array(
                        'post_type'      => 'post',
                        'posts_per_page' => 4,
                        'orderby'        => 'date',
                        'order'          => 'DESC',
                    ) );

                    if ( $articles_query->have_posts() ) :
                        while ( $articles_query->have_posts() ) :
                            $articles_query->the_post();
                            get_template_part( 'template-parts/components/article-card' );
                        endwhile;
                        wp_reset_postdata();
                    else :
                        ?>
                        <p class="empty-state"><?php esc_html_e( 'No articles found.', 'erh' ); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="home-sidebar">
                <?php get_template_part( 'template-parts/components/sidebar-about' ); ?>
            </aside>
        </div>
    </div>
</section>

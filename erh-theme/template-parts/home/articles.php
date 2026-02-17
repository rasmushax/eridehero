<?php
/**
 * Latest Articles Section
 *
 * Displays latest articles (excluding reviews and buying guides) with About sidebar.
 *
 * Accepts optional args:
 * - category (string): Category slug to filter by (e.g., 'electric-scooters')
 * - limit (int): Number of articles to show (default: 4)
 * - show_sidebar (bool): Whether to show sidebar (default: true)
 * - view_all_url (string): Custom "View all articles" URL (defaults to /articles/)
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get args from get_template_part() or use defaults
$category      = $args['category'] ?? '';
$limit         = $args['limit'] ?? 4;
$show_sidebar  = $args['show_sidebar'] ?? true;
$view_all_url  = ! empty( $args['view_all_url'] ) ? $args['view_all_url'] : home_url( '/articles/' );

// Build query args
$query_args = array(
    'post_type'      => 'post',
    'posts_per_page' => $limit,
    'post_status'    => 'publish',
    'orderby'        => 'date',
    'order'          => 'DESC',
    'tag__not_in'    => array_filter( array(
        get_term_by( 'slug', 'review', 'post_tag' ) ? get_term_by( 'slug', 'review', 'post_tag' )->term_id : 0,
        get_term_by( 'slug', 'buying-guide', 'post_tag' ) ? get_term_by( 'slug', 'buying-guide', 'post_tag' )->term_id : 0,
    ) ),
);

// Add category filter if specified
if ( $category ) {
    $query_args['category_name'] = $category;
}

$articles_query = new WP_Query( $query_args );
?>

<section class="section latest-articles" id="articles">
    <div class="container">
        <div class="section-header">
            <h2><?php esc_html_e( 'Latest articles', 'erh' ); ?></h2>
            <a href="<?php echo esc_url( $view_all_url ); ?>" class="btn btn-secondary">
                <?php esc_html_e( 'View all articles', 'erh' ); ?>
                <?php erh_the_icon( 'arrow-right' ); ?>
            </a>
        </div>

        <div class="content-with-sidebar">
            <?php if ( $articles_query->have_posts() ) : ?>
                <div class="card-scroll-grid">
                    <?php while ( $articles_query->have_posts() ) : $articles_query->the_post();
                        // Get first category for badge (with short name)
                        $categories = get_the_category();
                        $category   = ! empty( $categories ) ? erh_get_category_short_name( $categories[0]->name ) : '';
                    ?>
                        <a href="<?php the_permalink(); ?>" class="content-card">
                            <div class="content-card-img">
                                <?php if ( has_post_thumbnail() ) : ?>
                                    <?php the_post_thumbnail( 'medium_large', array( 'alt' => get_the_title() ) ); ?>
                                <?php else : ?>
                                    <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/placeholder-article.jpg' ); ?>" alt="<?php the_title_attribute(); ?>">
                                <?php endif; ?>

                                <?php if ( $category ) : ?>
                                    <span class="card-category"><?php echo esc_html( $category ); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="content-card-content">
                                <h3 class="content-card-title"><?php the_title(); ?></h3>
                                <span class="content-card-date"><?php echo esc_html( get_the_date( 'M j, Y' ) ); ?></span>
                            </div>
                        </a>
                    <?php endwhile; ?>
                </div>
                <?php wp_reset_postdata(); ?>
            <?php else : ?>
                <div class="empty-state">
                    <p><?php esc_html_e( 'No articles yet.', 'erh' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( $show_sidebar ) : ?>
                <?php get_template_part( 'template-parts/components/sidebar-about' ); ?>
            <?php endif; ?>
        </div>
    </div>
</section>

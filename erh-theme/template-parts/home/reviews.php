<?php
/**
 * Homepage Latest Reviews Section
 *
 * Displays 4 latest review posts with "How We Test" sidebar.
 * Reviews are posts tagged with 'review' and linked to products CPT.
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Query latest 4 posts with 'review' tag
$reviews_query = new WP_Query( array(
    'post_type'      => 'post',
    'tag'            => 'review',
    'posts_per_page' => 4,
    'post_status'    => 'publish',
    'orderby'        => 'date',
    'order'          => 'DESC',
) );
?>

<section class="section latest-reviews">
    <div class="container">
        <div class="section-header">
            <h2><?php esc_html_e( 'Latest reviews', 'erh' ); ?></h2>
            <a href="<?php echo esc_url( home_url( '/reviews/' ) ); ?>" class="btn btn-secondary">
                <?php esc_html_e( 'View all reviews', 'erh' ); ?>
                <?php erh_the_icon( 'arrow-right' ); ?>
            </a>
        </div>

        <div class="content-with-sidebar">
            <?php if ( $reviews_query->have_posts() ) : ?>
                <div class="card-scroll-grid">
                    <?php while ( $reviews_query->have_posts() ) : $reviews_query->the_post();
                        // Get linked product and its data
                        $product_id   = get_field( 'review_product' );
                        $product_type = '';
                        $rating       = null;

                        if ( $product_id ) {
                            // Get product type (category display name)
                            $product_type_raw = get_field( 'product_type', $product_id );
                            $product_type     = $product_type_raw ? erh_get_product_type_short_name( $product_type_raw ) : '';

                            // Get rating from product's editor_rating field
                            $editor_rating = get_field( 'editor_rating', $product_id );
                            if ( $editor_rating ) {
                                $rating = floatval( $editor_rating );
                            }
                        }
                    ?>
                        <a href="<?php the_permalink(); ?>" class="content-card">
                            <div class="content-card-img">
                                <?php if ( has_post_thumbnail() ) : ?>
                                    <?php the_post_thumbnail( 'medium_large', array( 'alt' => get_the_title() ) ); ?>
                                <?php else : ?>
                                    <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/placeholder-review.jpg' ); ?>" alt="<?php the_title_attribute(); ?>">
                                <?php endif; ?>

                                <?php if ( $product_type ) : ?>
                                    <span class="card-category"><?php echo esc_html( $product_type ); ?></span>
                                <?php endif; ?>

                                <?php if ( $rating ) : ?>
                                    <span class="review-card-score"><?php echo esc_html( number_format( $rating, 1 ) ); ?></span>
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
                    <p><?php esc_html_e( 'No reviews yet.', 'erh' ); ?></p>
                </div>
            <?php endif; ?>

            <?php get_template_part( 'template-parts/components/sidebar-how-we-test' ); ?>
        </div>
    </div>
</section>

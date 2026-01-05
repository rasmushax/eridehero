<?php
/**
 * Latest Reviews Section
 *
 * Displays latest review posts with "How We Test" sidebar.
 * Reviews are posts tagged with 'review' and linked to products CPT.
 *
 * Accepts optional args:
 * - category (string): Category slug to filter by (e.g., 'electric-scooters')
 * - limit (int): Number of reviews to show (default: 4)
 * - show_sidebar (bool): Whether to show sidebar (default: true)
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get args from get_template_part() or use defaults
$category     = $args['category'] ?? '';
$limit        = $args['limit'] ?? 4;
$show_sidebar = $args['show_sidebar'] ?? true;

// Build query args
$query_args = array(
    'post_type'      => 'post',
    'tag'            => 'review',
    'posts_per_page' => $limit,
    'post_status'    => 'publish',
    'orderby'        => 'date',
    'order'          => 'DESC',
);

// Add category filter if specified
if ( $category ) {
    $query_args['category_name'] = $category;
}

$reviews_query = new WP_Query( $query_args );
?>

<section class="section latest-reviews" id="reviews">
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
                            // Get product type from taxonomy
                            $product_type_terms = get_the_terms( $product_id, 'product_type' );
                            if ( $product_type_terms && ! is_wp_error( $product_type_terms ) ) {
                                $product_type = erh_get_product_type_short_name( $product_type_terms[0]->name );
                            }

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

            <?php if ( $show_sidebar ) : ?>
                <?php get_template_part( 'template-parts/components/sidebar-how-we-test' ); ?>
            <?php endif; ?>
        </div>
    </div>
</section>

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

// Get ACF options with defaults
$section_title  = get_field( 'reviews_section_title', 'option' ) ?: __( 'Latest reviews', 'erh' );
$link_text      = get_field( 'reviews_link_text', 'option' ) ?: __( 'View all reviews', 'erh' );
$link_url       = get_field( 'reviews_link_url', 'option' ) ?: home_url( '/reviews/' );
$selected_posts = get_field( 'reviews_posts', 'option' );

// Build query args
if ( ! empty( $selected_posts ) ) {
    // Use manually selected posts
    $query_args = array(
        'post_type'      => 'post',
        'post__in'       => $selected_posts,
        'orderby'        => 'post__in',
        'posts_per_page' => 4,
        'post_status'    => 'publish',
    );
} else {
    // Fallback: latest posts with 'review' tag
    $query_args = array(
        'post_type'      => 'post',
        'tag'            => 'review',
        'posts_per_page' => 4,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    );
}

$reviews_query = new WP_Query( $query_args );
?>

<section class="section latest-reviews">
    <div class="container">
        <div class="section-header">
            <h2><?php echo esc_html( $section_title ); ?></h2>
            <a href="<?php echo esc_url( $link_url ); ?>" class="btn btn-secondary">
                <?php echo esc_html( $link_text ); ?>
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
                            $product_type     = erh_get_product_type_short_name( $product_type_raw );

                            // Get rating from product's ratings field
                            $ratings = get_field( 'ratings', $product_id );
                            if ( ! empty( $ratings['overall'] ) ) {
                                $rating = floatval( $ratings['overall'] );
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
                    <p><?php esc_html_e( 'No reviews found. Add posts with the "review" tag or select reviews in Theme Settings > Homepage.', 'erh' ); ?></p>
                </div>
            <?php endif; ?>

            <?php get_template_part( 'template-parts/components/sidebar-how-we-test' ); ?>
        </div>
    </div>
</section>

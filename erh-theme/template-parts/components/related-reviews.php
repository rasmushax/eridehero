<?php
/**
 * Related Reviews Component
 *
 * Displays related reviews from the same product category.
 *
 * @package ERideHero
 *
 * @var array $args {
 *     @type int    $post_id       Current post ID to exclude.
 *     @type string $product_type  Product type to filter by.
 *     @type int    $count         Number of reviews to show. Default 3.
 * }
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$post_id      = $args['post_id'] ?? get_the_ID();
$product_type = $args['product_type'] ?? '';
$count        = $args['count'] ?? 3;

if ( empty( $product_type ) ) {
    return;
}

// Get the category slug for this product type.
$category_slug = erh_product_type_slug( $product_type );
$category_name = strtolower( erh_get_product_type_short_name( $product_type ) );

// Query related reviews (posts with 'review' tag in this category).
$related_args = array(
    'post_type'      => 'post',
    'posts_per_page' => $count,
    'post__not_in'   => array( $post_id ),
    'tag'            => 'review',
    'orderby'        => 'date',
    'order'          => 'DESC',
    'meta_query'     => array(
        array(
            'key'     => 'review_product',
            'compare' => 'EXISTS',
        ),
    ),
);

// Filter by category if we have one.
$category = get_category_by_slug( str_replace( '-', '', $category_slug ) );
if ( ! $category ) {
    $category = get_category_by_slug( $category_slug );
}
if ( ! $category ) {
    $category = get_category_by_slug( sanitize_title( $product_type . 's' ) );
}
if ( $category ) {
    $related_args['cat'] = $category->term_id;
}

$related_query = new WP_Query( $related_args );

if ( ! $related_query->have_posts() ) {
    wp_reset_postdata();
    return;
}

// Build the correct reviews archive URL.
// E-scooters has a dedicated page; others use /reviews/?category=slug.
$wp_category_slug = $category ? $category->slug : sanitize_title( $product_type . 's' );
if ( 'electric-scooters' === $wp_category_slug ) {
    $reviews_url = home_url( '/electric-scooters/reviews/' );
} else {
    $reviews_url = home_url( '/reviews/?category=' . $wp_category_slug );
}
?>

<section class="related-reviews content-section">
    <div class="section-header">
        <h2 class="section-title">More <?php echo esc_html( $category_name ); ?> reviews</h2>
        <a href="<?php echo esc_url( $reviews_url ); ?>" class="btn btn-secondary btn-sm">
            View all
            <?php erh_the_icon( 'arrow-right' ); ?>
        </a>
    </div>

    <div class="related-grid">
        <?php
        while ( $related_query->have_posts() ) :
            $related_query->the_post();

            $related_post_id = get_the_ID();
            $related_product = get_field( 'review_product', $related_post_id );

            // Get score from the linked product.
            $score = 0;
            if ( $related_product ) {
                $related_product_id = is_object( $related_product ) ? $related_product->ID : (int) $related_product;
                $score = get_field( 'editor_rating', $related_product_id );
            }

            $score_attr = $score ? erh_get_score_attr( (float) $score ) : 'good';
            ?>
            <a href="<?php the_permalink(); ?>" class="content-card">
                <div class="content-card-img">
                    <?php if ( has_post_thumbnail() ) : ?>
                        <?php the_post_thumbnail( 'medium_large' ); ?>
                    <?php else : ?>
                        <img src="<?php echo esc_url( ERH_THEME_URI . '/assets/images/placeholder.jpg' ); ?>" alt="">
                    <?php endif; ?>
                    <?php if ( $score ) : ?>
                        <span class="card-score" data-score="<?php echo esc_attr( $score_attr ); ?>">
                            <?php echo esc_html( number_format( (float) $score, 1 ) ); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="content-card-content">
                    <h3 class="content-card-title"><?php the_title(); ?></h3>
                    <span class="content-card-date"><?php echo esc_html( get_the_date( 'M j, Y' ) ); ?></span>
                </div>
            </a>
            <?php
        endwhile;
        wp_reset_postdata();
        ?>
    </div>
</section>

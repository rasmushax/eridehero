<?php
/**
 * Related Products
 *
 * Displays related products of the same type, sorted by popularity.
 * For product pages, shows product cards. For review pages, shows review cards.
 *
 * @package ERideHero
 *
 * @var array $args {
 *     @type int    $product_id    Current product ID to exclude.
 *     @type string $product_type  Product type label (e.g., 'Electric Scooter').
 *     @type string $product_slug  Category slug for finder link (e.g., 'e-scooters').
 *     @type string $category_name Short category name (e.g., 'E-Scooter').
 *     @type string $context       Display context: 'product' or 'review' (default: 'product').
 *     @type int    $limit         Number of products to show (default: 4).
 * }
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Extract args with fallbacks for backwards compatibility.
$product_id    = $args['product_id'] ?? get_the_ID();
$product_type  = $args['product_type'] ?? erh_get_product_type( $product_id );
$product_slug  = $args['product_slug'] ?? erh_product_type_slug( $product_type );
$category_name = $args['category_name'] ?? erh_get_product_type_short_name( $product_type );
$context       = $args['context'] ?? 'product';
$limit         = $args['limit'] ?? 4;

if ( empty( $product_type ) ) {
    return;
}

// Query products sorted by popularity (from wp_product_data table).
global $wpdb;
$table = $wpdb->prefix . 'product_data';

// Check if product_data table exists.
$table_exists = $wpdb->get_var( $wpdb->prepare(
    "SHOW TABLES LIKE %s",
    $table
) );

if ( $table_exists ) {
    // Get product IDs sorted by popularity from cache table.
    $related_ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT pd.product_id
         FROM {$table} pd
         INNER JOIN {$wpdb->posts} p ON pd.product_id = p.ID
         WHERE pd.product_type = %s
           AND pd.product_id != %d
           AND p.post_status = 'publish'
         ORDER BY pd.popularity_score DESC
         LIMIT %d",
        $product_type,
        $product_id,
        $limit
    ) );
} else {
    // Fallback: random products if cache table doesn't exist.
    $related_ids = array();
}

// If we have IDs from popularity query, use them.
if ( ! empty( $related_ids ) ) {
    $related_query = new WP_Query( array(
        'post_type'      => 'products',
        'posts_per_page' => $limit,
        'post__in'       => $related_ids,
        'orderby'        => 'post__in', // Preserve popularity order.
    ) );
} else {
    // Fallback query without popularity sort.
    $related_query = new WP_Query( array(
        'post_type'      => 'products',
        'posts_per_page' => $limit,
        'post__not_in'   => array( $product_id ),
        'meta_query'     => array(
            array(
                'key'   => 'product_type',
                'value' => $product_type,
            ),
        ),
        'orderby'        => 'rand',
    ) );
}

if ( ! $related_query->have_posts() ) {
    wp_reset_postdata();
    return;
}

// Determine section title and card template based on context.
$section_title = $context === 'review'
    ? __( 'Related reviews', 'erh' )
    : sprintf( __( 'More %ss', 'erh' ), $category_name );

$card_template = $context === 'review'
    ? 'template-parts/components/review-card'
    : 'template-parts/components/product-card';

// Finder URL.
$finder_url = home_url( '/' . $product_slug . '-finder/' );
?>

<section class="review-section related-products" id="related">
    <div class="related-products-header">
        <h2 class="review-section-title"><?php echo esc_html( $section_title ); ?></h2>
        <a href="<?php echo esc_url( $finder_url ); ?>" class="related-products-link">
            <?php printf( esc_html__( 'Find more %ss', 'erh' ), esc_html( $category_name ) ); ?>
            <?php erh_the_icon( 'arrow-right' ); ?>
        </a>
    </div>

    <div class="related-grid">
        <?php
        while ( $related_query->have_posts() ) :
            $related_query->the_post();
            get_template_part( $card_template );
        endwhile;
        wp_reset_postdata();
        ?>
    </div>
</section>

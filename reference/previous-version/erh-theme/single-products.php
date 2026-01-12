<?php
/**
 * Single Product Template
 *
 * Product database page - all specs, pricing, and data without editorial voice.
 * Full-width layout with no sidebar.
 *
 * Destination when:
 * - Clicking product card in Finder
 * - Direct Google searches for "[product] specs/price"
 * - Products without editorial reviews
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

while ( have_posts() ) :
    the_post();

    // Get product data.
    $product_id   = get_the_ID();
    $product_name = get_the_title();
    $product_type = erh_get_product_type( $product_id );
    $product_slug = erh_product_type_slug( $product_type );
    $category_key = erh_get_category_key( $product_type );

    // Get brand from title (e.g., "Segway Ninebot Max G2" → "Segway").
    $brand = erh_extract_brand_from_title( $product_name );

    // Get product images.
    $big_thumbnail   = get_field( 'big_thumbnail', $product_id );
    $featured_image  = get_post_thumbnail_id( $product_id );
    $product_image   = $big_thumbnail ?: $featured_image;

    // Get linked review (if exists).
    // ACF fields are nested under 'review' group: review.review_post, review.youtube_video
    $review_group = get_field( 'review', $product_id );
    $review_post  = null;
    $video_url    = '';

    if ( is_array( $review_group ) ) {
        // Get the review post object (field returns post ID)
        $review_post_id = $review_group['review_post'] ?? null;
        if ( $review_post_id ) {
            $review_post = get_post( $review_post_id );
        }
        $video_url = $review_group['youtube_video'] ?? '';
    }

    // Build key specs summary for hero.
    $key_specs = erh_get_hero_key_specs( $product_id, $product_type );

    // Category display info.
    $category_name  = erh_get_product_type_short_name( $product_type );
    $category_plural = erh_get_product_type_short_name( $product_type ) . 's';
    ?>

    <article id="product-<?php the_ID(); ?>" <?php post_class( 'product-page' ); ?> data-product-page data-product-id="<?php echo esc_attr( $product_id ); ?>" data-category="<?php echo esc_attr( $category_key ); ?>">

        <!-- Breadcrumb -->
        <div class="container">
            <?php
            $finder_url = erh_get_finder_url( $product_id );
            erh_breadcrumb( [
                [ 'label' => $category_plural, 'url' => $finder_url ?: '' ],
                [ 'label' => $product_name ],
            ] );
            ?>
        </div>

        <!-- Hero Section -->
        <?php
        get_template_part( 'template-parts/product/hero', null, [
            'product_id'    => $product_id,
            'product_name'  => $product_name,
            'brand'         => $brand,
            'product_image' => $product_image,
            'product_type'  => $product_type,
            'review_post'   => $review_post,
            'video_url'     => $video_url,
            'key_specs'     => $key_specs,
        ] );
        ?>

        <!-- Main Content (Full Width) -->
        <div class="product-content">
            <div class="container">

                <!-- Price Intelligence -->
                <?php
                get_template_part( 'template-parts/components/price-intel', null, [
                    'product_id'   => $product_id,
                    'product_name' => $product_name,
                ] );
                ?>

                <!-- Performance Profile -->
                <?php
                get_template_part( 'template-parts/product/performance-profile', null, [
                    'product_id'   => $product_id,
                    'product_type' => $product_type,
                    'category_key' => $category_key,
                ] );
                ?>

                <!-- Head-to-Head Comparison Widget -->
                <?php
                get_template_part( 'template-parts/product/comparison-widget', null, [
                    'product_id'    => $product_id,
                    'product_name'  => $product_name,
                    'product_image' => $product_image,
                    'category_key'  => $category_key,
                ] );
                ?>

                <!-- Related Products -->
                <?php
                get_template_part( 'template-parts/product/related', null, [
                    'product_id'    => $product_id,
                    'product_type'  => $product_type,
                    'product_slug'  => $product_slug,
                    'category_name' => $category_name,
                ] );
                ?>

                <!-- Full Specifications (Grouped by Category) -->
                <?php
                get_template_part( 'template-parts/product/specs-grouped', null, [
                    'product_id'   => $product_id,
                    'product_type' => $product_type,
                    'category_key' => $category_key,
                ] );
                ?>

            </div>
        </div>

    </article>

    <?php
endwhile;

get_footer();

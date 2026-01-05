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
    $review_post = get_field( 'review_relationship', $product_id );
    $video_url   = get_field( 'video_review_url', $product_id );

    // Get editor rating or calculated score.
    $editor_rating = get_field( 'editor_rating', $product_id );
    $overall_score = $editor_rating ? round( $editor_rating * 10 ) : null;

    // Category display info.
    $category_name  = erh_get_product_type_short_name( $product_type );
    $category_plural = erh_get_product_type_short_name( $product_type ) . 's';
    ?>

    <article id="product-<?php the_ID(); ?>" <?php post_class( 'product-page' ); ?> data-product-page data-product-id="<?php echo esc_attr( $product_id ); ?>" data-category="<?php echo esc_attr( $category_key ); ?>">

        <!-- Breadcrumb -->
        <div class="container">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="<?php echo esc_url( home_url( '/' . $product_slug . '/' ) ); ?>"><?php echo esc_html( $category_plural ); ?></a>
                <span class="breadcrumb-sep">/</span>
                <span class="breadcrumb-current"><?php echo esc_html( $product_name ); ?></span>
            </nav>
        </div>

        <!-- Hero Section -->
        <?php
        get_template_part( 'template-parts/product/hero', null, [
            'product_id'    => $product_id,
            'product_name'  => $product_name,
            'brand'         => $brand,
            'product_image' => $product_image,
            'overall_score' => $overall_score,
            'review_post'   => $review_post,
            'video_url'     => $video_url,
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

                <!-- Full Specifications (Grouped by Category) -->
                <?php
                get_template_part( 'template-parts/product/specs-grouped', null, [
                    'product_id'   => $product_id,
                    'product_type' => $product_type,
                    'category_key' => $category_key,
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

            </div>
        </div>

    </article>

    <?php
endwhile;

get_footer();

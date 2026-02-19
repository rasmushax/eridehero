<?php
/**
 * Single Review Post Template Part
 *
 * Template for posts with the "review" tag - editorial product reviews.
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$post_id = get_the_ID();

// Get the related product via ACF post_object field
$product = get_field( 'review_product', $post_id );

// If no product linked, fall back to standard post template
if ( ! $product ) {
    get_template_part( 'template-parts/single', 'post' );
    return;
}

// post_object field returns the post object directly (or ID depending on settings)
$product_id   = is_object( $product ) ? $product->ID : (int) $product;
$product_type = get_field( 'product_type', $product_id );

// Fallback chain if product_type not set
if ( empty( $product_type ) ) {
    // Try product_type taxonomy on the product
    $product_type_terms = get_the_terms( $product_id, 'product_type' );
    if ( ! empty( $product_type_terms ) && ! is_wp_error( $product_type_terms ) ) {
        $product_type = $product_type_terms[0]->name;
    }
}

if ( empty( $product_type ) ) {
    // Try post category as fallback
    $categories = get_the_category( $post_id );
    if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
        $product_type = $categories[0]->name;
    }
}

// Final fallback
if ( empty( $product_type ) ) {
    $product_type = 'Electric Scooter';
}

// Get product category info for breadcrumb and tools
$category_slug = erh_product_type_slug( $product_type );
$category_name = erh_get_product_type_short_name( $product_type );

// Get finder/deals pages from product_type taxonomy term
$finder_page = '';
$deals_page  = '';
$product_type_terms = get_the_terms( $product_id, 'product_type' );
if ( ! empty( $product_type_terms ) && ! is_wp_error( $product_type_terms ) ) {
    $term_id     = $product_type_terms[0]->term_id;
    $finder_page = get_field( 'finder_page', 'product_type_' . $term_id );
    $deals_page  = get_field( 'deals_page', 'product_type_' . $term_id );
    // Also get short_name from ACF if available
    $acf_short_name = get_field( 'short_name', 'product_type_' . $term_id );
    if ( $acf_short_name ) {
        $category_name = $acf_short_name;
    }
}

// Check if product has price data and performance data (used for TOC + conditional rendering)
$has_prices      = erh_product_has_prices( $product_id );
$has_performance = erh_product_has_performance_data( $product_id );

// Build TOC items for sidebar
$toc_items = erh_get_toc_items( $product_id, array(
    'has_prices'      => $has_prices,
    'has_performance' => $has_performance,
    'content_post_id' => $post_id, // Review post has the content, not the product
) );
?>

<main id="main-content" class="review-page">
    <div class="review-layout">
        <div class="container">
            <!-- Title Row -->
            <div class="review-title-row">
                <div class="review-title-content">
                    <?php erh_the_breadcrumbs(); ?>
                    <h1 class="review-title"><?php the_title(); ?></h1>
                </div>
            </div>

            <div class="review-layout-grid">
                <!-- Left Column: Main Content -->
                <article class="review-main">

                    <?php
                    // Gallery section
                    $review_gallery = get_field( 'review_gallery', $post_id );
                    get_template_part( 'template-parts/components/gallery', null, array(
                        'post_id'    => $post_id,
                        'gallery'    => $review_gallery,
                        'product_id' => $product_id,
                    ) );

                    // Byline section
                    get_template_part( 'template-parts/components/byline', null, array(
                        'post_id' => $post_id,
                    ) );
                    ?>

                    <?php
                    // Quick take section
                    $quick_take = get_field( 'review_quick_take', $post_id );
                    $score      = get_field( 'editor_rating', $product_id );

                    get_template_part( 'template-parts/components/quick-take', null, array(
                        'text'  => $quick_take,
                        'score' => $score,
                    ) );

                    // Pros & cons section
                    $pros = get_field( 'review_pros', $post_id );
                    $cons = get_field( 'review_cons', $post_id );

                    get_template_part( 'template-parts/components/pros-cons', null, array(
                        'pros' => $pros,
                        'cons' => $cons,
                    ) );
                    ?>

                    <?php
                    // Price intelligence section - only show if product has price data
                    if ( $has_prices ) {
                        get_template_part( 'template-parts/components/price-intel', null, array(
                            'product_id'   => $product_id,
                            'product_name' => get_the_title( $product_id ),
                        ) );
                    }

                    // Tested performance section
                    get_template_part( 'template-parts/components/tested-performance', null, array(
                        'product_id' => $product_id,
                    ) );
                    ?>

                    <!-- Review content -->
                    <div class="review-body" id="full-review">
                        <?php the_content(); ?>
                    </div>

                    <?php
                    // Full specifications section (after content)
                    get_template_part( 'template-parts/components/full-specs', null, array(
                        'product_id'   => $product_id,
                        'product_type' => $product_type,
                    ) );

                    // Author box
                    get_template_part( 'template-parts/components/author-box', null, array(
                        'post_id' => $post_id,
                    ) );

                    // Related reviews section
                    get_template_part( 'template-parts/components/related-reviews', null, array(
                        'post_id'      => $post_id,
                        'product_type' => $product_type,
                        'count'        => 3,
                    ) );
                    ?>

                </article>

                <!-- Sidebar -->
                <aside class="sidebar">
                    <?php
                    // Tools section (Finder, Deals, Compare)
                    get_template_part( 'template-parts/sidebar/tools', null, array(
                        'product_type'  => $product_type,
                        'category_name' => $category_name,
                        'finder_page'   => $finder_page,
                        'deals_page'    => $deals_page,
                    ) );
                    ?>

                    <hr>

                    <?php
                    // Head-to-head comparison widget
                    get_template_part( 'template-parts/sidebar/comparison', null, array(
                        'product_id'    => $product_id,
                        'product_name'  => get_the_title( $product_id ),
                        'product_type'  => $product_type,
                        'category_slug' => $category_slug,
                    ) );
                    ?>

                    <hr>

                    <?php
                    // Table of contents
                    get_template_part( 'template-parts/sidebar/toc', null, array(
                        'items' => $toc_items,
                    ) );
                    ?>
                </aside>
            </div>
        </div>
    </div>
</main>

<?php
// Sticky buy bar - only show if product has prices
if ( $has_prices ) {
    get_template_part( 'template-parts/components/sticky-buy-bar', null, array(
        'product_id'   => $product_id,
        'product_name' => get_the_title( $product_id ),
        'compare_url'  => home_url( '/compare/' ),
    ) );
}
?>

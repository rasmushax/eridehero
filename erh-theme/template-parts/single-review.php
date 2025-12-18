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
$product_id   = is_object( $product ) ? $product->ID : $product;
$product_type = get_field( 'product_type', $product_id );

// Get product category for breadcrumb
$category_slug = erh_product_type_slug( $product_type );
$category_name = erh_get_product_type_short_name( $product_type );
?>

<main id="main-content" class="review-page">
    <div class="review-layout">
        <div class="container">
            <!-- Title Row -->
            <div class="review-title-row">
                <div class="review-title-content">
                    <?php erh_review_breadcrumb( $category_slug, $category_name ); ?>
                    <h1 class="review-title"><?php the_title(); ?></h1>
                </div>
            </div>

            <div class="review-layout-grid">
                <!-- Left Column: Main Content -->
                <article class="review-main">

                    <?php
                    // TODO: Gallery section
                    // TODO: Byline section
                    // TODO: Quick take + score
                    // TODO: Pros & cons
                    // TODO: Price intelligence
                    // TODO: Tested performance
                    // TODO: Review content (the_content)
                    // TODO: Full specifications
                    // TODO: Author box
                    // TODO: Related reviews
                    ?>

                    <!-- Placeholder: Review content -->
                    <div class="review-body">
                        <?php the_content(); ?>
                    </div>

                </article>

                <!-- Sidebar -->
                <aside class="sidebar">
                    <?php
                    // TODO: Sidebar components
                    ?>
                </aside>
            </div>
        </div>
    </div>
</main>

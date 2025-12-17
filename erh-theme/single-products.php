<?php
/**
 * Single Product Template
 *
 * The template for displaying single product reviews.
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

while ( have_posts() ) :
    the_post();

    // Get product data
    $product_id   = get_the_ID();
    $product_type = erh_get_product_type( $product_id );
    $product_slug = erh_product_type_slug( $product_type );
    ?>

    <article id="product-<?php the_ID(); ?>" <?php post_class( 'single-review' ); ?>>

        <!-- Breadcrumb -->
        <div class="container">
            <?php erh_the_breadcrumbs(); ?>
        </div>

        <!-- Product Header -->
        <header class="single-review-header">
            <div class="container">
                <h1 class="single-review-title"><?php the_title(); ?></h1>
            </div>
        </header>

        <!-- Main Content Layout -->
        <div class="container">
            <div class="content-with-sidebar">

                <!-- Main Content -->
                <div class="single-review-content">

                    <?php get_template_part( 'template-parts/product/gallery' ); ?>

                    <?php get_template_part( 'template-parts/product/quick-take' ); ?>

                    <?php get_template_part( 'template-parts/product/pros-cons' ); ?>

                    <?php get_template_part( 'template-parts/product/price-intel' ); ?>

                    <?php get_template_part( 'template-parts/product/key-specs' ); ?>

                    <?php get_template_part( 'template-parts/product/video-review' ); ?>

                    <!-- Review Content -->
                    <div class="single-review-body">
                        <?php the_content(); ?>
                    </div>

                    <?php get_template_part( 'template-parts/product/full-specs' ); ?>

                    <?php get_template_part( 'template-parts/product/related' ); ?>

                </div>

                <!-- Sidebar -->
                <aside class="single-review-sidebar">
                    <?php get_template_part( 'template-parts/product/sidebar' ); ?>
                </aside>

            </div>
        </div>

    </article>

    <?php
endwhile;

get_footer();

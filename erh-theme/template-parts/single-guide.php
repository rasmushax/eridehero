<?php
/**
 * Single Buying Guide Post Template Part
 *
 * Template for posts with the "buying-guide" tag.
 * Similar to articles but with tools sidebar for product categories.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$post_id = get_the_ID();

// Get product info via ACF relationship (Category -> Product Type).
$product_info = erh_get_product_info_for_post( $post_id );

// Get ToC items from post content.
$toc_items = erh_get_toc_from_content( $post_id );
?>

<main id="main-content" class="guide-page">
    <div class="guide-layout">
        <div class="container">
            <!-- Title Row -->
            <div class="guide-title-row">
                <div class="guide-title-content">
                    <?php erh_the_breadcrumbs(); ?>
                    <h1 class="guide-title"><?php the_title(); ?></h1>
                    <?php
                    $subtitle = get_field( 'buying_guide_subtitle', $post_id );
                    if ( $subtitle ) : ?>
                        <p class="guide-subtitle"><?php echo esc_html( $subtitle ); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="guide-layout-grid">
                <!-- Main Content -->
                <article class="guide-main">
                    <?php
                    // Guide hero with featured image.
                    get_template_part( 'template-parts/components/article-hero' );

                    // Byline section.
                    get_template_part( 'template-parts/components/byline', null, array(
                        'post_id' => $post_id,
                    ) );
                    ?>

                    <!-- Affiliate Disclaimer -->
                    <div class="affiliate-disclaimer">
                        <p>We earn commissions from links on our site, enabling us to deliver independent reviews. See our <a href="<?php echo esc_url( home_url( '/editorial/' ) ); ?>">editorial policy</a>.</p>
                    </div>

                    <!-- Guide content -->
                    <div class="guide-body" id="guide-content">
                        <?php the_content(); ?>
                    </div>

                    <?php
                    // Author box.
                    get_template_part( 'template-parts/components/author-box', null, array(
                        'post_id' => $post_id,
                    ) );

                    // Related buying guides.
                    get_template_part( 'template-parts/components/related-posts', null, array(
                        'title'     => __( 'More Buying Guides', 'erh' ),
                        'post_type' => 'buying-guide',
                        'count'     => 3,
                    ) );
                    ?>
                </article>

                <!-- Sidebar -->
                <aside class="sidebar">
                    <?php
                    // Tools section - only show if we have product category info.
                    if ( $product_info ) :
                        get_template_part( 'template-parts/sidebar/tools', null, array(
                            'product_type'  => $product_info['product_type'],
                            'category_name' => $product_info['category_name'],
                            'finder_page'   => $product_info['finder_page'] ?? '',
                            'deals_page'    => $product_info['deals_page'] ?? '',
                        ) );
                        ?>
                        <hr>
                    <?php endif; ?>

                    <?php
                    // Head-to-head comparison widget (open mode).
                    get_template_part( 'template-parts/sidebar/comparison-open' );
                    ?>

                    <?php
                    // Table of contents - only show if we have items.
                    if ( ! empty( $toc_items ) ) :
                        ?>
                        <hr>
                        <?php
                        get_template_part( 'template-parts/sidebar/toc', null, array(
                            'items' => $toc_items,
                        ) );
                    endif;
                    ?>
                </aside>
            </div>
        </div>
    </div>
</main>

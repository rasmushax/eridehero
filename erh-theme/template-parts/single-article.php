<?php
/**
 * Single Article Post Template Part
 *
 * Template for regular articles (without "review" or "buying-guide" tags).
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

<main id="main-content" class="article-page">
    <div class="article-layout">
        <div class="container">
            <!-- Title Row -->
            <div class="article-title-row">
                <div class="article-title-content">
                    <?php erh_the_breadcrumbs(); ?>
                    <h1 class="article-title"><?php the_title(); ?></h1>
                </div>
            </div>

            <div class="article-layout-grid">
                <!-- Main Content -->
                <article class="article-main">
                    <?php
                    // Article hero with featured image.
                    get_template_part( 'template-parts/components/article-hero' );

                    // Byline section.
                    get_template_part( 'template-parts/components/byline', null, array(
                        'post_id' => $post_id,
                    ) );
                    ?>

                    <!-- Article content -->
                    <div class="article-body" id="article-content">
                        <?php the_content(); ?>
                    </div>

                    <?php
                    // Author box.
                    get_template_part( 'template-parts/components/author-box', null, array(
                        'post_id' => $post_id,
                    ) );

                    // Related articles.
                    get_template_part( 'template-parts/components/related-posts', null, array(
                        'title'        => __( 'More articles', 'erh' ),
                        'post_type'    => 'article',
                        'count'        => 3,
                        'view_all_url' => home_url( '/articles/' ),
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
                    // Head-to-head comparison widget (open mode - filters by post categories).
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

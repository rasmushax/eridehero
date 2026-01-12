<?php
/**
 * Similar Products Carousel
 *
 * Displays similar products in a horizontally scrollable carousel.
 * Products are loaded client-side via REST API for geo-aware pricing.
 *
 * @package ERideHero
 *
 * @var array $args {
 *     @type int    $product_id    Current product ID to find similar products for.
 *     @type string $product_type  Product type label (e.g., 'Electric Scooter').
 *     @type string $category_name Short category name (e.g., 'E-Scooter').
 *     @type int    $limit         Number of products to show (default: 10).
 * }
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Extract args with fallbacks.
$product_id    = $args['product_id'] ?? get_the_ID();
$product_type  = $args['product_type'] ?? erh_get_product_type( $product_id );
$category_name = $args['category_name'] ?? erh_get_product_type_short_name( $product_type );
$limit         = $args['limit'] ?? 10;

if ( empty( $product_type ) || empty( $product_id ) ) {
    return;
}

// Section title.
$section_title = sprintf( __( 'Similar %ss', 'erh' ), $category_name );

// Get finder page URL from ACF on product_type taxonomy.
$finder_url = erh_get_finder_url( $product_id );
?>

<section
    class="content-section similar-products"
    id="similar"
    data-similar-products
    data-product-id="<?php echo esc_attr( $product_id ); ?>"
    data-limit="<?php echo esc_attr( $limit ); ?>"
>
    <div class="section-header">
        <h2><?php echo esc_html( $section_title ); ?></h2>
        <?php if ( $finder_url ) : ?>
            <a href="<?php echo esc_url( $finder_url ); ?>" class="btn btn-secondary">
                <?php printf( esc_html__( 'Find more %ss', 'erh' ), $category_name ); ?>
                <?php erh_the_icon( 'arrow-right' ); ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="similar-carousel">
        <!-- Carousel Arrows -->
        <button type="button" class="carousel-arrow carousel-arrow-left" aria-label="<?php esc_attr_e( 'Scroll left', 'erh' ); ?>" disabled>
            <?php erh_the_icon( 'chevron-left' ); ?>
        </button>
        <button type="button" class="carousel-arrow carousel-arrow-right" aria-label="<?php esc_attr_e( 'Scroll right', 'erh' ); ?>">
            <?php erh_the_icon( 'chevron-right' ); ?>
        </button>

        <!-- Products Grid -->
        <div class="similar-grid" data-similar-grid>
            <!-- Skeleton loading cards -->
            <?php for ( $i = 0; $i < min( 6, $limit ); $i++ ) : ?>
                <div class="similar-card similar-card-skeleton">
                    <div class="similar-card-image">
                        <div class="skeleton skeleton-img"></div>
                    </div>
                    <div class="similar-card-content">
                        <div class="skeleton skeleton-text"></div>
                        <div class="skeleton skeleton-text-sm"></div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>

        <!-- Empty state (hidden by default) -->
        <div class="similar-empty empty-state" style="display: none;" data-similar-empty>
            <?php erh_the_icon( 'search' ); ?>
            <p><?php esc_html_e( 'No similar products found.', 'erh' ); ?></p>
        </div>
    </div>
</section>

<!-- Similar Card Template (used by JavaScript) -->
<template id="similar-card-template">
    <a href="" class="similar-card" data-category="">
        <div class="similar-card-image">
            <img src="" alt="" loading="lazy">
            <div class="similar-card-price-row">
                <span class="similar-card-price" data-card-price></span>
                <span class="similar-card-indicator" data-card-indicator style="display: none;"></span>
            </div>
        </div>
        <div class="similar-card-content">
            <span class="similar-card-name" data-card-name></span>
            <span class="similar-card-specs" data-card-specs></span>
        </div>
    </a>
</template>

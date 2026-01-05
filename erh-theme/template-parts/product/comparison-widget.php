<?php
/**
 * Product Comparison Widget
 *
 * Head-to-head comparison widget for product pages.
 * Uses dark card style from homepage with locked product pattern.
 *
 * @package ERideHero
 *
 * @var array $args {
 *     @type int         $product_id    Product ID (locked in slot 0).
 *     @type string      $product_name  Product name.
 *     @type int|null    $product_image Image attachment ID.
 *     @type string      $category_key  Category key (escooter, ebike, etc.).
 * }
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Extract args.
$product_id    = $args['product_id'] ?? 0;
$product_name  = $args['product_name'] ?? '';
$product_image = $args['product_image'] ?? null;
$category_key  = $args['category_key'] ?? 'escooter';

// Bail if no product.
if ( empty( $product_id ) || empty( $product_name ) ) {
    return;
}

// Get thumbnail URL.
$thumbnail_url = '';
if ( $product_image ) {
    $thumbnail_url = wp_get_attachment_image_url( $product_image, 'thumbnail' );
}

// Fallback to featured image.
if ( empty( $thumbnail_url ) ) {
    $featured_id = get_post_thumbnail_id( $product_id );
    if ( $featured_id ) {
        $thumbnail_url = wp_get_attachment_image_url( $featured_id, 'thumbnail' );
    }
}

// Get JSON URL.
$upload_dir = wp_upload_dir();
$json_url   = $upload_dir['baseurl'] . '/comparison_products.json';
?>

<section class="review-section product-comparison-section" id="compare">
    <div class="product-comparison"
         data-product-comparison
         data-json-url="<?php echo esc_url( $json_url ); ?>"
         data-locked-id="<?php echo esc_attr( $product_id ); ?>"
         data-locked-name="<?php echo esc_attr( $product_name ); ?>"
         data-locked-image="<?php echo esc_url( $thumbnail_url ); ?>"
         data-locked-category="<?php echo esc_attr( $category_key ); ?>">

        <!-- Dark gradient background -->
        <div class="product-comparison-bg" aria-hidden="true">
            <div class="product-comparison-orb"></div>
        </div>

        <div class="product-comparison-content">
            <!-- Header -->
            <header class="product-comparison-header">
                <h2>Compare head-to-head</h2>
                <p>See how this product stacks up against competitors.</p>
            </header>

            <!-- Screen reader announcements -->
            <div class="sr-only" aria-live="polite" aria-atomic="true" data-comparison-announcer></div>

            <!-- Comparison Row -->
            <div class="product-comparison-row">

                <!-- Locked Product (Slot 0) -->
                <div class="product-comparison-slot product-comparison-slot--locked">
                    <div class="product-comparison-locked">
                        <?php if ( $thumbnail_url ) : ?>
                            <img class="product-comparison-locked-thumb"
                                 src="<?php echo esc_url( $thumbnail_url ); ?>"
                                 alt=""
                                 loading="lazy">
                        <?php endif; ?>
                        <span class="product-comparison-locked-name"><?php echo esc_html( $product_name ); ?></span>
                        <span class="product-comparison-locked-badge">This product</span>
                    </div>
                </div>

                <!-- VS Divider -->
                <span class="product-comparison-vs">vs</span>

                <!-- Search Input (Slot 1) -->
                <div class="product-comparison-slot">
                    <div class="comparison-input-wrapper">
                        <input type="text"
                               class="comparison-input"
                               placeholder="Search products..."
                               autocomplete="off"
                               data-slot="1">
                        <button type="button" class="comparison-input-clear" aria-label="Clear selection">
                            <?php erh_the_icon( 'x' ); ?>
                        </button>
                        <img class="comparison-input-thumb" src="" alt="" aria-hidden="true">
                        <div class="comparison-results"></div>
                    </div>
                </div>

                <!-- Compare Button -->
                <div class="product-comparison-actions">
                    <button type="button" class="product-comparison-btn" data-compare-submit disabled>
                        Compare
                        <?php erh_the_icon( 'arrow-right' ); ?>
                    </button>
                </div>

            </div>

        </div>
    </div>
</section>

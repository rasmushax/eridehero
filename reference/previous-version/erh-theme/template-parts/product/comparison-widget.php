<?php
/**
 * Product Comparison Widget
 *
 * Head-to-head comparison widget for product pages.
 * Reuses homepage comparison structure with locked first slot.
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

<section class="content-section product-comparison-section" id="compare">
    <div class="comparison-card"
         id="comparison-container"
         data-json-url="<?php echo esc_url( $json_url ); ?>"
         data-locked-id="<?php echo esc_attr( $product_id ); ?>"
         data-locked-name="<?php echo esc_attr( $product_name ); ?>"
         data-locked-image="<?php echo esc_url( $thumbnail_url ); ?>"
         data-locked-category="<?php echo esc_attr( $category_key ); ?>">

        <!-- Background (clips orbs, allows dropdown overflow) -->
        <div class="comparison-card-bg" aria-hidden="true">
            <div class="comparison-orb"></div>
        </div>

        <div class="comparison-content">
            <!-- Header (no category pill on product page) -->
            <header class="comparison-header">
                <h2><?php
                    $category_labels = array(
                        'escooter'   => 'e-scooters',
                        'ebike'      => 'e-bikes',
                        'eskate'     => 'e-skateboards',
                        'euc'        => 'electric unicycles',
                        'hoverboard' => 'hoverboards',
                    );
                    $category_label = $category_labels[ $category_key ] ?? 'products';
                    printf( esc_html__( 'Compare %1$s to other %2$s', 'erh' ), esc_html( $product_name ), esc_html( $category_label ) );
                ?></h2>
            </header>

            <!-- Screen reader announcements -->
            <div id="comparison-announcer" class="sr-only" aria-live="polite" aria-atomic="true"></div>

            <div class="comparison-row-main">
                <!-- Left column: Locked current product -->
                <div class="comparison-column-left">
                    <div class="comparison-input-wrapper comparison-input-wrapper--locked">
                        <div class="comparison-locked-product">
                            <?php if ( $thumbnail_url ) : ?>
                                <img class="comparison-locked-thumb"
                                     src="<?php echo esc_url( $thumbnail_url ); ?>"
                                     alt=""
                                     loading="lazy">
                            <?php endif; ?>
                            <span class="comparison-locked-name"><?php echo esc_html( $product_name ); ?></span>
                        </div>
                    </div>
                </div>

                <!-- VS divider -->
                <span class="comparison-vs"><?php esc_html_e( 'vs', 'erh' ); ?></span>

                <!-- Right column: search inputs (supports multiple) -->
                <div class="comparison-column-right" id="comparison-right-column">
                    <div class="comparison-input-wrapper">
                        <input type="text" class="comparison-input" placeholder="<?php esc_attr_e( 'Search products...', 'erh' ); ?>" autocomplete="off" data-slot="1">
                        <button type="button" class="comparison-input-clear" aria-label="<?php esc_attr_e( 'Clear selection', 'erh' ); ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                        <img class="comparison-input-thumb" src="" alt="" aria-hidden="true">
                        <div class="comparison-results"></div>
                    </div>
                </div>

                <!-- Compare button -->
                <div class="comparison-actions">
                    <button type="button" class="comparison-btn" id="comparison-submit" disabled>
                        <?php esc_html_e( 'Compare', 'erh' ); ?>
                        <?php echo erh_icon( 'arrow-right', 'icon' ); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

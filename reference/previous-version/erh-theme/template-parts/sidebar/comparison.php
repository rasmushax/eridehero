<?php
/**
 * Sidebar Comparison Widget
 *
 * Compact head-to-head comparison tool for sidebar.
 * Pre-populates with current product and allows selecting one to compare.
 *
 * @package ERideHero
 *
 * Expected $args:
 *   'product_id'   => int    - Current product ID (locked in slot 0)
 *   'product_name' => string - Product name
 *   'product_type' => string - Product type (e.g., 'Electric Scooter')
 *   'category_slug' => string - Category slug for filtering (e.g., 'escooter')
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get arguments
$product_id    = $args['product_id'] ?? 0;
$product_name  = $args['product_name'] ?? '';
$product_type  = $args['product_type'] ?? '';
$category_slug = $args['category_slug'] ?? '';

// Bail if no product
if ( empty( $product_id ) || empty( $product_name ) ) {
    return;
}

// Get product thumbnail
$thumbnail_id  = get_post_thumbnail_id( $product_id );
$thumbnail_url = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ) : '';

// Fallback to big_thumbnail ACF field if no featured image
if ( empty( $thumbnail_url ) ) {
    $big_thumbnail = get_field( 'big_thumbnail', $product_id );
    if ( $big_thumbnail ) {
        $thumbnail_url = is_array( $big_thumbnail ) ? $big_thumbnail['sizes']['thumbnail'] ?? $big_thumbnail['url'] : wp_get_attachment_image_url( $big_thumbnail, 'thumbnail' );
    }
}

// Map product type to category key for JS filtering
$category_map = array(
    'Electric Scooter'    => 'escooter',
    'Electric Bike'       => 'ebike',
    'Electric Skateboard' => 'eskate',
    'Electric Unicycle'   => 'euc',
    'Hoverboard'          => 'hoverboard',
);
$category_key = $category_map[ $product_type ] ?? '';

// Get JSON URL
$upload_dir = wp_upload_dir();
$json_url   = $upload_dir['baseurl'] . '/comparison_products.json';
?>

<div class="sidebar-section">
    <h3 class="sidebar-title">Compare</h3>

    <div class="sidebar-comparison"
         id="sidebar-comparison"
         data-json-url="<?php echo esc_url( $json_url ); ?>"
         data-locked-id="<?php echo esc_attr( $product_id ); ?>"
         data-locked-name="<?php echo esc_attr( $product_name ); ?>"
         data-locked-image="<?php echo esc_url( $thumbnail_url ); ?>"
         data-locked-category="<?php echo esc_attr( $category_key ); ?>">

        <!-- Locked product (current review) -->
        <div class="sidebar-comparison-locked">
            <?php if ( $thumbnail_url ) : ?>
                <img class="sidebar-comparison-locked-thumb"
                     src="<?php echo esc_url( $thumbnail_url ); ?>"
                     alt="">
            <?php endif; ?>
            <span class="sidebar-comparison-locked-name"><?php echo esc_html( $product_name ); ?></span>
        </div>

        <!-- VS divider -->
        <span class="sidebar-comparison-vs">vs</span>

        <!-- Inputs container (for dynamic additions) -->
        <div class="sidebar-comparison-inputs" id="sidebar-comparison-inputs">
            <div class="comparison-input-wrapper">
                <input type="text"
                       class="comparison-input"
                       placeholder="Search to compare..."
                       autocomplete="off"
                       data-slot="1">
                <button type="button" class="comparison-input-clear" aria-label="Clear selection">
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
        <button type="button" class="sidebar-comparison-btn" id="sidebar-comparison-submit" disabled>
            Compare
            <?php erh_the_icon( 'arrow-right' ); ?>
        </button>

        <!-- Screen reader announcements -->
        <div id="sidebar-comparison-announcer" class="sr-only" aria-live="polite" aria-atomic="true"></div>
    </div>
</div>

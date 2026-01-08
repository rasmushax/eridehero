<?php
/**
 * Sidebar Comparison Widget (Open Mode)
 *
 * Head-to-head comparison tool without a locked product.
 * Filters available products by post categories.
 *
 * @package ERideHero
 *
 * Expected $args:
 *   'allowed_categories' => array - Category slugs to filter by (e.g., ['escooter', 'ebike'])
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get allowed categories from args or derive from post
$allowed_categories = $args['allowed_categories'] ?? array();

// If no categories passed, try to get from current post
if ( empty( $allowed_categories ) ) {
    $post_categories = get_the_category();

    // Map WP category slugs to product category keys
    $category_map = array(
        'electric-scooters' => 'escooter',
        'e-scooters'        => 'escooter',
        'escooter'          => 'escooter',
        'electric-bikes'    => 'ebike',
        'e-bikes'           => 'ebike',
        'ebike'             => 'ebike',
        'electric-skateboards' => 'eskate',
        'e-skateboards'     => 'eskate',
        'eskate'            => 'eskate',
        'electric-unicycles' => 'euc',
        'eucs'              => 'euc',
        'euc'               => 'euc',
        'hoverboards'       => 'hoverboard',
        'hoverboard'        => 'hoverboard',
    );

    foreach ( $post_categories as $cat ) {
        $slug = $cat->slug;
        if ( isset( $category_map[ $slug ] ) ) {
            $allowed_categories[] = $category_map[ $slug ];
        }
    }

    // Remove duplicates
    $allowed_categories = array_unique( $allowed_categories );
}

// Bail if no product-related categories
if ( empty( $allowed_categories ) ) {
    return;
}

// Get JSON URL
$upload_dir = wp_upload_dir();
$json_url   = $upload_dir['baseurl'] . '/comparison_products.json';

// Generate unique ID for this instance
$instance_id = 'comparison-open-' . wp_rand( 1000, 9999 );
?>

<div class="sidebar-section">
    <h3 class="sidebar-title">Compare Products</h3>

    <div class="sidebar-comparison sidebar-comparison--open"
         id="<?php echo esc_attr( $instance_id ); ?>"
         data-json-url="<?php echo esc_url( $json_url ); ?>"
         data-allowed-categories="<?php echo esc_attr( implode( ',', $allowed_categories ) ); ?>">

        <!-- First product input -->
        <div class="sidebar-comparison-inputs">
            <div class="comparison-input-wrapper">
                <input type="text"
                       class="comparison-input"
                       placeholder="Search first product..."
                       autocomplete="off"
                       data-slot="0">
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

        <!-- VS divider -->
        <span class="sidebar-comparison-vs">vs</span>

        <!-- Second product input -->
        <div class="sidebar-comparison-inputs" id="<?php echo esc_attr( $instance_id ); ?>-inputs">
            <div class="comparison-input-wrapper">
                <input type="text"
                       class="comparison-input"
                       placeholder="Search second product..."
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
        <button type="button" class="sidebar-comparison-btn" disabled>
            Compare
            <?php erh_the_icon( 'arrow-right' ); ?>
        </button>

        <!-- Screen reader announcements -->
        <div class="sr-only" aria-live="polite" aria-atomic="true"></div>
    </div>
</div>

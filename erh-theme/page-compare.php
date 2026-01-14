<?php
/**
 * Template Name: Product Comparison
 *
 * Multi-purpose comparison page template:
 * - Hub page (no products): Shows hero, categories, featured, popular
 * - Comparison page (with products): Shows head-to-head comparison
 *
 * @package ERideHero
 */

defined( 'ABSPATH' ) || exit;

get_header();

// Parse product IDs from URL (SEO slugs or query string).
$product_ids   = erh_get_compare_product_ids();
$has_products  = count( $product_ids ) >= 2;
$is_hub_page   = ! $has_products;

// Detect category from first product.
$category      = 'escooter';
$category_name = 'E-Scooters';
$category_slug = 'escooter';

if ( ! empty( $product_ids[0] ) ) {
    $product_type = get_field( 'product_type', $product_ids[0] );
    if ( $product_type ) {
        $category_data = erh_get_category_from_type( $product_type );
        $category      = $category_data['key'];
        $category_name = $category_data['name'];
        $category_slug = $category_data['slug'];
    }
}

// Build page title from product names.
$product_names = array_map( 'get_the_title', $product_ids );
$page_title    = $has_products
    ? implode( ' vs ', array_slice( $product_names, 0, 3 ) ) . ( count( $product_names ) > 3 ? ' & more' : '' )
    : 'Compare Products';
?>

<main class="compare-page<?php echo $is_hub_page ? ' compare-page--hub' : ''; ?>" data-compare-page data-category="<?php echo esc_attr( $category ); ?>">

    <?php if ( $is_hub_page ) : ?>
        <!-- Hub Page: Compare Center -->
        <?php get_template_part( 'template-parts/compare/hub-hero' ); ?>
        <?php get_template_part( 'template-parts/compare/hub-categories' ); ?>
        <?php get_template_part( 'template-parts/compare/hub-featured' ); ?>
        <?php get_template_part( 'template-parts/compare/hub-popular' ); ?>

    <?php else : ?>
        <!-- Comparison Content -->
        <div class="container">
            <?php
            erh_breadcrumb( [
                [ 'label' => $category_name, 'url' => home_url( '/' . $category_slug . '/' ) ],
                [ 'label' => $page_title ],
            ] );
            ?>
        </div>

        <!-- Sticky Product Header -->
        <header class="compare-header" data-compare-header>
            <div class="container">
                <div class="compare-header-products" data-compare-products>
                    <!-- Product cards rendered by JS -->
                </div>
            </div>
        </header>

        <!-- Sticky Section Nav -->
        <nav class="compare-nav" data-compare-nav>
            <div class="container">
                <div class="compare-nav-links">
                    <a href="#overview" class="compare-nav-link is-active" data-nav-link="overview">Overview</a>
                    <a href="#specifications" class="compare-nav-link" data-nav-link="specs">Specifications</a>
                    <a href="#pricing" class="compare-nav-link" data-nav-link="pricing">Pricing</a>
                </div>
            </div>
        </nav>

        <!-- All Sections (Single Scroll) -->
        <div class="compare-content">
            <div class="container">

                <!-- Overview Section -->
                <section id="overview" class="compare-section" data-section="overview">
                    <h2 class="compare-section-title">Overview</h2>
                    <div class="compare-overview" data-compare-overview>
                        <div class="compare-loading">
                            <span class="compare-loading-spinner"></span>
                            <span>Loading comparison...</span>
                        </div>
                    </div>
                </section>

                <!-- Specifications Section -->
                <section id="specifications" class="compare-section" data-section="specs">
                    <h2 class="compare-section-title">Specifications</h2>
                    <div class="compare-specs" data-compare-specs></div>
                </section>

                <!-- Pricing Section -->
                <section id="pricing" class="compare-section" data-section="pricing">
                    <h2 class="compare-section-title">Pricing</h2>
                    <div class="compare-pricing" data-compare-pricing></div>
                </section>

            </div>
        </div>

    <?php endif; ?>

</main>

<!-- Add Product Modal -->
<div class="modal" id="compare-add-modal" data-modal aria-hidden="true">
    <div class="modal-content modal-content--md">
        <button class="modal-close" data-modal-close aria-label="Close modal">
            <?php erh_the_icon( 'x' ); ?>
        </button>
        <h2 class="modal-title">Add Product to Compare</h2>
        <div class="modal-body">
            <div class="compare-search">
                <div class="compare-search-field">
                    <?php erh_the_icon( 'search' ); ?>
                    <input type="text" class="compare-search-input" placeholder="Search products..." data-compare-search autocomplete="off">
                </div>
                <div class="compare-search-results" data-compare-results></div>
            </div>
        </div>
    </div>
</div>

<?php
get_footer();

// Page-specific data must come AFTER get_footer() so it runs after wp_localize_script output.
?>
<script>
// Extend erhData with page-specific config (erhData set by wp_localize_script in footer)
window.erhData = window.erhData || {};
window.erhData.compareConfig = {
    productIds: <?php echo wp_json_encode( array_map( 'intval', $product_ids ) ); ?>,
    category: <?php echo wp_json_encode( $category ); ?>,
    categoryName: <?php echo wp_json_encode( $category_name ); ?>,
    categorySlug: <?php echo wp_json_encode( $category_slug ); ?>
};
</script>
<?php

/**
 * Get product IDs from URL (SEO slugs or query string).
 *
 * @return int[] Array of product post IDs.
 */
function erh_get_compare_product_ids(): array {
    $compare_slugs = get_query_var( 'compare_slugs', '' );

    if ( ! empty( $compare_slugs ) ) {
        $slugs = array_filter( explode( ',', $compare_slugs ) );
        if ( empty( $slugs ) ) {
            return [];
        }

        global $wpdb;
        $placeholders = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return array_map( 'intval', $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_type = 'products'
                 AND post_status = 'publish'
                 AND post_name IN ({$placeholders})
                 ORDER BY FIELD(post_name, {$placeholders})",
                ...array_merge( $slugs, $slugs )
            )
        ) );
    }

    // Query string fallback: ?products=123,456
    $products_param = isset( $_GET['products'] ) ? sanitize_text_field( wp_unslash( $_GET['products'] ) ) : '';
    if ( ! empty( $products_param ) ) {
        return array_filter( array_map( 'absint', explode( ',', $products_param ) ) );
    }

    return [];
}

/**
 * Get category data from product type.
 *
 * @param string|null $product_type The product type field value.
 * @return array{key: string, name: string, slug: string}
 */
function erh_get_category_from_type( ?string $product_type ): array {
    $types = [
        'Electric Scooter'    => [ 'key' => 'escooter', 'name' => 'E-Scooters', 'slug' => 'escooter' ],
        'Electric Bike'       => [ 'key' => 'ebike', 'name' => 'E-Bikes', 'slug' => 'ebike' ],
        'Electric Skateboard' => [ 'key' => 'eskateboard', 'name' => 'E-Skateboards', 'slug' => 'eskateboard' ],
        'Electric Unicycle'   => [ 'key' => 'euc', 'name' => 'Electric Unicycles', 'slug' => 'euc' ],
        'Hoverboard'          => [ 'key' => 'hoverboard', 'name' => 'Hoverboards', 'slug' => 'hoverboard' ],
    ];

    return $types[ $product_type ?? '' ] ?? [ 'key' => 'escooter', 'name' => 'E-Scooters', 'slug' => 'escooter' ];
}

<?php
/**
 * Template Name: Product Comparison
 *
 * Head-to-head product comparison page.
 * All content rendered in single scrollable page with sticky section nav.
 *
 * @package ERideHero
 */

defined( 'ABSPATH' ) || exit;

get_header();

// Parse product IDs from URL (SEO slugs or query string).
$product_ids   = erh_get_compare_product_ids();
$has_products  = count( $product_ids ) >= 2;

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

<main class="compare-page" data-compare-page data-category="<?php echo esc_attr( $category ); ?>">

    <?php if ( ! $has_products ) : ?>
        <!-- Empty State -->
        <div class="container">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="<?php echo esc_url( home_url( '/' . $category_slug . '/' ) ); ?>"><?php echo esc_html( $category_name ); ?></a>
                <span class="breadcrumb-sep">/</span>
                <span class="breadcrumb-current">Compare</span>
            </nav>
        </div>

        <section class="compare-empty">
            <div class="container">
                <div class="compare-empty-content">
                    <h1>Compare <?php echo esc_html( $category_name ); ?></h1>
                    <p>Select products to compare specs, prices, and features side-by-side.</p>

                    <div class="compare-selector" id="compare-selector">
                        <div class="compare-selector-inputs" data-compare-inputs></div>
                        <button type="button" class="btn btn--outline compare-selector-add" data-compare-add>
                            <?php erh_the_icon( 'plus' ); ?>
                            <span>Add Product</span>
                        </button>
                    </div>
                </div>
            </div>
        </section>

    <?php else : ?>
        <!-- Comparison Content -->
        <div class="container">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="<?php echo esc_url( home_url( '/' . $category_slug . '/' ) ); ?>"><?php echo esc_html( $category_name ); ?></a>
                <span class="breadcrumb-sep">/</span>
                <span class="breadcrumb-current"><?php echo esc_html( $page_title ); ?></span>
            </nav>
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

<script>
window.ERideHero = window.ERideHero || {};
window.ERideHero.siteUrl = <?php echo wp_json_encode( untrailingslashit( home_url() ) ); ?>;
window.ERideHero.compareConfig = {
    productIds: <?php echo wp_json_encode( array_map( 'intval', $product_ids ) ); ?>,
    category: <?php echo wp_json_encode( $category ); ?>,
    categoryName: <?php echo wp_json_encode( $category_name ); ?>,
    categorySlug: <?php echo wp_json_encode( $category_slug ); ?>
};
</script>

<?php get_footer();

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
 * @param string $product_type The product type field value.
 * @return array{key: string, name: string, slug: string}
 */
function erh_get_category_from_type( string $product_type ): array {
    $types = [
        'Electric Scooter'    => [ 'key' => 'escooter', 'name' => 'E-Scooters', 'slug' => 'escooter' ],
        'Electric Bike'       => [ 'key' => 'ebike', 'name' => 'E-Bikes', 'slug' => 'ebike' ],
        'Electric Skateboard' => [ 'key' => 'eskateboard', 'name' => 'E-Skateboards', 'slug' => 'eskateboard' ],
        'Electric Unicycle'   => [ 'key' => 'euc', 'name' => 'Electric Unicycles', 'slug' => 'euc' ],
        'Hoverboard'          => [ 'key' => 'hoverboard', 'name' => 'Hoverboards', 'slug' => 'hoverboard' ],
    ];

    return $types[ $product_type ] ?? [ 'key' => 'escooter', 'name' => 'E-Scooters', 'slug' => 'escooter' ];
}

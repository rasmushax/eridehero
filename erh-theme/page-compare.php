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

// SSR: Fetch products from database for server-side rendering.
$compare_products = array();
$geo              = isset( $_COOKIE['erh_geo'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['erh_geo'] ) ) : 'US';
$currency_symbol  = erh_get_currency_symbol( $geo );

if ( $has_products ) {
    $compare_products = erh_get_compare_products( $product_ids, $geo );
    // If not enough products found in cache, fall back to hub page.
    if ( count( $compare_products ) < 2 ) {
        $has_products = false;
        $is_hub_page  = true;
    }
}
?>

<?php
$product_count      = count( $product_ids );
$product_count_attr = $has_products ? ' data-product-count="' . $product_count . '" style="--product-count: ' . $product_count . ';"' : '';
$is_full_width      = $has_products && $product_count >= 5;
$page_classes       = 'compare-page';
$page_classes      .= $is_hub_page ? ' compare-page--hub' : '';
$page_classes      .= $is_full_width ? ' compare-page--full-width' : '';
?>
<main class="<?php echo esc_attr( $page_classes ); ?>" data-compare-page data-category="<?php echo esc_attr( $category ); ?>"<?php echo $product_count_attr; ?>>

    <?php if ( $is_hub_page ) : ?>
        <!-- Hub Page: Compare Center -->
        <?php get_template_part( 'template-parts/compare/hub-hero' ); ?>
        <?php get_template_part( 'template-parts/compare/hub-categories' ); ?>
        <?php get_template_part( 'template-parts/compare/hub-featured' ); ?>
        <?php get_template_part( 'template-parts/compare/hub-popular' ); ?>

    <?php else : ?>
        <!-- SSR Comparison Content -->
        <div data-ssr-rendered>
            <div class="container">
                <?php
                erh_breadcrumb( [
                    [ 'label' => $category_name, 'url' => home_url( '/' . $category_slug . '/' ) ],
                    [ 'label' => $page_title ],
                ] );
                ?>
            </div>

            <!-- SSR: Product Hero Cards -->
            <?php
            get_template_part( 'template-parts/compare/header-products', null, [
                'products' => $compare_products,
                'geo'      => $geo,
            ] );
            ?>

            <!-- Sticky Section Nav -->
            <?php
            $spec_groups = erh_get_compare_spec_groups( $category );
            ?>
            <nav class="compare-nav" data-compare-nav>
                <div class="container">
                    <div class="compare-nav-links" data-nav-links>
                        <a href="#overview" class="compare-nav-link is-active" data-nav-link="overview">Overview</a>
                        <?php foreach ( $spec_groups as $group_name => $group ) : ?>
                            <a href="#<?php echo esc_attr( sanitize_title( $group_name ) ); ?>"
                               class="compare-nav-link"
                               data-nav-link="<?php echo esc_attr( sanitize_title( $group_name ) ); ?>">
                                <?php echo esc_html( $group_name ); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </nav>

            <!-- SSR: Overview Section -->
            <div class="compare-content">
                <div class="container">
                    <section id="overview" class="compare-section" data-section="overview">
                        <h2 class="compare-section-title">Overview</h2>
                        <div class="compare-overview" data-compare-overview>
                            <?php
                            get_template_part( 'template-parts/compare/overview', null, [
                                'products' => $compare_products,
                                'category' => $category,
                            ] );
                            ?>
                        </div>
                    </section>
                </div>
            </div>

            <!-- SSR: Specifications Section -->
            <section id="specifications" class="compare-section compare-section--specs" data-section="specs">
                <div class="container">
                    <h2 class="compare-section-title">Specifications</h2>
                    <div class="compare-specs" data-compare-specs>
                        <?php
                        // Render mini-header.
                        get_template_part( 'template-parts/compare/mini-header', null, [
                            'products'        => $compare_products,
                            'geo'             => $geo,
                            'currency_symbol' => $currency_symbol,
                        ] );

                        // Check if any product has pricing in any geo (for Value Analysis).
                        $any_has_pricing = erh_any_product_has_pricing( $product_ids );

                        // Render each spec category table.
                        foreach ( $spec_groups as $group_name => $group ) {
                            $is_value_section = ! empty( $group['isValueSection'] );

                            // Skip Value Analysis entirely if no product has any pricing.
                            if ( $is_value_section && ! $any_has_pricing ) {
                                continue;
                            }

                            $specs = $group['specs'] ?? array();

                            // For Value Analysis, render with empty cells (JS will hydrate).
                            if ( $is_value_section ) {
                                get_template_part( 'template-parts/compare/specs-table-value', null, [
                                    'group_name'      => $group_name,
                                    'group_slug'      => sanitize_title( $group_name ),
                                    'specs'           => $specs,
                                    'products'        => $compare_products,
                                    'currency_symbol' => $currency_symbol,
                                ] );
                                continue;
                            }

                            $rows = erh_build_compare_spec_rows( $compare_products, $specs, $geo, $currency_symbol );

                            if ( empty( $rows ) ) {
                                continue;
                            }

                            get_template_part( 'template-parts/compare/specs-table', null, [
                                'group_name'      => $group_name,
                                'group_slug'      => sanitize_title( $group_name ),
                                'rows'            => $rows,
                                'products'        => $compare_products,
                                'currency_symbol' => $currency_symbol,
                            ] );
                        }
                        ?>
                    </div>
                </div>
            </section>
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
    categorySlug: <?php echo wp_json_encode( $category_slug ); ?>,
    geo: <?php echo wp_json_encode( $geo ); ?>,
    currencySymbol: <?php echo wp_json_encode( $currency_symbol ); ?>
};
</script>
<?php if ( ! empty( $compare_products ) ) : ?>
<!-- Products JSON for JS hydration -->
<script type="application/json" data-products-json>
<?php echo wp_json_encode( $compare_products ); ?>
</script>
<?php endif; ?>
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

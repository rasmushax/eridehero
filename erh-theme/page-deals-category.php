<?php
/**
 * Template Name: Deals Category
 *
 * Template for category-specific deals pages (e.g., E-Scooter Deals).
 * Uses page slug to determine which category to display.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

// Category configuration based on page slug.
$page_slug = get_post_field( 'post_name', get_the_ID() );

$category_config = [
    'electric-scooters' => [
        'title'       => 'E-Scooter Deals',
        'description' => 'Electric scooters currently priced below their average.',
        'category'    => 'escooter',
        'type'        => 'Electric Scooter',
    ],
    'electric-bikes' => [
        'title'       => 'E-Bike Deals',
        'description' => 'Electric bikes currently priced below their average.',
        'category'    => 'ebike',
        'type'        => 'Electric Bike',
    ],
    'electric-skateboards' => [
        'title'       => 'E-Skateboard Deals',
        'description' => 'Electric skateboards currently priced below their average.',
        'category'    => 'eskateboard',
        'type'        => 'Electric Skateboard',
    ],
    'electric-unicycles' => [
        'title'       => 'EUC Deals',
        'description' => 'Electric unicycles currently priced below their average.',
        'category'    => 'euc',
        'type'        => 'Electric Unicycle',
    ],
    'hoverboards' => [
        'title'       => 'Hoverboard Deals',
        'description' => 'Hoverboards currently priced below their average.',
        'category'    => 'hoverboard',
        'type'        => 'Hoverboard',
    ],
];

// Get config for current page, fallback to e-scooters.
$config = $category_config[ $page_slug ] ?? $category_config['electric-scooters'];

// Get parent deals page URL.
$parent_id  = wp_get_post_parent_id( get_the_ID() );
$parent_url = $parent_id ? get_permalink( $parent_id ) : home_url( '/deals/' );
?>

<main class="deals-page" data-deals-page data-category="<?php echo esc_attr( $config['category'] ); ?>">

    <?php
    // Hero section.
    get_template_part( 'template-parts/deals/hero', null, [
        'title'       => $config['title'],
        'description' => $config['description'],
        'category'    => $config['category'],
        'back_url'    => $parent_url,
        'back_text'   => 'All Deals',
    ] );
    ?>

    <!-- Deals Content -->
    <section class="deals-content">
        <div class="container">

            <!-- Toolbar (sort, filters) -->
            <div class="deals-toolbar" data-deals-toolbar>
                <div class="deals-toolbar-left">
                    <!-- Period toggle -->
                    <div class="deals-period">
                        <label class="deals-period-label">Compare to:</label>
                        <select class="custom-select-sm" data-custom-select data-deals-period>
                            <option value="3m">3-month avg</option>
                            <option value="6m" selected>6-month avg</option>
                            <option value="12m">12-month avg</option>
                        </select>
                    </div>
                </div>
                <div class="deals-toolbar-right">
                    <!-- Sort -->
                    <div class="deals-sort">
                        <label class="deals-sort-label">Sort:</label>
                        <select class="custom-select-sm" data-custom-select data-deals-sort>
                            <option value="discount" selected>Biggest discount</option>
                            <option value="price-asc">Price: Low to High</option>
                            <option value="price-desc">Price: High to Low</option>
                            <option value="name">Name A-Z</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Deals Grid (uses finder-grid styles) -->
            <div class="finder-grid" data-deals-grid>
                <!-- Skeleton loaders while loading -->
                <?php for ( $i = 0; $i < 8; $i++ ) : ?>
                    <div class="product-card product-card-skeleton">
                        <div class="product-card-image">
                            <div class="skeleton skeleton-img"></div>
                        </div>
                        <div class="product-card-content">
                            <div class="skeleton skeleton-text"></div>
                            <div class="skeleton skeleton-text-sm"></div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- Empty State -->
            <div class="deals-empty" data-deals-empty hidden>
                <p>No deals available for this category right now. Check back soon!</p>
            </div>

        </div>
    </section>

    <?php get_template_part( 'template-parts/components/comparison-bar' ); ?>

</main>

<!-- Deal Card Template (matches finder product-card exactly) -->
<template id="deal-card-template">
    <div class="product-card">
        <label class="product-card-select" onclick="event.stopPropagation()">
            <input type="checkbox" data-compare-select value="">
            <span class="product-card-select-box">
                <?php erh_the_icon( 'check' ); ?>
            </span>
        </label>

        <button class="product-card-track" data-track-price aria-label="<?php esc_attr_e( 'Track price', 'erh' ); ?>">
            <?php erh_the_icon( 'bell' ); ?>
        </button>

        <a href="" class="product-card-link">
            <div class="product-card-image">
                <img src="" alt="" loading="lazy">
                <div class="product-card-price-row">
                    <span class="product-card-price" data-price></span>
                    <span class="product-card-indicator product-card-indicator--below" data-indicator>
                        <?php erh_the_icon( 'arrow-down', 'product-card-indicator-icon' ); ?><span data-indicator-value></span>
                    </span>
                </div>
            </div>
        </a>

        <div class="product-card-content">
            <h3 class="product-card-name">
                <a href="" data-product-link></a>
            </h3>
            <div class="deal-card-averages" data-deal-averages>
                <div class="deal-card-avg" data-avg-3m>
                    <span class="deal-card-avg-label">3-mo avg</span>
                    <span class="deal-card-avg-price" data-avg-3m-price></span>
                </div>
                <div class="deal-card-avg" data-avg-6m>
                    <span class="deal-card-avg-label">6-mo avg</span>
                    <span class="deal-card-avg-price" data-avg-6m-price></span>
                </div>
                <div class="deal-card-avg" data-avg-12m>
                    <span class="deal-card-avg-label">12-mo avg</span>
                    <span class="deal-card-avg-price" data-avg-12m-price></span>
                </div>
            </div>
        </div>
    </div>
</template>

<?php get_footer(); ?>

<script>
// Page-specific config
window.erhData = window.erhData || {};
window.erhData.dealsConfig = {
    category: <?php echo wp_json_encode( $config['category'] ); ?>,
    productType: <?php echo wp_json_encode( $config['type'] ); ?>
};
</script>

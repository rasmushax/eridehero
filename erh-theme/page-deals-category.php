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
        'category'         => 'escooter',
        'type'             => 'Electric Scooter',
        'breadcrumb_title' => 'Electric Scooters',
    ],
    'electric-bikes' => [
        'category'         => 'ebike',
        'type'             => 'Electric Bike',
        'breadcrumb_title' => 'Electric Bikes',
    ],
    'electric-skateboards' => [
        'category'         => 'eskateboard',
        'type'             => 'Electric Skateboard',
        'breadcrumb_title' => 'Electric Skateboards',
    ],
    'electric-unicycles' => [
        'category'         => 'euc',
        'type'             => 'Electric Unicycle',
        'breadcrumb_title' => 'Electric Unicycles',
    ],
    'hoverboards' => [
        'category'         => 'hoverboard',
        'type'             => 'Hoverboard',
        'breadcrumb_title' => 'Hoverboards',
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
    // Hero section - uses WordPress title and content.
    get_template_part( 'template-parts/deals/hero', null, [
        'title'            => get_the_title(),
        'content'          => get_the_content(),
        'category'         => $config['category'],
        'back_url'         => $parent_url,
        'breadcrumb_title' => $config['breadcrumb_title'],
    ] );
    ?>

    <!-- Deals Content -->
    <section class="deals-content">
        <div class="container">

            <!-- Toolbar (sort, filters) -->
            <div class="deals-toolbar" data-deals-toolbar>
                <div class="deals-toolbar-left">
                    <!-- Price filter -->
                    <div class="deals-filter">
                        <span class="deals-filter-label">Price</span>
                        <?php
                        erh_custom_select( [
                            'name'     => 'price_filter',
                            'variant'  => 'inline',
                            'selected' => 'all',
                            'options'  => [
                                'all'       => 'Any price',
                                '0-500'     => 'Under $500',
                                '500-1000'  => '$500 – $1,000',
                                '1000-1500' => '$1,000 – $1,500',
                                '1500-2000' => '$1,500 – $2,000',
                                '2000+'     => '$2,000+',
                            ],
                            'attrs'    => [ 'data-deals-price-filter' => true ],
                        ] );
                        ?>
                    </div>

                    <!-- Period toggle with info popover -->
                    <div class="deals-period">
                        <span class="deals-period-label">Compare to</span>
                        <?php
                        erh_custom_select( [
                            'name'     => 'period',
                            'variant'  => 'inline',
                            'selected' => '6m',
                            'options'  => [
                                '3m'  => '3-month avg',
                                '6m'  => '6-month avg',
                                '12m' => '12-month avg',
                            ],
                            'attrs'    => [ 'data-deals-period' => true ],
                        ] );
                        ?>
                        <div class="popover-wrapper">
                            <button type="button" class="deals-period-info" data-popover-trigger="compare-to-popover" aria-label="What does this mean?">
                                <?php erh_the_icon( 'info' ); ?>
                            </button>
                            <div id="compare-to-popover" class="popover popover--bottom-start" aria-hidden="true">
                                <div class="popover-arrow"></div>
                                <p class="popover-text">We calculate the average price over your selected time period. Products are shown as deals when their current price falls below this average.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="deals-toolbar-right">
                    <!-- Sort -->
                    <div class="deals-sort">
                        <span class="deals-sort-label">Sort by</span>
                        <?php
                        erh_custom_select( [
                            'name'     => 'sort',
                            'variant'  => 'inline',
                            'align'    => 'right',
                            'selected' => 'discount',
                            'options'  => [
                                'discount'   => 'Biggest discount',
                                'price-asc'  => 'Price: Low to High',
                                'price-desc' => 'Price: High to Low',
                                'name'       => 'Name A-Z',
                            ],
                            'attrs'    => [ 'data-deals-sort' => true ],
                        ] );
                        ?>
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
            <h2 class="product-card-name">
                <a href="" data-product-link></a>
            </h2>
            <div class="deal-card-compare" data-deal-compare>
                <span class="deal-card-compare-label" data-compare-label>6-mo avg</span>
                <span class="deal-card-compare-price" data-compare-price></span>
            </div>
        </div>
    </div>
</template>

<!-- Inline CTA Card Template -->
<template id="deals-info-card-template">
    <div class="deals-info-card deals-info-card--cta">
        <p class="deals-info-card-title"><?php erh_the_icon( 'bell' ); ?> Never miss a deal</p>
        <p class="deals-info-card-text">Not ready to buy? Click the <?php erh_the_icon( 'bell' ); ?> icon on any product to track its price.</p>
        <p class="deals-info-card-text">We'll notify you when it drops below your target so you can buy at the perfect time.</p>
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

<?php
/**
 * Template Name: Deals Hub
 *
 * Main deals hub page showing:
 * - Hub-style header with stats
 * - Top deals carousel (best across all categories)
 * - Carousel for each category (if deals available)
 * - Price tracking CTA
 * - FAQ section
 *
 * SSR: Fetches US deals server-side for fast first paint and SEO.
 * JS hydrates with user's actual geo on load.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

// Category configuration.
$categories = [
    'escooter'   => [
        'label' => __( 'E-Scooter Deals', 'erh' ),
        'url'   => home_url( '/deals/electric-scooters/' ),
        'type'  => 'Electric Scooter',
    ],
    'ebike'      => [
        'label' => __( 'E-Bike Deals', 'erh' ),
        'url'   => home_url( '/deals/electric-bikes/' ),
        'type'  => 'Electric Bike',
    ],
    'eskate'     => [
        'label' => __( 'E-Skateboard Deals', 'erh' ),
        'url'   => home_url( '/deals/electric-skateboards/' ),
        'type'  => 'Electric Skateboard',
    ],
    'euc'        => [
        'label' => __( 'EUC Deals', 'erh' ),
        'url'   => home_url( '/deals/electric-unicycles/' ),
        'type'  => 'Electric Unicycle',
    ],
    'hoverboard' => [
        'label' => __( 'Hoverboard Deals', 'erh' ),
        'url'   => home_url( '/deals/hoverboards/' ),
        'type'  => 'Hoverboard',
    ],
];

// Fetch US deals server-side for SSR.
$all_ssr_deals = [];
$ssr_counts    = [];
$ssr_top_deals = [];

if ( class_exists( 'ERH\Pricing\DealsFinder' ) ) {
    $finder        = new \ERH\Pricing\DealsFinder();
    $all_ssr_deals = $finder->get_all_deals(
        \ERH\Pricing\DealsFinder::DEFAULT_THRESHOLD,
        50,
        'US',
        \ERH\Pricing\DealsFinder::DEFAULT_PERIOD
    );

    // Build counts keyed by category slug (escooter, ebike, etc.).
    foreach ( $all_ssr_deals as $type => $deals ) {
        $slug                = \ERH\CategoryConfig::type_to_finder_key( $type );
        $ssr_counts[ $slug ] = count( $deals );
    }

    // Flatten and sort for top deals (most discounted first).
    $all_flat = [];
    foreach ( $all_ssr_deals as $type => $deals ) {
        foreach ( $deals as $deal ) {
            $all_flat[] = $deal;
        }
    }
    usort( $all_flat, function ( $a, $b ) {
        return $a['deal_analysis']['discount_percent'] <=> $b['deal_analysis']['discount_percent'];
    } );
    $ssr_top_deals = array_slice( $all_flat, 0, 8 );
}
$total_deals = array_sum( $ssr_counts );
?>

<main class="deals-hub" data-deals-hub data-ssr-geo="US">

    <!-- Hub Header -->
    <section class="hub-header">
        <div class="container">
            <h1 class="hub-title"><?php the_title(); ?></h1>
            <?php if ( get_the_content() ) : ?>
                <p class="hub-subtitle"><?php echo wp_strip_all_tags( get_the_content() ); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Top Deals Section -->
    <section class="section deals-hub-top scroll-section<?php echo empty( $ssr_top_deals ) ? ' is-loading' : ''; ?>" data-top-deals>
        <div class="container">
            <div class="section-header">
                <h2><?php esc_html_e( "Today's Top Deals", 'erh' ); ?></h2>
            </div>

            <div class="deals-carousel">
                <button class="carousel-arrow carousel-arrow-left" aria-label="<?php esc_attr_e( 'Previous deals', 'erh' ); ?>" disabled>
                    <?php erh_the_icon( 'chevron-left' ); ?>
                </button>
                <button class="carousel-arrow carousel-arrow-right" aria-label="<?php esc_attr_e( 'Next deals', 'erh' ); ?>">
                    <?php erh_the_icon( 'chevron-right' ); ?>
                </button>

                <div class="deals-grid scroll-section" data-top-deals-grid>
                    <?php if ( ! empty( $ssr_top_deals ) ) : ?>
                        <?php foreach ( $ssr_top_deals as $deal ) : ?>
                            <?php get_template_part( 'template-parts/deals/ssr-hub-deal-card', null, [ 'deal' => $deal ] ); ?>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <?php for ( $i = 0; $i < 8; $i++ ) : ?>
                            <div class="deal-card deal-card-skeleton">
                                <div class="deal-card-image">
                                    <div class="skeleton skeleton-img"></div>
                                </div>
                                <div class="deal-card-content">
                                    <div class="skeleton skeleton-text" style="height: 11px;"></div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Info Cards -->
    <section class="section deals-hub-info">
        <div class="container">
            <div class="deals-hub-info-grid">
                <div class="deals-info-card deals-info-card--wide">
                    <h2 class="deals-info-card-title">
                        <?php erh_the_icon( 'globe-search' ); ?>
                        <?php esc_html_e( 'How we find deals', 'erh' ); ?>
                    </h2>
                    <p class="deals-info-card-text"><?php esc_html_e( 'Unlike retailer "sales" based on inflated list prices, we compare current prices against what products actually sell for. Our system tracks 1,000+ products daily across dozens of retailers in the US, UK, EU, Canada, and Australia. When a product drops below its 6-month average, it shows up here.', 'erh' ); ?></p>
                </div>
                <div class="deals-info-card">
                    <h2 class="deals-info-card-title">
                        <?php erh_the_icon( 'bell' ); ?>
                        <?php esc_html_e( 'Track prices', 'erh' ); ?>
                    </h2>
                    <p class="deals-info-card-text"><?php esc_html_e( "Not ready to buy? Tap the bell icon on any product to set a price alert. We'll email you when it drops to your target.", 'erh' ); ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Category Sections -->
    <?php foreach ( $categories as $slug => $cat ) :
        $cat_deals = [];
        if ( isset( $all_ssr_deals[ $cat['type'] ] ) ) {
            $cat_deals = array_slice( $all_ssr_deals[ $cat['type'] ], 0, 15 );
        }
        $cat_count = $ssr_counts[ $slug ] ?? 0;
        $has_deals = ! empty( $cat_deals );
    ?>
        <section class="section deals-hub-category scroll-section<?php echo ! $has_deals ? ' is-loading' : ''; ?>"
                 data-category-section="<?php echo esc_attr( $slug ); ?>"
                 <?php echo ! $has_deals ? 'hidden' : ''; ?>>
            <div class="container">
                <div class="section-header">
                    <h2>
                        <?php echo esc_html( $cat['label'] ); ?>
                        <span class="deals-hub-category-count"
                              data-category-count="<?php echo esc_attr( $slug ); ?>"
                              <?php echo $cat_count > 0 ? '' : 'hidden'; ?>>
                            <?php if ( $cat_count > 0 ) : ?>
                                <?php echo esc_html( $cat_count ); ?>
                            <?php endif; ?>
                        </span>
                    </h2>
                    <a href="<?php echo esc_url( $cat['url'] ); ?>" class="btn btn-secondary">
                        <?php esc_html_e( 'View all', 'erh' ); ?>
                        <?php erh_the_icon( 'arrow-right' ); ?>
                    </a>
                </div>

                <div class="deals-carousel">
                    <button class="carousel-arrow carousel-arrow-left" aria-label="<?php esc_attr_e( 'Previous deals', 'erh' ); ?>" disabled>
                        <?php erh_the_icon( 'chevron-left' ); ?>
                    </button>
                    <button class="carousel-arrow carousel-arrow-right" aria-label="<?php esc_attr_e( 'Next deals', 'erh' ); ?>">
                        <?php erh_the_icon( 'chevron-right' ); ?>
                    </button>

                    <div class="deals-grid scroll-section" data-category-grid="<?php echo esc_attr( $slug ); ?>">
                        <?php if ( $has_deals ) : ?>
                            <?php foreach ( $cat_deals as $deal ) : ?>
                                <?php get_template_part( 'template-parts/deals/ssr-hub-deal-card', null, [ 'deal' => $deal ] ); ?>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <?php for ( $i = 0; $i < 6; $i++ ) : ?>
                                <div class="deal-card deal-card-skeleton">
                                    <div class="deal-card-image">
                                        <div class="skeleton skeleton-img"></div>
                                    </div>
                                    <div class="deal-card-content">
                                        <div class="skeleton skeleton-text" style="height: 11px;"></div>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    <?php endforeach; ?>

    <?php get_template_part( 'template-parts/deals/faq' ); ?>

</main>

<!-- Deal Card Template -->
<template id="hub-deal-card-template">
    <a href="" class="deal-card" data-category="">
        <button type="button" class="deal-card-track" data-track-price aria-label="<?php esc_attr_e( 'Track price', 'erh' ); ?>">
            <?php erh_the_icon( 'bell' ); ?>
        </button>
        <div class="deal-card-image">
            <img src="" alt="" loading="lazy">
            <div class="deal-card-price-row">
                <span class="deal-card-price" data-price></span>
                <span class="deal-card-indicator deal-card-indicator--below" data-indicator>
                    <svg class="icon deal-card-indicator-icon" aria-hidden="true"><use href="#icon-arrow-down"></use></svg>
                    <span data-indicator-value></span>
                </span>
            </div>
        </div>
        <div class="deal-card-content">
            <h3 class="deal-card-title"></h3>
        </div>
    </a>
</template>

<?php get_footer(); ?>

<script data-no-optimize="1">
window.erhData = window.erhData || {};
window.erhData.dealsHubConfig = {
    ssrGeo: 'US',
    ssrCounts: <?php echo wp_json_encode( $ssr_counts ); ?>
};
</script>

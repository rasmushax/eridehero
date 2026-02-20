<?php
/**
 * Template Name: Deals Hub
 *
 * Main deals hub page showing:
 * - Hub-style header with stats
 * - Top deals carousel (best across all categories)
 * - Carousel for each category (if deals available)
 * - Price tracking CTA
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
    ],
    'ebike'      => [
        'label' => __( 'E-Bike Deals', 'erh' ),
        'url'   => home_url( '/deals/electric-bikes/' ),
    ],
    'eskate'     => [
        'label' => __( 'E-Skateboard Deals', 'erh' ),
        'url'   => home_url( '/deals/electric-skateboards/' ),
    ],
    'euc'        => [
        'label' => __( 'EUC Deals', 'erh' ),
        'url'   => home_url( '/deals/electric-unicycles/' ),
    ],
    'hoverboard' => [
        'label' => __( 'Hoverboard Deals', 'erh' ),
        'url'   => home_url( '/deals/hoverboards/' ),
    ],
];
?>

<main class="deals-hub" data-deals-hub>

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
    <section class="section deals-hub-top scroll-section is-loading" data-top-deals>
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
                </div>
            </div>
        </div>
    </section>

    <!-- Info Cards -->
    <section class="section deals-hub-info">
        <div class="container">
            <div class="deals-hub-info-grid">
                <div class="deals-info-card deals-info-card--wide">
                    <p class="deals-info-card-title">
                        <?php erh_the_icon( 'globe-search' ); ?>
                        <?php esc_html_e( 'How we find deals', 'erh' ); ?>
                    </p>
                    <p class="deals-info-card-text"><?php esc_html_e( 'Unlike retailer "sales" based on inflated list prices, we compare current prices against what products actually sell for. Our system tracks 1,000+ products daily across dozens of retailers in the US, UK, EU, Canada, and Australia. When a product drops below its 6-month average, it shows up here.', 'erh' ); ?></p>
                </div>
                <div class="deals-info-card">
                    <p class="deals-info-card-title">
                        <?php erh_the_icon( 'bell' ); ?>
                        <?php esc_html_e( 'Track prices', 'erh' ); ?>
                    </p>
                    <p class="deals-info-card-text"><?php esc_html_e( "Not ready to buy? Tap the bell icon on any product to set a price alert. We'll email you when it drops to your target.", 'erh' ); ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Category Sections -->
    <?php foreach ( $categories as $slug => $cat ) : ?>
        <section class="section deals-hub-category scroll-section is-loading" data-category-section="<?php echo esc_attr( $slug ); ?>">
            <div class="container">
                <div class="section-header">
                    <h2>
                        <?php echo esc_html( $cat['label'] ); ?>
                        <span class="deals-hub-category-count" data-category-count="<?php echo esc_attr( $slug ); ?>" hidden></span>
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
                    </div>
                </div>
            </div>
        </section>
    <?php endforeach; ?>


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
            <p class="deal-card-title"></p>
        </div>
    </a>
</template>

<?php get_footer(); ?>

<?php
/**
 * Homepage Deals Section
 *
 * Displays today's best deals with category filtering.
 * Prices are loaded dynamically via JavaScript for geo-aware display.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Category configuration.
$categories = [
    'all'        => __( 'All', 'erh' ),
    'escooter'   => __( 'E-Scooters', 'erh' ),
    'ebike'      => __( 'E-Bikes', 'erh' ),
    'eskate'     => __( 'E-Skates', 'erh' ),
    'euc'        => __( 'EUCs', 'erh' ),
    'hoverboard' => __( 'Hoverboards', 'erh' ),
];
?>
<section class="deals-section section" id="deals-section">
    <div class="container">
        <!-- Section Header -->
        <div class="section-header">
            <div class="deals-heading">
                <h2 class="section-title"><?php esc_html_e( "Today's Best Deals", 'erh' ); ?></h2>
                <span class="deals-stat" id="deals-count"></span>
            </div>
            <a href="<?php echo esc_url( home_url( '/deals/' ) ); ?>" class="btn btn-secondary">
                <?php esc_html_e( 'View all deals', 'erh' ); ?>
                <?php erh_the_icon( 'arrow-right' ); ?>
            </a>
        </div>

        <!-- Category Tabs -->
        <div class="deals-tabs filter-pills" role="tablist">
            <?php foreach ( $categories as $slug => $label ) : ?>
                <button
                    class="filter-pill <?php echo $slug === 'all' ? 'active' : ''; ?>"
                    role="tab"
                    aria-selected="<?php echo $slug === 'all' ? 'true' : 'false'; ?>"
                    data-filter="<?php echo esc_attr( $slug ); ?>"
                >
                    <?php echo esc_html( $label ); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Deals Carousel -->
        <div class="deals-carousel">
            <!-- Arrow buttons -->
            <button class="carousel-arrow carousel-arrow-left" aria-label="<?php esc_attr_e( 'Previous deals', 'erh' ); ?>" disabled>
                <?php erh_the_icon( 'chevron-left' ); ?>
            </button>
            <button class="carousel-arrow carousel-arrow-right" aria-label="<?php esc_attr_e( 'Next deals', 'erh' ); ?>">
                <?php erh_the_icon( 'chevron-right' ); ?>
            </button>

            <!-- Deals Grid -->
            <div class="deals-grid scroll-section">
                <!-- Loading skeleton cards -->
                <?php for ( $i = 0; $i < 6; $i++ ) : ?>
                    <div class="deal-card deal-card-skeleton">
                        <div class="deal-card-img">
                            <div class="skeleton skeleton-img"></div>
                        </div>
                        <div class="deal-card-content">
                            <div class="skeleton skeleton-text"></div>
                            <div class="skeleton skeleton-text-sm"></div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- Empty state -->
            <div class="deals-empty empty-state" style="display: none;">
                <?php erh_the_icon( 'tag' ); ?>
                <h3><?php esc_html_e( 'No deals found', 'erh' ); ?></h3>
                <p><?php esc_html_e( 'Check back later for new deals in this category.', 'erh' ); ?></p>
            </div>
        </div>
    </div>
</section>

<!-- Deal Card Template (used by JavaScript) -->
<template id="deal-card-template">
    <a href="" class="deal-card" data-category="">
        <button type="button" class="deal-card-track" data-track-price aria-label="<?php esc_attr_e( 'Track price', 'erh' ); ?>">
            <?php erh_the_icon( 'bell' ); ?>
        </button>
        <div class="deal-card-img">
            <img src="" alt="" class="deal-thumbnail" loading="lazy">
            <div class="deal-price" data-geo-price data-product-id="">
                <span class="deal-price-currency">$</span><span class="deal-price-value" data-price>---</span>
            </div>
        </div>
        <div class="deal-card-content">
            <h3 class="deal-card-title"></h3>
            <div class="deal-trend">
                <?php erh_the_icon( 'trending-down' ); ?>
                <span class="deal-discount-text"></span>
            </div>
        </div>
    </a>
</template>

<!-- View All CTA Card Template -->
<template id="deal-cta-template">
    <a href="<?php echo esc_url( home_url( '/deals/' ) ); ?>" class="deal-card deal-card-cta">
        <span class="deal-card-cta-count"></span>
        <span class="deal-card-cta-text"><?php esc_html_e( 'more deals', 'erh' ); ?></span>
        <?php erh_the_icon( 'arrow-right' ); ?>
    </a>
</template>

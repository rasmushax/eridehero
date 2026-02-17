<?php
/**
 * Deals Section
 *
 * Displays today's best deals with category filtering.
 * Prices are loaded dynamically via JavaScript for geo-aware display.
 *
 * Accepts optional args:
 * - category (string): Fixed category filter (e.g., 'escooter'). When set, hides category tabs.
 * - show_tabs (bool): Whether to show category tabs (default: true, false when category is set)
 * - limit (int): Number of deals to load (default: 12)
 * - deals_url (string): Custom "View all deals" URL (defaults to /deals/)
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get args from get_template_part() or use defaults.
$fixed_category = $args['category'] ?? '';
$show_tabs      = $args['show_tabs'] ?? ( empty( $fixed_category ) );
$limit          = $args['limit'] ?? 12;
$deals_url      = ! empty( $args['deals_url'] ) ? $args['deals_url'] : home_url( '/deals/' );

// Category configuration for tabs.
$categories = [
    'all'        => __( 'All', 'erh' ),
    'escooter'   => __( 'E-Scooters', 'erh' ),
    'ebike'      => __( 'E-Bikes', 'erh' ),
    'eskate'     => __( 'E-Skates', 'erh' ),
    'euc'        => __( 'EUCs', 'erh' ),
    'hoverboard' => __( 'Hoverboards', 'erh' ),
];

// Default active category.
$default_category = $fixed_category ? $fixed_category : 'all';
?>
<section
    class="deals-section section"
    id="deals-section"
    data-default-category="<?php echo esc_attr( $default_category ); ?>"
    data-limit="<?php echo esc_attr( $limit ); ?>"
    <?php if ( $fixed_category ) : ?>data-fixed-category="<?php echo esc_attr( $fixed_category ); ?>"<?php endif; ?>
>
    <div class="container">
        <!-- Section Header -->
        <div class="section-header">
            <div class="deals-heading">
                <h2 class="section-title"><?php esc_html_e( "Today's Best Deals", 'erh' ); ?></h2>
                <span class="deals-stat" id="deals-count"></span>
            </div>
            <a href="<?php echo esc_url( $deals_url ); ?>" class="btn btn-secondary">
                <?php esc_html_e( 'View all deals', 'erh' ); ?>
                <?php erh_the_icon( 'arrow-right' ); ?>
            </a>
        </div>

        <?php if ( $show_tabs ) : ?>
        <!-- Category Tabs -->
        <div class="deals-tabs filter-pills" role="tablist">
            <?php foreach ( $categories as $slug => $label ) : ?>
                <button
                    class="filter-pill <?php echo $slug === $default_category ? 'active' : ''; ?>"
                    role="tab"
                    aria-selected="<?php echo $slug === $default_category ? 'true' : 'false'; ?>"
                    data-filter="<?php echo esc_attr( $slug ); ?>"
                >
                    <?php echo esc_html( $label ); ?>
                </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

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
                        <div class="deal-card-image">
                            <div class="skeleton skeleton-img"></div>
                        </div>
                        <div class="deal-card-content">
                            <div class="skeleton skeleton-text"></div>
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

<!-- View All CTA Card Template -->
<template id="deal-cta-template">
    <a href="<?php echo esc_url( $deals_url ); ?>" class="deal-card deal-card-cta">
        <span class="deal-card-cta-count"></span>
        <span class="deal-card-cta-text"><?php esc_html_e( 'more deals', 'erh' ); ?></span>
        <?php erh_the_icon( 'arrow-right' ); ?>
    </a>
</template>

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
    'eskate'     => __( 'E-Skateboards', 'erh' ),
    'euc'        => __( 'EUCs', 'erh' ),
    'hoverboard' => __( 'Hoverboards', 'erh' ),
];
?>
<section class="deals-section py-12 md:py-16 bg-clow" id="deals-section">
    <div class="container mx-auto px-4">
        <!-- Section Header -->
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl md:text-3xl font-bold text-cldark">
                <?php esc_html_e( "Today's Best Deals", 'erh' ); ?>
            </h2>
            <a href="<?php echo esc_url( home_url( '/deals/' ) ); ?>"
               class="hidden sm:flex items-center gap-1.5 text-clmain font-medium hover:underline">
                <?php esc_html_e( 'View all deals', 'erh' ); ?>
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>

        <!-- Category Tabs -->
        <div class="deals-tabs flex gap-2 mb-6 overflow-x-auto pb-2 scrollbar-hide" role="tablist">
            <?php foreach ( $categories as $slug => $label ) : ?>
                <button
                    class="filter-pill whitespace-nowrap px-4 py-2 rounded-full text-sm font-medium transition-colors
                           <?php echo $slug === 'all' ? 'bg-clmain text-white' : 'bg-white text-clbody hover:bg-gray-100'; ?>"
                    role="tab"
                    aria-selected="<?php echo $slug === 'all' ? 'true' : 'false'; ?>"
                    data-filter="<?php echo esc_attr( $slug ); ?>"
                >
                    <?php echo esc_html( $label ); ?>
                    <span class="deals-count ml-1 text-xs opacity-75" data-count="<?php echo esc_attr( $slug ); ?>"></span>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Deals Carousel -->
        <div class="deals-carousel relative">
            <!-- Arrow buttons (desktop) -->
            <button class="carousel-arrow carousel-arrow-left absolute left-0 top-1/2 -translate-y-1/2 -translate-x-4 z-10
                          hidden lg:flex items-center justify-center w-10 h-10 rounded-full bg-white shadow-card
                          hover:shadow-dropdown transition-shadow disabled:opacity-30 disabled:cursor-not-allowed"
                    aria-label="<?php esc_attr_e( 'Previous deals', 'erh' ); ?>"
                    disabled>
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            <button class="carousel-arrow carousel-arrow-right absolute right-0 top-1/2 -translate-y-1/2 translate-x-4 z-10
                          hidden lg:flex items-center justify-center w-10 h-10 rounded-full bg-white shadow-card
                          hover:shadow-dropdown transition-shadow disabled:opacity-30 disabled:cursor-not-allowed"
                    aria-label="<?php esc_attr_e( 'Next deals', 'erh' ); ?>">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            <!-- Deals Grid (scrollable) -->
            <div class="deals-grid flex gap-4 overflow-x-auto pb-4 snap-x snap-mandatory scrollbar-hide lg:overflow-visible lg:grid lg:grid-cols-4">
                <!-- Loading skeleton -->
                <?php for ( $i = 0; $i < 4; $i++ ) : ?>
                    <div class="deal-card-skeleton flex-shrink-0 w-72 lg:w-auto snap-start animate-pulse">
                        <div class="bg-white rounded-xl overflow-hidden shadow-card">
                            <div class="aspect-[4/3] bg-gray-200"></div>
                            <div class="p-4">
                                <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                                <div class="h-6 bg-gray-200 rounded w-1/2 mb-3"></div>
                                <div class="h-8 bg-gray-200 rounded"></div>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- Empty state (hidden by default) -->
            <div class="deals-empty hidden text-center py-12">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                    <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="text-gray-400">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M12 2a10 10 0 110 20 10 10 0 010-20z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-cldark mb-1">
                    <?php esc_html_e( 'No deals found', 'erh' ); ?>
                </h3>
                <p class="text-clbody">
                    <?php esc_html_e( 'Check back later for new deals in this category.', 'erh' ); ?>
                </p>
            </div>
        </div>

        <!-- Mobile "View all" link -->
        <div class="mt-6 text-center sm:hidden">
            <a href="<?php echo esc_url( home_url( '/deals/' ) ); ?>"
               class="inline-flex items-center gap-1.5 text-clmain font-medium">
                <?php esc_html_e( 'View all deals', 'erh' ); ?>
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    </div>
</section>

<!-- Deal Card Template (used by JavaScript) -->
<template id="deal-card-template">
    <article class="deal-card flex-shrink-0 w-72 lg:w-auto snap-start" data-category="">
        <a href="" class="block bg-white rounded-xl overflow-hidden shadow-card hover:shadow-dropdown transition-shadow group">
            <!-- Image with discount badge -->
            <div class="relative aspect-[4/3] bg-gray-100 overflow-hidden">
                <img src="" alt="" class="deal-thumbnail w-full h-full object-contain p-4 group-hover:scale-105 transition-transform duration-300" loading="lazy">
                <span class="deal-badge absolute top-3 left-3 px-2.5 py-1 bg-clgreen text-white text-xs font-bold rounded-full">
                    -<span class="deal-discount">0</span>%
                </span>
            </div>

            <!-- Content -->
            <div class="p-4">
                <!-- Category label -->
                <span class="deal-category text-xs font-medium text-clmain uppercase tracking-wide"></span>

                <!-- Product name -->
                <h3 class="deal-name text-base font-semibold text-cldark mt-1 mb-2 line-clamp-2 min-h-[2.5rem]"></h3>

                <!-- Price section -->
                <div class="flex items-baseline gap-2 mb-3" data-geo-price data-product-id="">
                    <span class="deal-price text-xl font-bold text-cldark" data-price>
                        <span class="inline-block w-16 h-6 bg-gray-200 rounded animate-pulse"></span>
                    </span>
                    <span class="deal-avg-price text-sm text-gray-400 line-through" data-avg-price></span>
                </div>

                <!-- CTA button -->
                <div class="flex items-center justify-between">
                    <span class="text-sm text-clbody">
                        <?php esc_html_e( 'View deal', 'erh' ); ?>
                    </span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="text-clmain group-hover:translate-x-1 transition-transform">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
            </div>
        </a>
    </article>
</template>

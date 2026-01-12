<?php
/**
 * Header Search Component
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<!-- Search -->
<div class="search-wrapper">
    <!-- Desktop inline search -->
    <div class="search-inline">
        <?php erh_the_icon( 'search' ); ?>
        <input type="text" class="search-inline-input" placeholder="<?php esc_attr_e( 'Search products, reviews...', 'erh' ); ?>" aria-label="<?php esc_attr_e( 'Search', 'erh' ); ?>">
    </div>

    <!-- Search toggle for smaller screens -->
    <button class="search-toggle" aria-label="<?php esc_attr_e( 'Open search', 'erh' ); ?>" aria-expanded="false" aria-controls="search-dropdown">
        <?php erh_the_icon( 'search' ); ?>
    </button>

    <!-- Search dropdown for expanded search -->
    <div class="search-dropdown" id="search-dropdown" role="dialog" aria-label="<?php esc_attr_e( 'Search', 'erh' ); ?>">
        <div class="search-input-wrapper">
            <?php erh_the_icon( 'search' ); ?>
            <input type="text" class="search-input" placeholder="<?php esc_attr_e( 'Search products, reviews, guides...', 'erh' ); ?>" aria-label="<?php esc_attr_e( 'Search', 'erh' ); ?>">
            <button type="button" class="search-close" aria-label="<?php esc_attr_e( 'Close search', 'erh' ); ?>">
                <?php erh_the_icon( 'x' ); ?>
            </button>
        </div>
        <div class="search-suggestions">
            <div class="search-suggestions-label"><?php esc_html_e( 'Popular searches', 'erh' ); ?></div>
            <a href="<?php echo esc_url( home_url( '/buying-guides/best-commuter-scooters/' ) ); ?>" class="search-suggestion">
                <?php erh_the_icon( 'trending-up' ); ?>
                <span><?php esc_html_e( 'Best commuter scooters 2025', 'erh' ); ?></span>
            </a>
            <a href="<?php echo esc_url( home_url( '/compare/segway-max-g2-vs-navee-st3-pro/' ) ); ?>" class="search-suggestion">
                <?php erh_the_icon( 'grid' ); ?>
                <span><?php esc_html_e( 'Segway Max G2 vs Navee ST3 Pro', 'erh' ); ?></span>
            </a>
            <a href="<?php echo esc_url( home_url( '/reviews/niu-kqi-300x/' ) ); ?>" class="search-suggestion">
                <?php erh_the_icon( 'star' ); ?>
                <span><?php esc_html_e( 'NIU KQi 300X review', 'erh' ); ?></span>
            </a>
        </div>
    </div>
</div>

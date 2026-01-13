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
        <input type="text" class="search-inline-input" placeholder="<?php esc_attr_e( 'Search products, reviews...', 'erh' ); ?>" aria-label="<?php esc_attr_e( 'Search', 'erh' ); ?>" autocomplete="off">
    </div>

    <!-- Search toggle for smaller screens -->
    <button class="search-toggle" aria-label="<?php esc_attr_e( 'Open search', 'erh' ); ?>" aria-expanded="false" aria-controls="search-dropdown">
        <?php erh_the_icon( 'search' ); ?>
    </button>

    <!-- Search dropdown for expanded search -->
    <div class="search-dropdown" id="search-dropdown" role="dialog" aria-label="<?php esc_attr_e( 'Search', 'erh' ); ?>">
        <div class="search-input-wrapper">
            <?php erh_the_icon( 'search' ); ?>
            <input type="text" class="search-input" placeholder="<?php esc_attr_e( 'Search products, reviews, guides...', 'erh' ); ?>" aria-label="<?php esc_attr_e( 'Search', 'erh' ); ?>" autocomplete="off">
            <button type="button" class="search-close" aria-label="<?php esc_attr_e( 'Close search', 'erh' ); ?>">
                <?php erh_the_icon( 'x' ); ?>
            </button>
        </div>
        <!-- Search results container (populated by JS) -->
        <div class="search-results-container" aria-live="polite"></div>
    </div>
</div>

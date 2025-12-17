<?php
/**
 * E-bikes Mega Menu Navigation
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_url = home_url( '/e-bikes' );
?>
<!-- E-bikes (Mega Menu) -->
<div class="nav-item" data-dropdown>
    <button type="button" class="nav-link" aria-expanded="false" aria-haspopup="true" aria-controls="dropdown-ebikes">
        <?php esc_html_e( 'E-bikes', 'erh' ); ?>
        <?php erh_the_icon( 'chevron-down', 'chevron' ); ?>
    </button>
    <div class="dropdown mega-dropdown" id="dropdown-ebikes" role="menu" aria-label="<?php esc_attr_e( 'E-bikes submenu', 'erh' ); ?>">
        <div class="mega-dropdown-header">
            <h3><?php esc_html_e( 'Electric bikes', 'erh' ); ?></h3>
            <a href="<?php echo esc_url( $base_url ); ?>" role="menuitem">
                <?php esc_html_e( 'View all', 'erh' ); ?>
                <?php erh_the_icon( 'arrow-right' ); ?>
            </a>
        </div>
        <div class="mega-grid" role="group" aria-label="<?php esc_attr_e( 'E-bike pages', 'erh' ); ?>">
            <a href="<?php echo esc_url( $base_url . '/finder/' ); ?>" class="mega-item" role="menuitem">
                <div class="mega-icon" aria-hidden="true">
                    <?php erh_the_icon( 'search' ); ?>
                </div>
                <div class="mega-content">
                    <h4><?php esc_html_e( 'Product finder', 'erh' ); ?></h4>
                    <span><?php esc_html_e( '80+ e-bikes', 'erh' ); ?></span>
                </div>
            </a>
            <a href="<?php echo esc_url( home_url( '/e-bike-reviews/' ) ); ?>" class="mega-item" role="menuitem">
                <div class="mega-icon" aria-hidden="true">
                    <?php erh_the_icon( 'star' ); ?>
                </div>
                <div class="mega-content">
                    <h4><?php esc_html_e( 'Reviews', 'erh' ); ?></h4>
                    <span><?php esc_html_e( '12 in-depth reviews', 'erh' ); ?></span>
                </div>
            </a>
            <a href="<?php echo esc_url( $base_url . '/compare/' ); ?>" class="mega-item" role="menuitem">
                <div class="mega-icon" aria-hidden="true">
                    <?php erh_the_icon( 'grid' ); ?>
                </div>
                <div class="mega-content">
                    <h4><?php esc_html_e( 'H2H Compare', 'erh' ); ?></h4>
                    <span><?php esc_html_e( 'Side-by-side specs', 'erh' ); ?></span>
                </div>
            </a>
            <a href="<?php echo esc_url( home_url( '/buying-guides/e-bikes/' ) ); ?>" class="mega-item" role="menuitem">
                <div class="mega-icon" aria-hidden="true">
                    <?php erh_the_icon( 'book' ); ?>
                </div>
                <div class="mega-content">
                    <h4><?php esc_html_e( 'Buying guides', 'erh' ); ?></h4>
                    <span><?php esc_html_e( 'Expert recommendations', 'erh' ); ?></span>
                </div>
            </a>
        </div>
        <div class="mega-footer" role="group" aria-label="<?php esc_attr_e( 'Quick links', 'erh' ); ?>">
            <a href="<?php echo esc_url( home_url( '/deals/e-bikes/' ) ); ?>" class="mega-footer-link" role="menuitem">
                <?php erh_the_icon( 'percent' ); ?>
                <?php esc_html_e( 'Best deals', 'erh' ); ?>
            </a>
            <a href="<?php echo esc_url( home_url( '/coupons/e-bikes/' ) ); ?>" class="mega-footer-link" role="menuitem">
                <?php erh_the_icon( 'tag' ); ?>
                <?php esc_html_e( 'Coupon codes', 'erh' ); ?>
            </a>
        </div>
    </div>
</div>

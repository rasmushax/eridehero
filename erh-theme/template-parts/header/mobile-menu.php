<?php
/**
 * Mobile Menu Overlay
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<!-- Mobile Menu Overlay -->
<div class="mobile-menu" id="mobile-menu" role="dialog" aria-label="<?php esc_attr_e( 'Navigation menu', 'erh' ); ?>" aria-modal="true">
    <div class="mobile-menu-inner">
        <nav class="mobile-nav" role="navigation" aria-label="<?php esc_attr_e( 'Mobile navigation', 'erh' ); ?>">

            <!-- E-scooters -->
            <div class="mobile-nav-item" data-has-submenu>
                <button class="mobile-nav-link" aria-expanded="false">
                    <?php esc_html_e( 'E-scooters', 'erh' ); ?>
                    <?php erh_the_icon( 'chevron-down' ); ?>
                </button>
                <div class="mobile-submenu">
                    <div class="mobile-submenu-inner">
                        <a href="<?php echo esc_url( home_url( '/e-scooters/' ) ); ?>" class="mobile-submenu-viewall">
                            <?php esc_html_e( 'View all e-scooters', 'erh' ); ?>
                            <?php erh_the_icon( 'arrow-right' ); ?>
                        </a>
                        <a href="<?php echo esc_url( home_url( '/e-scooters/finder/' ) ); ?>" class="mobile-submenu-link">
                            <?php erh_the_icon( 'search' ); ?>
                            <?php esc_html_e( 'Product finder', 'erh' ); ?>
                        </a>
                        <a href="<?php echo esc_url( home_url( '/e-scooter-reviews/' ) ); ?>" class="mobile-submenu-link">
                            <?php erh_the_icon( 'star' ); ?>
                            <?php esc_html_e( 'Reviews', 'erh' ); ?>
                        </a>
                        <a href="<?php echo esc_url( home_url( '/e-scooters/compare/' ) ); ?>" class="mobile-submenu-link">
                            <?php erh_the_icon( 'grid' ); ?>
                            <?php esc_html_e( 'H2H Compare', 'erh' ); ?>
                        </a>
                        <a href="<?php echo esc_url( home_url( '/buying-guides/e-scooters/' ) ); ?>" class="mobile-submenu-link">
                            <?php erh_the_icon( 'book' ); ?>
                            <?php esc_html_e( 'Buying guides', 'erh' ); ?>
                        </a>
                        <a href="<?php echo esc_url( home_url( '/deals/e-scooters/' ) ); ?>" class="mobile-submenu-link">
                            <?php erh_the_icon( 'percent' ); ?>
                            <?php esc_html_e( 'Best deals', 'erh' ); ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- E-bikes -->
            <div class="mobile-nav-item" data-has-submenu>
                <button class="mobile-nav-link" aria-expanded="false">
                    <?php esc_html_e( 'E-bikes', 'erh' ); ?>
                    <?php erh_the_icon( 'chevron-down' ); ?>
                </button>
                <div class="mobile-submenu">
                    <div class="mobile-submenu-inner">
                        <a href="<?php echo esc_url( home_url( '/e-bikes/' ) ); ?>" class="mobile-submenu-viewall">
                            <?php esc_html_e( 'View all e-bikes', 'erh' ); ?>
                            <?php erh_the_icon( 'arrow-right' ); ?>
                        </a>
                        <a href="<?php echo esc_url( home_url( '/e-bikes/finder/' ) ); ?>" class="mobile-submenu-link">
                            <?php erh_the_icon( 'search' ); ?>
                            <?php esc_html_e( 'Product finder', 'erh' ); ?>
                        </a>
                        <a href="<?php echo esc_url( home_url( '/e-bike-reviews/' ) ); ?>" class="mobile-submenu-link">
                            <?php erh_the_icon( 'star' ); ?>
                            <?php esc_html_e( 'Reviews', 'erh' ); ?>
                        </a>
                        <a href="<?php echo esc_url( home_url( '/e-bikes/compare/' ) ); ?>" class="mobile-submenu-link">
                            <?php erh_the_icon( 'grid' ); ?>
                            <?php esc_html_e( 'H2H Compare', 'erh' ); ?>
                        </a>
                        <a href="<?php echo esc_url( home_url( '/buying-guides/e-bikes/' ) ); ?>" class="mobile-submenu-link">
                            <?php erh_the_icon( 'book' ); ?>
                            <?php esc_html_e( 'Buying guides', 'erh' ); ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- EUCs -->
            <div class="mobile-nav-item">
                <a href="<?php echo esc_url( home_url( '/eucs/' ) ); ?>" class="mobile-nav-link">
                    <?php esc_html_e( 'EUCs', 'erh' ); ?>
                </a>
            </div>

            <!-- Hoverboards -->
            <div class="mobile-nav-item">
                <a href="<?php echo esc_url( home_url( '/hoverboards/' ) ); ?>" class="mobile-nav-link">
                    <?php esc_html_e( 'Hoverboards', 'erh' ); ?>
                </a>
            </div>

            <!-- E-Skateboards -->
            <div class="mobile-nav-item">
                <a href="<?php echo esc_url( home_url( '/e-skateboards/' ) ); ?>" class="mobile-nav-link">
                    <?php esc_html_e( 'E-Skateboards', 'erh' ); ?>
                </a>
            </div>

            <!-- Buying Guides -->
            <div class="mobile-nav-item">
                <a href="<?php echo esc_url( home_url( '/buying-guides/' ) ); ?>" class="mobile-nav-link">
                    <?php esc_html_e( 'Buying guides', 'erh' ); ?>
                </a>
            </div>

        </nav>

        <!-- Mobile menu footer -->
        <div class="mobile-menu-footer">
            <?php if ( is_user_logged_in() ) : ?>
                <a href="<?php echo esc_url( home_url( '/account/' ) ); ?>" class="btn btn-primary btn-block">
                    <?php esc_html_e( 'My account', 'erh' ); ?>
                </a>
            <?php else : ?>
                <a href="<?php echo esc_url( home_url( '/register/' ) ); ?>" class="btn btn-primary btn-block">
                    <?php esc_html_e( 'Sign up free', 'erh' ); ?>
                </a>
                <a href="<?php echo esc_url( home_url( '/login/' ) ); ?>" class="btn btn-secondary btn-block">
                    <?php esc_html_e( 'Log in', 'erh' ); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

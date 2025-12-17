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
                        <a href="<?php echo esc_url( home_url( '/e-scooters/finder/' ) ); ?>" class="mobile-submenu-item">
                            <div class="mobile-submenu-icon">
                                <?php erh_the_icon( 'search' ); ?>
                            </div>
                            <div class="mobile-submenu-content">
                                <h4><?php esc_html_e( 'Product finder', 'erh' ); ?></h4>
                                <span><?php esc_html_e( '200+ scooters', 'erh' ); ?></span>
                            </div>
                        </a>
                        <a href="<?php echo esc_url( home_url( '/e-scooter-reviews/' ) ); ?>" class="mobile-submenu-item">
                            <div class="mobile-submenu-icon">
                                <?php erh_the_icon( 'star' ); ?>
                            </div>
                            <div class="mobile-submenu-content">
                                <h4><?php esc_html_e( 'Reviews', 'erh' ); ?></h4>
                                <span><?php esc_html_e( '58 in-depth reviews', 'erh' ); ?></span>
                            </div>
                        </a>
                        <a href="<?php echo esc_url( home_url( '/e-scooters/compare/' ) ); ?>" class="mobile-submenu-item">
                            <div class="mobile-submenu-icon">
                                <?php erh_the_icon( 'grid' ); ?>
                            </div>
                            <div class="mobile-submenu-content">
                                <h4><?php esc_html_e( 'H2H Compare', 'erh' ); ?></h4>
                                <span><?php esc_html_e( 'Side-by-side specs', 'erh' ); ?></span>
                            </div>
                        </a>
                        <a href="<?php echo esc_url( home_url( '/buying-guides/e-scooters/' ) ); ?>" class="mobile-submenu-item">
                            <div class="mobile-submenu-icon">
                                <?php erh_the_icon( 'book' ); ?>
                            </div>
                            <div class="mobile-submenu-content">
                                <h4><?php esc_html_e( 'Buying guides', 'erh' ); ?></h4>
                                <span><?php esc_html_e( 'Expert recommendations', 'erh' ); ?></span>
                            </div>
                        </a>
                        <div class="mobile-submenu-secondary">
                            <a href="<?php echo esc_url( home_url( '/deals/e-scooters/' ) ); ?>" class="mobile-submenu-tag">
                                <?php erh_the_icon( 'percent' ); ?>
                                <?php esc_html_e( 'Deals', 'erh' ); ?>
                            </a>
                            <a href="<?php echo esc_url( home_url( '/coupons/e-scooters/' ) ); ?>" class="mobile-submenu-tag">
                                <?php erh_the_icon( 'tag' ); ?>
                                <?php esc_html_e( 'Coupons', 'erh' ); ?>
                            </a>
                        </div>
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
                        <a href="<?php echo esc_url( home_url( '/e-bikes/finder/' ) ); ?>" class="mobile-submenu-item">
                            <div class="mobile-submenu-icon">
                                <?php erh_the_icon( 'search' ); ?>
                            </div>
                            <div class="mobile-submenu-content">
                                <h4><?php esc_html_e( 'Product finder', 'erh' ); ?></h4>
                                <span><?php esc_html_e( '80+ e-bikes', 'erh' ); ?></span>
                            </div>
                        </a>
                        <a href="<?php echo esc_url( home_url( '/e-bike-reviews/' ) ); ?>" class="mobile-submenu-item">
                            <div class="mobile-submenu-icon">
                                <?php erh_the_icon( 'star' ); ?>
                            </div>
                            <div class="mobile-submenu-content">
                                <h4><?php esc_html_e( 'Reviews', 'erh' ); ?></h4>
                                <span><?php esc_html_e( '12 in-depth reviews', 'erh' ); ?></span>
                            </div>
                        </a>
                        <a href="<?php echo esc_url( home_url( '/e-bikes/compare/' ) ); ?>" class="mobile-submenu-item">
                            <div class="mobile-submenu-icon">
                                <?php erh_the_icon( 'grid' ); ?>
                            </div>
                            <div class="mobile-submenu-content">
                                <h4><?php esc_html_e( 'H2H Compare', 'erh' ); ?></h4>
                                <span><?php esc_html_e( 'Side-by-side specs', 'erh' ); ?></span>
                            </div>
                        </a>
                        <a href="<?php echo esc_url( home_url( '/buying-guides/e-bikes/' ) ); ?>" class="mobile-submenu-item">
                            <div class="mobile-submenu-icon">
                                <?php erh_the_icon( 'book' ); ?>
                            </div>
                            <div class="mobile-submenu-content">
                                <h4><?php esc_html_e( 'Buying guides', 'erh' ); ?></h4>
                                <span><?php esc_html_e( 'Expert recommendations', 'erh' ); ?></span>
                            </div>
                        </a>
                        <div class="mobile-submenu-secondary">
                            <a href="<?php echo esc_url( home_url( '/deals/e-bikes/' ) ); ?>" class="mobile-submenu-tag">
                                <?php erh_the_icon( 'percent' ); ?>
                                <?php esc_html_e( 'Deals', 'erh' ); ?>
                            </a>
                            <a href="<?php echo esc_url( home_url( '/coupons/e-bikes/' ) ); ?>" class="mobile-submenu-tag">
                                <?php erh_the_icon( 'tag' ); ?>
                                <?php esc_html_e( 'Coupons', 'erh' ); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- EUCs -->
            <div class="mobile-nav-item">
                <a href="<?php echo esc_url( home_url( '/eucs/' ) ); ?>" class="mobile-nav-link">
                    <?php esc_html_e( 'EUCs', 'erh' ); ?>
                </a>
            </div>

            <!-- More -->
            <div class="mobile-nav-item" data-has-submenu>
                <button class="mobile-nav-link" aria-expanded="false">
                    <?php esc_html_e( 'More', 'erh' ); ?>
                    <?php erh_the_icon( 'chevron-down' ); ?>
                </button>
                <div class="mobile-submenu">
                    <div class="mobile-submenu-inner">
                        <a href="<?php echo esc_url( home_url( '/hoverboards/' ) ); ?>" class="mobile-submenu-item">
                            <div class="mobile-submenu-icon">
                                <?php erh_the_icon( 'hoverboard' ); ?>
                            </div>
                            <div class="mobile-submenu-content">
                                <h4><?php esc_html_e( 'Hoverboards', 'erh' ); ?></h4>
                                <span><?php esc_html_e( '10 reviews', 'erh' ); ?></span>
                            </div>
                        </a>
                        <a href="<?php echo esc_url( home_url( '/e-skateboards/' ) ); ?>" class="mobile-submenu-item">
                            <div class="mobile-submenu-icon">
                                <?php erh_the_icon( 'eskate' ); ?>
                            </div>
                            <div class="mobile-submenu-content">
                                <h4><?php esc_html_e( 'E-Skateboards', 'erh' ); ?></h4>
                                <span><?php esc_html_e( '8 reviews', 'erh' ); ?></span>
                            </div>
                        </a>
                        <a href="<?php echo esc_url( home_url( '/skating/' ) ); ?>" class="mobile-submenu-item">
                            <div class="mobile-submenu-icon">
                                <?php erh_the_icon( 'book' ); ?>
                            </div>
                            <div class="mobile-submenu-content">
                                <h4><?php esc_html_e( 'Skating', 'erh' ); ?></h4>
                                <span><?php esc_html_e( '6 guides', 'erh' ); ?></span>
                            </div>
                        </a>
                        <div class="mobile-submenu-divider"></div>
                        <a href="<?php echo esc_url( home_url( '/buying-guides/' ) ); ?>" class="mobile-submenu-item">
                            <div class="mobile-submenu-icon">
                                <?php erh_the_icon( 'book' ); ?>
                            </div>
                            <div class="mobile-submenu-content">
                                <h4><?php esc_html_e( 'All buying guides', 'erh' ); ?></h4>
                                <span><?php esc_html_e( '14 guides', 'erh' ); ?></span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

        </nav>

        <!-- Mobile menu footer with login -->
        <div class="mobile-menu-footer">
            <?php if ( is_user_logged_in() ) : ?>
                <a href="<?php echo esc_url( home_url( '/account/' ) ); ?>" class="mobile-login-btn">
                    <?php erh_the_icon( 'user' ); ?>
                    <?php esc_html_e( 'My account', 'erh' ); ?>
                </a>
            <?php else : ?>
                <a href="<?php echo esc_url( home_url( '/register/' ) ); ?>" class="mobile-signup-btn">
                    <?php esc_html_e( 'Sign up free', 'erh' ); ?>
                    <?php erh_the_icon( 'arrow-right' ); ?>
                </a>
                <a href="<?php echo esc_url( home_url( '/login/' ) ); ?>" class="mobile-login-btn">
                    <?php erh_the_icon( 'user' ); ?>
                    <?php esc_html_e( 'Log in to your account', 'erh' ); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

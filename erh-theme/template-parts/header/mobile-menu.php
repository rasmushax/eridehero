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
            <?php foreach ( erh_get_header_nav() as $item ) : ?>
                <?php erh_render_mobile_nav_item( $item ); ?>
            <?php endforeach; ?>
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

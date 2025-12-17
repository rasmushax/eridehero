<?php
/**
 * Theme Footer
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
</main><!-- #main-content -->

<footer class="footer" role="contentinfo">
    <div class="container">
        <div class="footer-top">
            <nav class="footer-categories" aria-label="<?php esc_attr_e( 'Product categories', 'erh' ); ?>">
                <a href="<?php echo esc_url( home_url( '/e-scooters/' ) ); ?>"><?php esc_html_e( 'E-scooters', 'erh' ); ?></a>
                <a href="<?php echo esc_url( home_url( '/e-bikes/' ) ); ?>"><?php esc_html_e( 'E-bikes', 'erh' ); ?></a>
                <a href="<?php echo esc_url( home_url( '/eucs/' ) ); ?>"><?php esc_html_e( 'EUCs', 'erh' ); ?></a>
                <a href="<?php echo esc_url( home_url( '/e-skating/' ) ); ?>"><?php esc_html_e( 'E-skating', 'erh' ); ?></a>
                <a href="<?php echo esc_url( home_url( '/hoverboards/' ) ); ?>"><?php esc_html_e( 'Hoverboards', 'erh' ); ?></a>
            </nav>
            <div class="footer-socials">
                <?php
                $socials = array(
                    'youtube'   => 'https://youtube.com/@eridehero',
                    'instagram' => 'https://instagram.com/eridehero',
                    'facebook'  => 'https://facebook.com/eridehero',
                    'twitter'   => 'https://twitter.com/eridehero',
                    'linkedin'  => 'https://linkedin.com/company/eridehero',
                );

                foreach ( $socials as $platform => $url ) :
                    ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="footer-social" aria-label="<?php echo esc_attr( ucfirst( $platform ) ); ?>" target="_blank" rel="noopener noreferrer">
                        <?php erh_the_icon( $platform ); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-left">
                <span class="footer-copyright">&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></span>
                <span class="footer-separator">&middot;</span>
                <nav class="footer-legal" aria-label="<?php esc_attr_e( 'Legal links', 'erh' ); ?>">
                    <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>"><?php esc_html_e( 'Privacy', 'erh' ); ?></a>
                    <a href="<?php echo esc_url( home_url( '/cookies/' ) ); ?>"><?php esc_html_e( 'Cookies', 'erh' ); ?></a>
                    <a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>"><?php esc_html_e( 'Terms', 'erh' ); ?></a>
                    <a href="<?php echo esc_url( home_url( '/disclaimers/' ) ); ?>"><?php esc_html_e( 'Disclaimers', 'erh' ); ?></a>
                </nav>
            </div>
            <nav class="footer-links" aria-label="<?php esc_attr_e( 'Footer navigation', 'erh' ); ?>">
                <a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( 'About', 'erh' ); ?></a>
                <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact', 'erh' ); ?></a>
                <a href="<?php echo esc_url( home_url( '/how-we-test/' ) ); ?>"><?php esc_html_e( 'How we test', 'erh' ); ?></a>
                <a href="<?php echo esc_url( home_url( '/editorial/' ) ); ?>"><?php esc_html_e( 'Editorial', 'erh' ); ?></a>
            </nav>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>

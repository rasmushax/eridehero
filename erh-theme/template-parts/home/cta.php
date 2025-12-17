<?php
/**
 * Homepage CTA Section
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<section class="cta-section">
    <div class="container">
        <div class="cta-card">
            <div class="cta-content">
                <h2 class="cta-title"><?php esc_html_e( 'Never miss a deal', 'erh' ); ?></h2>
                <p class="cta-description"><?php esc_html_e( 'Get price drop alerts and exclusive deals delivered to your inbox.', 'erh' ); ?></p>
            </div>
            <div class="cta-action">
                <a href="<?php echo esc_url( home_url( '/register/' ) ); ?>" class="btn btn-primary btn-lg">
                    <?php esc_html_e( 'Sign up free', 'erh' ); ?>
                    <?php erh_the_icon( 'arrow-right' ); ?>
                </a>
                <span class="cta-note"><?php esc_html_e( 'No spam, unsubscribe anytime.', 'erh' ); ?></span>
            </div>
        </div>
    </div>
</section>

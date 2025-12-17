<?php
/**
 * Sidebar - About ERideHero Card
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="sidebar-card">
    <h3 class="sidebar-card-title"><?php esc_html_e( 'About ERideHero', 'erh' ); ?></h3>
    <p class="sidebar-card-text">
        <?php esc_html_e( 'ERideHero is an independent review platform dedicated to helping you find the perfect electric ride. Since 2019, we\'ve tested hundreds of products.', 'erh' ); ?>
    </p>
    <a href="<?php echo esc_url( home_url( '/about/' ) ); ?>" class="btn btn-link">
        <?php esc_html_e( 'Learn more about us', 'erh' ); ?>
        <?php erh_the_icon( 'arrow-right' ); ?>
    </a>
</div>

<?php
/**
 * Sidebar - How We Test Card
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="sidebar-card">
    <h3 class="sidebar-card-title"><?php esc_html_e( 'How we test', 'erh' ); ?></h3>
    <p class="sidebar-card-text">
        <?php esc_html_e( 'We ride every product we review, conducting real-world tests on range, speed, and handling to give you the most accurate information.', 'erh' ); ?>
    </p>
    <a href="<?php echo esc_url( home_url( '/how-we-test/' ) ); ?>" class="btn btn-link">
        <?php esc_html_e( 'Learn about our testing', 'erh' ); ?>
        <?php erh_the_icon( 'arrow-right' ); ?>
    </a>
</div>

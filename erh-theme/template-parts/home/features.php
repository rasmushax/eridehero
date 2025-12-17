<?php
/**
 * Homepage Features Section
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<section class="features">
    <div class="container">
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <?php erh_the_icon( 'trending-down' ); ?>
                </div>
                <h3 class="feature-title"><?php esc_html_e( 'Price history', 'erh' ); ?></h3>
                <p class="feature-description"><?php esc_html_e( 'Track price changes over time and find the best time to buy.', 'erh' ); ?></p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <?php erh_the_icon( 'bell' ); ?>
                </div>
                <h3 class="feature-title"><?php esc_html_e( 'Price alerts', 'erh' ); ?></h3>
                <p class="feature-description"><?php esc_html_e( 'Get notified when prices drop on products you\'re watching.', 'erh' ); ?></p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <?php erh_the_icon( 'database' ); ?>
                </div>
                <h3 class="feature-title"><?php esc_html_e( 'Product database', 'erh' ); ?></h3>
                <p class="feature-description"><?php esc_html_e( 'Compare specs across 300+ electric vehicles in our database.', 'erh' ); ?></p>
            </div>
        </div>
    </div>
</section>

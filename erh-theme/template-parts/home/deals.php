<?php
/**
 * Homepage Deals Section
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// This will be populated dynamically from erh-core
// For now, show placeholder structure
?>
<section class="deals-section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title"><?php esc_html_e( 'Today\'s best deals', 'erh' ); ?></h2>
            <a href="<?php echo esc_url( home_url( '/deals/' ) ); ?>" class="section-link">
                <?php esc_html_e( 'View all deals', 'erh' ); ?>
                <?php erh_the_icon( 'arrow-right' ); ?>
            </a>
        </div>

        <div class="deals-tabs" role="tablist">
            <button class="tab is-active" role="tab" aria-selected="true" data-filter="all">
                <?php esc_html_e( 'All', 'erh' ); ?>
            </button>
            <button class="tab" role="tab" aria-selected="false" data-filter="e-scooters">
                <?php esc_html_e( 'E-scooters', 'erh' ); ?>
            </button>
            <button class="tab" role="tab" aria-selected="false" data-filter="e-bikes">
                <?php esc_html_e( 'E-bikes', 'erh' ); ?>
            </button>
        </div>

        <div class="deals-grid scroll-section">
            <!-- Deals will be populated dynamically -->
            <p class="empty-state"><?php esc_html_e( 'Deals will be loaded here.', 'erh' ); ?></p>
        </div>
    </div>
</section>

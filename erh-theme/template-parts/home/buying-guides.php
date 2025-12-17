<?php
/**
 * Homepage Buying Guides Section
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Query buying guides (posts with 'buying-guide' category or custom taxonomy)
// This is a placeholder - adjust query based on actual content structure
?>
<section class="buying-guides-section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title"><?php esc_html_e( 'Buying guides', 'erh' ); ?></h2>
            <a href="<?php echo esc_url( home_url( '/buying-guides/' ) ); ?>" class="section-link">
                <?php esc_html_e( 'View all guides', 'erh' ); ?>
                <?php erh_the_icon( 'arrow-right' ); ?>
            </a>
        </div>

        <div class="guides-grid grid-4">
            <!-- Guides will be populated dynamically -->
            <p class="empty-state"><?php esc_html_e( 'Buying guides will be loaded here.', 'erh' ); ?></p>
        </div>
    </div>
</section>

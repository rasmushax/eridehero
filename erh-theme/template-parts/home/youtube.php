<?php
/**
 * Homepage YouTube Section
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<section class="youtube-section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">
                <?php erh_the_icon( 'youtube', 'youtube-icon' ); ?>
                <?php esc_html_e( 'On our YouTube', 'erh' ); ?>
            </h2>
            <a href="https://youtube.com/@eridehero" class="btn btn-youtube" target="_blank" rel="noopener noreferrer">
                <?php esc_html_e( 'Subscribe', 'erh' ); ?>
                <?php erh_the_icon( 'external-link' ); ?>
            </a>
        </div>

        <div class="youtube-grid scroll-section">
            <!-- YouTube videos will be populated here -->
            <p class="empty-state"><?php esc_html_e( 'YouTube videos will be loaded here.', 'erh' ); ?></p>
        </div>
    </div>
</section>

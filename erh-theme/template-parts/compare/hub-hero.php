<?php
/**
 * Compare Hub Hero Section
 *
 * Centered hero with heading, subtitle, and comparison card.
 * Uses same comparison card structure as homepage.
 *
 * @package ERideHero
 */

defined( 'ABSPATH' ) || exit;

// Get the JSON file path and URL (same pattern as home/comparison.php).
$upload_dir = wp_upload_dir();
$json_path  = $upload_dir['basedir'] . '/comparison_products.json';
$json_url   = $upload_dir['baseurl'] . '/comparison_products.json';

// Check if JSON exists - widget won't work without it.
$has_json = file_exists( $json_path );
?>

<section class="hero hero--compare">
    <div class="hero-grid" aria-hidden="true"></div>
    <div class="container">
        <div class="compare-hub-intro">
            <h1><?php esc_html_e( 'Compare', 'erh' ); ?> <span><?php esc_html_e( 'electric rides', 'erh' ); ?></span></h1>
            <p class="hero-subtitle"><?php esc_html_e( 'Find your perfect ride by comparing specs, prices, and features side-by-side.', 'erh' ); ?></p>
        </div>

        <?php if ( $has_json ) : ?>
        <div class="comparison-card">
            <div class="comparison-card-bg" aria-hidden="true">
                <div class="comparison-orb"></div>
            </div>
            <div class="comparison-content">
                <header class="comparison-header">
                    <h2><?php esc_html_e( 'Head-to-head comparison', 'erh' ); ?></h2>
                    <div class="comparison-category-pill" id="hub-category-pill" aria-live="polite">
                        <span id="hub-category-text"><?php esc_html_e( 'Showing e-scooters', 'erh' ); ?></span>
                        <button type="button" aria-label="<?php esc_attr_e( 'Clear category filter', 'erh' ); ?>" id="hub-category-clear">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                </header>

                <!-- Screen reader announcements -->
                <div id="hub-comparison-announcer" class="sr-only" aria-live="polite" aria-atomic="true"></div>

                <div class="comparison-row-main" id="hub-comparison-container" data-json-url="<?php echo esc_url( $json_url ); ?>">
                    <!-- Left column: first product -->
                    <div class="comparison-column-left">
                        <div class="comparison-input-wrapper">
                            <input type="text" class="comparison-input" placeholder="<?php esc_attr_e( 'Search products...', 'erh' ); ?>" autocomplete="off" data-slot="0">
                            <button type="button" class="comparison-input-clear" aria-label="<?php esc_attr_e( 'Clear selection', 'erh' ); ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                            <img class="comparison-input-thumb" src="" alt="" aria-hidden="true">
                            <div class="comparison-results"></div>
                        </div>
                    </div>

                    <!-- VS divider -->
                    <span class="comparison-vs"><?php esc_html_e( 'vs', 'erh' ); ?></span>

                    <!-- Right column: second product + dynamic additions -->
                    <div class="comparison-column-right" id="hub-comparison-right">
                        <div class="comparison-input-wrapper">
                            <input type="text" class="comparison-input" placeholder="<?php esc_attr_e( 'Search products...', 'erh' ); ?>" autocomplete="off" data-slot="1">
                            <button type="button" class="comparison-input-clear" aria-label="<?php esc_attr_e( 'Clear selection', 'erh' ); ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                            <img class="comparison-input-thumb" src="" alt="" aria-hidden="true">
                            <div class="comparison-results"></div>
                        </div>
                    </div>

                    <!-- Compare button -->
                    <div class="comparison-actions">
                        <button type="button" class="comparison-btn" id="hub-comparison-submit" disabled>
                            <?php esc_html_e( 'Compare', 'erh' ); ?>
                            <?php echo erh_icon( 'arrow-right', 'icon' ); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php else : ?>
        <div class="comparison-card comparison-card--loading">
            <div class="comparison-card-bg" aria-hidden="true">
                <div class="comparison-orb"></div>
            </div>
            <div class="comparison-content">
                <p class="comparison-loading-msg"><?php esc_html_e( 'Comparison tool loading...', 'erh' ); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

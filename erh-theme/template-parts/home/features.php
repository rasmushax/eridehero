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
            <!-- Price History Card -->
            <div class="feature-card feature-card-chart">
                <div class="feature-visual feature-visual-chart">
                    <svg class="feature-chart" viewBox="0 0 100 50" preserveAspectRatio="none" fill="none" aria-hidden="true">
                        <!-- Gradient fill under the curve -->
                        <path d="M-10 5 C0 5, 5 12, 10 12 C15 12, 17 23, 22 23 C27 23, 28 16, 33 16 C38 16, 40 25, 45 25 C50 25, 52 19, 57 19 C62 19, 64 46, 68 46 C72 46, 77 25, 82 25 C87 25, 90 33, 95 33 C100 33, 105 31, 110 31 L110 55 L-10 55 Z" fill="url(#chart-gradient)"/>
                        <!-- Smooth spline curve using smooth cubic beziers -->
                        <path d="M-10 5 C0 5, 5 12, 10 12 C15 12, 17 23, 22 23 C27 23, 28 16, 33 16 C38 16, 40 25, 45 25 C50 25, 52 19, 57 19 C62 19, 64 46, 68 46 C72 46, 77 25, 82 25 C87 25, 90 33, 95 33 C100 33, 105 31, 110 31" stroke="var(--color-primary)" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
                        <defs>
                            <linearGradient id="chart-gradient" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="var(--color-primary)" stop-opacity="0.2"/>
                                <stop offset="90%" stop-color="var(--color-primary)" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                    </svg>
                    <!-- Dots positioned with CSS -->
                    <div class="chart-dots">
                        <span class="chart-dot" style="left: 10%; top: 24%;"></span>
                        <span class="chart-dot" style="left: 22%; top: 46%;"></span>
                        <span class="chart-dot" style="left: 33%; top: 32%;"></span>
                        <span class="chart-dot" style="left: 45%; top: 50%;"></span>
                        <span class="chart-dot" style="left: 57%; top: 38%;"></span>
                        <span class="chart-dot chart-dot-final" style="left: 68%; top: 90%;"></span>
                        <span class="chart-dot" style="left: 82%; top: 50%;"></span>
                        <span class="chart-dot" style="left: 95%; top: 66%;"></span>
                        <span class="chart-callout" style="left: 68%; top: 90%;">
                            <span class="chart-callout-inner">
                                <span class="chart-callout-img">
                                    <img src="<?php echo esc_url( ERH_THEME_URI . '/assets/images/scooter.png' ); ?>" alt="" aria-hidden="true">
                                </span>
                                <span class="chart-callout-text">
                                    <span class="chart-callout-label"><?php esc_html_e( 'All-time low', 'erh' ); ?></span>
                                    <span class="chart-callout-value">
                                        <?php esc_html_e( '28% off', 'erh' ); ?>
                                        <svg class="chart-callout-triangle" viewBox="0 0 8 6" aria-hidden="true">
                                            <path d="M4 6 L0 0 L8 0 Z" fill="currentColor"/>
                                        </svg>
                                    </span>
                                </span>
                            </span>
                        </span>
                    </div>
                </div>
                <div class="feature-content">
                    <span class="feature-label"><?php esc_html_e( 'Price history', 'erh' ); ?></span>
                    <h3><?php esc_html_e( 'Find the best price at the right time', 'erh' ); ?></h3>
                    <p><?php esc_html_e( '50+ retailers tracked daily. Avoid fake sales, know when to buy.', 'erh' ); ?></p>
                </div>
            </div>

            <!-- Price Alerts Card -->
            <div class="feature-card">
                <div class="feature-visual feature-visual-notification">
                    <div class="feature-notification-rings" aria-hidden="true">
                        <span class="feature-notification-ring"></span>
                        <span class="feature-notification-ring"></span>
                        <span class="feature-notification-ring"></span>
                        <span class="feature-notification-ring"></span>
                        <span class="feature-notification-ring"></span>
                    </div>
                    <div class="feature-notification-bell">
                        <?php erh_the_icon( 'bell' ); ?>
                        <span class="feature-notification-badge">1</span>
                    </div>
                    <div class="feature-notification-connector"></div>
                    <div class="feature-notification">
                        <div class="feature-notification-img">
                            <img src="<?php echo esc_url( ERH_THEME_URI . '/assets/images/ebike.png' ); ?>" alt="" aria-hidden="true">
                        </div>
                        <div class="feature-notification-content">
                            <span class="feature-notification-title"><?php esc_html_e( 'Price dropped!', 'erh' ); ?></span>
                            <span class="feature-notification-text">
                                <strong>$499</strong>
                                <svg class="feature-notification-triangle" viewBox="0 0 8 6" aria-hidden="true">
                                    <path d="M4 6 L0 0 L8 0 Z" fill="currentColor"/>
                                </svg>
                                <s>$699</s>
                            </span>
                        </div>
                        <span class="feature-notification-time">2m</span>
                    </div>
                </div>
                <div class="feature-content">
                    <span class="feature-label"><?php esc_html_e( 'Price alerts', 'erh' ); ?></span>
                    <h3><?php esc_html_e( 'Never miss a deal', 'erh' ); ?></h3>
                    <p><?php esc_html_e( 'Get notified when prices drop on products you\'re watching.', 'erh' ); ?></p>
                </div>
            </div>

            <!-- Product Database Card -->
            <div class="feature-card feature-card-database">
                <div class="feature-visual feature-visual-database">
                    <div class="feature-search-box">
                        <?php erh_the_icon( 'search' ); ?>
                        <span class="feature-search-text"><?php esc_html_e( 'Search 1,000+ products...', 'erh' ); ?></span>
                    </div>
                    <div class="feature-category-pills">
                        <span class="filter-pill feature-pill active">
                            <?php esc_html_e( 'E-scooters', 'erh' ); ?>
                            <?php erh_the_icon( 'x' ); ?>
                        </span>
                        <span class="filter-pill feature-pill"><?php esc_html_e( 'E-bikes', 'erh' ); ?></span>
                        <span class="filter-pill feature-pill"><?php esc_html_e( 'EUCs', 'erh' ); ?></span>
                        <span class="filter-pill feature-pill"><?php esc_html_e( 'E-skates', 'erh' ); ?></span>
                        <span class="filter-pill feature-pill"><?php esc_html_e( 'Hoverboards', 'erh' ); ?></span>
                    </div>
                </div>
                <div class="feature-content">
                    <span class="feature-label"><?php esc_html_e( 'Product database', 'erh' ); ?></span>
                    <h3><?php esc_html_e( 'All electric rides in one place', 'erh' ); ?></h3>
                    <p><?php esc_html_e( 'Browse, compare, and filter 1,000+ electric rides by specs and price.', 'erh' ); ?></p>
                </div>
            </div>
        </div>
    </div>
</section>

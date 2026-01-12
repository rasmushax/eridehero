<?php
/**
 * Product Price Intelligence
 *
 * Where to buy section with price comparison and history chart.
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$product_id = get_the_ID();

// This will integrate with erh-core PriceFetcher
// For now, show placeholder structure
?>
<section class="price-intel" id="where-to-buy">
    <div class="price-intel-header">
        <div class="price-intel-best">
            <span class="price-intel-label"><?php esc_html_e( 'Best price', 'erh' ); ?></span>
            <div class="price-intel-price-link">
                <span class="price-intel-price">--</span>
                <span class="price-intel-retailer"><?php esc_html_e( 'Loading...', 'erh' ); ?></span>
            </div>
        </div>
        <a href="#" class="btn btn-primary btn-lg price-intel-cta" data-product-id="<?php echo esc_attr( $product_id ); ?>">
            <?php esc_html_e( 'Buy now', 'erh' ); ?>
            <?php erh_the_icon( 'external-link' ); ?>
        </a>
    </div>

    <div class="price-intel-retailers">
        <div class="price-intel-retailers-header">
            <h3><?php esc_html_e( 'Compare prices', 'erh' ); ?></h3>
            <span class="price-intel-updated"><?php esc_html_e( 'Updated recently', 'erh' ); ?></span>
        </div>
        <div class="price-intel-retailers-list" data-product-id="<?php echo esc_attr( $product_id ); ?>">
            <!-- Retailer rows will be populated by JS/REST API -->
            <p class="empty-state"><?php esc_html_e( 'Loading prices...', 'erh' ); ?></p>
        </div>
        <p class="price-intel-disclosure">
            <?php esc_html_e( 'We may earn a commission from purchases made through these links.', 'erh' ); ?>
        </p>
    </div>

    <div class="price-intel-history">
        <div class="price-intel-history-header">
            <h3><?php esc_html_e( 'Price history', 'erh' ); ?></h3>
            <div class="price-intel-period-tabs" role="tablist">
                <button class="tab tab-sm" role="tab" aria-selected="false" data-period="3m"><?php esc_html_e( '3M', 'erh' ); ?></button>
                <button class="tab tab-sm is-active" role="tab" aria-selected="true" data-period="6m"><?php esc_html_e( '6M', 'erh' ); ?></button>
                <button class="tab tab-sm" role="tab" aria-selected="false" data-period="1y"><?php esc_html_e( '1Y', 'erh' ); ?></button>
                <button class="tab tab-sm" role="tab" aria-selected="false" data-period="all"><?php esc_html_e( 'All', 'erh' ); ?></button>
            </div>
        </div>
        <div class="price-intel-chart-visual" data-erh-chart="main" data-product-id="<?php echo esc_attr( $product_id ); ?>">
            <!-- Chart will be rendered by chart.js -->
        </div>
        <div class="price-intel-stats">
            <div class="price-intel-stat">
                <span class="price-intel-stat-label" data-period-label><?php esc_html_e( '6-month avg', 'erh' ); ?></span>
                <span class="price-intel-stat-value" data-period-avg>--</span>
            </div>
            <div class="price-intel-stat">
                <span class="price-intel-stat-label" data-period-low-label><?php esc_html_e( '6-month low', 'erh' ); ?></span>
                <span class="price-intel-stat-value" data-period-low>--</span>
                <span class="price-intel-stat-meta" data-period-low-meta></span>
            </div>
            <div class="price-intel-stat price-intel-stat--action">
                <button type="button" class="btn btn-secondary btn-sm" data-modal-trigger="price-alert-modal">
                    <?php erh_the_icon( 'bell' ); ?>
                    <?php esc_html_e( 'Set price alert', 'erh' ); ?>
                </button>
            </div>
        </div>
    </div>
</section>

<?php
/**
 * Price Intelligence Component
 *
 * Displays geo-aware pricing with retailer comparison and price history.
 * Renders a shell that JavaScript hydrates with dynamic data based on user's region.
 *
 * @package ERideHero
 *
 * @var array $args {
 *     @type int    $product_id   The product ID to display pricing for.
 *     @type string $product_name The product name for display.
 * }
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get arguments.
$product_id   = $args['product_id'] ?? 0;
$product_name = $args['product_name'] ?? '';

if ( ! $product_id ) {
    return;
}

// Get product thumbnail for section header.
$thumb_id = get_post_thumbnail_id( $product_id );

// Get product name if not provided.
if ( empty( $product_name ) ) {
    $product_name = get_the_title( $product_id );
}
?>

<section class="review-section" id="prices">
    <h2 class="review-section-title">
        Where to buy
        <?php if ( $thumb_id ) : ?>
            <?php echo wp_get_attachment_image( $thumb_id, 'erh-product-xs', false, array( 'class' => 'review-section-product-img' ) ); ?>
        <?php endif; ?>
        <span class="review-section-product-name"><?php echo esc_html( $product_name ); ?></span>
    </h2>

    <div class="price-intel" data-price-intel data-product-id="<?php echo esc_attr( $product_id ); ?>">
        <!-- Header: Best Price + CTA -->
        <div class="price-intel-header">
            <div class="price-intel-best">
                <span class="label">Best price</span>
                <div class="price-intel-price-row">
                    <a href="#" class="price-intel-price-link" data-best-price-link target="_blank" rel="nofollow noopener">
                        <span class="price-intel-amount" data-best-price>
                            <span class="skeleton skeleton-text" style="width: 80px;"></span>
                        </span>
                        <span class="price-intel-at">at</span>
                        <span class="price-intel-retailer-logo" data-best-retailer-logo>
                            <span class="skeleton skeleton-text" style="width: 60px;"></span>
                        </span>
                    </a>
                    <span class="price-intel-verdict" data-price-verdict style="display: none;"></span>
                </div>
            </div>
            <a href="#" class="btn btn-primary" data-buy-cta target="_blank" rel="nofollow noopener">
                <span data-buy-cta-text style="display: none;">Buy now</span>
                <svg class="icon" aria-hidden="true" data-buy-cta-icon style="display: none;"><use href="#icon-external-link"></use></svg>
                <svg class="spinner" viewBox="0 0 24 24" data-buy-cta-spinner><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4" stroke-linecap="round"/></svg>
            </a>
        </div>

        <!-- Retailer List -->
        <div class="price-intel-retailers" id="all-retailers">
            <div class="price-intel-retailers-header">
                <span class="price-intel-retailers-title">Compare prices</span>
                <span class="price-intel-retailers-updated" data-updated-time></span>
            </div>
            <div class="price-intel-retailer-list" data-retailer-list>
                <!-- Loading skeleton - single row -->
                <div class="price-intel-retailer-row" data-retailer-skeleton>
                    <div class="skeleton skeleton-avatar"></div>
                    <div class="price-intel-retailer-info">
                        <span class="skeleton skeleton-text" style="width: 70px;"></span>
                        <span class="skeleton skeleton-text" style="width: 55px;"></span>
                    </div>
                    <div class="price-intel-retailer-price">
                        <span class="skeleton skeleton-text" style="width: 50px;"></span>
                    </div>
                    <div class="skeleton" style="width: 18px; height: 18px; border-radius: 4px; flex-shrink: 0;"></div>
                </div>
            </div>
            <p class="price-intel-disclosure">We may earn a commission from purchases.</p>
        </div>

        <!-- Price History Section -->
        <div class="price-intel-history" data-price-history>
            <div class="price-intel-history-header">
                <span class="price-intel-history-title">Price history</span>
                <div class="price-intel-chart-period" data-period-buttons>
                    <button type="button" data-period="3m">3M</button>
                    <button type="button" data-period="6m" class="is-active">6M</button>
                    <button type="button" data-period="1y">1Y</button>
                    <button type="button" data-period="all">All</button>
                </div>
            </div>
            <div class="price-intel-chart-visual" data-erh-chart="price-intel-<?php echo esc_attr( $product_id ); ?>">
                <!-- Skeleton loader for chart -->
                <div class="price-intel-chart-skeleton" data-chart-skeleton>
                    <div class="skeleton"></div>
                </div>
            </div>
            <div class="price-intel-stats">
                <div class="price-intel-stat">
                    <span class="price-intel-stat-label" data-period-label>6-month avg</span>
                    <span class="price-intel-stat-value" data-period-avg>
                        <span class="skeleton skeleton-text" style="width: 50px;"></span>
                    </span>
                </div>
                <div class="price-intel-stat">
                    <span class="price-intel-stat-label" data-period-low-label>6-month low</span>
                    <span class="price-intel-stat-value price-intel-stat-value--low" data-period-low>
                        <span class="skeleton skeleton-text" style="width: 50px;"></span>
                    </span>
                    <span class="price-intel-stat-meta" data-period-low-meta>
                        <span class="skeleton skeleton-text" style="width: 70px;"></span>
                    </span>
                </div>
                <div class="price-intel-stat price-intel-stat--action">
                    <button type="button" class="btn btn-secondary btn-sm" data-price-alert-trigger>
                        <svg class="icon" aria-hidden="true"><use href="#icon-bell"></use></svg>
                        Set price alert
                    </button>
                </div>
            </div>
        </div>

        <!-- No Price Data State -->
        <div class="price-intel-empty" data-no-prices style="display: none;">
            <div class="empty-state">
                <p>No pricing data available for your region.</p>
            </div>
        </div>

        <!-- Region Fallback Notice -->
        <div class="price-intel-notice" data-region-notice style="display: none;">
            <svg class="icon" aria-hidden="true"><use href="#icon-info"></use></svg>
            <span data-region-notice-text></span>
        </div>
    </div>
</section>

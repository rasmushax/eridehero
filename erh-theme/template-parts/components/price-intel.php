<?php
/**
 * Price Intelligence Component
 *
 * Displays geo-aware pricing with retailer comparison and price history.
 * Renders a shell that JavaScript hydrates with dynamic data based on user's region.
 *
 * Handles states:
 * - Normal: Show prices, chart, retailers (JS hydrates)
 * - Obsolete (superseded): Show discontinued message + successor + alternatives
 * - Obsolete (not superseded): Show discontinued message + alternatives
 * - No pricing: Show "no pricing available" message + alternatives
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

// Get obsolete status.
$obsolete_status = erh_get_product_obsolete_status( $product_id );
$is_obsolete     = $obsolete_status['is_obsolete'];
$is_superseded   = $obsolete_status['is_superseded'];

// Check if product has any pricing data.
$has_pricing = erh_product_has_pricing( $product_id );
?>

<section class="content-section" id="prices">
    <h2 class="section-title">
        Where to buy
        <?php if ( $thumb_id ) : ?>
            <?php echo wp_get_attachment_image( $thumb_id, 'erh-product-xs', false, array( 'class' => 'section-product-img' ) ); ?>
        <?php endif; ?>
        <span class="section-product-name"><?php echo esc_html( $product_name ); ?></span>
    </h2>

    <?php if ( $is_obsolete ) : ?>
        <!-- Discontinued Product State with Alternatives -->
        <div class="price-intel price-intel--discontinued">
            <div class="price-intel-discontinued">
                <div class="discontinued-header">
                    <div class="discontinued-icon">
                        <?php erh_the_icon( 'archive' ); ?>
                    </div>
                    <h3 class="discontinued-title"><?php esc_html_e( 'This product has been discontinued', 'erh' ); ?></h3>
                    <p class="discontinued-text"><?php esc_html_e( 'No longer available from major retailers.', 'erh' ); ?></p>
                </div>

                <?php
                // Show successor product if superseded - rendered client-side for geo-aware pricing.
                if ( $is_superseded && ! empty( $obsolete_status['new_product_id'] ) ) :
                    $successor_id   = $obsolete_status['new_product_id'];
                    $successor_name = $obsolete_status['new_product_name'];
                    $successor_url  = $obsolete_status['new_product_url'];

                    // Get successor image.
                    $successor_thumb_id = get_post_thumbnail_id( $successor_id );
                    $successor_image    = $successor_thumb_id
                        ? wp_get_attachment_image_url( $successor_thumb_id, 'erh-product-sm' )
                        : '';

                    // Get successor specs and pricing from wp_product_data cache.
                    global $wpdb;
                    $table_name     = $wpdb->prefix . 'product_data';
                    $successor_data = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT specs, price_history FROM {$table_name} WHERE product_id = %d",
                            $successor_id
                        ),
                        ARRAY_A
                    );

                    $successor_specs_line = '';
                    $successor_pricing    = array();

                    if ( $successor_data ) {
                        $successor_specs = maybe_unserialize( $successor_data['specs'] );
                        if ( is_array( $successor_specs ) ) {
                            $successor_specs_line = erh_format_card_specs( $successor_specs );
                        }

                        $successor_pricing = maybe_unserialize( $successor_data['price_history'] );
                        if ( ! is_array( $successor_pricing ) ) {
                            $successor_pricing = array();
                        }
                    }

                    // Encode successor data for JS hydration.
                    $successor_json = wp_json_encode( array(
                        'id'      => $successor_id,
                        'pricing' => $successor_pricing,
                    ) );
                ?>
                    <div class="discontinued-successor" data-successor='<?php echo esc_attr( $successor_json ); ?>'>
                        <span class="discontinued-section-label"><?php esc_html_e( 'Replaced by', 'erh' ); ?></span>
                        <a href="<?php echo esc_url( $successor_url ); ?>" class="discontinued-successor-card">
                            <div class="discontinued-successor-img-wrap">
                                <?php if ( $successor_image ) : ?>
                                    <img src="<?php echo esc_url( $successor_image ); ?>" alt="" class="discontinued-successor-img" loading="lazy">
                                <?php else : ?>
                                    <div class="discontinued-successor-img discontinued-successor-img--placeholder"></div>
                                <?php endif; ?>
                            </div>
                            <div class="discontinued-successor-info">
                                <span class="discontinued-successor-name"><?php echo esc_html( $successor_name ); ?></span>
                                <?php if ( $successor_specs_line ) : ?>
                                    <span class="discontinued-successor-specs"><?php echo esc_html( $successor_specs_line ); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="discontinued-successor-action">
                                <span class="discontinued-successor-price" data-successor-price>
                                    <span class="skeleton skeleton-text" style="width: 50px;"></span>
                                </span>
                                <?php erh_the_icon( 'chevron-right' ); ?>
                            </div>
                        </a>
                    </div>
                <?php endif; ?>

                <?php
                // Get similar products as alternatives - rendered client-side for geo-aware pricing.
                $similar_products = erh_get_similar_products( $product_id, 4 );

                if ( ! empty( $similar_products ) ) :
                    // Encode pricing data for JS.
                    $alternatives_data = array_map( function( $p ) {
                        return array(
                            'id'        => $p['product_id'],
                            'name'      => $p['name'],
                            'url'       => $p['permalink'],
                            'image'     => $p['image_url'],
                            'specs'     => erh_format_card_specs( $p['specs'] ?? array() ),
                            'pricing'   => $p['pricing'] ?? array(),
                        );
                    }, $similar_products );
                ?>
                    <div class="discontinued-alternatives" data-alternatives='<?php echo esc_attr( wp_json_encode( $alternatives_data ) ); ?>'>
                        <span class="discontinued-section-label"><?php esc_html_e( 'Similar alternatives', 'erh' ); ?></span>
                        <div class="discontinued-alternatives-grid">
                            <!-- Skeleton loading cards -->
                            <?php for ( $i = 0; $i < min( 4, count( $similar_products ) ); $i++ ) : ?>
                                <div class="discontinued-alternative-card discontinued-alternative-skeleton">
                                    <div class="discontinued-alternative-img-wrap">
                                        <div class="skeleton skeleton-img"></div>
                                    </div>
                                    <div class="discontinued-alternative-info">
                                        <span class="skeleton skeleton-text"></span>
                                        <span class="skeleton skeleton-text-sm"></span>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ( ! $has_pricing ) : ?>
        <!-- No Pricing Available State with Alternatives -->
        <div class="price-intel price-intel--unavailable">
            <div class="price-intel-discontinued">
                <div class="discontinued-header">
                    <div class="discontinued-icon">
                        <?php erh_the_icon( 'tag' ); ?>
                    </div>
                    <h3 class="discontinued-title"><?php esc_html_e( 'No pricing available', 'erh' ); ?></h3>
                    <p class="discontinued-text"><?php esc_html_e( 'We don\'t have current pricing for this product.', 'erh' ); ?></p>
                </div>

                <?php
                // Get similar products as alternatives - rendered client-side for geo-aware pricing.
                $similar_products = erh_get_similar_products( $product_id, 4 );

                if ( ! empty( $similar_products ) ) :
                    // Encode pricing data for JS.
                    $alternatives_data = array_map( function( $p ) {
                        return array(
                            'id'        => $p['product_id'],
                            'name'      => $p['name'],
                            'url'       => $p['permalink'],
                            'image'     => $p['image_url'],
                            'specs'     => erh_format_card_specs( $p['specs'] ?? array() ),
                            'pricing'   => $p['pricing'] ?? array(),
                        );
                    }, $similar_products );
                ?>
                    <div class="discontinued-alternatives" data-alternatives='<?php echo esc_attr( wp_json_encode( $alternatives_data ) ); ?>'>
                        <span class="discontinued-section-label"><?php esc_html_e( 'Similar alternatives', 'erh' ); ?></span>
                        <div class="discontinued-alternatives-grid">
                            <!-- Skeleton loading cards -->
                            <?php for ( $i = 0; $i < min( 4, count( $similar_products ) ); $i++ ) : ?>
                                <div class="discontinued-alternative-card discontinued-alternative-skeleton">
                                    <div class="discontinued-alternative-img-wrap">
                                        <div class="skeleton skeleton-img"></div>
                                    </div>
                                    <div class="discontinued-alternative-info">
                                        <span class="skeleton skeleton-text"></span>
                                        <span class="skeleton skeleton-text-sm"></span>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php else : ?>

    <div class="price-intel" data-price-intel data-product-id="<?php echo esc_attr( $product_id ); ?>">
        <!-- Header: Best Price + CTA -->
        <div class="price-intel-header">
            <div class="price-intel-best">
                <span class="label">Best price</span>
                <div class="price-intel-price-row">
                    <a href="#" class="price-intel-price-link" data-best-price-link target="_blank" rel="sponsored noopener">
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
            <a href="#" class="btn btn-primary" data-buy-cta target="_blank" rel="sponsored noopener">
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
            <p class="price-intel-disclosure">We may earn a commission from purchases. <a href="<?php echo esc_url( home_url( '/disclaimers/' ) ); ?>">Learn more</a></p>
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

    <?php endif; // End obsolete check. ?>
</section>

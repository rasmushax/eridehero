<?php
/**
 * SSR Deal Card — Category Page
 *
 * Renders a single deal product card with real data for SEO.
 * HTML structure matches the JS `deal-card-template` in page-deals-category.php
 * so JS can hydrate/replace without layout shift.
 *
 * @param array $args {
 *     @type array  $deal   Deal data from DealsFinder.
 *     @type string $period Current period label (e.g., '6m').
 * }
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$deal   = $args['deal'] ?? [];
$period = $args['period'] ?? '6m';

if ( empty( $deal ) ) {
    return;
}

$product_id    = (int) ( $deal['product_id'] ?? $deal['id'] ?? 0 );
$name          = $deal['name'] ?? '';
$permalink     = $deal['permalink'] ?? '';
$current_price = (float) ( $deal['deal_analysis']['current_price'] ?? 0 );
$currency      = $deal['deal_analysis']['currency'] ?? 'USD';
$discount      = abs( (float) ( $deal['deal_analysis']['discount_percent'] ?? 0 ) );
$avg_price     = (float) ( $deal['deal_analysis']['avg_price'] ?? 0 );

// Thumbnail.
$thumbnail = '';
if ( ! empty( $deal['image_url'] ) ) {
    $thumbnail = $deal['image_url'];
} elseif ( $product_id ) {
    $thumb_id = get_post_thumbnail_id( $product_id );
    if ( $thumb_id ) {
        $thumb_data = wp_get_attachment_image_src( $thumb_id, 'medium' );
        $thumbnail  = $thumb_data[0] ?? '';
    }
}

// Format price.
$price_formatted = $current_price > 0 ? erh_format_price( $current_price, $currency ) : '';

// Period labels.
$period_labels = [
    '3m'  => '3-mo avg',
    '6m'  => '6-mo avg',
    '12m' => '12-mo avg',
];
$period_label = $period_labels[ $period ] ?? '6-mo avg';

// Average price for selected period.
$avg_key       = 'avg_price_' . $period;
$period_avg    = (float) ( $deal['deal_analysis'][ $avg_key ] ?? $avg_price );
$avg_formatted = $period_avg > 0 ? erh_format_price( $period_avg, $currency ) : '—';

$placeholder = get_theme_file_uri( 'assets/images/placeholder.svg' );
?>
<div class="product-card" data-product-id="<?php echo esc_attr( $product_id ); ?>">
    <label class="product-card-select" onclick="event.stopPropagation()">
        <input type="checkbox" data-compare-select value="<?php echo esc_attr( $product_id ); ?>">
        <span class="product-card-select-box">
            <?php erh_the_icon( 'check' ); ?>
        </span>
    </label>

    <button class="product-card-track" data-track-price="<?php echo esc_attr( $product_id ); ?>" aria-label="<?php esc_attr_e( 'Track price', 'erh' ); ?>">
        <?php erh_the_icon( 'bell' ); ?>
    </button>

    <a href="<?php echo esc_url( $permalink ); ?>" class="product-card-link">
        <div class="product-card-image">
            <img src="<?php echo esc_url( $thumbnail ?: $placeholder ); ?>"
                 alt="<?php echo esc_attr( $name ); ?>"
                 loading="lazy"
                 onerror="this.src='<?php echo esc_url( $placeholder ); ?>'">
            <div class="product-card-price-row">
                <?php if ( $price_formatted ) : ?>
                    <span class="product-card-price" data-price><?php echo esc_html( $price_formatted ); ?></span>
                <?php else : ?>
                    <span class="product-card-price" data-price></span>
                <?php endif; ?>
                <?php if ( $discount > 0 ) : ?>
                    <span class="product-card-indicator product-card-indicator--below" data-indicator>
                        <?php erh_the_icon( 'arrow-down', 'product-card-indicator-icon' ); ?><span data-indicator-value><?php echo esc_html( round( $discount ) . '%' ); ?></span>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </a>

    <div class="product-card-content">
        <h3 class="product-card-name">
            <a href="<?php echo esc_url( $permalink ); ?>" data-product-link><?php echo esc_html( $name ); ?></a>
        </h3>
        <div class="deal-card-compare" data-deal-compare>
            <span class="deal-card-compare-label" data-compare-label><?php echo esc_html( $period_label ); ?></span>
            <span class="deal-card-compare-price" data-compare-price><?php echo esc_html( $avg_formatted ); ?></span>
        </div>
    </div>
</div>

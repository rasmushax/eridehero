<?php
/**
 * SSR Deal Card — Hub Page
 *
 * Renders a single deal card for the hub page carousels.
 * HTML structure matches the JS `hub-deal-card-template` in page-deals.php.
 *
 * @param array $args {
 *     @type array $deal Deal data from DealsFinder.
 * }
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$deal = $args['deal'] ?? [];

if ( empty( $deal ) ) {
    return;
}

$product_id    = (int) ( $deal['product_id'] ?? $deal['id'] ?? 0 );
$name          = $deal['name'] ?? '';
$permalink     = $deal['permalink'] ?? '';
$current_price = (float) ( $deal['deal_analysis']['current_price'] ?? 0 );
$currency      = $deal['deal_analysis']['currency'] ?? 'USD';
$discount      = abs( (float) ( $deal['deal_analysis']['discount_percent'] ?? 0 ) );
$category_slug = '';

// Determine category slug from product type.
if ( ! empty( $deal['product_type'] ) ) {
    $category_slug = \ERH\CategoryConfig::type_to_finder_key( $deal['product_type'] );
}

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

$price_formatted = $current_price > 0 ? erh_format_price( $current_price, $currency ) : '';
$placeholder     = get_theme_file_uri( 'assets/images/placeholder.svg' );
?>
<a href="<?php echo esc_url( $permalink ); ?>" class="deal-card" data-category="<?php echo esc_attr( $category_slug ); ?>">
    <button type="button" class="deal-card-track" data-track-price="<?php echo esc_attr( $product_id ); ?>" aria-label="<?php esc_attr_e( 'Track price', 'erh' ); ?>">
        <?php erh_the_icon( 'bell' ); ?>
    </button>
    <div class="deal-card-image">
        <img src="<?php echo esc_url( $thumbnail ?: $placeholder ); ?>"
             alt="<?php echo esc_attr( $name ); ?>"
             loading="lazy"
             onerror="this.src='<?php echo esc_url( $placeholder ); ?>'">
        <div class="deal-card-price-row">
            <?php if ( $price_formatted ) : ?>
                <span class="deal-card-price" data-price><?php echo esc_html( $price_formatted ); ?></span>
            <?php else : ?>
                <span class="deal-card-price" data-price></span>
            <?php endif; ?>
            <?php if ( $discount > 0 ) : ?>
                <span class="deal-card-indicator deal-card-indicator--below" data-indicator>
                    <svg class="icon deal-card-indicator-icon" aria-hidden="true"><use href="#icon-arrow-down"></use></svg>
                    <span data-indicator-value><?php echo esc_html( round( $discount ) . '%' ); ?></span>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="deal-card-content">
        <h3 class="deal-card-title"><?php echo esc_html( $name ); ?></h3>
    </div>
</a>

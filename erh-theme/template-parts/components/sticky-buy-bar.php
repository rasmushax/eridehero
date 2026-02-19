<?php
/**
 * Sticky Buy Bar Component
 *
 * Persistent bottom CTA that appears after scrolling past the price section.
 * Price data is loaded via JS for geo-awareness.
 *
 * @package ERideHero
 *
 * Expected $args:
 *   'product_id'   => int    - Product post ID
 *   'product_name' => string - Product name
 *   'compare_url'  => string - URL to compare page for this category
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get arguments
$product_id   = $args['product_id'] ?? 0;
$product_name = $args['product_name'] ?? '';
$compare_url  = $args['compare_url'] ?? '';

// Bail if no product
if ( empty( $product_id ) ) {
    return;
}

// Get product thumbnail
$thumbnail_id  = get_post_thumbnail_id( $product_id );
$thumbnail_url = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ) : '';

// Fallback to big_thumbnail ACF field if no featured image
if ( empty( $thumbnail_url ) ) {
    $big_thumbnail = get_field( 'big_thumbnail', $product_id );
    if ( $big_thumbnail ) {
        $thumbnail_url = is_array( $big_thumbnail ) ? $big_thumbnail['sizes']['thumbnail'] ?? $big_thumbnail['url'] : wp_get_attachment_image_url( $big_thumbnail, 'thumbnail' );
    }
}
?>

<div class="sticky-buy-bar"
     id="sticky-buy-bar"
     aria-hidden="true"
     data-product-id="<?php echo esc_attr( $product_id ); ?>"
     data-product-name="<?php echo esc_attr( $product_name ); ?>">
    <div class="sticky-buy-bar-inner">
        <!-- Left: Product info + action links -->
        <div class="sticky-buy-bar-left">
            <?php if ( $thumbnail_url ) : ?>
                <a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" class="sticky-buy-bar-thumb-link">
                    <img src="<?php echo esc_url( $thumbnail_url ); ?>"
                         alt=""
                         class="sticky-buy-bar-img">
                </a>
            <?php endif; ?>
            <div class="sticky-buy-bar-info">
                <a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" class="sticky-buy-bar-name"><?php echo esc_html( $product_name ); ?></a>
                <div class="sticky-buy-bar-actions">
                    <button type="button" class="sticky-buy-bar-link" data-modal-trigger="price-alert-modal">
                        <?php erh_the_icon( 'bell' ); ?>
                        Track price
                    </button>
                    <?php if ( $compare_url ) : ?>
                        <a href="<?php echo esc_url( $compare_url ); ?>" class="sticky-buy-bar-link">
                            <?php erh_the_icon( 'grid' ); ?>
                            Compare
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Verdict + Price + CTA -->
        <div class="sticky-buy-bar-right">
            <!-- Verdict badge (arrow + %) -->
            <div class="sticky-buy-bar-verdict" data-sticky-verdict style="display: none;">
                <svg class="icon sticky-buy-bar-verdict-icon" aria-hidden="true" data-sticky-verdict-icon><use href="#icon-arrow-down"></use></svg>
                <span data-sticky-verdict-text>2% below avg</span>
            </div>

            <!-- Price block -->
            <div class="sticky-buy-bar-price">
                <span class="sticky-buy-bar-price-label">Best price</span>
                <span class="sticky-buy-bar-price-amount" data-sticky-price>--</span>
            </div>

            <!-- CTA button -->
            <a href="#"
               class="btn btn-primary btn-sm"
               data-sticky-buy-link
               target="_blank"
               rel="sponsored noopener">
                <span data-sticky-retailer>Buy now</span>
                <?php erh_the_icon( 'external-link' ); ?>
            </a>
        </div>

        <!-- Screen reader announcements -->
        <div id="sticky-buy-bar-announcer" class="sr-only" aria-live="polite" aria-atomic="true"></div>
    </div>
</div>

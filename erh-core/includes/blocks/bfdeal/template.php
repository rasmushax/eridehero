<?php
/**
 * Black Friday Deal Block Template
 *
 * Seasonal deal card with product image, pricing, savings badge, and CTA.
 * Supports dynamic data from product + manual overrides.
 * Integrates with geo-aware pricing when no manual_link is set.
 *
 * Field names match legacy block for backward compatibility with existing content.
 *
 * @package ERH\Blocks
 *
 * @var array  $block      The block settings and attributes.
 * @var string $content    The block inner HTML (empty for ACF blocks).
 * @var bool   $is_preview True during AJAX preview in editor.
 * @var int    $post_id    The post ID this block is saved to.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get block data (field names match legacy block).
$product_id     = get_field( 'product' );
$description    = get_field( 'description' );
$deal_link      = get_field( 'manual_link' );
$price_now      = get_field( 'manual_price_now' );
$price_was      = get_field( 'manual_price_was' );
$override_name  = get_field( 'manual_name' );
$override_image = get_field( 'manual_image' );

// Layout: new field 'bfdeal_layout', fallback to legacy 'small' boolean.
$layout = get_field( 'bfdeal_layout' );
if ( ! $layout ) {
    $layout = get_field( 'small' ) ? 'compact' : 'full';
}

// Early return if no product and no manual name.
if ( empty( $product_id ) && empty( $override_name ) ) {
    if ( $is_preview ) {
        echo '<div class="erh-bfdeal-empty"><p>';
        esc_html_e( 'Select a product or enter a name to see the preview.', 'erh-core' );
        echo '</p></div>';
    }
    return;
}

// Resolve product data.
$product_name = '';
$review_url   = null;
$image_id     = 0;

if ( $product_id ) {
    $product_name = get_the_title( $product_id );
    $image_id     = get_post_thumbnail_id( $product_id );

    // Get review link.
    $review_data = get_field( 'review', $product_id );
    if ( ! empty( $review_data['review_post'] ) ) {
        $review_post = get_post( $review_data['review_post'] );
        if ( $review_post ) {
            $review_url = get_permalink( $review_post->ID );
        }
    }
}

// Apply manual overrides.
$name = ! empty( $override_name ) ? $override_name : $product_name;
if ( ! empty( $override_image ) ) {
    $image_id = is_array( $override_image ) ? $override_image['ID'] : $override_image;
}

// Determine link strategy: manual link vs geo-aware pricing.
$has_manual_link = ! empty( $deal_link );
$needs_hydration = false;

if ( ! $has_manual_link && $product_id && ! $is_preview ) {
    $needs_hydration = erh_product_has_pricing( $product_id );
}

// Calculate savings from manual prices.
$savings_pct = null;
if ( $price_was && $price_now && (float) $price_was > (float) $price_now ) {
    $savings_pct = round( ( ( (float) $price_was - (float) $price_now ) / (float) $price_was ) * 100 );
}

// Format manual prices.
$price_now_fmt = $price_now ? '$' . number_format( (float) $price_now, 2 ) : '';
$price_was_fmt = $price_was ? '$' . number_format( (float) $price_was ) : '';

// Build class list.
$classes = [ 'erh-bfdeal' ];
if ( 'compact' === $layout ) {
    $classes[] = 'erh-bfdeal--compact';
}
if ( ! empty( $block['className'] ) ) {
    $classes[] = $block['className'];
}

// Generate unique ID.
$block_id = 'bfdeal-' . ( $block['id'] ?? uniqid() );
if ( ! empty( $block['anchor'] ) ) {
    $block_id = $block['anchor'];
}

// Determine link attributes.
$link_attrs = 'target="_blank" rel="sponsored noopener"';

if ( $has_manual_link ) {
    // Manual link: use it directly everywhere.
    $link_href = $deal_link;
} elseif ( $needs_hydration ) {
    // No manual link but has pricing: JS will hydrate. Use # as placeholder.
    $link_href = '#';
} else {
    // No link and no pricing: link to product page or #.
    $link_href = $product_id ? get_permalink( $product_id ) : '#';
    $link_attrs = ''; // Internal link, no sponsored/blank.
}

// Data attribute for JS hydration.
$js_config = '';
if ( $needs_hydration ) {
    $js_config = wp_json_encode( [
        'productId' => $product_id,
    ] );
}
?>
<div
    id="<?php echo esc_attr( $block_id ); ?>"
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    <?php if ( $js_config ) : ?>data-bfdeal-item='<?php echo esc_attr( $js_config ); ?>'<?php endif; ?>
>
    <?php if ( $image_id ) : ?>
        <a href="<?php echo esc_url( $link_href ); ?>" <?php echo $link_attrs; ?> class="erh-bfdeal__image" data-bfdeal-link>
            <?php echo wp_get_attachment_image( $image_id, 'thumbnail', false, [
                'class'   => 'erh-bfdeal__img',
                'loading' => 'lazy',
            ] ); ?>
        </a>
    <?php endif; ?>

    <div class="erh-bfdeal__content">
        <div class="erh-bfdeal__name"><?php echo esc_html( $name ); ?></div>

        <?php if ( $price_now_fmt || $price_was_fmt || $savings_pct ) : ?>
            <a href="<?php echo esc_url( $link_href ); ?>" <?php echo $link_attrs; ?> class="erh-bfdeal__pricing" data-bfdeal-link>
                <?php if ( $price_now_fmt ) : ?>
                    <span class="erh-bfdeal__price-now"><?php echo esc_html( $price_now_fmt ); ?></span>
                <?php endif; ?>
                <?php if ( $price_was_fmt ) : ?>
                    <span class="erh-bfdeal__price-was"><?php echo esc_html( $price_was_fmt ); ?></span>
                <?php endif; ?>
                <?php if ( $savings_pct ) : ?>
                    <span class="erh-bfdeal__savings">-<?php echo esc_html( $savings_pct ); ?>%</span>
                <?php endif; ?>
            </a>
        <?php elseif ( $needs_hydration ) : ?>
            <div class="erh-bfdeal__pricing" data-bfdeal-pricing>
                <span class="skeleton skeleton-text" style="width: 80px; height: 1.2em;"></span>
            </div>
        <?php endif; ?>

        <?php if ( $description ) : ?>
            <div class="erh-bfdeal__desc"><?php echo esc_html( $description ); ?></div>
        <?php endif; ?>
    </div>

    <div class="erh-bfdeal__actions">
        <?php if ( $needs_hydration ) : ?>
            <a href="#" <?php echo $link_attrs; ?> class="btn btn-primary erh-bfdeal__cta" data-bfdeal-link>
                <span data-bfdeal-cta-text style="display: none;"><?php esc_html_e( 'Get Deal', 'erh-core' ); ?></span>
                <svg class="icon" aria-hidden="true" data-bfdeal-cta-icon style="display: none;"><use href="#icon-external-link"></use></svg>
                <svg class="spinner" viewBox="0 0 24 24" data-bfdeal-spinner><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4" stroke-linecap="round"/></svg>
            </a>
        <?php else : ?>
            <a href="<?php echo esc_url( $link_href ); ?>" <?php echo $link_attrs; ?> class="btn btn-primary erh-bfdeal__cta">
                <?php esc_html_e( 'Get Deal', 'erh-core' ); ?>
                <svg class="icon" aria-hidden="true"><use href="#icon-external-link"></use></svg>
            </a>
        <?php endif; ?>
        <?php if ( $review_url ) : ?>
            <a href="<?php echo esc_url( $review_url ); ?>" class="erh-bfdeal__review-link">
                <?php esc_html_e( 'Read Review', 'erh-core' ); ?>
            </a>
        <?php endif; ?>
    </div>
</div>

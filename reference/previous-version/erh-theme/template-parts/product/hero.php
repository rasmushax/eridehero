<?php
/**
 * Product Hero Section
 *
 * Hero display for product pages showing:
 * - Product image
 * - Brand + Name on same line
 * - Key specs summary
 * - Price with track button
 * - Content preview cards (review + video if available)
 *
 * @package ERideHero
 *
 * @var array $args {
 *     @type int         $product_id    Product ID.
 *     @type string      $product_name  Product name/title.
 *     @type string      $brand         Brand name.
 *     @type int|null    $product_image Image attachment ID.
 *     @type string      $product_type  Product type label.
 *     @type WP_Post|null $review_post  Linked review post object.
 *     @type string|null $video_url     YouTube video URL.
 *     @type array       $key_specs     Key specs for summary line.
 * }
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Extract args.
$product_id    = $args['product_id'] ?? 0;
$product_name  = $args['product_name'] ?? '';
$brand         = $args['brand'] ?? '';
$product_image = $args['product_image'] ?? null;
$product_type  = $args['product_type'] ?? '';
$review_post   = $args['review_post'] ?? null;
$video_url     = $args['video_url'] ?? '';
$key_specs     = $args['key_specs'] ?? array();

// Get obsolete status.
$obsolete_status = erh_get_product_obsolete_status( $product_id );
$is_obsolete     = $obsolete_status['is_obsolete'];

// Check if product has any pricing data.
$has_pricing = erh_product_has_pricing( $product_id );

// Get image HTML.
$image_html = '';
if ( $product_image ) {
    $image_html = wp_get_attachment_image(
        $product_image,
        'erh-product-lg',
        false,
        array( 'class' => 'product-hero-img' )
    );
} else {
    // Placeholder.
    $image_html = '<div class="product-hero-img-placeholder">' . erh_icon( 'image', 'placeholder-icon' ) . '</div>';
}

// Use product name as-is (already contains brand).
$full_title = $product_name;

// Build specs summary string.
$specs_summary = '';
if ( ! empty( $key_specs ) ) {
    $specs_summary = implode( ', ', array_filter( $key_specs ) );
}

// Get video ID if video URL exists.
$video_id = '';
if ( $video_url ) {
    $video_id = erh_extract_youtube_id( $video_url );
}

// Get review data if review exists.
$review_image_url = '';
$review_score     = null;
if ( $review_post ) {
    $review_thumb_id = get_post_thumbnail_id( $review_post->ID );
    if ( $review_thumb_id ) {
        $review_image_url = wp_get_attachment_image_url( $review_thumb_id, 'medium' );
    }
    // Get editor rating from the product (not the review post).
    $review_score = get_field( 'editor_rating', $product_id );
}
?>

<section class="product-hero">
    <div class="container">
        <div class="product-hero-inner">

            <!-- Product Image -->
            <div class="product-hero-media">
                <?php echo $image_html; ?>
            </div>

            <!-- Product Info -->
            <div class="product-hero-info">
                <h1 class="product-hero-title"><?php echo esc_html( $full_title ); ?></h1>

                <?php if ( $specs_summary ) : ?>
                    <p class="product-hero-specs"><?php echo esc_html( $specs_summary ); ?></p>
                <?php endif; ?>

                <!-- Price (links to Price Intel section) - hidden for obsolete or no-pricing products -->
                <?php if ( ! $is_obsolete && $has_pricing ) : ?>
                    <a href="#prices" class="product-hero-price-link" data-hero-price>
                        <span class="hero-price-label"><?php esc_html_e( 'from', 'erh' ); ?></span>
                        <span class="hero-price-amount"></span>
                        <span class="hero-price-stores"></span>
                        <span class="hero-price-chevron"><?php erh_the_icon( 'chevron-down' ); ?></span>
                        <!-- Skeleton loader -->
                        <span class="hero-price-skeleton">
                            <span class="skeleton"></span>
                        </span>
                    </a>
                <?php endif; ?>

                <!-- Content Preview Cards -->
                <?php if ( $review_post || $video_id ) : ?>
                    <div class="product-hero-content-cards">
                        <?php if ( $review_post ) : ?>
                            <a href="<?php echo esc_url( get_permalink( $review_post ) ); ?>" class="hero-content-card hero-content-card--review">
                                <?php if ( $review_image_url ) : ?>
                                    <img src="<?php echo esc_url( $review_image_url ); ?>" alt="" class="hero-content-card-img" loading="lazy">
                                <?php else : ?>
                                    <div class="hero-content-card-img hero-content-card-img--placeholder"></div>
                                <?php endif; ?>
                                <div class="hero-content-card-overlay"></div>
                                <?php if ( $review_score ) :
                                    $score_attr = erh_get_score_attr( (float) $review_score );
                                ?>
                                    <span class="hero-content-card-score" data-score="<?php echo esc_attr( $score_attr ); ?>">
                                        <?php echo esc_html( number_format( (float) $review_score, 1 ) ); ?>
                                    </span>
                                <?php endif; ?>
                                <span class="hero-content-card-label">
                                    <?php esc_html_e( 'Our review', 'erh' ); ?>
                                    <?php erh_the_icon( 'arrow-right' ); ?>
                                </span>
                            </a>
                        <?php endif; ?>

                        <?php if ( $video_id ) : ?>
                            <a href="<?php echo esc_url( 'https://www.youtube.com/watch?v=' . $video_id ); ?>" class="hero-content-card hero-content-card--video" target="_blank" rel="noopener">
                                <img src="<?php echo esc_url( 'https://img.youtube.com/vi/' . $video_id . '/mqdefault.jpg' ); ?>" alt="" class="hero-content-card-img" loading="lazy">
                                <div class="hero-content-card-overlay"></div>
                                <span class="hero-content-card-icon">
                                    <?php erh_the_icon( 'play' ); ?>
                                </span>
                                <span class="hero-content-card-label">
                                    <?php esc_html_e( 'Video review', 'erh' ); ?>
                                    <?php erh_the_icon( 'arrow-right' ); ?>
                                </span>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>

        </div>
    </div>
</section>

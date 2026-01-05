<?php
/**
 * Product Hero Section
 *
 * Hero display for product pages showing:
 * - Product image
 * - Name and brand
 * - Overall score badge (if available)
 * - Links to editorial review and video review (if available)
 *
 * @package ERideHero
 *
 * @var array $args {
 *     @type int         $product_id    Product ID.
 *     @type string      $product_name  Product name/title.
 *     @type string      $brand         Brand name.
 *     @type int|null    $product_image Image attachment ID.
 *     @type int|null    $overall_score Overall score 0-100.
 *     @type WP_Post|null $review_post  Linked review post object.
 *     @type string|null $video_url     YouTube video URL.
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
$overall_score = $args['overall_score'] ?? null;
$review_post   = $args['review_post'] ?? null;
$video_url     = $args['video_url'] ?? '';

// Get image URL.
$image_html = '';
if ( $product_image ) {
    $image_html = wp_get_attachment_image(
        $product_image,
        'erh-product-lg',
        false,
        array( 'class' => 'product-hero-img' )
    );
} else {
    // Placeholder
    $image_html = '<div class="product-hero-img-placeholder">' . erh_icon( 'image', 'placeholder-icon' ) . '</div>';
}

// Score label
$score_label = '';
if ( $overall_score !== null ) {
    $score_label = erh_get_score_label_100( $overall_score );
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
                <?php if ( $brand ) : ?>
                    <span class="product-hero-brand"><?php echo esc_html( $brand ); ?></span>
                <?php endif; ?>

                <h1 class="product-hero-name"><?php echo esc_html( $product_name ); ?></h1>

                <!-- Score Badge -->
                <?php if ( $overall_score !== null ) : ?>
                    <div class="product-hero-score" data-score="<?php echo esc_attr( $overall_score ); ?>">
                        <span class="product-hero-score-value"><?php echo esc_html( $overall_score ); ?></span>
                        <span class="product-hero-score-label"><?php echo esc_html( $score_label ); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Action Links -->
                <div class="product-hero-links">
                    <?php if ( $review_post ) : ?>
                        <a href="<?php echo esc_url( get_permalink( $review_post ) ); ?>" class="product-hero-link product-hero-link--review">
                            <?php erh_the_icon( 'file-text' ); ?>
                            <span>Read our full review</span>
                            <?php erh_the_icon( 'arrow-right' ); ?>
                        </a>
                    <?php endif; ?>

                    <?php if ( $video_url ) : ?>
                        <?php
                        $video_id = erh_extract_youtube_id( $video_url );
                        if ( $video_id ) :
                            ?>
                            <a href="<?php echo esc_url( 'https://www.youtube.com/watch?v=' . $video_id ); ?>" class="product-hero-link product-hero-link--video" target="_blank" rel="noopener">
                                <?php erh_the_icon( 'play-circle' ); ?>
                                <span>Watch video review</span>
                                <?php erh_the_icon( 'external-link' ); ?>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </div>
</section>

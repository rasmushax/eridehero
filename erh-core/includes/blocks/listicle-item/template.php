<?php
/**
 * Listicle Item Block Template
 *
 * Advanced product display component for buying guides with tabs:
 * - Overview: Quick take, key specs, pros/cons, body text (SSR)
 * - Specs & Tests: Full specifications and performance data (AJAX)
 * - Pricing: Geo-aware retailers, price chart, tracker (AJAX)
 *
 * @package ERH\Blocks
 *
 * @var array  $block      The block settings and attributes.
 * @var string $content    The block inner HTML (empty for ACF blocks).
 * @var bool   $is_preview True during AJAX preview in editor.
 * @var int    $post_id    The post ID this block is saved to.
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

// Get block data.
$label              = get_field('label');
$product_id         = get_field('product_relationship');
$item_image         = get_field('item_image');
$quick_take         = get_field('quick_take');
$what_i_like        = get_field('what_i_like');
$what_i_dont_like   = get_field('what_i_dont_like');
$body_text          = get_field('body_text');

// Early return if no product.
if (empty($product_id)) {
    if ($is_preview) {
        echo '<div class="listicle-item-empty">';
        echo '<p>' . esc_html__('Select a product to see the preview.', 'erh-core') . '</p>';
        echo '</div>';
    }
    return;
}

// Get product data.
$product_name  = get_the_title($product_id);
$product_url   = get_permalink($product_id);
$product_type  = erh_get_product_type($product_id);
$category_key  = erh_get_category_key($product_type);
$brand         = erh_extract_brand_from_title($product_name);

// Get review post if linked.
$review_data = get_field('review', $product_id);
$review_post = null;
$review_url  = null;

if (!empty($review_data['review_post'])) {
    $review_post = get_post($review_data['review_post']);
    if ($review_post) {
        $review_url = get_permalink($review_post->ID);
    }
}

// Get YouTube video URL.
$youtube_url = $review_data['youtube_video'] ?? '';
$video_id    = $youtube_url ? erh_extract_youtube_id($youtube_url) : '';

// Get editor rating/score.
$score = get_field('editor_rating', $product_id);

// Get image - use item_image if set, otherwise product featured image.
$image_id = $item_image ? $item_image['ID'] : get_post_thumbnail_id($product_id);

// Get key specs for summary (6 important specs).
$key_specs = erh_get_listicle_key_specs($product_id, $category_key);

// Check if product has pricing data.
$has_pricing = erh_product_has_pricing($product_id);

// Build class list.
$classes = ['listicle-item'];
if (!empty($block['className'])) {
    $classes[] = $block['className'];
}
if (!empty($block['align'])) {
    $classes[] = 'align' . $block['align'];
}

// Generate unique ID.
$block_id = 'listicle-' . ($block['id'] ?? uniqid());
if (!empty($block['anchor'])) {
    $block_id = $block['anchor'];
}

// Data for JavaScript.
$js_data = wp_json_encode([
    'productId'   => $product_id,
    'productName' => $product_name,
    'categoryKey' => $category_key,
    'hasPricing'  => $has_pricing,
]);
?>
<article
    id="<?php echo esc_attr($block_id); ?>"
    class="<?php echo esc_attr(implode(' ', $classes)); ?>"
    data-listicle-item='<?php echo esc_attr($js_data); ?>'
>
    <!-- Header: Product Name (left) + Label (right) -->
    <header class="listicle-item-header">
        <h3 class="listicle-item-title">
            <a href="<?php echo esc_url($product_url); ?>"><?php echo esc_html($product_name); ?></a>
        </h3>
        <?php if ($label) : ?>
            <span class="listicle-item-label"><?php echo esc_html($label); ?></span>
        <?php endif; ?>
    </header>

    <!-- Image Section with Overlays -->
    <div class="listicle-item-media">
        <?php if ($image_id) : ?>
            <?php echo wp_get_attachment_image($image_id, 'erh-product-lg', false, [
                'class'   => 'listicle-item-img',
                'loading' => 'lazy',
            ]); ?>
        <?php else : ?>
            <div class="listicle-item-img-placeholder">
                <?php erh_the_icon('image'); ?>
            </div>
        <?php endif; ?>

        <!-- Score Circle Overlay (top-right) -->
        <?php if ($score) : ?>
            <div class="listicle-item-score" data-score="<?php echo esc_attr($score); ?>">
                <svg class="listicle-item-score-ring" viewBox="0 0 40 40">
                    <circle cx="20" cy="20" r="17" fill="none" stroke="currentColor" stroke-width="3" opacity="0.2"/>
                    <circle
                        cx="20" cy="20" r="17"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="3"
                        stroke-dasharray="<?php echo esc_attr(($score / 10) * 106.8); ?> 106.8"
                        stroke-linecap="round"
                        transform="rotate(-90 20 20)"
                        class="listicle-item-score-progress"
                    />
                </svg>
                <span class="listicle-item-score-value"><?php echo esc_html(number_format($score, 1)); ?></span>
            </div>
        <?php endif; ?>

        <!-- Track Price Button Overlay (bottom-left) -->
        <?php if ($has_pricing && !$is_preview) : ?>
            <button
                type="button"
                class="listicle-item-track-btn"
                data-price-alert-trigger
                data-product-id="<?php echo esc_attr($product_id); ?>"
                data-product-name="<?php echo esc_attr($product_name); ?>"
            >
                <?php erh_the_icon('bell'); ?>
                <span><?php esc_html_e('Track price', 'erh-core'); ?></span>
            </button>
        <?php endif; ?>

        <!-- Video Card Overlay (bottom-right) -->
        <?php if ($video_id) : ?>
            <button class="listicle-item-video-card" data-video="<?php echo esc_attr($video_id); ?>">
                <div class="listicle-item-video-thumb">
                    <img src="https://img.youtube.com/vi/<?php echo esc_attr($video_id); ?>/mqdefault.jpg" alt="" loading="lazy">
                    <svg class="icon icon-play" aria-hidden="true"><use href="#icon-play"></use></svg>
                    <svg class="icon icon-yt" aria-hidden="true"><use href="#icon-youtube-logo"></use></svg>
                </div>
                <div class="listicle-item-video-text">
                    <span><?php esc_html_e('Watch review', 'erh-core'); ?></span>
                    <span class="listicle-item-video-yt">
                        <?php esc_html_e('on', 'erh-core'); ?>
                        <svg class="icon" aria-hidden="true"><use href="#icon-youtube-logo"></use></svg>
                        <strong>YouTube</strong>
                    </span>
                </div>
            </button>
        <?php endif; ?>
    </div>

    <!-- Price Bar (server-rendered shell, hydrated by JS after geo detection) -->
    <?php if ($has_pricing && !$is_preview) : ?>
        <div class="listicle-item-price-bar" data-price-bar>
            <div class="listicle-item-price-bar-inner">
                <div class="listicle-item-best-price" data-best-price>
                    <span class="skeleton skeleton-text" style="width: 160px;"></span>
                </div>
                <a href="#" class="btn btn-primary" data-buy-btn target="_blank" rel="sponsored noopener">
                    <span data-buy-text style="display: none;"><?php esc_html_e('Buy now', 'erh-core'); ?></span>
                    <svg class="icon" aria-hidden="true" data-buy-icon style="display: none;"><use href="#icon-external-link"></use></svg>
                    <svg class="spinner" viewBox="0 0 24 24" data-buy-spinner><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4" stroke-linecap="round"/></svg>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <div class="listicle-item-tabs" role="tablist" aria-label="<?php esc_attr_e('Product information tabs', 'erh-core'); ?>">
        <button
            type="button"
            role="tab"
            aria-selected="true"
            aria-controls="<?php echo esc_attr($block_id); ?>-overview"
            id="<?php echo esc_attr($block_id); ?>-tab-overview"
            class="listicle-item-tab is-active"
            data-tab="overview"
        >
            <?php esc_html_e('Overview', 'erh-core'); ?>
        </button>
        <button
            type="button"
            role="tab"
            aria-selected="false"
            aria-controls="<?php echo esc_attr($block_id); ?>-specs"
            id="<?php echo esc_attr($block_id); ?>-tab-specs"
            class="listicle-item-tab"
            data-tab="specs"
        >
            <?php esc_html_e('Specs & Tests', 'erh-core'); ?>
        </button>
        <?php if ($has_pricing) : ?>
            <button
                type="button"
                role="tab"
                aria-selected="false"
                aria-controls="<?php echo esc_attr($block_id); ?>-pricing"
                id="<?php echo esc_attr($block_id); ?>-tab-pricing"
                class="listicle-item-tab"
                data-tab="pricing"
            >
                <?php esc_html_e('Pricing', 'erh-core'); ?>
            </button>
        <?php endif; ?>
    </div>

    <!-- Tab Panels -->
    <div class="listicle-item-panels">

        <!-- Overview Tab (Server-Rendered) -->
        <div
            role="tabpanel"
            id="<?php echo esc_attr($block_id); ?>-overview"
            aria-labelledby="<?php echo esc_attr($block_id); ?>-tab-overview"
            class="listicle-item-panel is-active"
            data-panel="overview"
        >
            <?php $has_prev = false; ?>

            <!-- Quick Take -->
            <?php if ($quick_take) : ?>
                <div class="listicle-item-quicktake">
                    <strong><?php esc_html_e('Quick take:', 'erh-core'); ?></strong>
                    <?php echo esc_html($quick_take); ?>
                </div>
                <?php $has_prev = true; ?>
            <?php endif; ?>

            <!-- Key Specs Grid -->
            <?php if (!empty($key_specs)) : ?>
                <?php if ($has_prev) : ?><hr><?php endif; ?>
                <div class="listicle-item-key-specs">
                    <?php foreach ($key_specs as $spec) : ?>
                        <div class="listicle-item-spec">
                            <?php if (!empty($spec['icon'])) : ?>
                                <span class="listicle-item-spec-icon">
                                    <?php erh_the_icon($spec['icon']); ?>
                                </span>
                            <?php endif; ?>
                            <span class="listicle-item-spec-text">
                                <span class="listicle-item-spec-label"><?php echo esc_html($spec['label']); ?></span>
                                <span class="listicle-item-spec-value"><?php echo esc_html($spec['value']); ?></span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php $has_prev = true; ?>
            <?php endif; ?>

            <!-- Pros & Cons -->
            <?php if ($what_i_like || $what_i_dont_like) : ?>
                <?php if ($has_prev) : ?><hr><?php endif; ?>
                <div class="listicle-item-pros-cons">
                    <?php if ($what_i_like) : ?>
                        <div class="listicle-item-pros">
                            <h4 class="listicle-item-pros-title"><?php esc_html_e('What I like', 'erh-core'); ?></h4>
                            <ul class="listicle-item-pros-list">
                                <?php
                                $pros_items = preg_split('/\r\n|\r|\n/', $what_i_like);
                                foreach (array_filter(array_map('trim', $pros_items)) as $item) :
                                ?>
                                    <li>
                                        <?php erh_the_icon('check'); ?>
                                        <?php echo esc_html($item); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($what_i_dont_like) : ?>
                        <div class="listicle-item-cons">
                            <h4 class="listicle-item-cons-title"><?php esc_html_e("What I don't like", 'erh-core'); ?></h4>
                            <ul class="listicle-item-cons-list">
                                <?php
                                $cons_items = preg_split('/\r\n|\r|\n/', $what_i_dont_like);
                                foreach (array_filter(array_map('trim', $cons_items)) as $item) :
                                ?>
                                    <li>
                                        <?php erh_the_icon('x'); ?>
                                        <?php echo esc_html($item); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
                <?php $has_prev = true; ?>
            <?php endif; ?>

            <!-- Body Text -->
            <?php if ($body_text) : ?>
                <?php if ($has_prev) : ?><hr><?php endif; ?>
                <div class="listicle-item-body">
                    <?php echo wp_kses_post($body_text); ?>
                </div>
            <?php endif; ?>

            <!-- Review Link -->
            <?php if ($review_url) : ?>
                <a href="<?php echo esc_url($review_url); ?>" class="listicle-item-review-link">
                    <?php esc_html_e('Read full review', 'erh-core'); ?>
                    <?php erh_the_icon('arrow-right'); ?>
                </a>
            <?php endif; ?>
        </div>

        <!-- Specs Tab (AJAX Lazy-Loaded) -->
        <div
            role="tabpanel"
            id="<?php echo esc_attr($block_id); ?>-specs"
            aria-labelledby="<?php echo esc_attr($block_id); ?>-tab-specs"
            class="listicle-item-panel"
            data-panel="specs"
            hidden
        >
            <div class="listicle-item-loader" data-loader>
                <div class="spinner"></div>
                <span><?php esc_html_e('Loading specifications...', 'erh-core'); ?></span>
            </div>
            <div class="listicle-item-specs-content" data-specs-content></div>
        </div>

        <!-- Pricing Tab (AJAX Lazy-Loaded with Geo) -->
        <?php if ($has_pricing) : ?>
            <div
                role="tabpanel"
                id="<?php echo esc_attr($block_id); ?>-pricing"
                aria-labelledby="<?php echo esc_attr($block_id); ?>-tab-pricing"
                class="listicle-item-panel"
                data-panel="pricing"
                hidden
            >
                <div class="listicle-item-loader" data-loader>
                    <div class="spinner"></div>
                    <span><?php esc_html_e('Loading prices for your region...', 'erh-core'); ?></span>
                </div>
                <div class="listicle-item-pricing-content" data-pricing-content></div>
            </div>
        <?php endif; ?>

    </div>
</article>

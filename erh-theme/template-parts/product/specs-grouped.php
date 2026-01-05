<?php
/**
 * Product Grouped Specifications
 *
 * Full specifications table organized by category with collapsible sections.
 * Content is rendered server-side from wp_product_data cache using
 * SPEC_GROUPS config for consistency with comparison tool.
 *
 * @package ERideHero
 *
 * @var array $args {
 *     @type int    $product_id   Product ID.
 *     @type string $product_type Product type label.
 *     @type string $category_key Category key (escooter, ebike, etc.).
 * }
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Extract args.
$product_id   = $args['product_id'] ?? 0;
$product_type = $args['product_type'] ?? '';
$category_key = $args['category_key'] ?? 'escooter';

if ( ! $product_id ) {
    return;
}

// Render specs from cache.
$specs_html = erh_render_specs_from_cache( $product_id, $category_key );
?>

<section class="review-section specs-grouped" id="full-specs" data-specs-grouped>
    <h2 class="review-section-title">Full Specifications</h2>

    <div class="specs-grouped-categories">
        <?php echo $specs_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped in function. ?>
    </div>
</section>

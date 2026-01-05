<?php
/**
 * Product Specifications
 *
 * Simple flat table layout with category headers.
 * Uses same spec groupings as the compare tool.
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

<section class="review-section" id="full-specs">
    <h2 class="review-section-title">Specifications</h2>
    <?php echo $specs_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped in function. ?>
</section>

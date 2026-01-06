<?php
/**
 * Product Specifications
 *
 * SEO-friendly spec groups with heading + bordered box per group.
 * Uses logical groupings matching how users search for specs.
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

// Render specs using SEO-friendly grouped layout.
$specs_html = erh_render_product_specs( $product_id, $category_key );
?>

<section class="content-section" id="full-specs">
    <h2 class="section-title">Specifications</h2>
    <?php echo $specs_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped in function. ?>
</section>

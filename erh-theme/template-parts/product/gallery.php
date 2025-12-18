<?php
/**
 * Product Gallery (Wrapper)
 *
 * Wrapper for the gallery component when used on single product pages.
 * Uses the product's featured image and gallery.
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$product_id = get_the_ID();

// Get product gallery from ACF (if exists)
$gallery = get_field( 'gallery', $product_id );

// Use the reusable gallery component
get_template_part( 'template-parts/components/gallery', null, array(
    'post_id'    => $product_id,
    'gallery'    => $gallery,
    'product_id' => $product_id, // Same product for video lookup
) );

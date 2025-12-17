<?php
declare(strict_types=1);

/**
 * Affiliate Link Block Template.
 *
 * @package HousefreshTools
 * @param   array $block The block settings and attributes.
 * @param   string $content The block inner HTML (empty).
 * @param   bool $is_preview True during backend preview render.
 * @param   int $post_id The post ID this block is rendered on.
 * @param   array $context The context provided to the block by the post or its parent block.
 */

$selected_product_object = get_field('selected_product');
$product_id = null;
$product_title = '';

if ($selected_product_object instanceof WP_Post) {
	$product_id = $selected_product_object->ID;
	$product_title = $selected_product_object->post_title;
} elseif (is_numeric($selected_product_object)) {
	$product_id = (int) $selected_product_object;
	$product_post = get_post($product_id);
	if ($product_post) {
		$product_title = $product_post->post_title;
	}
}

$block_id = $block['id'] ?? 'hft-affiliate-links-' . uniqid();

// Pre-check: See if ANY links exist in the DB for this product_id
$has_any_links_in_db = false;
if ( !empty($product_id) ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hft_tracked_links';
    $link_exists_query_result = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT EXISTS (SELECT 1 FROM {$table_name} WHERE product_post_id = %d)",
            $product_id
        )
    );
    $has_any_links_in_db = (bool) $link_exists_query_result; // Cast to boolean (1 becomes true, 0 or null becomes false)
}

if ($is_preview) {
	// Editor Preview Logic
	echo '<div class="hft-affiliate-links-editor-preview" style="border:1px dashed #ccc; padding:15px; text-align:center;">';
	if (empty($product_id)) {
		echo '<div class="acf-notice -warning" style="margin:0;">' . esc_html__('Please select a product to link.', 'housefresh-tools') . '</div>';
	} elseif (!$has_any_links_in_db) {
        echo '<p style="margin:0; font-size:14px; color:#333;">';
        echo '<strong>' . esc_html__('Affiliate Links For:', 'housefresh-tools') . '</strong> ' . esc_html($product_title);
        echo '</p>';
        echo '<p style="margin:5px 0 0; font-size:12px; color:#c00;">'; // Warning color
        echo esc_html__('This product currently has NO affiliate links configured in the database. The block will not display on the frontend.', 'housefresh-tools');
        echo '</p>';
    } else {
		echo '<p style="margin:0; font-size:14px; color:#333;">';
		echo '<strong>' . esc_html__('Affiliate Links For:', 'housefresh-tools') . '</strong> ' . esc_html($product_title);
		echo '</p>';
		echo '<p style="margin:5px 0 0; font-size:12px; color:#777;">';
		echo esc_html__('GEO-targeted links (e.g., Amazon, Levoit) will be displayed here on the live site.', 'housefresh-tools');
		echo '</p>';
	}
	echo '</div>';
} else {
	// Frontend Logic
	if (empty($product_id) || !$has_any_links_in_db) {
        // If no product ID is set, or if the product has no links in the DB at all, render nothing on the frontend.
		return;
	}
	
	// Add SVG icons inline before the container (only on frontend)
	static $icons_added = false;
	if (!$icons_added) {
		?>
		<svg style="position: absolute; width: 0; height: 0; overflow: hidden;" aria-hidden="true">
			<defs>
				<symbol id="hft-amazon-icon" viewBox="0 0 1024 1024">
					<path fill="currentColor" d="M485 467.5c-11.6 4.9-20.9 12.2-27.8 22c-6.9 9.8-10.4 21.6-10.4 35.5c0 17.8 7.5 31.5 22.4 41.2c14.1 9.1 28.9 11.4 44.4 6.8c17.9-5.2 30-17.9 36.4-38.1c3-9.3 4.5-19.7 4.5-31.3v-50.2c-12.6.4-24.4 1.6-35.5 3.7c-11.1 2.1-22.4 5.6-34 10.4zM512 64C264.6 64 64 264.6 64 512s200.6 448 448 448s448-200.6 448-448S759.4 64 512 64zm35.8 262.7c-7.2-10.9-20.1-16.4-38.7-16.4c-1.3 0-3 .1-5.3.3c-2.2.2-6.6 1.5-12.9 3.7a79.4 79.4 0 0 0-17.9 9.1c-5.5 3.8-11.5 10-18 18.4c-6.4 8.5-11.5 18.4-15.3 29.8l-94-8.4c0-12.4 2.4-24.7 7-36.9c4.7-12.2 11.8-23.9 21.4-35c9.6-11.2 21.1-21 34.5-29.4c13.4-8.5 29.6-15.2 48.4-20.3c18.9-5.1 39.1-7.6 60.9-7.6c21.3 0 40.6 2.6 57.8 7.7c17.2 5.2 31.1 11.5 41.4 19.1a117 117 0 0 1 25.9 25.7c6.9 9.6 11.7 18.5 14.4 26.7c2.7 8.2 4 15.7 4 22.8v182.5c0 6.4 1.4 13 4.3 19.8c2.9 6.8 6.3 12.8 10.2 18c3.9 5.2 7.9 9.9 12 14.3c4.1 4.3 7.6 7.7 10.6 9.9l4.1 3.4l-72.5 69.4c-8.5-7.7-16.9-15.4-25.2-23.4c-8.3-8-14.5-14-18.5-18.1l-6.1-6.2c-2.4-2.3-5-5.7-8-10.2c-8.1 12.2-18.5 22.8-31.1 31.8c-12.7 9-26.3 15.6-40.7 19.7c-14.5 4.1-29.4 6.5-44.7 7.1c-15.3.6-30-1.5-43.9-6.5c-13.9-5-26.5-11.7-37.6-20.3c-11.1-8.6-19.9-20.2-26.5-35c-6.6-14.8-9.9-31.5-9.9-50.4c0-17.4 3-33.3 8.9-47.7c6-14.5 13.6-26.5 23-36.1c9.4-9.6 20.7-18.2 34-25.7s26.4-13.4 39.2-17.7c12.8-4.2 26.6-7.8 41.5-10.7c14.9-2.9 27.6-4.8 38.2-5.7c10.6-.9 21.2-1.6 31.8-2v-39.4c0-13.5-2.3-23.5-6.7-30.1zm180.5 379.6c-2.8 3.3-7.5 7.8-14.1 13.5s-16.8 12.7-30.5 21.1c-13.7 8.4-28.8 16-45 22.9c-16.3 6.9-36.3 12.9-60.1 18c-23.7 5.1-48.2 7.6-73.3 7.6c-25.4 0-50.7-3.2-76.1-9.6c-25.4-6.4-47.6-14.3-66.8-23.7c-19.1-9.4-37.6-20.2-55.1-32.2c-17.6-12.1-31.7-22.9-42.4-32.5c-10.6-9.6-19.6-18.7-26.8-27.1c-1.7-1.9-2.8-3.6-3.2-5.1c-.4-1.5-.3-2.8.3-3.7c.6-.9 1.5-1.6 2.6-2.2a7.42 7.42 0 0 1 7.4.8c40.9 24.2 72.9 41.3 95.9 51.4c82.9 36.4 168 45.7 255.3 27.9c40.5-8.3 82.1-22.2 124.9-41.8c3.2-1.2 6-1.5 8.3-.9c2.3.6 3.5 2.4 3.5 5.4c0 2.8-1.6 6.3-4.8 10.2zm59.9-29c-1.8 11.1-4.9 21.6-9.1 31.8c-7.2 17.1-16.3 30-27.1 38.4c-3.6 2.9-6.4 3.8-8.3 2.8c-1.9-1-1.9-3.5 0-7.4c4.5-9.3 9.2-21.8 14.2-37.7c5-15.8 5.7-26 2.1-30.5c-1.1-1.5-2.7-2.6-5-3.6c-2.2-.9-5.1-1.5-8.6-1.9s-6.7-.6-9.4-.8c-2.8-.2-6.5-.2-11.2 0c-4.7.2-8 .4-10.1.6a874.4 874.4 0 0 1-17.1 1.5c-1.3.2-2.7.4-4.1.5c-1.5.1-2.7.2-3.5.3l-2.7.3c-1 .1-1.7.2-2.2.2h-3.2l-1-.2l-.6-.5l-.5-.9c-1.3-3.3 3.7-7.4 15-12.4s22.3-8.1 32.9-9.3c9.8-1.5 21.3-1.5 34.5-.3s21.3 3.7 24.3 7.4c2.3 3.5 2.5 10.7.7 21.7z"></path>
				</symbol>
				<symbol id="hft-price-tag-icon" viewBox="0 0 640 512">
					<path fill="currentColor" d="M497.941 225.941L286.059 14.059A48 48 0 0 0 252.118 0H48C21.49 0 0 21.49 0 48v204.118a48 48 0 0 0 14.059 33.941l211.882 211.882c18.744 18.745 49.136 18.746 67.882 0l204.118-204.118c18.745-18.745 18.745-49.137 0-67.882zM112 160c-26.51 0-48-21.49-48-48s21.49-48 48-48 48 21.49 48 48-21.49 48-48 48zm513.941 133.823L421.823 497.941c-18.745 18.745-49.137 18.745-67.882 0l-.36-.36L527.64 323.522c16.999-16.999 26.36-39.6 26.36-63.64s-9.362-46.641-26.36-63.64L331.397 0h48.721a48 48 0 0 1 33.941 14.059l211.882 211.882c18.745 18.745 18.745 49.137 0 67.882z"></path>
				</symbol>
			</defs>
		</svg>
		<?php
		$icons_added = true;
	}
	
	// If we are on the frontend AND a product is selected AND it has links in the DB, output the container for JS.
	?>
	<div id="<?php echo esc_attr($block_id); ?>" 
		 class="hft-affiliate-links-container is-loading" 
		 data-hft-product-id="<?php echo esc_attr((string)$product_id); ?>"
		 data-hft-block-id="<?php echo esc_attr($block_id); ?>">
		<?php // NO SPINNER DIV HERE - CSS will handle initial invisible state via .is-loading and opacity ?>
		<?php // Affiliate link buttons will be dynamically inserted here by affiliate-link-block.js ?>
	</div>
	<?php
} 
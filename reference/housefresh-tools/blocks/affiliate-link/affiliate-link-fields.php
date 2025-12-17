<?php
/**
 * ACF Field Group for Affiliate Link Block.
 *
 * Supports configurable product post types via the hft_product_post_types filter.
 */

if( function_exists('acf_add_local_field_group') ):

// Get product post types (supports configurable post types)
$product_post_types = class_exists( 'HFT_Post_Type_Helper' )
	? HFT_Post_Type_Helper::get_product_post_types()
	: ['hf_product'];

acf_add_local_field_group(array(
	'key' => 'group_hft_affiliate_link_block',
	'title' => 'Affiliate Link (HFT) Settings',
	'fields' => array(
		array(
			'key' => 'field_hft_afflink_selected_product',
			'label' => 'Select Product',
			'name' => 'selected_product',
			'type' => 'post_object',
			'instructions' => 'Select the product to link to.',
			'required' => 1,
			'conditional_logic' => 0,
			'post_type' => $product_post_types,
			'taxonomy' => '',
			'allow_null' => 0,
			'multiple' => 0,
			'return_format' => 'object', // Return WP_Post object
			'ui' => 1,
		),
	),
	'location' => array(
		array(
			array(
				'param' => 'block',
				'operator' => '==',
				'value' => 'acf/hft-affiliate-link', // Match the block name from block.json
			),
		),
	),
	'menu_order' => 0,
	'position' => 'normal',
	'style' => 'default',
	'label_placement' => 'top',
	'instruction_placement' => 'label',
	'hide_on_screen' => '',
	'active' => true,
	'description' => '',
));

endif; 
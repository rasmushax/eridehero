<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HFT_ACF_Field_Groups' ) ) {
	/**
	 * Class HFT_ACF_Field_Groups.
	 *
	 * Handles the registration of ACF Field Groups for Products and Posts.
	 * Supports configurable post types via the hft_product_post_types filter.
	 */
	class HFT_ACF_Field_Groups {

		public function __construct() {
			// Hook for registering field groups
			add_action('acf/init', [ $this, 'register_field_groups' ]);
		}

		/**
		 * Get product post types.
		 *
		 * @return array Array of product post type slugs.
		 */
		private function get_product_post_types(): array {
			return class_exists( 'HFT_Post_Type_Helper' )
				? HFT_Post_Type_Helper::get_product_post_types()
				: ['hf_product'];
		}

		/**
		 * Register ACF Field Groups.
		 */
		public function register_field_groups(): void {
			// Check if ACF function exists
			if ( ! function_exists('acf_add_local_field_group') ) {
				return;
			}

			// Register HouseFresh Score field for Products
			$this->register_product_score_field();

			// Register Product Selection field for Posts
			$this->register_post_product_field();
		}

		/**
		 * Register HouseFresh Score field for product post types.
		 */
		private function register_product_score_field(): void {
			// Build location rules for all product post types
			$location_rules = [];
			foreach ( $this->get_product_post_types() as $post_type ) {
				$location_rules[] = array(
					array(
						'param' => 'post_type',
						'operator' => '==',
						'value' => $post_type,
					),
				);
			}

			acf_add_local_field_group(array(
				'key' => 'group_hft_product_score',
				'title' => 'HouseFresh Product Details',
				'fields' => array(
					array(
						'key' => 'field_hft_housefresh_score',
						'label' => 'HouseFresh Score',
						'name' => 'housefresh_score',
						'type' => 'number',
						'instructions' => 'Enter the HouseFresh score for this product (1-10).',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array(
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'default_value' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'min' => 1,
						'max' => 10,
						'step' => 0.001,
					),
				),
				'location' => $location_rules,
				'menu_order' => 0,
				'position' => 'normal',
				'style' => 'default',
				'label_placement' => 'top',
				'instruction_placement' => 'label',
				'hide_on_screen' => '',
				'active' => true,
				'description' => 'Product-specific HouseFresh data',
			));
		}

		/**
		 * Register Product Selection field for regular posts.
		 */
		private function register_post_product_field(): void {
			// Get all product post types for the post_object field
			$product_post_types = $this->get_product_post_types();

			acf_add_local_field_group(array(
				'key' => 'group_hft_post_product_selection',
				'title' => 'HouseFresh Product Selection',
				'fields' => array(
					array(
						'key' => 'field_hft_selected_product',
						'label' => 'Select Product',
						'name' => 'hft_selected_product',
						'type' => 'post_object',
						'instructions' => 'Select a HouseFresh product to associate with this post.',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array(
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'post_type' => $product_post_types,
						'taxonomy' => '',
						'allow_null' => 1,
						'multiple' => 0,
						'return_format' => 'object',
						'ui' => 1,
					),
				),
				'location' => array(
					array(
						array(
							'param' => 'post_type',
							'operator' => '==',
							'value' => 'post',
						),
					),
				),
				'menu_order' => 0,
				'position' => 'side',
				'style' => 'default',
				'label_placement' => 'top',
				'instruction_placement' => 'label',
				'hide_on_screen' => '',
				'active' => true,
				'description' => 'Associate a HouseFresh product with this post',
			));
		}
	}
}
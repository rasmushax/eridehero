<?php
declare(strict_types=1);

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HFT_CPT' ) ) {
	/**
	 * Class HFT_CPT.
	 *
	 * Responsible for registering Custom Post Types for the plugin.
	 */
	class HFT_CPT {

		/**
		 * Constructor.
		 */
		public function __construct() {
			// Actions can be added here or called from the loader.
		}

		/**
		 * Register the Product Custom Post Type.
		 *
		 * Registration can be disabled via the 'hft_register_product_cpt' filter.
		 * This is useful when using an external CPT via 'hft_product_post_types'.
		 *
		 * Example to disable:
		 * add_filter('hft_register_product_cpt', '__return_false');
		 */
		public function register_product_cpt(): void {
			/**
			 * Filter whether to register the built-in hf_product CPT.
			 *
			 * Set to false when using an external CPT via 'hft_product_post_types'.
			 *
			 * @param bool $register Whether to register the CPT. Default true.
			 */
			if ( ! apply_filters( 'hft_register_product_cpt', true ) ) {
				return;
			}

			$labels = [
				'name'                  => _x( 'Products', 'Post type general name', 'housefresh-tools' ),
				'singular_name'         => _x( 'Product', 'Post type singular name', 'housefresh-tools' ),
				'menu_name'             => _x( 'Products', 'Admin Menu text', 'housefresh-tools' ),
				'name_admin_bar'        => _x( 'Product', 'Add New on Toolbar', 'housefresh-tools' ),
				'add_new'               => __( 'Add New', 'housefresh-tools' ),
				'add_new_item'          => __( 'Add New Product', 'housefresh-tools' ),
				'new_item'              => __( 'New Product', 'housefresh-tools' ),
				'edit_item'             => __( 'Edit Product', 'housefresh-tools' ),
				'view_item'             => __( 'View Product', 'housefresh-tools' ),
				'all_items'             => __( 'All Products', 'housefresh-tools' ),
				'search_items'          => __( 'Search Products', 'housefresh-tools' ),
				'parent_item_colon'     => __( 'Parent Products:', 'housefresh-tools' ),
				'not_found'             => __( 'No products found.', 'housefresh-tools' ),
				'not_found_in_trash'    => __( 'No products found in Trash.', 'housefresh-tools' ),
				'featured_image'        => _x( 'Product Image', 'Overrides the "Featured Image" phrase for this post type.', 'housefresh-tools' ),
				'set_featured_image'    => _x( 'Set product image', 'Overrides the "Set featured image" phrase for this post type.', 'housefresh-tools' ),
				'remove_featured_image' => _x( 'Remove product image', 'Overrides the "Remove featured image" phrase for this post type.', 'housefresh-tools' ),
				'use_featured_image'    => _x( 'Use as product image', 'Overrides the "Use as featured image" phrase for this post type.', 'housefresh-tools' ),
				'archives'              => _x( 'Product archives', 'The post type archive label used in nav menus. Default "Post Archives".', 'housefresh-tools' ),
				'insert_into_item'      => _x( 'Insert into product', 'Overrides the "Insert into post"/"Insert into page" phrase (used when inserting media into a post).', 'housefresh-tools' ),
				'uploaded_to_this_item' => _x( 'Uploaded to this product', 'Overrides the "Uploaded to this post"/"Uploaded to this page" phrase (used when viewing media attached to a post).', 'housefresh-tools' ),
				'filter_items_list'     => _x( 'Filter products list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list".', 'housefresh-tools' ),
				'items_list_navigation' => _x( 'Products list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation".', 'housefresh-tools' ),
				'items_list'            => _x( 'Products list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list".', 'housefresh-tools' ),
			];

			$args = [
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => 'housefresh-tools-main-menu', // Placeholder, will be a top-level menu.
				'query_var'          => true,
				'rewrite'            => false,
				'capability_type'    => 'post', // If using custom capabilities, this would change.
				'has_archive'        => false,
				'hierarchical'       => false,
				'menu_position'      => null, // Will be controlled by add_menu_page for top-level.
				'menu_icon'          => 'dashicons-cart',
				'supports'           => [ 'title' ],
				'show_in_rest'       => false, // Set to true if you need Gutenberg support / REST API access.
			];

			register_post_type( 'hf_product', $args );
		}

		/**
		 * Adds the top-level admin menu page for Housefresh Tools.
		 * The Product CPT will be a submenu of this.
		 */
		public function add_admin_menu(): void {
			add_menu_page(
				__( 'Housefresh Tools', 'housefresh-tools' ),
				__( 'Housefresh Tools', 'housefresh-tools' ),
				'manage_options', // Capability required to see this menu.
				'housefresh-tools-main-menu',
				[ $this, 'redirect_to_first_submenu' ], // Redirect to first submenu
				'dashicons-admin-tools', // Icon for the top-level menu.
				20 // Position in the menu order.
			);
		}

		/**
		 * Redirects to the first submenu item (Products).
		 * This ensures proper URL generation for all submenu items.
		 * Uses the configured primary post type from HFT_Post_Type_Helper.
		 */
		public function redirect_to_first_submenu(): void {
			$post_type = class_exists( 'HFT_Post_Type_Helper' )
				? HFT_Post_Type_Helper::get_primary_post_type()
				: 'hf_product';
			$redirect_url = admin_url( 'edit.php?post_type=' . $post_type );

			// Use JavaScript redirect if headers already sent
			if ( headers_sent() ) {
				echo '<script type="text/javascript">window.location.href = "' . esc_js( $redirect_url ) . '";</script>';
				echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_url( $redirect_url ) . '"></noscript>';
				exit;
			}

			wp_safe_redirect( $redirect_url );
			exit;
		}
	}
} 
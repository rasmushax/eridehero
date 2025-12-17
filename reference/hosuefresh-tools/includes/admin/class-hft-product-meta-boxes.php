<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HFT_Product_Meta_Boxes' ) ) {
	/**
	 * Class HFT_Product_Meta_Boxes.
	 *
	 * Manages custom meta boxes for product CPTs.
	 * Supports configurable post types via the hft_product_post_types filter.
	 */
	class HFT_Product_Meta_Boxes {

		/**
		 * Constructor. Hooks into WordPress.
		 */
		public function __construct() {
			add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
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
		 * Check if a post type is a product post type.
		 *
		 * @param string $post_type The post type to check.
		 * @return bool True if it's a product post type.
		 */
		private function is_product_post_type( string $post_type ): bool {
			return class_exists( 'HFT_Post_Type_Helper' )
				? HFT_Post_Type_Helper::is_product_post_type( $post_type )
				: ( 'hf_product' === $post_type );
		}

		/**
		 * Adds the meta boxes for product CPTs.
		 *
		 * @param string $post_type The current post type.
		 */
		public function add_meta_boxes( string $post_type ): void {
			if ( $this->is_product_post_type( $post_type ) ) {
				add_meta_box(
					'hft_price_history_meta_box',          // ID
					__( 'Product Price History', 'housefresh-tools' ), // Title
					[ $this, 'render_price_history_meta_box' ], // Callback
					$post_type,                            // Screen (post type)
					'normal',                              // Context (normal, side, advanced)
					'low'                                 // Priority changed to low
				);
			}
		}

		/**
		 * Renders the content of the price history meta box.
		 *
		 * @param WP_Post $post The current post object.
		 */
		public function render_price_history_meta_box( WP_Post $post ): void {
			?>
			<div id="hft-price-history-container">
				<p><?php esc_html_e( 'Loading price history data...', 'housefresh-tools' ); ?></p>
				<div id="hft-price-history-summary">
					<!-- Summary table will be populated by JavaScript -->
				</div>
			</div>
			<?php
		}

		/**
		 * Enqueues scripts and styles for the price history meta box.
		 *
		 * @param string $hook_suffix The current admin page hook.
		 */
		public function enqueue_assets( string $hook_suffix ): void {
			global $post_type, $post;

			// Only load on the product edit screen for our CPT.
			if ( ( 'post.php' === $hook_suffix || 'post-new.php' === $hook_suffix ) && isset( $post->ID ) && $this->is_product_post_type( $post_type ) ) {
				
				// Enqueue our custom JavaScript file for the price history
				wp_enqueue_script(
					'hft-product-price-history',
					HFT_PLUGIN_URL . 'admin/js/hft-product-price-history.js',
					[ 'jquery', 'wp-api-fetch' ], // Dependencies updated
					HFT_VERSION,
					true
				);

				// Localize script with necessary data
				$product_id = $post->ID;
				$rest_url_base = rest_url( 'housefresh-tools/v1/product/' . $product_id . '/price-history' );
				
				wp_localize_script(
					'hft-product-price-history',
					'hftPriceHistoryData',
					[
						'productId'  => $product_id,
						'restApiUrl' => $rest_url_base,
						'nonce'      => wp_create_nonce( 'wp_rest' ), // Nonce for REST API
						'labels'     => [ // For i18n in JS if needed
							'loading' => __( 'Loading...', 'housefresh-tools' ),
							'error'   => __( 'Error loading price history.', 'housefresh-tools' ),
							'noData'  => __( 'No price history data available for this product.', 'housefresh-tools' ),
							'price'   => __( 'Price', 'housefresh-tools' ), // Kept for potential future use or if summary needs it
							'date'    => __( 'Date', 'housefresh-tools' ),   // Kept for potential future use or if summary needs it
						]
					]
				);
			}
		}
	}
}
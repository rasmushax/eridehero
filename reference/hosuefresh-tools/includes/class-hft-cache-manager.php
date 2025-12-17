<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HFT_Cache_Manager' ) ) {
	/**
	 * Class HFT_Cache_Manager.
	 *
	 * Manages cache invalidation for the plugin.
	 */
	class HFT_Cache_Manager {
		
		/**
		 * Cache group for frontend caches.
		 */
		private const CACHE_GROUP = 'hft_frontend';
		
		/**
		 * Constructor.
		 */
		public function __construct() {
			// Hook into product updates for all configured product post types
			$this->register_save_post_hooks();

			// Hook into price updates (when scraping completes)
			add_action( 'hft_price_updated', [ $this, 'invalidate_price_related_caches' ], 10, 2 );

			// Hook into scraper config updates
			add_action( 'hft_scraper_updated', [ $this, 'invalidate_scraper_caches' ] );

			// Add admin bar menu for cache clearing
			add_action( 'admin_bar_menu', [ $this, 'add_cache_clear_menu' ], 999 );

			// Handle cache clear action
			add_action( 'admin_init', [ $this, 'handle_cache_clear' ] );
		}

		/**
		 * Register save_post hooks for all configured product post types.
		 * Deferred to 'init' to allow other plugins to register hft_product_post_types filter.
		 */
		private function register_save_post_hooks(): void {
			add_action( 'init', function() {
				if ( class_exists( 'HFT_Post_Type_Helper' ) ) {
					foreach ( HFT_Post_Type_Helper::get_product_post_types() as $post_type ) {
						add_action( 'save_post_' . $post_type, [ $this, 'invalidate_product_caches' ], 10, 3 );
					}
				} else {
					// Fallback for backward compatibility
					add_action( 'save_post_hf_product', [ $this, 'invalidate_product_caches' ], 10, 3 );
				}
			} );
		}
		
		/**
		 * Invalidate caches when a product is saved.
		 *
		 * @param int $post_id Post ID.
		 * @param WP_Post $post Post object.
		 * @param bool $update Whether this is an existing post being updated.
		 */
		public function invalidate_product_caches( int $post_id, WP_Post $post, bool $update ): void {
			// Clear affiliate link caches for all GEOs
			$geos = [ 'US', 'UK', 'CA', 'AU', 'DE', 'FR', 'ES', 'IT' ];
			foreach ( $geos as $geo ) {
				$cache_key = 'hft_aff_links_' . $post_id . '_' . $geo;
				delete_transient( $cache_key );
				wp_cache_delete( $cache_key, self::CACHE_GROUP );
				
				// Clear price history cache
				$cache_key = 'hft_price_chart_' . $post_id . '_' . $geo;
				delete_transient( $cache_key );
				wp_cache_delete( $cache_key, self::CACHE_GROUP );
			}
			
			// Clear schema caches for posts that use this product
			$this->clear_schema_caches_for_product( $post_id );
		}
		
		/**
		 * Invalidate caches when price is updated.
		 *
		 * @param int $tracked_link_id Tracked link ID.
		 * @param int $product_id Product ID.
		 */
		public function invalidate_price_related_caches( int $tracked_link_id, int $product_id ): void {
			// Clear price history caches
			$geos = [ 'US', 'UK', 'CA', 'AU', 'DE', 'FR', 'ES', 'IT' ];
			foreach ( $geos as $geo ) {
				$cache_key = 'hft_price_chart_' . $product_id . '_' . $geo;
				delete_transient( $cache_key );
				wp_cache_delete( $cache_key, self::CACHE_GROUP );
			}
		}
		
		/**
		 * Invalidate scraper configuration caches.
		 */
		public function invalidate_scraper_caches(): void {
			$cache_key = 'hft_scraper_configs_active';
			delete_transient( $cache_key );
			wp_cache_delete( $cache_key, self::CACHE_GROUP );
		}
		
		/**
		 * Clear schema caches for posts using a specific product.
		 *
		 * @param int $product_id Product ID.
		 */
		private function clear_schema_caches_for_product( int $product_id ): void {
			// Find posts that use this product
			$args = [
				'post_type' => 'post',
				'posts_per_page' => -1,
				'meta_query' => [
					[
						'key' => 'hft_selected_product',
						'value' => $product_id,
						'compare' => '='
					]
				],
				'fields' => 'ids'
			];
			
			$posts = get_posts( $args );
			
			foreach ( $posts as $post_id ) {
				// Pattern match and delete schema caches
				global $wpdb;
				$pattern = $wpdb->esc_like( 'hft_schema_' . $post_id . '_' ) . '%';
				$wpdb->query( 
					$wpdb->prepare( 
						"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 
						'_transient_' . $pattern 
					) 
				);
			}
		}
		
		/**
		 * Add cache clear option to admin bar.
		 *
		 * @param WP_Admin_Bar $wp_admin_bar Admin bar object.
		 */
		public function add_cache_clear_menu( WP_Admin_Bar $wp_admin_bar ): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			
			$wp_admin_bar->add_menu( [
				'id' => 'hft-clear-cache',
				'title' => __( 'Clear HFT Cache', 'housefresh-tools' ),
				'href' => wp_nonce_url( admin_url( 'admin.php?action=hft_clear_cache' ), 'hft_clear_cache' ),
				'meta' => [
					'title' => __( 'Clear all Housefresh Tools caches', 'housefresh-tools' )
				]
			] );
		}
		
		/**
		 * Handle cache clear action.
		 */
		public function handle_cache_clear(): void {
			if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'hft_clear_cache' ) {
				return;
			}
			
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'You do not have permission to clear caches.', 'housefresh-tools' ) );
			}
			
			check_admin_referer( 'hft_clear_cache' );
			
			// Clear all HFT transients
			global $wpdb;
			$wpdb->query( 
				"DELETE FROM {$wpdb->options} 
				WHERE option_name LIKE '_transient_hft_%' 
				OR option_name LIKE '_transient_timeout_hft_%'" 
			);
			
			// Clear object cache group
			wp_cache_flush_group( self::CACHE_GROUP );
			
			// Redirect back with success message
			wp_redirect( add_query_arg( 'hft_cache_cleared', '1', wp_get_referer() ) );
			exit;
		}
		
		/**
		 * Clear all frontend caches.
		 * Can be called programmatically.
		 */
		public static function clear_all_caches(): void {
			global $wpdb;
			
			// Clear all HFT transients
			$wpdb->query( 
				"DELETE FROM {$wpdb->options} 
				WHERE option_name LIKE '_transient_hft_%' 
				OR option_name LIKE '_transient_timeout_hft_%'" 
			);
			
			// Clear object cache group if function exists
			if ( function_exists( 'wp_cache_flush_group' ) ) {
				wp_cache_flush_group( self::CACHE_GROUP );
			}
		}
	}
}
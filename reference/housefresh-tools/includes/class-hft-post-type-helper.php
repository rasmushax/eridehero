<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HFT_Post_Type_Helper' ) ) {
	/**
	 * Class HFT_Post_Type_Helper.
	 *
	 * Provides helper methods for configurable product post types.
	 * Allows the plugin to work with different CPTs on different installations
	 * while maintaining backward compatibility with the default 'hf_product' CPT.
	 */
	class HFT_Post_Type_Helper {

		/**
		 * Default post types for products.
		 *
		 * @var array
		 */
		private const DEFAULT_POST_TYPES = ['hf_product'];

		/**
		 * Cached post types to avoid repeated filter calls.
		 *
		 * @var array|null
		 */
		private static ?array $cached_post_types = null;

		/**
		 * Get the configured product post types.
		 *
		 * This method returns an array of post type slugs that should be treated
		 * as products by the HFT plugin. By default, this is ['hf_product'].
		 *
		 * Other plugins can modify this using the 'hft_product_post_types' filter.
		 * The filter should be registered early (e.g., in the main plugin file or
		 * on the 'plugins_loaded' hook) to ensure it takes effect.
		 *
		 * Example usage in another plugin:
		 * add_filter('hft_product_post_types', function() {
		 *     return ['products']; // Use 'products' CPT instead
		 * });
		 *
		 * @return array Array of post type slugs.
		 */
		public static function get_product_post_types(): array {
			// Only use cache after plugins_loaded has fired
			// This ensures all plugins have had a chance to register their filters
			$can_cache = did_action( 'plugins_loaded' ) > 0;

			if ( $can_cache && null !== self::$cached_post_types ) {
				return self::$cached_post_types;
			}

			/**
			 * Filter the product post types that HFT should work with.
			 *
			 * @param array $post_types Array of post type slugs. Default: ['hf_product'].
			 */
			$post_types = apply_filters( 'hft_product_post_types', self::DEFAULT_POST_TYPES );

			// Ensure we always have an array
			if ( ! is_array( $post_types ) ) {
				$post_types = [ $post_types ];
			}

			// Filter out empty values and sanitize
			$post_types = array_filter( array_map( 'sanitize_key', $post_types ) );

			// Fallback to default if empty
			if ( empty( $post_types ) ) {
				$post_types = self::DEFAULT_POST_TYPES;
			}

			// Only cache after plugins_loaded
			if ( $can_cache ) {
				self::$cached_post_types = $post_types;
			}

			return $post_types;
		}

		/**
		 * Get the primary product post type.
		 *
		 * Returns the first post type in the configured list.
		 * Useful when only one post type is expected.
		 *
		 * @return string The primary post type slug.
		 */
		public static function get_primary_post_type(): string {
			$post_types = self::get_product_post_types();
			return reset( $post_types );
		}

		/**
		 * Check if a given post type is a product post type.
		 *
		 * @param string|null $post_type The post type to check.
		 * @return bool True if the post type is a product post type.
		 */
		public static function is_product_post_type( ?string $post_type ): bool {
			if ( null === $post_type || '' === $post_type ) {
				return false;
			}
			return in_array( $post_type, self::get_product_post_types(), true );
		}

		/**
		 * Check if a given post is a product.
		 *
		 * @param int|WP_Post $post Post ID or WP_Post object.
		 * @return bool True if the post is a product.
		 */
		public static function is_product( $post ): bool {
			$post_obj = get_post( $post );
			if ( ! $post_obj ) {
				return false;
			}
			return self::is_product_post_type( $post_obj->post_type );
		}

		/**
		 * Get the save_post action hooks for all product post types.
		 *
		 * Returns an array of hook names like ['save_post_hf_product', 'save_post_products'].
		 *
		 * @return array Array of save_post hook names.
		 */
		public static function get_save_post_hooks(): array {
			return array_map( function( $post_type ) {
				return 'save_post_' . $post_type;
			}, self::get_product_post_types() );
		}

		/**
		 * Clear the cached post types.
		 *
		 * Call this if post types are modified dynamically during runtime.
		 */
		public static function clear_cache(): void {
			self::$cached_post_types = null;
		}
	}
}

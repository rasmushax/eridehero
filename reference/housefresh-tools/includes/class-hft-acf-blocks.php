<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HFT_ACF_Blocks' ) ) {
	/**
	 * Class HFT_ACF_Blocks.
	 *
	 * Handles the registration of ACF Blocks and their field groups for the plugin.
	 */
	class HFT_ACF_Blocks {

		public function __construct() {
			// Hook for registering blocks and including PHP fields
			add_action('acf/init', [ $this, 'register_blocks_and_fields' ]);
		}

		/**
		 * Register ACF Blocks and include their PHP field groups.
		 */
		public function register_blocks_and_fields(): void {
			// Check if ACF block registration function exists.
			if ( ! function_exists('acf_register_block_type') || ! function_exists('acf_add_local_field_group') ) {
				return;
			}

			// --- Register Affiliate Link block --- 
			$block_name = 'affiliate-link';
			$block_dir = HFT_PLUGIN_PATH . 'blocks/' . $block_name;
			$block_json_path = $block_dir . '/block.json';

			if ( file_exists( $block_json_path ) ) {
				register_block_type( $block_json_path, [
				'enqueue_assets' => function() {
					// Only enqueue on frontend (not in editor)
					if ( ! is_admin() ) {
						$this->enqueue_frontend_core_js();
					}
				}
			] );
				// error_log("Registered ACF Block: {$block_name}");
			} else {
				// error_log("ACF Block JSON not found for: {$block_name} at {$block_json_path}");
			}

			// --- Include Affiliate Link fields --- 
			$affiliate_link_fields_file = $block_dir . '/affiliate-link-fields.php';

			if ( file_exists( $affiliate_link_fields_file ) ) {
				include_once( $affiliate_link_fields_file );
			}

			// --- Register Price History block --- 
			$price_history_block_name = 'price-history';
			$price_history_block_dir = HFT_PLUGIN_PATH . 'blocks/' . $price_history_block_name;
			$price_history_block_json_path = $price_history_block_dir . '/block.json';

			if ( file_exists( $price_history_block_json_path ) ) {
				register_block_type( $price_history_block_json_path, [
				'enqueue_assets' => function() {
					// Only enqueue on frontend (not in editor)
					if ( ! is_admin() ) {
						$this->enqueue_frontend_core_js();
					}
				}
			] );
				// error_log("Registered ACF Block: {$price_history_block_name}");
			} else {
				// error_log("ACF Block JSON not found for: {$price_history_block_name} at {$price_history_block_json_path}");
			}

			// --- Include Price History fields --- 
			$price_history_fields_file = $price_history_block_dir . '/price-history-fields.php';
			
			if ( file_exists( $price_history_fields_file ) ) {
				include_once( $price_history_fields_file );
				// error_log('Successfully included: ' . $price_history_fields_file);
			} else {
				// error_log('ACF Field definition file NOT FOUND: ' . $price_history_fields_file);
			}
			
			// Add other blocks and their field inclusions here...
		}

		/**
		 * Enqueue the shared frontend core JS file.
		 * Called by blocks when they need the core functionality.
		 */
		private function enqueue_frontend_core_js(): void {
			// Use minified version for production, full version for SCRIPT_DEBUG
			$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
			$script_filename = "hft-frontend-core{$suffix}.js";
			$script_path = HFT_PLUGIN_PATH . 'assets/js/' . $script_filename;
			$script_url = HFT_PLUGIN_URL . 'assets/js/' . $script_filename;

			// Fallback to non-minified if minified doesn't exist
			if ( ! file_exists( $script_path ) && $suffix === '.min' ) {
				$script_filename = 'hft-frontend-core.js';
				$script_path = HFT_PLUGIN_PATH . 'assets/js/' . $script_filename;
				$script_url = HFT_PLUGIN_URL . 'assets/js/' . $script_filename;
			}

			if ( file_exists( $script_path ) ) {
				wp_enqueue_script(
					'hft-frontend-core',
					$script_url,
					[], // No dependencies
					HFT_VERSION,
					true // Load in footer
				);

				// Add defer loading strategy for better performance
				wp_script_add_data( 'hft-frontend-core', 'strategy', 'defer' );

				wp_localize_script(
					'hft-frontend-core',
					'hft_frontend_data',
					[
						'rest_url' => trailingslashit( rest_url() ),
					]
				);
			}
		}
	}
} 
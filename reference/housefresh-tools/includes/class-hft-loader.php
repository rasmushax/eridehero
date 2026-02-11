<?php
declare(strict_types=1);

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HFT_Loader' ) ) {
	/**
	 * Class HFT_Loader.
	 *
	 * Responsible for loading plugin components and registering hooks.
	 */
	class HFT_Loader {

		/**
		 * Stores the singleton instance of the HFT_Loader class.
		 *
		 * @var HFT_Loader|null
		 */
		private static ?HFT_Loader $instance = null;

		/**
		 * Instance of HFT_CPT.
		 *
		 * @var HFT_CPT|null
		 */
		private ?HFT_CPT $cpt_manager = null;

		/**
		 * Instance of HFT_Db.
		 *
		 * @var HFT_Db|null
		 */
		private ?HFT_Db $db_manager = null;

		/**
		 * Instance of HFT_Meta_Boxes.
		 *
		 * @var HFT_Meta_Boxes|null
		 */
		private ?HFT_Meta_Boxes $meta_box_manager = null;

		/**
		 * Instance of HFT_Admin_Settings.
		 *
		 * @var HFT_Admin_Settings|null
		 */
		private ?HFT_Admin_Settings $admin_settings_manager = null;

		/**
		 * Instance of HFT_Product_Meta_Boxes.
		 *
		 * @var HFT_Product_Meta_Boxes|null
		 */
		private ?HFT_Product_Meta_Boxes $product_meta_box_manager = null;

		/**
		 * Instance of HFT_Scraper_Admin.
		 *
		 * @var HFT_Scraper_Admin|null
		 */
		private ?HFT_Scraper_Admin $scraper_admin = null;

		/**
		 * Instance of HFT_Cron.
		 *
		 * @var HFT_Cron|null
		 */
		private ?HFT_Cron $cron_manager = null;

		/**
		 * Private constructor to prevent direct instantiation.
		 */
		private function __construct() {
			// Initialization code can go here if needed before init().
		}

		/**
		 * Ensures only one instance of the loader is created (Singleton pattern).
		 *
		 * @return HFT_Loader
		 */
		public static function get_instance(): HFT_Loader {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Initializes the plugin by setting up hooks and loading files.
		 */
		public function init(): void {
			// This is where we will add actions and filters.
			$this->load_dependencies();
			$this->add_hooks();
		}

		/**
		 * Placeholder for loading class dependencies.
		 */
		private function load_dependencies(): void {
			// Load the post type helper first (needed by many other classes)
			$post_type_helper_file = HFT_PLUGIN_PATH . 'includes/class-hft-post-type-helper.php';
			if ( file_exists( $post_type_helper_file ) ) {
				require_once $post_type_helper_file;
			}

			$cpt_file = HFT_PLUGIN_PATH . 'includes/class-hft-cpt.php';
			if ( file_exists( $cpt_file ) ) {
				require_once $cpt_file;
				$this->cpt_manager = new HFT_CPT();
			} else {
				// Handle error: CPT class file not found.
				// You might want to log this or show an admin notice.
			}

			$db_file = HFT_PLUGIN_PATH . 'includes/class-hft-db.php';
			if ( file_exists( $db_file ) ) {
				require_once $db_file;
				$this->db_manager = new HFT_Db();
			} else {
				// Handle error: DB class file not found.
				// You might want to log this or show an admin notice.
			}

			// Load Cron manager.
			$cron_file = HFT_PLUGIN_PATH . 'includes/class-hft-cron.php';
			if ( file_exists( $cron_file ) ) {
				require_once $cron_file;
				$this->cron_manager = new HFT_Cron();
			} else {
				// Handle error: Cron class file not found.
			}

			// Load Geo Groups class (needed by admin and frontend)
			$geo_groups_file = HFT_PLUGIN_PATH . 'includes/class-hft-geo-groups.php';
			if ( file_exists( $geo_groups_file ) ) {
				require_once $geo_groups_file;
			}

			// Load admin-specific classes only in admin area.
			if ( is_admin() ) {
				$meta_box_file = HFT_PLUGIN_PATH . 'includes/admin/class-hft-meta-boxes.php';
				if ( file_exists( $meta_box_file ) ) {
					require_once $meta_box_file;
					$this->meta_box_manager = new HFT_Meta_Boxes();
				} else {
					// Handle error: Meta Box class file not found.
				}

				$admin_settings_file = HFT_PLUGIN_PATH . 'includes/admin/class-hft-admin-settings.php';
				if ( file_exists( $admin_settings_file ) ) {
					require_once $admin_settings_file;
					$this->admin_settings_manager = new HFT_Admin_Settings();
				} else {
					// Handle error: Admin Settings class file not found.
				}

				// Load Product Meta Boxes (for price history, etc.)
				$product_meta_box_file = HFT_PLUGIN_PATH . 'includes/admin/class-hft-product-meta-boxes.php';
				if ( file_exists( $product_meta_box_file ) ) {
					require_once $product_meta_box_file;
					if ( class_exists( 'HFT_Product_Meta_Boxes' ) ) {
						$this->product_meta_box_manager = new HFT_Product_Meta_Boxes();
					}
				} else {
					// Handle error: Product Meta Boxes class file not found.
					// error_log('HFT_Loader: Product Meta Boxes class file not found.');
				}

				// Load Ajax handler
				$ajax_file = HFT_PLUGIN_PATH . 'includes/class-hft-ajax.php';
				if ( file_exists( $ajax_file ) ) {
					require_once $ajax_file;
					if ( class_exists( 'HFT_Ajax' ) ) {
						new HFT_Ajax();
					}
				} else {
					// Handle error: Ajax class file not found.
				}
				
				// Load Scraper-related classes first
				$scraper_files = [
					'models/class-hft-scraper.php',
					'models/class-hft-scraper-rule.php',
					'repositories/class-hft-scraper-repository.php',
					'class-hft-scraper-registry.php'
				];
				
				foreach ($scraper_files as $file) {
					$file_path = HFT_PLUGIN_PATH . 'includes/' . $file;
					if (file_exists($file_path)) {
						require_once $file_path;
					}
				}
				
				// Load Scraper Admin
				$scraper_admin_file = HFT_PLUGIN_PATH . 'includes/admin/class-hft-scraper-admin.php';
				if ( file_exists( $scraper_admin_file ) ) {
					require_once $scraper_admin_file;
					if ( class_exists( 'HFT_Scraper_Admin' ) && class_exists( 'HFT_Scraper_Repository' ) ) {
						$this->scraper_admin = new HFT_Scraper_Admin( new HFT_Scraper_Repository() );
					}
				} else {
					// Handle error: Scraper Admin class file not found.
				}
				
				// Load tracked links integration
				$integration_file = HFT_PLUGIN_PATH . 'includes/admin/class-hft-tracked-links-integration.php';
				if (file_exists($integration_file)) {
					require_once $integration_file;
				}
				
				// Load product scraper info
				$product_info_file = HFT_PLUGIN_PATH . 'includes/admin/class-hft-product-scraper-info.php';
				if (file_exists($product_info_file)) {
					require_once $product_info_file;
					new HFT_Product_Scraper_Info();
				}
				
				// Load scraper logs viewer
				$logs_file = HFT_PLUGIN_PATH . 'includes/admin/class-hft-scraper-logs.php';
				if (file_exists($logs_file)) {
					require_once $logs_file;
					new HFT_Scraper_Logs();
				}
				
				// Load product columns
				$columns_file = HFT_PLUGIN_PATH . 'includes/admin/class-hft-product-columns.php';
				if (file_exists($columns_file)) {
					require_once $columns_file;
					new HFT_Product_Columns();
				}
			}

			// Load Affiliate Link Generator (contains static methods, no instantiation needed here)
			$affiliate_link_generator_file = HFT_PLUGIN_PATH . 'includes/class-hft-affiliate-link-generator.php';
			if ( file_exists( $affiliate_link_generator_file ) ) {
				require_once $affiliate_link_generator_file;
			} else {
				// Handle error: Affiliate Link Generator class file not found.
				// error_log('HFT_Loader: Affiliate Link Generator class file not found.');
			}
			
			// Load Cache Manager
			$cache_manager_file = HFT_PLUGIN_PATH . 'includes/class-hft-cache-manager.php';
			if ( file_exists( $cache_manager_file ) ) {
				require_once $cache_manager_file;
				if ( class_exists( 'HFT_Cache_Manager' ) ) {
					new HFT_Cache_Manager();
				}
			}

			/* // Load Blocks handler - Native blocks removed, ACF blocks handled separately
			$blocks_class_file = HFT_PLUGIN_PATH . 'includes/class-hft-blocks.php';
			if ( file_exists( $blocks_class_file ) ) {
				require_once $blocks_class_file;
				if ( class_exists( 'HFT_Blocks' ) ) {
					new HFT_Blocks(); // Instantiate to run its constructor and hook block registration
				}
			} else {
				// Handle error: Blocks class file not found.
			}
			*/

			// Load ACF Block Registration handler (if ACF is active)
			if ( class_exists( 'ACF' ) ) {
				$acf_blocks_class_file = HFT_PLUGIN_PATH . 'includes/class-hft-acf-blocks.php';
				if ( file_exists( $acf_blocks_class_file ) ) {
					require_once $acf_blocks_class_file; 
					// DO NOT instantiate here: new HFT_ACF_Blocks(); 
					// Instantiation moved to a method hooked to acf/init
				} else {
					// Handle error: ACF Blocks class file not found.
				}

				// Load ACF Field Groups handler
				$acf_field_groups_file = HFT_PLUGIN_PATH . 'includes/class-hft-acf-field-groups.php';
				if ( file_exists( $acf_field_groups_file ) ) {
					require_once $acf_field_groups_file;
					// DO NOT instantiate here: new HFT_ACF_Field_Groups();
					// Instantiation moved to a method hooked to acf/init
				} else {
					// Handle error: ACF Field Groups class file not found.
				}
			}

			// Load IPInfo Service
			$ipinfo_service_file = HFT_PLUGIN_PATH . 'includes/class-hft-ipinfo-service.php';
			if ( file_exists( $ipinfo_service_file ) ) {
				require_once $ipinfo_service_file;
			}

			// Load Rate Limiter (needed by REST Controller)
			$rate_limiter_file = HFT_PLUGIN_PATH . 'includes/class-hft-rate-limiter.php';
			if ( file_exists( $rate_limiter_file ) ) {
				require_once $rate_limiter_file;
			}

			// Load REST Controller
			$rest_controller_file = HFT_PLUGIN_PATH . 'includes/class-hft-rest-controller.php';
			if ( file_exists( $rest_controller_file ) ) {
				require_once $rest_controller_file;
				if ( class_exists( 'HFT_REST_Controller' ) ) {
					new HFT_REST_Controller(); // Instantiate to run its constructor and hook REST routes
				}
			} else {
				// Handle error: REST Controller class file not found.
				// error_log('HFT_Loader: REST Controller class file not found.');
			}

			// Load Schema Output handler
			$schema_output_file = HFT_PLUGIN_PATH . 'includes/class-hft-schema-output.php';
			if ( file_exists( $schema_output_file ) ) {
				require_once $schema_output_file;
				if ( class_exists( 'HFT_Schema_Output' ) ) {
					new HFT_Schema_Output(); // Instantiate to run its constructor and hook schema output
				}
			} else {
				// Handle error: Schema Output class file not found.
				// error_log('HFT_Loader: Schema Output class file not found.');
			}

			// Load model classes
			$models = ['scraper', 'scraper-rule'];
			foreach ($models as $model) {
				$model_file = HFT_PLUGIN_PATH . 'includes/models/class-hft-' . $model . '.php';
				if (file_exists($model_file)) {
					require_once $model_file;
				}
			}

			// Load repository classes
			$repository_file = HFT_PLUGIN_PATH . 'includes/repositories/class-hft-scraper-repository.php';
			if (file_exists($repository_file)) {
				require_once $repository_file;
			}

			// Load scraper registry
			$registry_file = HFT_PLUGIN_PATH . 'includes/class-hft-scraper-registry.php';
			if (file_exists($registry_file)) {
				require_once $registry_file;
			}

			// Load Shopify currencies mapping (needed by parsers and admin)
			$shopify_currencies_file = HFT_PLUGIN_PATH . 'includes/class-hft-shopify-currencies.php';
			if (file_exists($shopify_currencies_file)) {
				require_once $shopify_currencies_file;
			}

			// Load Shopify Market Detector (needed by AJAX handler)
			$shopify_market_detector_file = HFT_PLUGIN_PATH . 'includes/class-hft-shopify-market-detector.php';
			if (file_exists($shopify_market_detector_file)) {
				require_once $shopify_market_detector_file;
			}

			// Load parser classes for dynamic scraper system
			// First load the parser interface
			$parser_interface_file = HFT_PLUGIN_PATH . 'includes/parsers/interface-hft-parserinterface.php';
			if (file_exists($parser_interface_file)) {
				require_once $parser_interface_file;
			}

			// Then load the base parser class
			$base_parser_file = HFT_PLUGIN_PATH . 'includes/parsers/class-hft-base-parser.php';
			if (file_exists($base_parser_file)) {
				require_once $base_parser_file;
			}

			// Load helper classes
			$xpath_extractor_file = HFT_PLUGIN_PATH . 'includes/parsers/class-hft-xpath-extractor.php';
			if (file_exists($xpath_extractor_file)) {
				require_once $xpath_extractor_file;
			}

			$post_processor_file = HFT_PLUGIN_PATH . 'includes/parsers/class-hft-post-processor.php';
			if (file_exists($post_processor_file)) {
				require_once $post_processor_file;
			}

			// Load extraction pipeline (depends on xpath extractor and post processor)
			$extraction_pipeline_file = HFT_PLUGIN_PATH . 'includes/parsers/class-hft-extraction-pipeline.php';
			if (file_exists($extraction_pipeline_file)) {
				require_once $extraction_pipeline_file;
			}

			// Now load the dynamic parser (which extends base parser)
			$dynamic_parser_file = HFT_PLUGIN_PATH . 'includes/parsers/class-hft-dynamic-parser.php';
			if (file_exists($dynamic_parser_file)) {
				require_once $dynamic_parser_file;
			}

			// Load Shopify Storefront API parser
			$shopify_storefront_parser_file = HFT_PLUGIN_PATH . 'includes/parsers/class-hft-shopify-storefront-parser.php';
			if (file_exists($shopify_storefront_parser_file)) {
				require_once $shopify_storefront_parser_file;
			}

			// Finally load the parser factory
			$parser_factory_file = HFT_PLUGIN_PATH . 'includes/parsers/class-hft-parser-factory.php';
			if (file_exists($parser_factory_file)) {
				require_once $parser_factory_file;
			}
			
			// Load Scraper AJAX handlers
			$scraper_ajax_file = HFT_PLUGIN_PATH . 'includes/ajax/class-hft-scraper-ajax.php';
			if (file_exists($scraper_ajax_file)) {
				require_once $scraper_ajax_file;
				new HFT_Scraper_Ajax();
			}
			
			// Load Scraper Alerts (outside admin check as it needs to run for cron)
			$alerts_file = HFT_PLUGIN_PATH . 'includes/class-hft-scraper-alerts.php';
			if (file_exists($alerts_file)) {
				require_once $alerts_file;
			}
		}

		/**
		 * Placeholder for adding WordPress hooks.
		 */
		private function add_hooks(): void {
			add_action( 'plugins_loaded', [ $this, 'load_plugin_textdomain' ] );

			if ( $this->cpt_manager instanceof HFT_CPT ) {
				add_action( 'admin_menu', [ $this->cpt_manager, 'add_admin_menu' ] );
				add_action( 'init', [ $this->cpt_manager, 'register_product_cpt' ], 0 ); // Priority 0 to ensure it's available early.
			}

			if ( $this->meta_box_manager instanceof HFT_Meta_Boxes ) {
				add_action( 'add_meta_boxes', [ $this->meta_box_manager, 'add_meta_boxes' ] );
				// Register save_post hooks for all configured product post types
				// Deferred to 'init' to allow other plugins to register hft_product_post_types filter
				add_action( 'init', function() {
					if ( class_exists( 'HFT_Post_Type_Helper' ) ) {
						foreach ( HFT_Post_Type_Helper::get_product_post_types() as $post_type ) {
							add_action( 'save_post_' . $post_type, [ $this->meta_box_manager, 'save_tracking_links_meta_data' ] );
						}
					} else {
						// Fallback for backward compatibility
						add_action( 'save_post_hf_product', [ $this->meta_box_manager, 'save_tracking_links_meta_data' ] );
					}
				} );
				// Hook for enqueuing admin scripts and styles for the CPT edit screen.
				add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
			}

			if ( $this->admin_settings_manager instanceof HFT_Admin_Settings ) {
				add_action( 'admin_menu', [ $this->admin_settings_manager, 'add_admin_menu' ] );
				add_action( 'admin_init', [ $this->admin_settings_manager, 'admin_init_settings' ] );
				add_action( 'admin_enqueue_scripts', [ $this->admin_settings_manager, 'enqueue_admin_assets' ] );
			}
			
			if ( $this->scraper_admin instanceof HFT_Scraper_Admin ) {
				add_action( 'admin_menu', [ $this->scraper_admin, 'add_admin_menu' ] );
				add_action( 'admin_enqueue_scripts', [ $this->scraper_admin, 'enqueue_scripts' ] );
				add_action( 'admin_init', [ $this->scraper_admin, 'process_admin_actions' ] );
			}

			if ( $this->cron_manager instanceof HFT_Cron ) {
				add_filter( 'cron_schedules', [ $this->cron_manager, 'add_custom_cron_intervals' ] );
				add_action( HFT_Cron::MAIN_CRON_HOOK, [ $this->cron_manager, 'process_scraper_batch' ] );
				// Ensure cron is scheduled (checks periodically via transient to avoid performance overhead)
				add_action( 'admin_init', [ 'HFT_Cron', 'ensure_scheduled' ] );
			}

			// Hook ACF Block initialization only if ACF is active
			if ( class_exists( 'ACF' ) ) {
				add_action( 'acf/init', [ $this, 'initialize_acf_blocks_handler' ], 5 );
			}

			// REST Controller is instantiated directly in load_dependencies and hooks itself
			// Affiliate Link Generator is static, no hooks needed here
		}

		/**
		 * Load the plugin text domain for translation.
		 *
		 * @since 0.1.0
		 */
		public function load_plugin_textdomain(): void {
			load_plugin_textdomain(
				'housefresh-tools',
				false,
				dirname( plugin_basename( HFT_PLUGIN_FILE ) ) . '/languages/'
			);
		}

		/**
		 * Handles plugin activation tasks.
		 *
		 * This method is called by the activation hook in the main plugin file.
		 */
		public static function activate(): void {
			// Ensure DB class is loaded, though load_dependencies might not have run yet for the instance.
			$db_file = HFT_PLUGIN_PATH . 'includes/class-hft-db.php';
			if ( ! class_exists('HFT_Db') && file_exists( $db_file ) ) {
				require_once $db_file;
			}

			if ( class_exists( 'HFT_Db' ) ) {
				$db_manager = new HFT_Db();
				$db_manager->create_tables();
			}

			// Ensure Cron class is loaded for static call
			$cron_file = HFT_PLUGIN_PATH . 'includes/class-hft-cron.php';
			if ( ! class_exists('HFT_Cron') && file_exists( $cron_file ) ) {
				require_once $cron_file;
			}
			if ( class_exists('HFT_Cron') ) {
				HFT_Cron::schedule_events(); // Add static call to schedule events
			}

			// Run scraper migration
			$migration_file = HFT_PLUGIN_PATH . 'includes/migrations/class-hft-scraper-migration.php';
			if (file_exists($migration_file)) {
				require_once $migration_file;
				
				// Load required dependencies for migration
				$models = ['scraper', 'scraper-rule'];
				foreach ($models as $model) {
					$model_file = HFT_PLUGIN_PATH . 'includes/models/class-hft-' . $model . '.php';
					if (!class_exists('HFT_' . ucfirst(str_replace('-', '_', $model))) && file_exists($model_file)) {
						require_once $model_file;
					}
				}
				
				$repository_file = HFT_PLUGIN_PATH . 'includes/repositories/class-hft-scraper-repository.php';
				if (!class_exists('HFT_Scraper_Repository') && file_exists($repository_file)) {
					require_once $repository_file;
				}
				
				HFT_Scraper_Migration::migrate();
			}


			// Flush rewrite rules.
			flush_rewrite_rules();

			// Set plugin version option (already handled in create_tables, but good to note it's part of activation)
			// update_option( 'hft_plugin_version', HFT_VERSION ); // Different from hft_db_version
		}

		/**
		 * Handles plugin deactivation tasks (placeholder).
		 */
		public static function deactivate(): void {
			// Ensure Cron class is loaded for static call
			$cron_file = HFT_PLUGIN_PATH . 'includes/class-hft-cron.php';
			if ( ! class_exists('HFT_Cron') && file_exists( $cron_file ) ) {
				require_once $cron_file;
			}
			if ( class_exists('HFT_Cron') ) {
				HFT_Cron::clear_scheduled_events(); // Add static call to clear events
			}

			// Placeholder for deactivation tasks, e.g., flushing rewrite rules.
			flush_rewrite_rules();
		}

		/**
		 * Enqueue admin scripts and styles.
		 *
		 * @param string $hook The current admin page hook.
		 */
		public function enqueue_admin_assets( string $hook ): void {
			// Only load on the CPT edit screen.
			// 'post.php' for edit screen, 'post-new.php' for new post screen.
			global $post_type;

			// Check if current post type is a product post type
			$is_product_screen = class_exists( 'HFT_Post_Type_Helper' )
				? HFT_Post_Type_Helper::is_product_post_type( $post_type )
				: ( 'hf_product' === $post_type );

			if ( ( 'post.php' === $hook || 'post-new.php' === $hook ) && $is_product_screen ) {
				// Plugin's Admin CSS
				wp_enqueue_style(
					'hft-admin-styles',
					HFT_PLUGIN_URL . 'admin/css/hft-admin-styles.css',
					['hft-tagify-style'], // Add tagify as dependency here too
					HFT_VERSION
				);

				// Enqueue Tagify CSS from local files
				wp_enqueue_style(
					'hft-tagify-style',
					HFT_PLUGIN_URL . 'assets/css/tagify.css',
					[],
					HFT_VERSION
				);

				// Enqueue Tagify JS from local files
				// Note: Polyfills might be needed for older browser compatibility, add if necessary.
				wp_enqueue_script(
					'hft-tagify-script',
					HFT_PLUGIN_URL . 'assets/js/tagify.min.js',
					[], // No WordPress script dependencies for Tagify itself
					HFT_VERSION,
					true // Load in footer
				);

				// Enqueue Plugin's Admin JS, dependent on jQuery and Tagify
				wp_enqueue_script(
					'hft-admin-scripts',
					HFT_PLUGIN_URL . 'admin/js/hft-admin-scripts.js',
					[ 'jquery', 'hft-tagify-script' ], // Dependencies: jQuery and Tagify
					HFT_VERSION,
					true // Load in footer
				);

				// Localize script (no changes needed here)
				$localized_data = [
					'ajax_url'             => admin_url( 'admin-ajax.php' ),
					'nonce'                => wp_create_nonce( 'hft_ajax_nonce' ), 
					'confirm_delete_row'   => __( 'Are you sure you want to delete this row?', 'housefresh-tools' ),
					'tracking_link_title'  => __( 'Tracking Link', 'housefresh-tools' ),
				];
				wp_localize_script( 'hft-admin-scripts', 'hft_admin_meta_box_l10n', $localized_data );
			}
		}


		/**
		 * Initializes the ACF Blocks handler once ACF is ready.
		 */
		public function initialize_acf_blocks_handler(): void {
			// Check if the class was loaded successfully earlier
			if ( class_exists( 'HFT_ACF_Blocks' ) ) {
				new HFT_ACF_Blocks(); // Now instantiate it
			} else {
				// error_log('HFT_Loader: HFT_ACF_Blocks class not found during acf/init.');
			}

			// Initialize ACF Field Groups
			if ( class_exists( 'HFT_ACF_Field_Groups' ) ) {
				new HFT_ACF_Field_Groups(); // Now instantiate it
			} else {
				// error_log('HFT_Loader: HFT_ACF_Field_Groups class not found during acf/init.');
			}
		}
	}
} 
<?php
declare(strict_types=1);

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HFT_Cron' ) ) {
	/**
	 * Class HFT_Cron.
	 *
	 * Manages WP Cron events for the plugin.
	 */
	class HFT_Cron {

		public const MAIN_CRON_HOOK = 'hft_run_scrapers_batch_event';
		private const FIXED_CRON_INTERVAL_KEY = 'hft_every_5_minutes'; // Internal key for our fixed cron schedule


		// Removed: private array $settings; // Settings will be fetched directly in methods needing them.

		public function __construct() {
			// This hook needs to be registered where WordPress can see it early, often in the main plugin file or a loader.
			// However, for class organization, the method is here. The add_filter call should be in init() or main plugin.
			// For now, assuming the HFT_Loader handles adding this filter correctly.
			// add_filter( 'cron_schedules', [ $this, 'add_custom_cron_intervals' ] );
		}

		/**
		 * Add custom cron schedules.
		 *
		 * @param array $schedules Existing cron schedules.
		 * @return array Modified cron schedules.
		 */
		public function add_custom_cron_intervals( array $schedules ): array {
			$schedules[self::FIXED_CRON_INTERVAL_KEY] = [
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => esc_html__( 'Every 5 Minutes (Housefresh Tools Scraper)', 'housefresh-tools' ), // Clarified display name
			];
			// Example for other intervals if needed:
			// $schedules['hft_every_15_minutes'] = [
			//     'interval' => 15 * MINUTE_IN_SECONDS,
			//     'display'  => esc_html__( 'Every 15 Minutes (Housefresh Tools)', 'housefresh-tools' ),
			// ];

			return $schedules;
		}

		/**
		 * Schedule the main scraper cron event if not already scheduled.
		 * This will now use a fixed internal interval.
		 */
		public static function schedule_events(): void { // Made static as it doesn't rely on instance properties
			// Clear any existing instances of this hook first to ensure only one is scheduled with the fixed interval.
			// This also helps if the plugin was deactivated and reactivated.
			wp_clear_scheduled_hook( self::MAIN_CRON_HOOK );

			// Schedule the event with our fixed internal interval.
			if ( ! wp_next_scheduled( self::MAIN_CRON_HOOK ) ) {
				wp_schedule_event( time(), self::FIXED_CRON_INTERVAL_KEY, self::MAIN_CRON_HOOK );
				// error_log('HFT Cron: ' . self::MAIN_CRON_HOOK . ' scheduled for ' . self::FIXED_CRON_INTERVAL_KEY);
			}

		}

		/**
		 * Ensure the cron event is scheduled.
		 * This method is called periodically to verify the cron job exists.
		 * Uses a transient to avoid checking on every page load.
		 */
		public static function ensure_scheduled(): void {
			// Only check once per hour to avoid performance overhead
			$transient_key = 'hft_cron_check_done';
			if ( get_transient( $transient_key ) ) {
				return; // Already checked recently
			}

			// Set transient to prevent checking again for 1 hour
			set_transient( $transient_key, true, HOUR_IN_SECONDS );

			// Check if the cron event is scheduled
			if ( ! wp_next_scheduled( self::MAIN_CRON_HOOK ) ) {
				// Verify our custom schedule is registered before trying to use it
				$schedules = wp_get_schedules();
				if ( ! isset( $schedules[ self::FIXED_CRON_INTERVAL_KEY ] ) ) {
					error_log('[Housefresh Tools Cron] ERROR: Custom schedule "' . self::FIXED_CRON_INTERVAL_KEY . '" is not registered! Cannot schedule cron job.');
					return;
				}

				// Schedule it if missing
				$result = wp_schedule_event( time(), self::FIXED_CRON_INTERVAL_KEY, self::MAIN_CRON_HOOK );
				if ( false === $result ) {
					error_log('[Housefresh Tools Cron] ERROR: Failed to schedule cron job: ' . self::MAIN_CRON_HOOK);
				} else {
					error_log('[Housefresh Tools Cron] Rescheduled missing cron job: ' . self::MAIN_CRON_HOOK);
				}
			}
		}

		/**
		 * Clear all scheduled events for this plugin.
		 * Typically called on deactivation.
		 */
		public static function clear_scheduled_events(): void { // Made static
			wp_clear_scheduled_hook( self::MAIN_CRON_HOOK );
			error_log('[Housefresh Tools Cron] All scheduled hooks (' . self::MAIN_CRON_HOOK . ') unscheduled.');
		}


		/**
		 * Convert user-friendly scrape interval setting to seconds.
		 *
		 * @param string $interval_key The key from settings (e.g., 'hourly', 'daily').
		 * @return int The interval in seconds, or 0 if disabled/invalid.
		 */
		private function get_scrape_interval_in_seconds( string $interval_key ): int {
			switch ( $interval_key ) {
				case 'hourly':
					return HOUR_IN_SECONDS;
				case 'six_hours':
					return 6 * HOUR_IN_SECONDS;
				case 'twelve_hours':
					return 12 * HOUR_IN_SECONDS;
				case 'twicedaily':
					return 12 * HOUR_IN_SECONDS; // WordPress twicedaily = 12 hours
				case 'daily':
					return DAY_IN_SECONDS;
				case 'disabled': // Fallthrough
				default:
					return 0; // Indicates disabled or an unknown/invalid interval
			}
		}

		/**
		 * Process a batch of links for scraping.
		 * This is the callback for the main cron hook.
		 * It fetches products based on their individual scrape_interval setting.
		 */
		public function process_scraper_batch(): void {
			$options = get_option( 'hft_settings', [] );
			$scrape_interval_setting = $options['scrape_interval'] ?? 'daily'; // This is the USER's desired frequency for a product.
			$products_per_batch    = (int) ( $options['products_per_batch'] ?? 10 ); // How many to process in this 5-min run.

			if ( 'disabled' === $scrape_interval_setting ) {
				return;
			}

			if ( $products_per_batch <= 0 ) {
				$products_per_batch = 10; // Ensure a valid batch size.
			}

			$interval_seconds = $this->get_scrape_interval_in_seconds( $scrape_interval_setting );

			if ( $interval_seconds === 0 ) { // Should only happen if 'disabled' was somehow passed here or an invalid value.
				return;
			}

			global $wpdb;
			// Use the correct table name directly
			$table_name = $wpdb->prefix . 'hft_tracked_links';

			// We need to compare last_scraped_at with NOW() - interval_seconds.
			// Products are due if last_scraped_at is older than (current_time - user_defined_interval).
			// current_time('mysql', 1) gives current time in GMT.
			// We assume last_scraped_at is also stored in GMT.
			$threshold_time_gmt = gmdate( 'Y-m-d H:i:s', time() - $interval_seconds );

			// Fetch link IDs that are active and due for scraping.
			$sql = $wpdb->prepare(
				"SELECT id FROM {$table_name}
				 WHERE ( last_scraped_at IS NULL OR last_scraped_at <= %s )
				 ORDER BY last_scraped_at ASC, id ASC
				 LIMIT %d",
				$threshold_time_gmt,
				$products_per_batch
			);

			$links_to_scrape_rows = $wpdb->get_results( $sql, ARRAY_A );

			if ( empty( $links_to_scrape_rows ) ) {
				return;
			}
			
			$link_ids_to_scrape = wp_list_pluck( $links_to_scrape_rows, 'id' );

			// Ensure Scraper Manager is available
			if ( ! class_exists('HFT_Scraper_Manager') ) {
				$manager_file = HFT_PLUGIN_PATH . 'includes/class-hft-scraper-manager.php';
				if(file_exists($manager_file)) {
					require_once $manager_file;
				} else {
					return;
				}
			}
			if ( ! class_exists('HFT_Scraper_Manager') ) {
				return;
			}

			$scraper_manager = new HFT_Scraper_Manager(); // Or get instance if it's a singleton

			$successful_scrapes = 0;
			$failed_scrapes = 0;

			foreach ( $link_ids_to_scrape as $tracked_link_id ) {
				$tracked_link_id = (int) $tracked_link_id;
				
				// Always update last_scraped_at BEFORE attempting scrape to prevent queue blocking
				$this->mark_scrape_attempt( $tracked_link_id );
				
				try {
					$result = $scraper_manager->scrape_link( $tracked_link_id );
					if ($result) {
						$successful_scrapes++;
					} else {
						$failed_scrapes++;
					}
				} catch ( Exception $e ) {
					$failed_scrapes++;
					// Error is already logged by scraper manager, just continue to next item
				}
			}
		}
		
		/**
		 * Mark a scrape attempt by updating last_scraped_at timestamp.
		 * This prevents timed-out or crashed scrapes from blocking the queue.
		 *
		 * @param int $tracked_link_id The tracked link ID to mark
		 */
		private function mark_scrape_attempt( int $tracked_link_id ): void {
			global $wpdb;
			$table_name = $wpdb->prefix . 'hft_tracked_links';
			
			$wpdb->update(
				$table_name,
				[ 'last_scraped_at' => current_time( 'mysql', true ) ],
				[ 'id' => $tracked_link_id ],
				[ '%s' ],
				[ '%d' ]
			);
		}
	}
} 
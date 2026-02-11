<?php
declare(strict_types=1);

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HFT_Db' ) ) {
	/**
	 * Class HFT_Db.
	 *
	 * Responsible for plugin database operations, table creation, and updates.
	 */
	class HFT_Db {

		/**
		 * Constructor.
		 */
		public function __construct() {
			// Future DB related initializations can go here.
		}

		/**
		 * Create or update plugin database tables.
		 */
		public function create_tables(): void {
			global $wpdb;

			$charset_collate = $wpdb->get_charset_collate();
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			// Table: hft_tracked_links
			$table_name_tracked_links = $wpdb->prefix . 'hft_tracked_links';
			$sql_tracked_links = "CREATE TABLE {$table_name_tracked_links} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				product_post_id BIGINT UNSIGNED NOT NULL,
				tracking_url TEXT NOT NULL,
				parser_identifier VARCHAR(100) NOT NULL,
				geo_target TEXT NULL,
				affiliate_link_override TEXT NULL,
				current_price DECIMAL(10,2) NULL,
				current_currency VARCHAR(10) NULL,
				current_status VARCHAR(50) NULL,
				current_shipping_info TEXT NULL,
				market_prices TEXT NULL,
				last_scraped_at DATETIME NULL,
				last_scrape_successful BOOLEAN NULL,
				consecutive_failures INT UNSIGNED NOT NULL DEFAULT 0,
				last_error_message TEXT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY product_post_id (product_post_id),
				KEY parser_identifier (parser_identifier),
				KEY last_scrape_successful (last_scrape_successful)
			) {$charset_collate};";
			dbDelta( $sql_tracked_links );

			// Table: hft_price_history
			$table_name_price_history = $wpdb->prefix . 'hft_price_history';
			$sql_price_history = "CREATE TABLE {$table_name_price_history} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				tracked_link_id BIGINT UNSIGNED NOT NULL,
				price DECIMAL(10,2) NOT NULL,
				currency VARCHAR(10) NOT NULL,
				status VARCHAR(50) NOT NULL,
				geo VARCHAR(5) NULL,
				scraped_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY tracked_link_id (tracked_link_id)
			) {$charset_collate};";
			dbDelta( $sql_price_history );

			// Table: hft_parser_rules
			$table_name_parser_rules = $wpdb->prefix . 'hft_parser_rules';
			$sql_parser_rules = "CREATE TABLE {$table_name_parser_rules} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				site_domain VARCHAR(191) NOT NULL UNIQUE,
				affiliate_format TEXT NULL,
				priority INT NOT NULL DEFAULT 10,
				PRIMARY KEY (id)
			) {$charset_collate};";
			dbDelta( $sql_parser_rules );

			// Table: hft_scrapers
			$table_name_scrapers = $wpdb->prefix . 'hft_scrapers';
			$sql_scrapers = "CREATE TABLE {$table_name_scrapers} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				domain VARCHAR(191) NOT NULL UNIQUE,
				name VARCHAR(255) NOT NULL,
				currency VARCHAR(3) NULL DEFAULT 'USD',
				geos TEXT NULL,
				is_active BOOLEAN NOT NULL DEFAULT 1,
				use_base_parser BOOLEAN NOT NULL DEFAULT 1,
				use_curl BOOLEAN NOT NULL DEFAULT 0,
				use_scrapingrobot BOOLEAN NOT NULL DEFAULT 0,
				scrapingrobot_render_js BOOLEAN NOT NULL DEFAULT 0,
				consecutive_successes INT UNSIGNED NOT NULL DEFAULT 0,
				health_reset_at DATETIME NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY is_active (is_active)
			) {$charset_collate};";
			dbDelta( $sql_scrapers );

			// Table: hft_scraper_rules
			$table_name_scraper_rules = $wpdb->prefix . 'hft_scraper_rules';
			$sql_scraper_rules = "CREATE TABLE {$table_name_scraper_rules} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				scraper_id BIGINT UNSIGNED NOT NULL,
				field_type ENUM('price', 'status', 'shipping') NOT NULL,
				priority INT NOT NULL DEFAULT 10,
				extraction_mode VARCHAR(50) NOT NULL DEFAULT 'xpath',
				xpath_selector TEXT NOT NULL,
				attribute VARCHAR(100) NULL DEFAULT NULL,
				regex_pattern TEXT NULL DEFAULT NULL,
				regex_fallbacks TEXT NULL DEFAULT NULL,
				return_boolean BOOLEAN NOT NULL DEFAULT 0,
				boolean_true_values TEXT NULL DEFAULT NULL,
				post_processing TEXT NULL DEFAULT NULL,
				is_active BOOLEAN NOT NULL DEFAULT 1,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY scraper_id (scraper_id),
				KEY field_type (field_type),
				KEY scraper_field_priority (scraper_id, field_type, priority)
			) {$charset_collate};";
			dbDelta( $sql_scraper_rules );

			// Table: hft_scraper_logs
			$table_name_scraper_logs = $wpdb->prefix . 'hft_scraper_logs';
			$sql_scraper_logs = "CREATE TABLE {$table_name_scraper_logs} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				scraper_id BIGINT UNSIGNED NOT NULL,
				tracked_link_id BIGINT UNSIGNED NULL,
				url TEXT NOT NULL,
				success BOOLEAN NOT NULL,
				extracted_data TEXT NULL,
				error_message TEXT NULL,
				execution_time FLOAT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY scraper_id (scraper_id),
				KEY tracked_link_id (tracked_link_id),
				KEY success (success),
				KEY created_at (created_at)
			) {$charset_collate};";
			dbDelta( $sql_scraper_logs );

			// Add scraper_id column to hft_tracked_links if it doesn't exist
			$tracked_links_table = $wpdb->prefix . 'hft_tracked_links';
			$column_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$tracked_links_table}` LIKE 'scraper_id'");
			if (!$column_exists) {
				$wpdb->query("ALTER TABLE `{$tracked_links_table}` ADD COLUMN `scraper_id` BIGINT UNSIGNED NULL AFTER `parser_identifier`");
				$wpdb->query("ALTER TABLE `{$tracked_links_table}` ADD INDEX `scraper_id` (`scraper_id`)");
			}

			// Add use_curl column to hft_scrapers if it doesn't exist
			$scrapers_table = $wpdb->prefix . 'hft_scrapers';
			$use_curl_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$scrapers_table}` LIKE 'use_curl'");
			if (!$use_curl_exists) {
				$wpdb->query("ALTER TABLE `{$scrapers_table}` ADD COLUMN `use_curl` BOOLEAN NOT NULL DEFAULT 0 AFTER `use_base_parser`");
			}

			// Add affiliate_link_format column to hft_scrapers if it doesn't exist
			$affiliate_format_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$scrapers_table}` LIKE 'affiliate_link_format'");
			if (!$affiliate_format_exists) {
				$wpdb->query("ALTER TABLE `{$scrapers_table}` ADD COLUMN `affiliate_link_format` TEXT NULL AFTER `geos`");
			}

			// Add use_scrapingrobot column to hft_scrapers if it doesn't exist
			$use_scrapingrobot_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$scrapers_table}` LIKE 'use_scrapingrobot'");
			if (!$use_scrapingrobot_exists) {
				$wpdb->query("ALTER TABLE `{$scrapers_table}` ADD COLUMN `use_scrapingrobot` BOOLEAN NOT NULL DEFAULT 0 AFTER `use_curl`");
			}

			// Add test_url column to hft_scrapers if it doesn't exist
			$test_url_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$scrapers_table}` LIKE 'test_url'");
			if (!$test_url_exists) {
				$wpdb->query("ALTER TABLE `{$scrapers_table}` ADD COLUMN `test_url` TEXT NULL AFTER `use_scrapingrobot`");
			}

			// Add scrapingrobot_render_js column to hft_scrapers if it doesn't exist
			$scrapingrobot_render_js_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$scrapers_table}` LIKE 'scrapingrobot_render_js'");
			if (!$scrapingrobot_render_js_exists) {
				$wpdb->query("ALTER TABLE `{$scrapers_table}` ADD COLUMN `scrapingrobot_render_js` BOOLEAN NOT NULL DEFAULT 0 AFTER `use_scrapingrobot`");
			}

			// Add consecutive_successes column to hft_scrapers if it doesn't exist
			$consecutive_successes_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$scrapers_table}` LIKE 'consecutive_successes'");
			if (!$consecutive_successes_exists) {
				$wpdb->query("ALTER TABLE `{$scrapers_table}` ADD COLUMN `consecutive_successes` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `scrapingrobot_render_js`");
			}

			// Add health_reset_at column to hft_scrapers if it doesn't exist
			$health_reset_at_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$scrapers_table}` LIKE 'health_reset_at'");
			if (!$health_reset_at_exists) {
				$wpdb->query("ALTER TABLE `{$scrapers_table}` ADD COLUMN `health_reset_at` DATETIME NULL AFTER `consecutive_successes`");
			}

			// Add logo_attachment_id column to hft_scrapers if it doesn't exist
			$logo_attachment_id_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$scrapers_table}` LIKE 'logo_attachment_id'");
			if (!$logo_attachment_id_exists) {
				$wpdb->query("ALTER TABLE `{$scrapers_table}` ADD COLUMN `logo_attachment_id` BIGINT UNSIGNED NULL AFTER `name`");
			}

			// Add geos_input column to hft_scrapers if it doesn't exist
			// This stores the original geo input (including group identifiers) before expansion
			$geos_input_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$scrapers_table}` LIKE 'geos_input'");
			if (!$geos_input_exists) {
				$wpdb->query("ALTER TABLE `{$scrapers_table}` ADD COLUMN `geos_input` TEXT NULL AFTER `geos`");
			}

			// Add Shopify Markets columns to hft_scrapers
			$shopify_markets_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$scrapers_table}` LIKE 'shopify_markets'");
			if (!$shopify_markets_exists) {
				$wpdb->query("ALTER TABLE `{$scrapers_table}` ADD COLUMN `shopify_markets` BOOLEAN NOT NULL DEFAULT 0 AFTER `scrapingrobot_render_js`");
				$wpdb->query("ALTER TABLE `{$scrapers_table}` ADD COLUMN `shopify_method` VARCHAR(20) NULL DEFAULT NULL AFTER `shopify_markets`");
				$wpdb->query("ALTER TABLE `{$scrapers_table}` ADD COLUMN `shopify_storefront_token` VARCHAR(255) NULL AFTER `shopify_method`");
				$wpdb->query("ALTER TABLE `{$scrapers_table}` ADD COLUMN `shopify_shop_domain` VARCHAR(255) NULL AFTER `shopify_storefront_token`");
			}

			// Add market_prices column to hft_tracked_links (multi-market JSON storage)
			$market_prices_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$tracked_links_table}` LIKE 'market_prices'");
			if (!$market_prices_exists) {
				$wpdb->query("ALTER TABLE `{$tracked_links_table}` ADD COLUMN `market_prices` TEXT NULL AFTER `current_shipping_info`");
			}

			// Add geo column to hft_price_history (identifies which geo a price entry belongs to)
			$price_history_table = $wpdb->prefix . 'hft_price_history';
			$geo_col_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$price_history_table}` LIKE 'geo'");
			if (!$geo_col_exists) {
				$wpdb->query("ALTER TABLE `{$price_history_table}` ADD COLUMN `geo` VARCHAR(5) NULL AFTER `status`");
			}

			// Migration for enhanced extraction system (hft_scraper_rules)
			$scraper_rules_table = $wpdb->prefix . 'hft_scraper_rules';

			// Add priority column
			$priority_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$scraper_rules_table}` LIKE 'priority'");
			if (!$priority_exists) {
				$wpdb->query("ALTER TABLE `{$scraper_rules_table}` ADD COLUMN `priority` INT NOT NULL DEFAULT 10 AFTER `field_type`");
			}

			// Add extraction_mode column
			$extraction_mode_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$scraper_rules_table}` LIKE 'extraction_mode'");
			if (!$extraction_mode_exists) {
				$wpdb->query("ALTER TABLE `{$scraper_rules_table}` ADD COLUMN `extraction_mode` VARCHAR(50) NOT NULL DEFAULT 'xpath' AFTER `priority`");
			}

			// Add regex_pattern column
			$regex_pattern_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$scraper_rules_table}` LIKE 'regex_pattern'");
			if (!$regex_pattern_exists) {
				$wpdb->query("ALTER TABLE `{$scraper_rules_table}` ADD COLUMN `regex_pattern` TEXT NULL DEFAULT NULL AFTER `attribute`");
			}

			// Add regex_fallbacks column
			$regex_fallbacks_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$scraper_rules_table}` LIKE 'regex_fallbacks'");
			if (!$regex_fallbacks_exists) {
				$wpdb->query("ALTER TABLE `{$scraper_rules_table}` ADD COLUMN `regex_fallbacks` TEXT NULL DEFAULT NULL AFTER `regex_pattern`");
			}

			// Add return_boolean column
			$return_boolean_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$scraper_rules_table}` LIKE 'return_boolean'");
			if (!$return_boolean_exists) {
				$wpdb->query("ALTER TABLE `{$scraper_rules_table}` ADD COLUMN `return_boolean` BOOLEAN NOT NULL DEFAULT 0 AFTER `regex_fallbacks`");
			}

			// Add boolean_true_values column
			$boolean_true_values_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$scraper_rules_table}` LIKE 'boolean_true_values'");
			if (!$boolean_true_values_exists) {
				$wpdb->query("ALTER TABLE `{$scraper_rules_table}` ADD COLUMN `boolean_true_values` TEXT NULL DEFAULT NULL AFTER `return_boolean`");
			}

			// Remove the UNIQUE constraint on (scraper_id, field_type) to allow multiple rules per field
			$unique_key_exists = $wpdb->get_var("SHOW INDEX FROM `{$scraper_rules_table}` WHERE Key_name = 'unique_scraper_field'");
			if ($unique_key_exists) {
				$wpdb->query("ALTER TABLE `{$scraper_rules_table}` DROP INDEX `unique_scraper_field`");
			}

			// Add composite index for priority-based queries
			$priority_index_exists = $wpdb->get_var("SHOW INDEX FROM `{$scraper_rules_table}` WHERE Key_name = 'scraper_field_priority'");
			if (!$priority_index_exists) {
				$wpdb->query("ALTER TABLE `{$scraper_rules_table}` ADD INDEX `scraper_field_priority` (`scraper_id`, `field_type`, `priority`)");
			}

			// Store/update plugin database version.
			update_option( 'hft_db_version', HFT_VERSION );
			
			// Add performance indexes
			$this->add_performance_indexes();
		}
		
		/**
		 * Add performance indexes for frontend queries.
		 */
		private function add_performance_indexes(): void {
			global $wpdb;
			
			$tracked_links_table = $wpdb->prefix . 'hft_tracked_links';
			$price_history_table = $wpdb->prefix . 'hft_price_history';
			$scraper_logs_table = $wpdb->prefix . 'hft_scraper_logs';
			
			// Check and add composite index for tracked links table (for frontend queries)
			$index_name = 'idx_product_geo';
			$index_exists = $wpdb->get_var("SHOW INDEX FROM `{$tracked_links_table}` WHERE Key_name = '{$index_name}'");
			if (!$index_exists) {
				$wpdb->query("ALTER TABLE `{$tracked_links_table}` ADD INDEX `{$index_name}` (`product_post_id`, `geo_target`(20))");
			}
			
			// Add index for cron job queries
			$index_name = 'idx_last_scraped';
			$index_exists = $wpdb->get_var("SHOW INDEX FROM `{$tracked_links_table}` WHERE Key_name = '{$index_name}'");
			if (!$index_exists) {
				$wpdb->query("ALTER TABLE `{$tracked_links_table}` ADD INDEX `{$index_name}` (`last_scraped_at`, `id`)");
			}
			
			// Add composite index for price history queries
			$index_name = 'idx_link_scraped';
			$index_exists = $wpdb->get_var("SHOW INDEX FROM `{$price_history_table}` WHERE Key_name = '{$index_name}'");
			if (!$index_exists) {
				$wpdb->query("ALTER TABLE `{$price_history_table}` ADD INDEX `{$index_name}` (`tracked_link_id`, `scraped_at`)");
			}
			
			// Add index for scraper logs if table exists
			if ($wpdb->get_var("SHOW TABLES LIKE '{$scraper_logs_table}'") === $scraper_logs_table) {
				$index_name = 'idx_scraper_created';
				$index_exists = $wpdb->get_var("SHOW INDEX FROM `{$scraper_logs_table}` WHERE Key_name = '{$index_name}'");
				if (!$index_exists) {
					$wpdb->query("ALTER TABLE `{$scraper_logs_table}` ADD INDEX `{$index_name}` (`scraper_id`, `created_at`)");
				}
			}
		}
	}
} 
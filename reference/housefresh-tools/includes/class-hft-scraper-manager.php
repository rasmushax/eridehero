<?php
declare(strict_types=1);

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure the parser interface is available
if ( ! interface_exists( 'HFT_ParserInterface' ) ) {
	$interface_path = HFT_PLUGIN_PATH . 'includes/interfaces/interface-hft-parser.php';
	if ( file_exists( $interface_path ) ) {
		require_once $interface_path;
	} else {
		// Critical error if interface is missing.
		trigger_error('HFT_ParserInterface not found. Scraping cannot proceed.', E_USER_ERROR);
		return;
	}
}

if ( ! class_exists( 'HFT_Scraper_Manager' ) ) {
	/**
	 * Class HFT_Scraper_Manager.
	 *
	 * Manages the scraping process for individual tracked links.
	 */
	class HFT_Scraper_Manager {

		public function __construct() {
			// Dependencies can be injected if needed, e.g., $wpdb
		}

		/**
		 * Scrapes a single tracked link.
		 *
		 * For Shopify Markets scrapers, iterates through all active currency markets
		 * and stores aggregated results in the market_prices JSON column.
		 *
		 * @param int $tracked_link_id The ID of the link in hft_tracked_links table.
		 * @return bool True on successful scrape and DB update, false otherwise.
		 */
		public function scrape_link( int $tracked_link_id ): bool {
			global $wpdb;
			$tracked_links_table = $wpdb->prefix . 'hft_tracked_links';
			$price_history_table = $wpdb->prefix . 'hft_price_history';

			$link_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tracked_links_table} WHERE id = %d", $tracked_link_id ), ARRAY_A );

			if ( ! $link_data ) {
				return false;
			}

			$parser_identifier = $link_data['parser_identifier'] ?? 'unknown';
			$tracking_url = $link_data['tracking_url'];

			// Ensure the HFT_ParserInterface is loaded
			$interface_file = HFT_PLUGIN_PATH . 'includes/parsers/interface-hft-parserinterface.php';
			if ( ! interface_exists('HFT_ParserInterface') && file_exists($interface_file) ) {
				require_once $interface_file;
			} elseif ( ! interface_exists('HFT_ParserInterface') ) {
				trigger_error('HFT_Scraper_Manager: HFT_ParserInterface not found.', E_USER_WARNING);
				$this->update_scrape_status( $tracked_link_id, false, $link_data['consecutive_failures'] + 1, 'Critical: Parser Interface not found.' );
				return false;
			}

			// 1. Determine and instantiate the parser
			$parser = null;

			if (!empty($link_data['scraper_id'])) {
				$parser = HFT_Parser_Factory::create_parser_by_scraper_id((int)$link_data['scraper_id']);
			} else {
				$parser = HFT_Parser_Factory::create_parser($tracking_url, $parser_identifier);
			}

			if (!($parser instanceof HFT_ParserInterface)) {
				$error_message = sprintf(
					__('No suitable parser found for URL: %s', 'housefresh-tools'),
					esc_html($tracking_url)
				);
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
					error_log( '[HFT Scraper] ' . $error_message );
				}
				$this->update_scrape_status($tracked_link_id, false, $link_data['consecutive_failures'] + 1, $error_message);
				return false;
			}

			// 2. Fetch scraper row early to determine if this is a Shopify Markets scraper
			$scraper = null;
			$is_shopify_markets = false;
			if (!empty($link_data['scraper_id'])) {
				$scrapers_table = $wpdb->prefix . 'hft_scrapers';
				$scraper = $wpdb->get_row($wpdb->prepare(
					"SELECT domain, geos, shopify_markets FROM {$scrapers_table} WHERE id = %d",
					(int) $link_data['scraper_id']
				), ARRAY_A);
				if ($scraper) {
					$is_shopify_markets = (bool) ($scraper['shopify_markets'] ?? false);
				}
			}

			// 3. Branch: Shopify Markets multi-market scrape vs. regular single scrape
			if ($is_shopify_markets && $scraper && !empty($scraper['geos'])) {
				return $this->scrape_shopify_markets($tracked_link_id, $link_data, $parser, $scraper, $tracking_url);
			}

			// --- Regular single-market scrape flow ---
			return $this->scrape_single($tracked_link_id, $link_data, $parser, $scraper, $tracking_url);
		}

		/**
		 * Performs a regular single-market scrape.
		 */
		private function scrape_single( int $tracked_link_id, array $link_data, HFT_ParserInterface $parser, ?array $scraper, string $tracking_url ): bool {
			global $wpdb;
			$tracked_links_table = $wpdb->prefix . 'hft_tracked_links';
			$price_history_table = $wpdb->prefix . 'hft_price_history';

			try {
				$parsed_data = $parser->parse( $tracking_url, $link_data );
			} catch ( Exception $e ) {
				$this->update_scrape_status( $tracked_link_id, false, $link_data['consecutive_failures'] + 1, 'Parser Exception: ' . $e->getMessage() );
				return false;
			}

			$current_time_mysql = current_time( 'mysql', true );

			if ( ! empty( $parsed_data['error'] ) ) {
				$this->update_scrape_status( $tracked_link_id, false, $link_data['consecutive_failures'] + 1, $parsed_data['error'] );
				return false;
			}

			$update_data = [
				'current_price'          => $parsed_data['price'],
				'current_currency'       => $parsed_data['currency'],
				'current_status'         => $parsed_data['status'],
				'current_shipping_info'  => $parsed_data['shipping_info'],
				'last_scraped_at'        => $current_time_mysql,
				'last_scrape_successful' => true,
				'consecutive_failures'   => 0,
				'last_error_message'     => null,
			];
			$update_format = ['%f', '%s', '%s', '%s', '%s', '%d', '%d', '%s'];

			// Sync geo_target and parser_identifier from scraper on successful scrape
			if ($scraper) {
				if (!empty($scraper['geos'])) {
					$update_data['geo_target'] = $scraper['geos'];
					$update_format[] = '%s';
				}
				if (!empty($scraper['domain'])) {
					$update_data['parser_identifier'] = $scraper['domain'];
					$update_format[] = '%s';
				}
			}

			$wpdb->update( $tracked_links_table, $update_data, [ 'id' => $tracked_link_id ], $update_format, ['%d'] );

			// Insert into price history if price is available
			if ( isset( $parsed_data['price'] ) && is_numeric( $parsed_data['price'] ) && isset( $parsed_data['currency'] ) && isset( $parsed_data['status'] ) ) {
				$wpdb->insert(
					$price_history_table,
					[
						'tracked_link_id' => $tracked_link_id,
						'price'           => (float) $parsed_data['price'],
						'currency'        => $parsed_data['currency'],
						'status'          => $parsed_data['status'],
						'scraped_at'      => $current_time_mysql,
					],
					['%d', '%f', '%s', '%s', '%s']
				);

				if ( !empty($link_data['product_post_id']) ) {
					do_action( 'hft_price_updated', $tracked_link_id, (int) $link_data['product_post_id'] );
				}
			}
			return true;
		}

		/**
		 * Performs a multi-market scrape for Shopify Markets scrapers.
		 *
		 * Iterates through all currency groups from the scraper's geos,
		 * scrapes each market, and stores aggregated results in market_prices JSON.
		 */
		private function scrape_shopify_markets( int $tracked_link_id, array $link_data, HFT_ParserInterface $parser, array $scraper, string $tracking_url ): bool {
			global $wpdb;
			$tracked_links_table = $wpdb->prefix . 'hft_tracked_links';
			$price_history_table = $wpdb->prefix . 'hft_price_history';
			$current_time_mysql = current_time( 'mysql', true );

			$geos_array = array_map('trim', explode(',', $scraper['geos']));
			$currency_groups = HFT_Shopify_Currencies::group_by_currency($geos_array);

			$market_prices = [];
			$first_market = null;
			$errors = [];

			foreach ($currency_groups as $currency => $countries) {
				$representative = $countries[0]; // First country in the group

				// Build link_data context for this market
				$market_link_data = $link_data;
				$market_link_data['geo_target'] = $representative;
				$market_link_data['tracked_link_id'] = $tracked_link_id;

				try {
					$parsed_data = $parser->parse($tracking_url, $market_link_data);
				} catch ( Exception $e ) {
					$errors[] = sprintf('%s/%s: %s', $representative, $currency, $e->getMessage());
					continue;
				}

				if (!empty($parsed_data['error'])) {
					$errors[] = sprintf('%s/%s: %s', $representative, $currency, $parsed_data['error']);
					continue;
				}

				// Validate returned currency matches expected
				$expected_currency = $currency;
				$actual_currency = strtoupper($parsed_data['currency'] ?? '');
				if (!empty($actual_currency) && $actual_currency !== $expected_currency) {
					$errors[] = sprintf('Currency mismatch for %s: expected %s, got %s', $representative, $expected_currency, $actual_currency);
					continue;
				}

				// Store market result
				$market_prices[$representative] = [
					'price'     => (float) $parsed_data['price'],
					'currency'  => $parsed_data['currency'],
					'status'    => $parsed_data['status'],
					'countries' => $countries,
				];

				// Track the first market for backward-compatible fields
				if ($first_market === null) {
					$first_market = $parsed_data;
				}

				// Insert price history entry for this market
				if (isset($parsed_data['price']) && is_numeric($parsed_data['price']) && isset($parsed_data['currency']) && isset($parsed_data['status'])) {
					$wpdb->insert(
						$price_history_table,
						[
							'tracked_link_id' => $tracked_link_id,
							'price'           => (float) $parsed_data['price'],
							'currency'        => $parsed_data['currency'],
							'status'          => $parsed_data['status'],
							'geo'             => $representative,
							'scraped_at'      => $current_time_mysql,
						],
						['%d', '%f', '%s', '%s', '%s', '%s']
					);
				}
			}

			// If no markets succeeded at all, report failure
			if (empty($market_prices)) {
				$error_msg = 'All markets failed: ' . implode('; ', $errors);
				$this->update_scrape_status($tracked_link_id, false, $link_data['consecutive_failures'] + 1, $error_msg);
				return false;
			}

			// Build update data using first market for backward-compatible fields
			$update_data = [
				'current_price'          => $first_market['price'],
				'current_currency'       => $first_market['currency'],
				'current_status'         => $first_market['status'],
				'current_shipping_info'  => $first_market['shipping_info'] ?? null,
				'market_prices'          => wp_json_encode($market_prices),
				'last_scraped_at'        => $current_time_mysql,
				'last_scrape_successful' => true,
				'consecutive_failures'   => 0,
				'last_error_message'     => !empty($errors) ? 'Partial: ' . implode('; ', $errors) : null,
			];
			$update_format = ['%f', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s'];

			// Sync geo_target from scraper's geos and parser_identifier from domain
			if (!empty($scraper['geos'])) {
				$update_data['geo_target'] = $scraper['geos'];
				$update_format[] = '%s';
			}
			if (!empty($scraper['domain'])) {
				$update_data['parser_identifier'] = $scraper['domain'];
				$update_format[] = '%s';
			}

			$wpdb->update($tracked_links_table, $update_data, ['id' => $tracked_link_id], $update_format, ['%d']);

			// Trigger cache invalidation
			if (!empty($link_data['product_post_id'])) {
				do_action('hft_price_updated', $tracked_link_id, (int) $link_data['product_post_id']);
			}

			return true;
		}

		/**
		 * Helper to update only the scrape status fields in hft_tracked_links on failure.
		 */
		private function update_scrape_status( int $tracked_link_id, bool $success, int $failures, ?string $error_message ): void {
			global $wpdb;
			$table_name = $wpdb->prefix . 'hft_tracked_links';

			$data = [
				'last_scraped_at'        => current_time( 'mysql', true ),
				'last_scrape_successful' => $success,
				'consecutive_failures'   => $failures,
				'last_error_message'     => $error_message,
			];
			$format = ['%s', '%d', '%d', '%s'];

			$wpdb->update( $table_name, $data, [ 'id' => $tracked_link_id ], $format, ['%d'] );
		}

		/**
		 * Scrapes a tracked link based on its associated Product CPT ID.
		 *
		 * @param int $product_id The ID of the hf_product CPT.
		 * @return bool|WP_Error True on success, WP_Error on failure.
		 */
		public function scrape_link_by_product_id( int $product_id ) {
			global $wpdb;
			$tracked_link_table = $wpdb->prefix . 'hft_tracked_links';

			if ( $product_id <= 0 ) {
				return new WP_Error('invalid_product_id', __( 'Invalid Product ID provided.', 'housefresh-tools' ) );
			}

			// Find the tracked link ID associated with this product_id
			// Assuming one active tracking link per product for simplicity here.
			// If multiple are possible, this logic might need refinement (e.g., get the latest or a specific one).
			$link_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$tracked_link_table} WHERE product_post_id = %d ORDER BY id DESC LIMIT 1",
					$product_id
				)
			);

			if ( ! $link_id ) {
				return new WP_Error('no_tracked_link', sprintf(__( 'No tracked link found for Product ID %d.', 'housefresh-tools' ), $product_id) );
			}

			return $this->scrape_link( absint( $link_id ) );
		}
	}
} 
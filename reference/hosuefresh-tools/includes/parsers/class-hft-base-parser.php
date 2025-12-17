<?php
declare(strict_types=1);

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure interface is loaded
$interface_file = HFT_PLUGIN_PATH . 'includes/parsers/interface-hft-parserinterface.php';
if ( ! interface_exists('HFT_ParserInterface') && file_exists($interface_file) ) {
	require_once $interface_file;
}

if ( ! class_exists( 'HFT_Base_Parser' ) && interface_exists('HFT_ParserInterface') ) {
	/**
	 * Abstract Class HFT_Base_Parser.
	 *
	 * Provides helper methods and default implementations for common parser interface methods.
	 */
	abstract class HFT_Base_Parser implements HFT_ParserInterface {

		protected ?HFT_Structured_Data_Helper $structured_data_helper_instance = null;

		/**
		 * Constructor (optional for children).
		 */
		// public function __construct() { }

		/**
		 * {@inheritdoc}
		 * Default implementation: Fetches HTML and uses Structured Data Helper, then allows modification.
		 * Child classes (like Amazon) MUST override this if they don't parse HTML.
		 */
		public function parse(string $url_or_identifier, array $link_meta = []): array {
			$result = [
				'price'         => null,
				'currency'      => null,
				'status'        => null,
				'shipping_info' => null,
				'error'         => null,
			];

			// Default parse assumes $url_or_identifier is a URL
			$url = $url_or_identifier;
			$fetch_error = null;
			$html_content = $this->_fetch_html($url, $fetch_error);

			if ($html_content === null) {
				$result['error'] = $fetch_error ?? __( 'Unknown error fetching HTML.', 'housefresh-tools' );
				return $result;
			}

			$helper = $this->_get_structured_data_helper();

			if ( $helper instanceof HFT_Structured_Data_Helper ) {
				$structured_data = $helper->extract_data_from_html( $html_content );
				$result['price']    = $structured_data['price'];
				$result['currency'] = $structured_data['currency'];
				$result['status']   = $structured_data['status'];

				if(!empty($structured_data['error'])){
					if ($result['price'] === null) { // Prioritize helper error only if price wasn't found
						 $result['error'] = 'Structured Data Helper Error: ' . $structured_data['error'];
					}
				}
			} else {
				// Don't set error if helper not found, maybe child parser handles everything
				// $result['error'] = 'Structured Data Helper not available.'; 
			}

			// ** Allow child class to modify results **
			$result = $this->_modify_parse_results($result, $html_content);

			// Generic Shipping Info Check (Optional)
			if (empty($result['shipping_info']) && strpos($html_content, 'Free shipping') !== false) { 
				 $result['shipping_info'] = 'Possibly Free Shipping'; 
			}

			if ( $result['price'] === null && $result['error'] === null) {
				$result['error'] = __( 'Could not extract product price using standard methods or overrides.', 'housefresh-tools' );
			}

			return $result;
		}

		/**
		 * {@inheritdoc}
		 * Must be implemented by child classes to return their specific slug (e.g., 'amazon', 'levoit.com').
		 */
		abstract public function get_source_type_slug(): string; // Made abstract again

		/**
		 * {@inheritdoc}
		 * Generates a label based on the slug. Can be overridden by child classes.
		 */
		public function get_source_type_label(): string {
			$slug = $this->get_source_type_slug();
			// Convert slug (e.g., 'levoit.com', 'my-site') to a label (e.g., 'Levoit.com Product', 'My Site Product')
			$label = ucwords(str_replace(['-', '.'], ' ', $slug));
			return sprintf(__( '%s Product', 'housefresh-tools' ), $label);
		}

		/**
		 * {@inheritdoc}
		 * Defaults to "Product URL". Can be overridden by child classes.
		 */
		public function get_identifier_label(): string {
			return __( 'Product URL', 'housefresh-tools' );
		}

		/**
		 * {@inheritdoc}
		 * Generates a placeholder based on the slug. Can be overridden by child classes.
		 */
		public function get_identifier_placeholder(): string {
			$slug = $this->get_source_type_slug();
			// Simple placeholder generation
			if (filter_var('http://' . $slug, FILTER_VALIDATE_URL)) {
				return sprintf(__( 'e.g., https://%s/product/...', 'housefresh-tools' ), $slug);
			} else {
				 return __( 'Enter identifier...', 'housefresh-tools' );
			}
		}

		/**
		 * {@inheritdoc}
		 * Defaults to false. Must be overridden by parsers using ASIN (like Amazon).
		 */
		public function is_identifier_asin(): bool {
			return false;
		}

		// --- Helper Methods for Child Classes --- 

		/**
		 * Fetches HTML content for a given URL.
		 *
		 * @param string $url The URL to fetch.
		 * @param array &$error_ref Reference to a string where error messages can be stored.
		 * @return string|null HTML content on success, null on failure.
		 */
		protected function _fetch_html(string $url, ?string &$error_ref = null): ?string {
			if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
				$error_ref = __( 'Invalid or empty URL provided.', 'housefresh-tools' );
				return null;
			}

			// Check if we should use ScrapingRobot first
			if ($this->should_use_scrapingrobot()) {
				return $this->_fetch_html_scrapingrobot($url, $error_ref);
			}
			
			// Check if we should use cURL based on scraper settings
			if ($this->should_use_curl()) {
				return $this->_fetch_html_curl($url, $error_ref);
			}

			// Use browser-like settings to avoid being blocked
			$args = [
				'timeout' => 30, // Increased from 20 to 30 seconds
				'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
				'headers' => [
					'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
					'Accept-Language' => 'en-US,en;q=0.9',
					'Accept-Encoding' => 'gzip, deflate',
					'Cache-Control' => 'no-cache',
					'Pragma' => 'no-cache',
					'Connection' => 'keep-alive',
					'Upgrade-Insecure-Requests' => '1',
					'Sec-Fetch-Dest' => 'document',
					'Sec-Fetch-Mode' => 'navigate',
					'Sec-Fetch-Site' => 'none',
					'Sec-Fetch-User' => '?1',
				],
				'compress' => true, // Enable compression support
				'decompress' => true, // Automatically decompress response
				'sslverify' => true, // Verify SSL certificates
				'redirection' => 5, // Follow up to 5 redirects
				'cookies' => [], // Enable cookie handling
			];

			$response = wp_remote_get( $url, $args );

			if ( is_wp_error( $response ) ) {
				$error_ref = __( 'Failed to fetch URL: ', 'housefresh-tools' ) . $response->get_error_message();
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
					error_log( '[HFT Scraper] wp_remote_get error for ' . $url . ': ' . $error_ref );
				}
				return null;
			}

			$http_code = wp_remote_retrieve_response_code( $response );
			if ( $http_code < 200 || $http_code >= 300 ) { // Check for non-2xx status codes
				$error_ref = sprintf( __( 'Failed to fetch URL: HTTP status code %d', 'housefresh-tools' ), $http_code );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
					error_log( '[HFT Scraper] HTTP error for ' . $url . ': ' . $error_ref );
				}
				return null;
			}

			$html_content = wp_remote_retrieve_body( $response );
			if ( empty( $html_content ) ) {
				$error_ref = __( 'Fetched HTML content is empty.', 'housefresh-tools' );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
					error_log( '[HFT Scraper] Empty response for ' . $url );
				}
				return null;
			}

			return $html_content;
		}

		/**
		 * Fetch HTML using cURL directly for sites that need special handling.
		 *
		 * @param string $url The URL to fetch.
		 * @param string|null &$error_ref Reference to store error messages.
		 * @return string|null HTML content on success, null on failure.
		 */
		protected function _fetch_html_curl(string $url, ?string &$error_ref = null): ?string {
			if (!function_exists('curl_init')) {
				$error_ref = __('cURL is not available on this server.', 'housefresh-tools');
				return null;
			}

			$ch = curl_init();
			
			// Set cURL options matching your working script
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
			
			// Cookie handling
			$cookie_file = wp_upload_dir()['basedir'] . '/hft-cookies-' . md5($url) . '.txt';
			curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
			curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
			
			// Headers matching your working script
			$headers = [
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
				'Accept-Language: en-US,en;q=0.9',
				'Accept-Encoding: gzip, deflate',
				'Cache-Control: no-cache',
				'Pragma: no-cache',
				'Connection: keep-alive',
				'Upgrade-Insecure-Requests: 1',
				'Sec-Fetch-Dest: document',
				'Sec-Fetch-Mode: navigate',
				'Sec-Fetch-Site: none',
				'Sec-Fetch-User: ?1'
			];
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_ENCODING, ''); // Handle compression
			
			// Execute request
			$response = curl_exec($ch);
			$errno = curl_errno($ch);
			$error = curl_error($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			
			curl_close($ch);
			
			// Clean up cookie file after a while (but keep it for the session)
			if (file_exists($cookie_file) && filemtime($cookie_file) < time() - 3600) {
				@unlink($cookie_file);
			}
			
			if ($errno) {
				$error_ref = sprintf(__('cURL Error %d: %s', 'housefresh-tools'), $errno, $error);
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
					error_log( '[HFT Scraper] cURL error for ' . $url . ': ' . $error_ref );
				}
				return null;
			}
			
			if ($http_code < 200 || $http_code >= 300) {
				$error_ref = sprintf(__('Failed to fetch URL: HTTP status code %d', 'housefresh-tools'), $http_code);
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
					error_log( '[HFT Scraper] cURL HTTP error for ' . $url . ': ' . $error_ref );
				}
				return null;
			}
			
			if (empty($response)) {
				$error_ref = __('Fetched HTML content is empty.', 'housefresh-tools');
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
					error_log( '[HFT Scraper] cURL empty response for ' . $url );
				}
				return null;
			}
			
			return $response;
		}

		/**
		 * Fetch HTML using ScrapingRobot API.
		 *
		 * @param string $url The URL to fetch.
		 * @param string|null &$error_ref Reference to store error messages.
		 * @return string|null HTML content on success, null on failure.
		 */
		protected function _fetch_html_scrapingrobot(string $url, ?string &$error_ref = null): ?string {
			// Get API key from settings
			$settings = get_option('hft_settings');
			$api_key = $settings['scrapingrobot_api_key'] ?? '';
			
			if (empty($api_key)) {
				$error_ref = __('ScrapingRobot API key not configured.', 'housefresh-tools');
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
					error_log( '[HFT Scraper] ScrapingRobot API key missing' );
				}
				return null;
			}
			
			// Build ScrapingRobot API URL
			$api_params = [
				'token' => $api_key,
				'url' => $url
			];
			
			// Only add render parameter if JS rendering is needed
			if ($this->should_render_javascript()) {
				$api_params['render'] = 'true';
			}
			
			$api_url = add_query_arg($api_params, 'https://api.scrapingrobot.com/');
			
			// Make request
			$args = [
				'timeout' => 60, // Longer timeout for rendering
				'headers' => [
					'Accept' => 'application/json',
				],
			];
			
			$response = wp_remote_get($api_url, $args);
			
			if (is_wp_error($response)) {
				$error_ref = __('Failed to connect to ScrapingRobot: ', 'housefresh-tools') . $response->get_error_message();
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
					error_log( '[HFT Scraper] ScrapingRobot connection error: ' . $error_ref );
				}
				return null;
			}
			
			$http_code = wp_remote_retrieve_response_code($response);
			$body = wp_remote_retrieve_body($response);
			
			// Try to decode response
			$json_response = json_decode($body, true);
			
			if ($json_response === null) {
				$error_ref = __('Invalid response from ScrapingRobot.', 'housefresh-tools');
				return null;
			}
			
			// Handle different response codes
			switch ($http_code) {
				case 200:
					if (isset($json_response['status']) && $json_response['status'] === 'SUCCESS' && isset($json_response['result'])) {
						return $json_response['result'];
					} else {
						$error_ref = __('ScrapingRobot returned unsuccessful status.', 'housefresh-tools');
						return null;
					}
					break;
					
				case 400:
					$error_ref = __('ScrapingRobot: Bad request. Check URL format.', 'housefresh-tools');
					break;
					
				case 401:
					$error_ref = __('ScrapingRobot: Invalid API key or insufficient credits.', 'housefresh-tools');
					break;
					
				case 429:
					$error_ref = __('ScrapingRobot: System overloaded. Please retry.', 'housefresh-tools');
					break;
					
				case 500:
					$error_ref = __('ScrapingRobot: Unable to scrape site (anti-scraping protection).', 'housefresh-tools');
					break;
					
				default:
					$error_ref = sprintf(__('ScrapingRobot: HTTP %d error.', 'housefresh-tools'), $http_code);
			}
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
				error_log( '[HFT Scraper] ScrapingRobot error for ' . $url . ': ' . $error_ref );
			}
			
			return null;
		}

		/**
		 * Check if this parser should use cURL.
		 * Base parser always returns false, subclasses can override.
		 *
		 * @return bool
		 */
		protected function should_use_curl(): bool {
			return false;
		}
		
		/**
		 * Check if this parser should use ScrapingRobot.
		 * Base parser always returns false, subclasses can override.
		 *
		 * @return bool
		 */
		protected function should_use_scrapingrobot(): bool {
			return false;
		}
		
		/**
		 * Check if this parser should render JavaScript when using ScrapingRobot.
		 * Base parser always returns false, subclasses can override.
		 *
		 * @return bool
		 */
		protected function should_render_javascript(): bool {
			return false;
		}

		/**
		 * Gets an instance of the Structured Data Helper.
		 *
		 * @return HFT_Structured_Data_Helper|null Instance or null if class not found.
		 */
		protected function _get_structured_data_helper(): ?HFT_Structured_Data_Helper {
			if ($this->structured_data_helper_instance === null) {
				 $helper_file = HFT_PLUGIN_PATH . 'includes/helpers/class-hft-structured-data-helper.php'; 
				 if ( ! class_exists('HFT_Structured_Data_Helper') && file_exists($helper_file) ) {
					require_once $helper_file;
				 }
				 if (class_exists('HFT_Structured_Data_Helper')) {
					 $this->structured_data_helper_instance = new HFT_Structured_Data_Helper();
				 } else {
					 return null;
				 }
			}
			return $this->structured_data_helper_instance;
		}

		// --- Optional Override Method for Child Classes --- 

		/**
		 * Allows child classes to modify the parse results obtained from the structured data helper.
		 *
		 * @param array $result The results obtained so far (from helper).
		 * @param string $html_content The raw HTML content.
		 * @return array The potentially modified result array.
		 */
		protected function _modify_parse_results(array $result, string $html_content): array {
			// Default implementation does nothing.
			// Child classes override this to add specific selector logic or overrides.
			return $result;
		}
	}
} 
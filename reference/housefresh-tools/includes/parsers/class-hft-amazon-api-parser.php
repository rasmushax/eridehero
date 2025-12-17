<?php
declare(strict_types=1);

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure the Parser Interface is loaded.
if ( ! interface_exists( 'HFT_ParserInterface' ) ) {
	$interface_path = HFT_PLUGIN_PATH . 'includes/interfaces/interface-hft-parser.php';
	if ( file_exists( $interface_path ) ) {
		require_once $interface_path;
	} else {
		return; // Cannot proceed without the interface
	}
}

// Ensure the Base Parser is loaded.
$base_parser_file = HFT_PLUGIN_PATH . 'includes/parsers/class-hft-base-parser.php';
if ( ! class_exists('HFT_Base_Parser') && file_exists( $base_parser_file ) ) {
	require_once $base_parser_file;
} elseif ( ! class_exists('HFT_Base_Parser') ) {
    return; // Stop if base class not found
}

// Ensure the new helper classes are loaded (consider using an autoloader in the future)
$locales_file = HFT_PLUGIN_PATH . 'includes/libs/amazon/class-hft-amazon-locales.php';
if ( file_exists( $locales_file ) ) {
	require_once $locales_file;
} else {
    return; // Stop if locales class not found
}
$signer_file = HFT_PLUGIN_PATH . 'includes/libs/amazon/class-hft-aws-v4-signer.php';
if ( file_exists( $signer_file ) ) {
	require_once $signer_file;
} else {
    return; // Stop if signer class not found
}

// Guzzle HTTP client (still needed for making requests)
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Use the new helper classes
use Housefresh\Tools\Libs\Amazon\HFT_Amazon_Locales;
use Housefresh\Tools\Libs\Amazon\HFT_Aws_V4_Signer;


if ( ! class_exists( 'HFT_Amazon_Api_Parser' ) && class_exists('HFT_Base_Parser') && class_exists('Housefresh\Tools\Libs\Amazon\HFT_Amazon_Locales') && class_exists('Housefresh\Tools\Libs\Amazon\HFT_Aws_V4_Signer') ) {
	/**
	 * Class HFT_Amazon_Api_Parser.
	 *
	 * Parses product information from Amazon using the Product Advertising API 5.0.
	 * Uses custom signing (HFT_Aws_V4_Signer) and Guzzle for HTTP requests.
	 */
	class HFT_Amazon_Api_Parser extends HFT_Base_Parser {

		private array $settings;
		private ?Client $guzzle_client = null;
		private static array $last_request_times = []; // Track last request time per associate tag

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->settings = get_option( 'hft_settings', [] );
		}

		/**
		 * Get Guzzle client instance.
		 */
		private function get_guzzle_client(): Client {
			if ( null === $this->guzzle_client ) {
				if (!class_exists('GuzzleHttp\Client')) {
					throw new \Exception('GuzzleHttp\Client class not found. Ensure Composer dependencies are installed and autoloaded.');
				}
				$this->guzzle_client = new Client([
					'timeout' => 15.0,
					'connect_timeout' => 10.0,
				]);
			}
			return $this->guzzle_client;
		}

		/**
		 * Enforce rate limiting for Amazon API requests.
		 * Amazon allows 1 request per second per associate tag.
		 * 
		 * @param string $associate_tag The associate tag being used.
		 */
		private function enforce_rate_limit( string $associate_tag ): void {
			$current_time = microtime(true);
			$min_interval = 1.2; // 1.2 seconds between requests for safety margin
			
			if ( isset( self::$last_request_times[$associate_tag] ) ) {
				$time_since_last = $current_time - self::$last_request_times[$associate_tag];
				if ( $time_since_last < $min_interval ) {
					$sleep_time = $min_interval - $time_since_last;
					usleep( (int)( $sleep_time * 1000000 ) ); // Convert to microseconds
				}
			}
			
			self::$last_request_times[$associate_tag] = microtime(true);
		}

		/**
		 * Make Amazon API request with retry logic for 429 errors.
		 * 
		 * @param string $api_endpoint The API endpoint URL.
		 * @param array $request_headers The signed headers.
		 * @param string $payload The request payload.
		 * @param int $attempt Current attempt number (starts at 1).
		 * @param int $max_attempts Maximum number of attempts.
		 * @return array Response with status_code and body.
		 */
		private function make_api_request_with_retry( string $api_endpoint, array $request_headers, string $payload, int $attempt = 1, int $max_attempts = 3 ): array {
			try {
				$client = $this->get_guzzle_client();
				$response = $client->request('POST', $api_endpoint, [
					'headers' => $request_headers,
					'body'    => $payload,
					'http_errors' => false,
				]);

				$status_code = $response->getStatusCode();
				$body = $response->getBody()->getContents();
				
				// If we get a 429, retry with exponential backoff
				if ( $status_code === 429 && $attempt < $max_attempts ) {
					$delay = pow(2, $attempt - 1); // 1s, 2s, 4s
					error_log("[HFT Amazon Parser] Rate limited (429), retrying in {$delay}s (attempt {$attempt}/{$max_attempts})");
					sleep($delay);
					return $this->make_api_request_with_retry( $api_endpoint, $request_headers, $payload, $attempt + 1, $max_attempts );
				}
				
				return [
					'status_code' => $status_code,
					'body' => $body,
					'attempt' => $attempt,
				];
				
			} catch (RequestException $e) {
				$error_message = 'Guzzle Request Error: ' . $e->getMessage();
				if ($e->hasResponse()) {
					$error_message .= ' | Status: ' . $e->getResponse()->getStatusCode();
					$error_message .= ' | Body: ' . $e->getResponse()->getBody()->getContents();
				}
				error_log("[HFT Amazon Parser] Guzzle RequestException: " . $error_message);
				
				return [
					'status_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0,
					'body' => '',
					'error' => $error_message,
					'attempt' => $attempt,
				];
			} catch (\Exception $e) {
				error_log("[HFT Amazon Parser] General Exception: " . $e->getMessage());
				
				return [
					'status_code' => 0,
					'body' => '',
					'error' => 'General Error during Amazon API call: ' . $e->getMessage(),
					'attempt' => $attempt,
				];
			}
		}

		/**
		 * Parses the given Amazon ASIN via PAAPI 5 using custom signing.
		 */
		public function parse( string $url_or_identifier, array $link_meta = [] ): array {
			$asin = $url_or_identifier;
			$tracked_link_data = $link_meta;

			$default_response = [
				'price'         => null,
				'currency'      => null,
				'status'        => null,
				'shipping_info' => null,
				'error'         => null,
			];

			$api_key = isset($this->settings['amazon_api_key']) ? trim($this->settings['amazon_api_key']) : null;
			$api_secret = isset($this->settings['amazon_api_secret']) ? trim($this->settings['amazon_api_secret']) : null;
			$associate_tags_by_geo = $this->settings['amazon_associate_tags'] ?? [];

			if ( empty( $api_key ) || empty( $api_secret ) ) {
				$default_response['error'] = __( 'Amazon API Key or Secret not configured.', 'housefresh-tools' );
				return $default_response;
			}

			if ( ! preg_match('/^[A-Z0-9]{10}$/', $asin) ) {
				 $default_response['error'] = sprintf(__( 'Invalid ASIN format provided: %s', 'housefresh-tools' ), $asin);
				 return $default_response;
			}

            // Get locale details using the new helper class
            $target_geo = strtoupper($tracked_link_data['geo_target'] ?? 'US'); // Default to US if not set
            if (!HFT_Amazon_Locales::is_valid_geo($target_geo)) {
                $target_geo = 'US'; // Fallback to US if invalid GEO provided
            }

			$api_host = HFT_Amazon_Locales::get_api_host($target_geo);
			$aws_region = HFT_Amazon_Locales::get_region($target_geo);
			$marketplace_name = HFT_Amazon_Locales::get_marketplace_name($target_geo);

			if ( !$api_host || !$aws_region || !$marketplace_name ) {
				$default_response['error'] = sprintf(__( 'Could not determine Amazon marketplace details for GEO: %s.', 'housefresh-tools' ), $target_geo);
                error_log("[HFT Amazon Parser] Failed to get locale details for GEO: {$target_geo}");
				return $default_response;
			}

			$associate_tag = $this->get_associate_tag_for_geo( $target_geo, $associate_tags_by_geo );
			if ( ! $associate_tag ) {
				$default_response['error'] = sprintf( __( 'No Amazon Associate Tag configured for GEO: %s.', 'housefresh-tools' ), $target_geo );
				return $default_response;
			}

			$api_path = "/paapi5/getitems"; // API path for GetItems operation
            $service_name = "ProductAdvertisingAPI";
            $operation = "GetItems"; // Operation name for target header
			$target_header_value = "com.amazon.paapi5.v1.ProductAdvertisingAPIv1." . $operation;

			$resources = [
				"ItemInfo.Title",
				"Offers.Listings.Availability.Type",
				"Offers.Listings.Availability.Message",
				"Offers.Listings.Condition",
				"Offers.Listings.IsBuyBoxWinner",
				"Offers.Listings.MerchantInfo",
				"Offers.Listings.Price",
			];

			$payload_array = [
				'ItemIds'     => [$asin],
                'ItemIdType'  => 'ASIN', // Explicitly state ItemIdType
				'Resources'   => $resources,
				'PartnerTag'  => $associate_tag,
				'PartnerType' => 'Associates',
				'Marketplace' => $marketplace_name,
			];
			$payload = wp_json_encode($payload_array);
			if (false === $payload) {
				$default_response['error'] = 'Failed to encode API request payload.';
				return $default_response;
			}

            // --- Use Custom Signing Logic ---
            $request_headers = [];
            try {
                // Instantiate the custom signer
                $signer = new HFT_Aws_V4_Signer($api_key, $api_secret);
                $signer->setRegionName($aws_region);
                $signer->setServiceName($service_name);
                $signer->setPath($api_path);
                $signer->setPayload($payload);
                $signer->setRequestMethod('POST');

                // Add required headers for signing
                $signer->addHeader('host', $api_host);
                $signer->addHeader('content-encoding', 'amz-1.0');
                $signer->addHeader('content-type', 'application/json; charset=utf-8');
                $signer->addHeader('x-amz-target', $target_header_value);
                // The signer adds x-amz-date automatically

                // Get all signed headers, including Authorization
                $request_headers = $signer->getHeaders();

            } catch (\Exception $e) {
                $error_message = 'AWS Signature V4 Signing Error: ' . $e->getMessage();
                error_log("[HFT Amazon Parser] Signing Exception: " . $error_message);
                $default_response['error'] = $error_message;
                return $default_response;
            }
            // --- End Custom Signing Logic ---

			// Enforce rate limiting before making the request
			$this->enforce_rate_limit( $associate_tag );

			// Make the request with retry logic
			$api_endpoint = "https://" . $api_host . $api_path;
			$api_response = $this->make_api_request_with_retry( $api_endpoint, $request_headers, $payload );
			
			// Handle any request-level errors
			if ( isset( $api_response['error'] ) ) {
				$default_response['error'] = $api_response['error'];
				return $default_response;
			}
			
			$status_code = $api_response['status_code'];
			$body = $api_response['body'];
			$parsed_body = json_decode($body, true); // Decode as array

			// Check for HTTP errors or errors in the response body
			if ($status_code >= 400 || isset($parsed_body['Errors'])) {
				$error_message = "Amazon API Error (HTTP {$status_code}): ";
				if (isset($parsed_body['Errors']) && is_array($parsed_body['Errors']) && !empty($parsed_body['Errors'])) {
					$first_error = $parsed_body['Errors'][0];
					$error_message .= ($first_error['Code'] ?? 'UnknownCode') . ' - ' . ($first_error['Message'] ?? 'UnknownMessage');
				} else {
					$error_message .= "Check logs for raw response body.";
					error_log("[HFT Amazon Parser] Raw Error Body for ASIN {$asin} GEO {$target_geo}: " . $body);
				}
				$default_response['error'] = $error_message;
				error_log("[HFT Amazon Parser] API Error Log: " . $error_message);
				return $default_response;
			}

			// --- Process successful response (raw JSON) ---
			if (isset($parsed_body['ItemsResult']['Items'][0])) {
				$item = $parsed_body['ItemsResult']['Items'][0];

				$offers_data = $item['Offers'] ?? null;
				$listings = $offers_data['Listings'] ?? [];

				if (empty($listings)) {
					// No listings means item is not available for purchase
					$default_response['status'] = 'Out of Stock';
					$default_response['price'] = null;
					$default_response['currency'] = null;
					// Don't treat this as an error - it's a valid state
					return $default_response;
				}

				$buy_box_listing = null;
				$lowest_new_listing = null;

				foreach ($listings as $listing) {
					if (isset($listing['IsBuyBoxWinner']) && $listing['IsBuyBoxWinner'] === true) {
						$buy_box_listing = $listing;
						break;
					}
					$condition = $listing['Condition']['Value'] ?? null;
					$price_amount = $listing['Price']['Amount'] ?? null;
					if ($condition === 'New' && $price_amount !== null) {
						$current_price = (float) $price_amount;
						if ($lowest_new_listing === null) {
							$lowest_new_listing = $listing;
						} else {
							$lowest_price_so_far = (float) ($lowest_new_listing['Price']['Amount'] ?? PHP_FLOAT_MAX);
							if ($current_price < $lowest_price_so_far) {
								$lowest_new_listing = $listing;
							}
						}
					}
				}

				$target_listing = $buy_box_listing ?? $lowest_new_listing;

				if ($target_listing) {
					// Simplified availability logic based on presence of price and listings
					$price_info = $target_listing['Price'] ?? null;
					if ($price_info && isset($price_info['Amount'])) {
						$default_response['price'] = (float) $price_info['Amount'];
						$default_response['currency'] = $price_info['Currency'] ?? null;
						// If we have a price, the item is available for purchase
						$default_response['status'] = 'In Stock';
					} else {
						// No price but listing exists - item is not currently purchasable
						$default_response['status'] = 'Price Unavailable';
					}

					// Use availability message for additional context only
					$availability = $target_listing['Availability'] ?? [];
					$availability_message = $availability['Message'] ?? '';

					// Check for specific conditions that override the simple logic
					if (!empty($availability_message)) {
						// Check for pre-order indicators
						if (stripos($availability_message, 'pre-order') !== false ||
							stripos($availability_message, 'preorder') !== false ||
							stripos($availability_message, 'vorbestellung') !== false ||
							stripos($availability_message, 'précommande') !== false) {
							$default_response['status'] = 'Pre-Order';
						}
						// Check for explicit out of stock in any language
						elseif (stripos($availability_message, 'out of stock') !== false ||
								stripos($availability_message, 'nicht auf lager') !== false ||
								stripos($availability_message, 'rupture de stock') !== false ||
								stripos($availability_message, 'esaurito') !== false) {
							$default_response['status'] = 'Out of Stock';
							$default_response['price'] = null; // Clear price for out of stock items
						}
						// Add shipping delay info if item is in stock but has delay message
						elseif ($default_response['status'] === 'In Stock' &&
								(stripos($availability_message, 'ships in') !== false ||
								 stripos($availability_message, 'usually ships') !== false ||
								 stripos($availability_message, 'expédié sous') !== false ||
								 stripos($availability_message, 'versand in') !== false)) {
							// Keep as "In Stock" but could append delay info if needed
							// Optionally: $default_response['status'] = 'In Stock (' . esc_html($availability_message) . ')';
						}
					}

					$merchant_name = $target_listing['MerchantInfo']['Name'] ?? '';
					$deal_access_type = '';

					if (stripos($merchant_name, 'amazon') !== false) {
						 $default_response['shipping_info'] = 'Usually Prime Eligible';
					} else if (!empty($availability_message) && stripos($availability_message, 'free shipping') !== false) {
						$default_response['shipping_info'] = 'Free Shipping Eligible';
					} else if (!empty($availability_message) && stripos($availability_message, 'prime') !== false) {
						$default_response['shipping_info'] = 'Prime Eligible';
					} else {
					   $default_response['shipping_info'] = null;
					}

				} else {
					// No suitable listing found (no buy box winner and no new items)
					$default_response['status'] = 'Out of Stock';
					$default_response['price'] = null;
					$default_response['currency'] = null;
					error_log("[HFT Amazon Parser] No Buy Box or New listing found for ASIN {$asin} in GEO {$target_geo}");
				}
			} else {
				$default_response['error'] = 'Item not found or invalid response structure for ASIN: ' . $asin;
				error_log("[HFT Amazon Parser] Unexpected response structure for ASIN {$asin} GEO {$target_geo}. Raw Body: " . $body);
			}

			return $default_response;
		}

		/**
		 * Gets the associate tag for a given GEO.
		 * Moved from helper methods as it uses plugin settings.
		 */
		private function get_associate_tag_for_geo( string $target_geo, array $tags_by_geo ): ?string {
			$target_geo_upper = strtoupper($target_geo);
			$global_tag = null;

			foreach ( $tags_by_geo as $tag_config ) {
				$config_geo = isset($tag_config['geo']) && is_string($tag_config['geo']) ? strtoupper(trim($tag_config['geo'])) : null;
				$tag_value = isset($tag_config['tag']) ? trim($tag_config['tag']) : null;

				if ( $config_geo === $target_geo_upper && !empty($tag_value) ) {
					return $tag_value; // Exact match found
				}
				if (($config_geo === 'GLOBAL' || empty($config_geo)) && !empty($tag_value)) {
				    $global_tag = $tag_value; // Store potential global tag
				}
			}
			return $global_tag; // Return global tag if no exact match, or null
		}

		/**
		 * {@inheritdoc}
		 */
		public function get_source_type_label(): string {
			return __( 'Amazon Product (ASIN)', 'housefresh-tools' );
		}

		/**
		 * {@inheritdoc}
		 */
		public function get_identifier_label(): string {
			return __( 'ASIN', 'housefresh-tools' );
		}

		/**
		 * {@inheritdoc}
		 */
		public function get_identifier_placeholder(): string {
			return __( 'e.g., B00XXXXXXX', 'housefresh-tools' );
		}

		/**
		 * {@inheritdoc}
		 */
		public function is_identifier_asin(): bool {
			return true;
		}

		/**
		 * {@inheritdoc}
		 */
		public function get_source_type_slug(): string {
			return 'amazon';
		}

        // Remove previous helper methods as they are now in HFT_Amazon_Locales
        // private function extract_asin_from_url(...)
        // public function get_marketplace_host_for_asin(...)
        // private function get_aws_region_from_host(...)
        // private function get_marketplace_name_from_host(...)
        // private function get_target_geo_from_host(...)
        // private function get_currency_for_host(...)
	}
} 
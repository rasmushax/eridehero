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

// Ensure the helper classes are loaded
$locales_file = HFT_PLUGIN_PATH . 'includes/libs/amazon/class-hft-amazon-locales.php';
if ( file_exists( $locales_file ) ) {
	require_once $locales_file;
} else {
    return;
}
$auth_file = HFT_PLUGIN_PATH . 'includes/libs/amazon/class-hft-creators-api-auth.php';
if ( file_exists( $auth_file ) ) {
	require_once $auth_file;
} else {
    return;
}

// Guzzle HTTP client
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Use the helper classes
use Housefresh\Tools\Libs\Amazon\HFT_Amazon_Locales;
use Housefresh\Tools\Libs\Amazon\HFT_Creators_Api_Auth;


if ( ! class_exists( 'HFT_Amazon_Api_Parser' ) && class_exists('HFT_Base_Parser') && class_exists('Housefresh\Tools\Libs\Amazon\HFT_Amazon_Locales') && class_exists('Housefresh\Tools\Libs\Amazon\HFT_Creators_Api_Auth') ) {
	/**
	 * Class HFT_Amazon_Api_Parser.
	 *
	 * Parses product information from Amazon using the Creators API.
	 * Uses OAuth 2.0 bearer tokens and Guzzle for HTTP requests.
	 */
	class HFT_Amazon_Api_Parser extends HFT_Base_Parser {

		private const API_ENDPOINT = 'https://creatorsapi.amazon/catalog/v1/getItems';

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
		 * Make Creators API request with retry logic for 429 errors.
		 *
		 * @param array $request_headers The request headers.
		 * @param string $payload The request payload.
		 * @param int $attempt Current attempt number (starts at 1).
		 * @param int $max_attempts Maximum number of attempts.
		 * @return array Response with status_code and body.
		 */
		private function make_api_request_with_retry( array $request_headers, string $payload, int $attempt = 1, int $max_attempts = 3 ): array {
			try {
				$client = $this->get_guzzle_client();
				$response = $client->request('POST', self::API_ENDPOINT, [
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
					return $this->make_api_request_with_retry( $request_headers, $payload, $attempt + 1, $max_attempts );
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
		 * Get credentials for a given region group from settings.
		 *
		 * @param string $region_group The region group ('NA', 'EU', or 'FE').
		 * @return array|null ['credential_id' => ..., 'credential_secret' => ...] or null if not configured.
		 */
		private function get_credentials_for_region(string $region_group): ?array {
			$credentials = $this->settings['amazon_credentials'] ?? [];
			$region_creds = $credentials[$region_group] ?? null;

			if ( ! $region_creds || empty($region_creds['credential_id']) || empty($region_creds['credential_secret']) ) {
				return null;
			}

			return [
				'credential_id'     => trim($region_creds['credential_id']),
				'credential_secret' => trim($region_creds['credential_secret']),
			];
		}

		/**
		 * Parses the given Amazon ASIN via the Creators API.
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

			if ( ! preg_match('/^[A-Z0-9]{10}$/', $asin) ) {
				 $default_response['error'] = sprintf(__( 'Invalid ASIN format provided: %s', 'housefresh-tools' ), $asin);
				 return $default_response;
			}

            // Get locale details
            $target_geo = strtoupper($tracked_link_data['geo_target'] ?? 'US');
            if (!HFT_Amazon_Locales::is_valid_geo($target_geo)) {
                $target_geo = 'US';
            }

			$region_group = HFT_Amazon_Locales::get_region_group($target_geo);
			$credential_version = HFT_Amazon_Locales::get_credential_version($target_geo);
			$marketplace_name = HFT_Amazon_Locales::get_marketplace_name($target_geo);
			$token_endpoint = HFT_Amazon_Locales::get_token_endpoint($target_geo);

			if ( !$region_group || !$credential_version || !$marketplace_name || !$token_endpoint ) {
				$default_response['error'] = sprintf(__( 'Could not determine Amazon marketplace details for GEO: %s.', 'housefresh-tools' ), $target_geo);
                error_log("[HFT Amazon Parser] Failed to get locale details for GEO: {$target_geo}");
				return $default_response;
			}

			// Get credentials for this region
			$creds = $this->get_credentials_for_region($region_group);
			if ( ! $creds ) {
				$default_response['error'] = sprintf(__( 'Amazon Creators API credentials not configured for region: %s.', 'housefresh-tools' ), $region_group);
				return $default_response;
			}

			$associate_tags_by_geo = $this->settings['amazon_associate_tags'] ?? [];
			$associate_tag = $this->get_associate_tag_for_geo( $target_geo, $associate_tags_by_geo );
			if ( ! $associate_tag ) {
				$default_response['error'] = sprintf( __( 'No Amazon Associate Tag configured for GEO: %s.', 'housefresh-tools' ), $target_geo );
				return $default_response;
			}

			// Get OAuth 2.0 bearer token
			$access_token = HFT_Creators_Api_Auth::get_access_token(
				$region_group,
				$creds['credential_id'],
				$creds['credential_secret'],
				$token_endpoint
			);
			if ( ! $access_token ) {
				$default_response['error'] = sprintf(__( 'Failed to obtain Creators API access token for region: %s.', 'housefresh-tools' ), $region_group);
				return $default_response;
			}

			// Build request
			$resources = [
				'itemInfo.title',
				'offersV2.listings.availability',
				'offersV2.listings.condition',
				'offersV2.listings.isBuyBoxWinner',
				'offersV2.listings.merchantInfo',
				'offersV2.listings.price',
			];

			$payload_array = [
				'itemIds'     => [$asin],
				'itemIdType'  => 'ASIN',
				'marketplace' => $marketplace_name,
				'partnerTag'  => $associate_tag,
				'resources'   => $resources,
			];
			$payload = wp_json_encode($payload_array);
			if (false === $payload) {
				$default_response['error'] = 'Failed to encode API request payload.';
				return $default_response;
			}

			$request_headers = [
				'Authorization'  => "Bearer {$access_token}, Version {$credential_version}",
				'Content-Type'   => 'application/json',
				'x-marketplace'  => $marketplace_name,
			];

			// Enforce rate limiting before making the request
			$this->enforce_rate_limit( $associate_tag );

			// Make the request with retry logic
			$api_response = $this->make_api_request_with_retry( $request_headers, $payload );

			// Handle token expiry: if 401 with TokenExpired, clear cache and retry once
			if ( $api_response['status_code'] === 401 ) {
				$parsed_error = json_decode($api_response['body'] ?? '', true);
				if ( isset($parsed_error['reason']) && $parsed_error['reason'] === 'TokenExpired' ) {
					error_log("[HFT Amazon Parser] Token expired for region {$region_group}, refreshing...");
					HFT_Creators_Api_Auth::clear_cached_token($region_group);

					$access_token = HFT_Creators_Api_Auth::get_access_token(
						$region_group,
						$creds['credential_id'],
						$creds['credential_secret'],
						$token_endpoint
					);
					if ( ! $access_token ) {
						$default_response['error'] = sprintf(__( 'Failed to refresh Creators API access token for region: %s.', 'housefresh-tools' ), $region_group);
						return $default_response;
					}

					$request_headers['Authorization'] = "Bearer {$access_token}, Version {$credential_version}";
					$api_response = $this->make_api_request_with_retry( $request_headers, $payload );
				}
			}

			// Handle any request-level errors
			if ( isset( $api_response['error'] ) ) {
				$default_response['error'] = $api_response['error'];
				return $default_response;
			}

			$status_code = $api_response['status_code'];
			$body = $api_response['body'];
			$parsed_body = json_decode($body, true);

			// Check for HTTP errors or errors in the response body
			if ($status_code >= 400 || isset($parsed_body['type'])) {
				$error_message = "Amazon Creators API Error (HTTP {$status_code}): ";
				if (isset($parsed_body['message'])) {
					$error_type = $parsed_body['type'] ?? 'UnknownType';
					$error_reason = isset($parsed_body['reason']) ? " (Reason: {$parsed_body['reason']})" : '';
					$error_message .= "{$error_type} - {$parsed_body['message']}{$error_reason}";
				} else {
					$error_message .= "Check logs for raw response body.";
					error_log("[HFT Amazon Parser] Raw Error Body for ASIN {$asin} GEO {$target_geo}: " . $body);
				}
				$default_response['error'] = $error_message;
				error_log("[HFT Amazon Parser] API Error Log: " . $error_message);
				return $default_response;
			}

			// --- Process successful response ---
			if (isset($parsed_body['itemsResult']['items'][0])) {
				$item = $parsed_body['itemsResult']['items'][0];

				$offers_data = $item['offersV2'] ?? null;
				$listings = $offers_data['listings'] ?? [];

				if (empty($listings)) {
					$default_response['status'] = 'Out of Stock';
					$default_response['price'] = null;
					$default_response['currency'] = null;
					return $default_response;
				}

				$buy_box_listing = null;
				$lowest_new_listing = null;

				foreach ($listings as $listing) {
					if (isset($listing['isBuyBoxWinner']) && $listing['isBuyBoxWinner'] === true) {
						$buy_box_listing = $listing;
						break;
					}
					$condition = $listing['condition']['value'] ?? null;
					$price_amount = $listing['price']['money']['amount'] ?? null;
					if ($condition === 'New' && $price_amount !== null) {
						$current_price = (float) $price_amount;
						if ($lowest_new_listing === null) {
							$lowest_new_listing = $listing;
						} else {
							$lowest_price_so_far = (float) ($lowest_new_listing['price']['money']['amount'] ?? PHP_FLOAT_MAX);
							if ($current_price < $lowest_price_so_far) {
								$lowest_new_listing = $listing;
							}
						}
					}
				}

				$target_listing = $buy_box_listing ?? $lowest_new_listing;

				if ($target_listing) {
					$price_info = $target_listing['price']['money'] ?? null;
					if ($price_info && isset($price_info['amount'])) {
						$default_response['price'] = (float) $price_info['amount'];
						$default_response['currency'] = $price_info['currency'] ?? null;
						$default_response['status'] = 'In Stock';
					} else {
						$default_response['status'] = 'Price Unavailable';
					}

					$availability = $target_listing['availability'] ?? [];
					$availability_type = $availability['type'] ?? '';
					$availability_message = $availability['message'] ?? '';

					if (!empty($availability_message)) {
						if (stripos($availability_message, 'pre-order') !== false ||
							stripos($availability_message, 'preorder') !== false ||
							stripos($availability_message, 'vorbestellung') !== false ||
							stripos($availability_message, 'précommande') !== false) {
							$default_response['status'] = 'Pre-Order';
						}
						elseif (stripos($availability_message, 'out of stock') !== false ||
								stripos($availability_message, 'nicht auf lager') !== false ||
								stripos($availability_message, 'rupture de stock') !== false ||
								stripos($availability_message, 'esaurito') !== false) {
							$default_response['status'] = 'Out of Stock';
							$default_response['price'] = null;
						}
					}

					$merchant_name = $target_listing['merchantInfo']['name'] ?? '';

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
				    $global_tag = $tag_value;
				}
			}
			return $global_tag;
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
	}
}

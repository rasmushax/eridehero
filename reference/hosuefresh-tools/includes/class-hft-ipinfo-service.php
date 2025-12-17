<?php
declare(strict_types=1);

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HFT_IPInfo_Service' ) ) {
	/**
	 * Class HFT_IPInfo_Service.
	 *
	 * Simple wrapper for IPInfo API calls.
	 */
	class HFT_IPInfo_Service {

		private ?string $api_token;
		private string $default_country_code;

		public function __construct() {
			$options = get_option( 'hft_settings', [] );
			$this->api_token = $options['ipinfo_api_token'] ?? null;
			$this->default_country_code = 'US'; // Default fallback country
		}

		/**
		 * Get country code for IP address.
		 *
		 * @param string $ip_address The IP address to look up.
		 * @return array Array with 'country_code', 'ip', and optional 'source' or 'error' keys.
		 */
		public function get_country_code( string $ip_address ): array {
			// Validate IP address
			if ( ! filter_var( $ip_address, FILTER_VALIDATE_IP ) ) {
				error_log('[Housefresh Tools IPInfo] Invalid IP address: ' . $ip_address);
				return [
					'country_code' => $this->default_country_code,
					'ip' => $ip_address,
					'error' => 'Invalid IP address format'
				];
			}

			try {
				// Check if IPInfo client is available with correct namespace
				if ( ! class_exists( 'ipinfo\ipinfo\IPinfo' ) ) {
					error_log('[Housefresh Tools IPInfo] ipinfo\ipinfo\IPinfo class not found. Ensure composer dependencies are installed.');
					return [
						'country_code' => $this->default_country_code,
						'ip' => $ip_address,
						'error' => 'IPInfo library not available'
					];
				}

				// Create IPInfo client with correct namespace
				$client = new \ipinfo\ipinfo\IPinfo( $this->api_token );
				
				// Get IP details
				$details = $client->getDetails( $ip_address );
				
				// Extract country code
				$country_code = $details->country ?? null;
				
				if ( ! empty( $country_code ) && strlen( $country_code ) === 2 ) {
					error_log('[Housefresh Tools IPInfo] Successfully looked up IP ' . $ip_address . ': ' . $country_code);
					return [
						'country_code' => strtoupper( $country_code ),
						'ip' => $ip_address,
						'source' => 'ipinfo_api'
					];
				} else {
					error_log('[Housefresh Tools IPInfo] No valid country code returned for IP: ' . $ip_address);
					return [
						'country_code' => $this->default_country_code,
						'ip' => $ip_address,
						'note' => 'No country code in IPInfo response'
					];
				}

			} catch ( Exception $e ) {
				error_log('[Housefresh Tools IPInfo] Exception during lookup for IP ' . $ip_address . ': ' . $e->getMessage());
				return [
					'country_code' => $this->default_country_code,
					'ip' => $ip_address,
					'error' => 'IPInfo API error: ' . $e->getMessage()
				];
			}
		}
	}
}
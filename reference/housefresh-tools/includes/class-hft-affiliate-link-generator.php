<?php
declare(strict_types=1);

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HFT_Affiliate_Link_Generator' ) ) {
	/**
	 * Class HFT_Affiliate_Link_Generator.
	 *
	 * Handles the generation of affiliate links based on scraper settings.
	 */
	class HFT_Affiliate_Link_Generator {

		/**
		 * Generates an affiliate link for a given original URL, target GEO, and optional product ID.
		 *
		 * @param string      $original_url The original product URL.
		 * @param string|null $target_geo The target GEO (e.g., 'US', 'GB'). Defaults to 'US' if null.
		 * @param string|null $product_id_override An optional product identifier (e.g., ASIN).
		 * @return string|null The affiliate link, or null if no transformation applies.
		 */
		public static function get_affiliate_link(string $original_url, ?string $target_geo = null, ?string $product_id_override = null): ?string {
			$geo_to_use = strtoupper(trim($target_geo ?? 'US'));
			$main_settings = get_option('hft_settings', []); // Get main settings for Amazon tags

			$is_amazon_link = ($product_id_override && self::is_valid_asin($product_id_override)) || self::is_amazon_url($original_url);

			// --- Handle Amazon Link --- 
			if ($is_amazon_link) {
				$asin = $product_id_override ?: self::extract_asin_from_url($original_url);
				if (!$asin || !self::is_valid_asin($asin)) {
					return null; // Cannot proceed without a valid ASIN, return null
				}

				// Find the Associate Tag for the target GEO from main settings
				$associate_tag = '';
				$amazon_tags = $main_settings['amazon_associate_tags'] ?? [];
				if (is_array($amazon_tags)) {
					foreach ($amazon_tags as $tag_data) {
						if (isset($tag_data['geo'], $tag_data['tag']) && strtoupper(trim($tag_data['geo'])) === $geo_to_use) {
							$associate_tag = trim($tag_data['tag']);
							break;
						}
					}
				}
				if (empty($associate_tag)) {
					return null; // No tag configured for this GEO, return null
				}

				// Determine Amazon domain based on GEO
				$geo_to_marketplace_domain = [
					'US' => 'www.amazon.com', 'CA' => 'www.amazon.ca', 'GB' => 'www.amazon.co.uk', 'DE' => 'www.amazon.de', 'FR' => 'www.amazon.fr',
					'ES' => 'www.amazon.es', 'IT' => 'www.amazon.it', 'AU' => 'www.amazon.com.au', 'BR' => 'www.amazon.com.br', 'IN' => 'www.amazon.in',
					'JP' => 'www.amazon.co.jp', 'MX' => 'www.amazon.com.mx'
				];
				$amazon_domain = $geo_to_marketplace_domain[$geo_to_use] ?? 'www.amazon.com'; // Default to .com

				// Construct the final Amazon affiliate link
				$affiliate_link = sprintf('https://%s/dp/%s?tag=%s', $amazon_domain, $asin, $associate_tag);
				return $affiliate_link;
			}

			// --- Handle Custom Scraper Link --- 
			// Get domain from URL
			$url_host = parse_url($original_url, PHP_URL_HOST);
			if (!empty($url_host)) {
				// Try to find a scraper for this domain
				$registry = HFT_Scraper_Registry::get_instance();
				$domain = $registry->extract_domain_from_url($original_url);
				
				if ($domain) {
					$scraper = $registry->get_scraper_for_domain($domain);
					
					if ($scraper && !empty($scraper->affiliate_format)) {
						// Check if this scraper applies to the target GEO
						$allowed_geos = [];
						if (!empty($scraper->geos)) {
							$allowed_geos = array_map('strtoupper', array_map('trim', explode(',', $scraper->geos)));
						}
						
						// If no GEOs configured, or target GEO is in allowed list
						if (empty($allowed_geos) || in_array($geo_to_use, $allowed_geos, true)) {
							// Prepare placeholders
							$id_to_use = $product_id_override ?? '';
							
							$replacements = [
								'{URL}'  => $original_url,
								'{URLE}' => rawurlencode($original_url),
								'{ID}'   => $id_to_use
							];
							
							return str_replace(array_keys($replacements), array_values($replacements), $scraper->affiliate_format);
						}
					}
				}
			}

			return null; // Fallback: no matching configuration found or other failure, return null
		}

		/**
		 * Checks if the given URL is a recognized Amazon domain.
		 *
		 * @param string $url
		 * @return boolean
		 */
		public static function is_amazon_url(string $url): bool {
			$host = parse_url($url, PHP_URL_HOST);
			if (!is_string($host) || empty($host)) {
				return false;
			}
			// Common Amazon TLDs - can be expanded
			$amazon_domains = [
				'amazon.com',
				'amazon.co.uk',
				'amazon.ca',
				'amazon.de',
				'amazon.fr',
				'amazon.es',
				'amazon.it',
				'amazon.com.au',
				'amazon.com.br',
				'amazon.in',
				'amazon.co.jp',
				'amazon.com.mx'
			];
			foreach ($amazon_domains as $amazon_domain) {
				// Ensure $host is a string before using strpos
				if (is_string($host) && strpos($host, $amazon_domain) !== false) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Extracts ASIN from an Amazon product URL.
		 *
		 * @param string $url
		 * @return string|null ASIN or null if not found.
		 */
		public static function extract_asin_from_url(string $url): ?string {
			// Regex for ASIN in URL (typically /dp/ASIN or /gp/product/ASIN)
			// B0[0-9A-Z]{8} is a common pattern for ASINs
			if (preg_match('/(?:\/dp\/|\/gp\/product\/)([A-Z0-9]{10})/', $url, $matches)) {
				if (isset($matches[1]) && self::is_valid_asin($matches[1])) {
					return $matches[1];
				}
			}
			return null;
		}

		/**
		 * Validates if a string is a plausible ASIN format.
		 *
		 * @param string $asin
		 * @return boolean
		 */
		public static function is_valid_asin(string $asin): bool {
			// ASINs are typically 10 characters, alphanumeric.
			// More specific regex: ^B0[A-Z0-9]{8}$ often for newer ones, but can vary.
			// For simplicity, checking length and alphanumeric.
			return strlen($asin) === 10 && ctype_alnum($asin);
		}
	}
} 
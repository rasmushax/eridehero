<?php
declare(strict_types=1);

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HFT_Structured_Data_Helper' ) ) { // Renamed class
	/**
	 * Class HFT_Structured_Data_Helper. // Renamed class
	 *
	 * Provides helper methods to extract common product data from structured data formats
	 * like JSON-LD, Microdata, and OpenGraph meta tags.
	 */
	class HFT_Structured_Data_Helper { // Renamed class

		public function __construct() {
			// Constructor
		}

		/**
		 * Extracts product data from HTML content using various structured data methods.
		 *
		 * @param string $html_content The HTML content of the product page.
		 * @return array An array containing extracted data:
		 *               [
		 *                   'price'    => (float|null),
		 *                   'currency' => (string|null),
		 *                   'status'   => (string|null) 'In Stock', 'Out of Stock', or null.
		 *                   'error'    => (string|null) If any specific error occurred during extraction.
		 *               ]
		 */
		public function extract_data_from_html( string $html_content ): array {
			$data = [
				'price'    => null,
				'currency' => null,
				'status'   => null,
				'error'    => null,
			];

			if ( empty( $html_content ) ) {
				$data['error'] = 'HTML content was empty.';
				return $data;
			}

			libxml_use_internal_errors(true);
			$doc = new DOMDocument();
			$doc->loadHTML($html_content);
			libxml_clear_errors();
			$xpath = new DOMXPath($doc);

			// --- Attempt extraction methods in order of preference --- 

			// 1. Try JSON-LD
			$json_ld_data = $this->_extract_from_json_ld($xpath);
            $data = array_merge($data, array_filter($json_ld_data)); // Merge non-null values

			// 2. Try Microdata if data is still missing
            if ($data['price'] === null || $data['currency'] === null || $data['status'] === null) {
                $microdata_data = $this->_extract_from_microdata($xpath);
                // Only update fields that were previously null
                if ($data['price'] === null && $microdata_data['price'] !== null) $data['price'] = $microdata_data['price'];
                if ($data['currency'] === null && $microdata_data['currency'] !== null) $data['currency'] = $microdata_data['currency'];
                if ($data['status'] === null && $microdata_data['status'] !== null) $data['status'] = $microdata_data['status'];
            }

			// 3. Try OpenGraph if data is still missing
            if ($data['price'] === null || $data['currency'] === null || $data['status'] === null) {
                $opengraph_data = $this->_extract_from_opengraph($xpath);
                // Only update fields that were previously null
                if ($data['price'] === null && $opengraph_data['price'] !== null) $data['price'] = $opengraph_data['price'];
                if ($data['currency'] === null && $opengraph_data['currency'] !== null) $data['currency'] = $opengraph_data['currency'];
                if ($data['status'] === null && $opengraph_data['status'] !== null) $data['status'] = $opengraph_data['status'];
            }

			// Final cleanup/validation if needed
			// e.g., ensure currency is uppercase, status is standardized

			return $data;
		}

		// --- Private Helper Methods --- 

        /** Tries multiple XPath queries and returns the first non-empty result. */
        private function _xpath_query_first(DOMXPath $xpath, array $paths): ?string {
            foreach ($paths as $path) {
                $nodes = $xpath->query($path);
                if ($nodes && $nodes->length > 0) {
                    $value = trim($nodes->item(0)->nodeValue);
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
            return null;
        }

        /** Normalizes price strings (removes currency, commas). */
        private function _normalize_price(?string $price_string): ?float {
            if ($price_string === null || $price_string === '') return null;
            // Remove currency symbols, thousands separators, trim whitespace
            $cleaned_price = preg_replace('/[^0-9.,]/', '', $price_string);
            // Ensure $cleaned_price is not null or empty before string operations
            if (!is_string($cleaned_price) || $cleaned_price === '') {
                return null;
            }
            // Handle comma as decimal separator if needed (e.g., European format)
            if (strpos($cleaned_price, ',') !== false && strpos($cleaned_price, '.') === false) {
                $cleaned_price = str_replace(',', '.', $cleaned_price);
            }
            // Remove any remaining commas (as thousands separators)
            $cleaned_price = str_replace(',', '', $cleaned_price);
            
            return is_numeric($cleaned_price) ? (float) $cleaned_price : null;
        }

        /** Normalizes availability strings. */
        private function _normalize_status(?string $status_string): ?string {
            if ($status_string === null || $status_string === '') return null;
            $status_lower = strtolower(trim($status_string));
            // Ensure $status_lower is not empty before string operations
            if (!is_string($status_lower) || $status_lower === '') {
                return null;
            }

            // Check for positive indicators
            $in_stock_indicators = ['instock', 'in stock', 'available', 'onhand', 'http://schema.org/instock', 'https://schema.org/instock'];
            foreach ($in_stock_indicators as $indicator) {
                if (strpos($status_lower, $indicator) !== false) {
                    return 'In Stock';
                }
            }

            // Check for negative indicators
            $out_of_stock_indicators = ['outofstock', 'out of stock', 'soldout', 'sold out', 'discontinued', 'http://schema.org/outofstock', 'https://schema.org/outofstock', 'http://schema.org/soldout', 'https://schema.org/soldout', 'http://schema.org/discontinued', 'https://schema.org/discontinued'];
             foreach ($out_of_stock_indicators as $indicator) {
                if (strpos($status_lower, $indicator) !== false) {
                    return 'Out of Stock';
                }
            }
            
            // Could add checks for PreOrder, BackOrder etc. here if needed

            return null; // Could not determine standard status
        }

        /** Extracts data using JSON-LD scripts. */
        private function _extract_from_json_ld(DOMXPath $xpath): array {
            $data = ['price' => null, 'currency' => null, 'status' => null];
            $scripts = $xpath->query('//script[@type="application/ld+json"]');

            if (!$scripts) return $data;

            foreach ($scripts as $script) {
                if (empty($script->nodeValue)) continue;
                $json_data = json_decode($script->nodeValue, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($json_data)) continue;

                // Handle cases where JSON-LD contains a graph array
                $potential_products = [];
                if (isset($json_data['@graph']) && is_array($json_data['@graph'])) {
                    $potential_products = $json_data['@graph'];
                } else {
                    $potential_products[] = $json_data; // Treat as single object
                }

                foreach($potential_products as $item) {
                    if (!is_array($item)) continue;
                    // Look for Product or Offer types
                    $type = $item['@type'] ?? null;
                    if ($type !== 'Product' && $type !== 'Offer' && !is_array($type)) continue; // Basic type check
                     if (is_array($type) && !in_array('Product', $type) && !in_array('Offer', $type)) continue;

                    $offers = $item['offers'] ?? null;
                    if ($offers) {
                        $offer_data = null;
                        if (isset($offers['@type']) && $offers['@type'] === 'Offer') {
                            // Single offer object
                            $offer_data = $offers;
                        } elseif (isset($offers[0]['@type']) && $offers[0]['@type'] === 'Offer') {
                             // Array of offers, take the first one for now
                             // TODO: Could add logic to find best offer (e.g., lowest price, specific seller)
                             $offer_data = $offers[0];
                        }
                        
                        if ($offer_data) {
                            if ($data['price'] === null && isset($offer_data['price'])) $data['price'] = $this->_normalize_price((string)$offer_data['price']);
                            if ($data['price'] === null && isset($offer_data['lowPrice'])) $data['price'] = $this->_normalize_price((string)$offer_data['lowPrice']);
                            if ($data['price'] === null && isset($offer_data['priceSpecification']['price'])) $data['price'] = $this->_normalize_price((string)$offer_data['priceSpecification']['price']);

                            if ($data['currency'] === null && isset($offer_data['priceCurrency'])) $data['currency'] = strtoupper(trim((string)$offer_data['priceCurrency']));
                             if ($data['currency'] === null && isset($offer_data['priceSpecification']['priceCurrency'])) $data['currency'] = strtoupper(trim((string)$offer_data['priceSpecification']['priceCurrency']));

                            if ($data['status'] === null && isset($offer_data['availability'])) $data['status'] = $this->_normalize_status((string)$offer_data['availability']);
                        }
                    }
                    // Sometimes priceRange is at the Product level
                    if ($data['price'] === null && isset($item['priceRange'])) $data['price'] = $this->_normalize_price((string)$item['priceRange']);
                    // Sometimes price is directly on product (less common for specific price)
                     if ($data['price'] === null && isset($item['price'])) $data['price'] = $this->_normalize_price((string)$item['price']);
                    if ($data['currency'] === null && isset($item['priceCurrency'])) $data['currency'] = strtoupper(trim((string)$item['priceCurrency']));

                }
                // If we found all data from this script, no need to check others (usually)
                if ($data['price'] !== null && $data['currency'] !== null && $data['status'] !== null) break;
            }
            return $data;
        }

        /** Extracts data using Microdata itemprop attributes. */
        private function _extract_from_microdata(DOMXPath $xpath): array {
            $data = ['price' => null, 'currency' => null, 'status' => null];

            // Price
            $price_paths = [
                ".//*[@itemprop='offers']//*[@itemprop='price']/@content",
                ".//*[@itemtype='http://schema.org/Offer']//*[@itemprop='price']/@content",
                ".//*[@itemtype='http://schema.org/Product']//*[@itemprop='price']/@content",
                ".//*[@itemprop='price']/@content",
                ".//*[@itemprop='lowPrice']/@content", // Check lowPrice as well
                ".//*[@itemprop='offers']//*[@itemprop='price']", // Get text content if @content fails
                ".//*[@itemtype='http://schema.org/Offer']//*[@itemprop='price']",
                ".//*[@itemtype='http://schema.org/Product']//*[@itemprop='price']",
                 ".//*[@itemprop='price']",
                ".//*[@itemprop='lowPrice']"
            ];
            $data['price'] = $this->_normalize_price($this->_xpath_query_first($xpath, $price_paths));

            // Currency
            $currency_paths = [
                ".//*[@itemprop='offers']//*[@itemprop='priceCurrency']/@content",
                ".//*[@itemtype='http://schema.org/Offer']//*[@itemprop='priceCurrency']/@content",
                ".//*[@itemprop='priceCurrency']/@content",
            ];
            $currency_value = $this->_xpath_query_first($xpath, $currency_paths);
            if (is_string($currency_value) && $currency_value !== '') $data['currency'] = strtoupper(trim($currency_value));

            // Availability Status
            $availability_paths = [
                ".//*[@itemprop='offers']//*[@itemprop='availability']/@href", // Check href first
                ".//*[@itemtype='http://schema.org/Offer']//*[@itemprop='availability']/@href",
                ".//*[@itemprop='availability']/@href",
                ".//*[@itemprop='offers']//*[@itemprop='availability']/@content", // Then content
                ".//*[@itemtype='http://schema.org/Offer']//*[@itemprop='availability']/@content",
                ".//*[@itemprop='availability']/@content",
                 ".//*[@itemprop='availability']" // Then text content
            ];
             $data['status'] = $this->_normalize_status($this->_xpath_query_first($xpath, $availability_paths));

            return $data;
        }

        /** Extracts data using OpenGraph meta tags. */
        private function _extract_from_opengraph(DOMXPath $xpath): array {
             $data = ['price' => null, 'currency' => null, 'status' => null];

            // Price (og:price:amount)
            $price_value = $this->_xpath_query_first($xpath, ["//meta[@property='og:price:amount']/@content"]);
            $data['price'] = $this->_normalize_price($price_value);

            // Currency (og:price:currency)
            $currency_value = $this->_xpath_query_first($xpath, ["//meta[@property='og:price:currency']/@content"]);
             if (is_string($currency_value) && $currency_value !== '') $data['currency'] = strtoupper(trim($currency_value));

            // Availability (og:availability)
            $status_value = $this->_xpath_query_first($xpath, ["//meta[@property='og:availability']/@content"]);
            $data['status'] = $this->_normalize_status($status_value);

            return $data;
        }
	}
} 
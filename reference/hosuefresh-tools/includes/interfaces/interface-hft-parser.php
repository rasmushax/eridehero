<?php
declare(strict_types=1);

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface HFT_ParserInterface.
 *
 * Defines the contract for all product information parsers.
 */
interface HFT_ParserInterface {

	/**
	 * Parses the given URL to extract product information.
	 *
	 * @param string $url The URL of the product page to parse.
	 * @param array  $tracked_link_data The full data array for the tracked link from the hft_tracked_links table.
	 *                                  This can provide context like GEO targets or existing known data.
	 *
	 * @return array An array containing the parsed product data or an error message.
	 *               Expected keys on success:
	 *               'price'         => (float|null) The product price.
	 *               'currency'      => (string|null) The currency code (e.g., USD, EUR).
	 *               'status'        => (string|null) The product availability status (e.g., "In Stock", "Out of Stock").
	 *               'shipping_info' => (string|null) Brief shipping information, if available.
	 *               'error_message' => (string|null) Null if successful, or an error message string on failure.
	 *               All keys should be present, with null values if data is not found or applicable.
	 */
	public function parse( string $url, array $tracked_link_data ): array;

} 
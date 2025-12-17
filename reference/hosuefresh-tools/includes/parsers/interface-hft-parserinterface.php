<?php
declare(strict_types=1);

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! interface_exists( 'HFT_ParserInterface' ) ) {
	/**
	 * Interface HFT_ParserInterface
	 *
	 * Defines the contract for all product information parsers.
	 */
	interface HFT_ParserInterface {

		/**
		 * Parses the product information from the given URL or identifier.
		 *
		 * @param string $url_or_identifier The URL or unique identifier (e.g., ASIN) for the product.
		 * @param array  $link_meta Optional. Additional metadata related to the link, like GEO target.
		 * @return array An array containing structured product data:
		 *               [
		 *                   'price'         => (float|null) Product price,
		 *                   'currency'      => (string|null) Currency code (e.g., USD, EUR),
		 *                   'status'        => (string|null) Availability status (e.g., 'In Stock', 'Out of Stock'),
		 *                   'shipping_info' => (string|null) Brief shipping information,
		 *                   'error'         => (string|null) Error message if parsing failed.
		 *               ]
		 */
		public function parse(string $url_or_identifier, array $link_meta = []): array;

		/**
		 * Gets the unique slug for this parser's source type.
		 *
		 * @return string The source type slug (e.g., 'amazon_api', 'levoit_direct').
		 */
		public function get_source_type_slug(): string;

		/**
		 * Gets the human-readable label for this parser's source type.
		 *
		 * @return string The source type label (e.g., 'Amazon Product (API)').
		 */
		public function get_source_type_label(): string;

		/**
		 * Gets the label for the identifier input field for this source type.
		 *
		 * @return string The label (e.g., 'ASIN or Product URL', 'Product URL').
		 */
		public function get_identifier_label(): string;

		/**
		 * Gets the placeholder text for the identifier input field.
		 *
		 * @return string The placeholder text.
		 */
		public function get_identifier_placeholder(): string;

		/**
		 * Checks if the primary identifier for this parser is an ASIN.
		 * This can be used for conditional UI in meta boxes (e.g., showing GEO target field).
		 *
		 * @return bool True if the identifier is an ASIN, false otherwise.
		 */
		public function is_identifier_asin(): bool;
	}
} 
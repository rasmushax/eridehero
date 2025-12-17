<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HFT_Schema_Output' ) ) {
	/**
	 * Class HFT_Schema_Output.
	 *
	 * Handles outputting structured data (JSON-LD) for products on the frontend.
	 */
	class HFT_Schema_Output {

		public function __construct() {
			// Hook to output schema in the head
			add_action( 'wp_head', [ $this, 'output_product_schema' ] );
		}

		/**
		 * Output Product review schema if applicable.
		 */
		public function output_product_schema(): void {
			// Only run on single post pages
			if ( ! is_singular( 'post' ) ) {
				return;
			}

			// Get the current post ID
			$post_id = get_the_ID();
			if ( ! $post_id ) {
				return;
			}

			// Check if ACF function exists
			if ( ! function_exists( 'get_field' ) ) {
				return;
			}

			// Get the selected product
			$selected_product = get_field( 'hft_selected_product', $post_id );
			if ( ! $selected_product || ! is_object( $selected_product ) ) {
				return;
			}

			// Get the HouseFresh score
			$housefresh_score = get_field( 'housefresh_score', $selected_product->ID );
			if ( ! $housefresh_score || ! is_numeric( $housefresh_score ) ) {
				return;
			}

			// Implement caching for schema markup
			$cache_key = 'hft_schema_' . $post_id . '_' . $selected_product->ID . '_' . $housefresh_score;
			$cache_group = 'hft_frontend';
			
			// Try object cache first
			$schema = wp_cache_get( $cache_key, $cache_group );
			if ( false === $schema ) {
				// Try transient cache
				$schema = get_transient( $cache_key );
				if ( false === $schema ) {
					// Build the schema
					$schema = $this->build_product_review_schema( $selected_product, (float) $housefresh_score, $post_id );
					
					// Cache the schema
					if ( ! empty( $schema ) ) {
						set_transient( $cache_key, $schema, 2 * HOUR_IN_SECONDS );
						wp_cache_set( $cache_key, $schema, $cache_group, 2 * HOUR_IN_SECONDS );
					}
				} else {
					// Also set in object cache
					wp_cache_set( $cache_key, $schema, $cache_group, 2 * HOUR_IN_SECONDS );
				}
			}

			// Output the schema
			if ( ! empty( $schema ) ) {
				echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . '</script>' . "\n";
			}
		}

		/**
		 * Build the Product review schema array.
		 *
		 * @param WP_Post $product The product post object.
		 * @param float $score The HouseFresh score.
		 * @param int $post_id The post ID containing the review.
		 * @return array The schema array.
		 */
		private function build_product_review_schema( WP_Post $product, float $score, int $post_id ): array {
			// Get post data
			$post = get_post( $post_id );
			if ( ! $post ) {
				return [];
			}

			// Get author data
			$author_id = $post->post_author;
			$author_name = get_the_author_meta( 'display_name', $author_id );
			if ( empty( $author_name ) ) {
				$author_name = 'HouseFresh Team';
			}
			
			// Get author archive URL
			$author_url = get_author_posts_url( $author_id );
			if ( empty( $author_url ) ) {
				$author_url = home_url( '/author/housefresh-team/' );
			}

			// Get the product tracking links meta
			$tracking_links = get_post_meta( $product->ID, '_hft_tracking_links', true );
			$product_brand = '';
			$product_image = '';
			
			// Try to extract brand from tracking links
			if ( ! empty( $tracking_links ) && is_array( $tracking_links ) ) {
				foreach ( $tracking_links as $link ) {
					if ( ! empty( $link['merchant_name'] ) ) {
						// Use the first merchant as brand if no specific brand field exists
						$product_brand = $link['merchant_name'];
						break;
					}
				}
			}

			// Get featured image if available
			$thumbnail_id = get_post_thumbnail_id( $product->ID );
			if ( $thumbnail_id ) {
				$image_url = wp_get_attachment_image_url( $thumbnail_id, 'full' );
				if ( $image_url ) {
					$product_image = $image_url;
				}
			}

			// Build the schema
			$schema = [
				'@context' => 'https://schema.org/',
				'@type' => 'Product',
				'name' => $product->post_title,
			];

			// Add brand if available
			if ( ! empty( $product_brand ) ) {
				$schema['brand'] = [
					'@type' => 'Brand',
					'name' => $product_brand
				];
			}

			// Add image if available
			if ( ! empty( $product_image ) ) {
				$schema['image'] = $product_image;
			}

			// Add review
			$schema['review'] = [
				'@type' => 'Review',
				'reviewRating' => [
					'@type' => 'Rating',
					'ratingValue' => number_format( $score, 1 ),
					'bestRating' => '10',
					'worstRating' => '1'
				],
				'author' => [
					'@type' => 'Person',
					'@id' => $author_url,
					'name' => $author_name
				],
				'datePublished' => get_the_date( 'c', $post_id ),
				'dateModified' => get_the_modified_date( 'c', $post_id ),
				'reviewBody' => wp_trim_words( get_the_excerpt( $post_id ), 50 ),
				'publisher' => [
					'@type' => 'Organization',
					'name' => get_bloginfo( 'name' ),
					'url' => home_url()
				]
			];

			// Add offers if tracking links are available
			if ( ! empty( $tracking_links ) && is_array( $tracking_links ) ) {
				$offers = [];
				foreach ( $tracking_links as $link ) {
					if ( ! empty( $link['affiliate_link'] ) && ! empty( $link['merchant_name'] ) ) {
						$offer = [
							'@type' => 'Offer',
							'url' => $link['affiliate_link'],
							'seller' => [
								'@type' => 'Organization',
								'name' => $link['merchant_name']
							],
							'availability' => 'https://schema.org/InStock'
						];

						// Add price if available
						if ( ! empty( $link['current_price'] ) && is_numeric( $link['current_price'] ) ) {
							$offer['price'] = $link['current_price'];
							$offer['priceCurrency'] = ! empty( $link['currency'] ) ? $link['currency'] : 'USD';
						}

						$offers[] = $offer;
					}
				}

				if ( ! empty( $offers ) ) {
					$schema['offers'] = count( $offers ) > 1 ? $offers : $offers[0];
				}
			}

			return $schema;
		}
	}
}
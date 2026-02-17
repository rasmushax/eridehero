<?php
/**
 * Author Helper Functions
 *
 * Functions for retrieving author data including social links from Rank Math.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get author social links from Rank Math SEO user fields.
 *
 * Retrieves Facebook, Twitter, and additional profile URLs from Rank Math
 * user meta and parses them into a structured array.
 *
 * @param int $author_id The user ID.
 * @return array {
 *     Associative array of social links. Empty string if not set.
 *
 *     @type string $facebook  Facebook profile URL.
 *     @type string $twitter   Twitter/X profile URL.
 *     @type string $linkedin  LinkedIn profile URL.
 *     @type string $instagram Instagram profile URL.
 *     @type string $youtube   YouTube channel URL.
 * }
 */
function erh_get_author_socials( int $author_id ): array {
	$socials = array(
		'facebook'  => '',
		'twitter'   => '',
		'linkedin'  => '',
		'instagram' => '',
		'youtube'   => '',
		'tiktok'    => '',
	);

	if ( ! $author_id ) {
		return $socials;
	}

	// Get Rank Math user meta fields.
	// These are stored without prefix in Rank Math's user meta.
	$facebook = get_user_meta( $author_id, 'facebook', true );
	$twitter  = get_user_meta( $author_id, 'twitter', true );
	$additional_urls = get_user_meta( $author_id, 'additional_profile_urls', true );

	// Facebook URL.
	if ( $facebook ) {
		$socials['facebook'] = esc_url( $facebook );
	}

	// Twitter - stored as username without @, build full URL.
	if ( $twitter ) {
		$twitter = ltrim( $twitter, '@' ); // Remove @ if present.
		$socials['twitter'] = 'https://x.com/' . sanitize_user( $twitter );
	}

	// Parse additional profile URLs (one per line).
	if ( $additional_urls ) {
		$urls = array_filter( array_map( 'trim', explode( "\n", $additional_urls ) ) );

		foreach ( $urls as $url ) {
			$url = esc_url( $url );
			if ( ! $url ) {
				continue;
			}

			// Match URL to platform.
			if ( strpos( $url, 'linkedin.com' ) !== false ) {
				$socials['linkedin'] = $url;
			} elseif ( strpos( $url, 'instagram.com' ) !== false ) {
				$socials['instagram'] = $url;
			} elseif ( strpos( $url, 'youtube.com' ) !== false || strpos( $url, 'youtu.be' ) !== false ) {
				$socials['youtube'] = $url;
			} elseif ( strpos( $url, 'tiktok.com' ) !== false ) {
				$socials['tiktok'] = $url;
			}
		}
	}

	return $socials;
}

/**
 * Get all author social links as flat array for schema sameAs property.
 *
 * @param int $author_id The user ID.
 * @return array Array of social profile URLs (non-empty values only).
 */
function erh_get_author_sameas_urls( int $author_id ): array {
	$socials = erh_get_author_socials( $author_id );

	return array_values( array_filter( $socials ) );
}

/**
 * Check if author has any social links.
 *
 * @param int $author_id The user ID.
 * @return bool True if at least one social link exists.
 */
function erh_author_has_socials( int $author_id ): bool {
	$socials = erh_get_author_socials( $author_id );

	foreach ( $socials as $url ) {
		if ( $url ) {
			return true;
		}
	}

	return false;
}

/**
 * Override Rank Math author schema to use ACF profile image.
 *
 * Rank Math uses Gravatar by default. This filter replaces it with
 * the ACF profile_image field if available.
 *
 * @param array $data The schema data.
 * @return array Modified schema data.
 */
add_filter( 'rank_math/json_ld', function( array $data ): array {
	if ( ! is_author() ) {
		return $data;
	}

	$author = get_queried_object();
	if ( ! $author instanceof WP_User ) {
		return $data;
	}

	$profile_image = get_field( 'profile_image', 'user_' . $author->ID );
	if ( ! $profile_image || empty( $profile_image['url'] ) ) {
		return $data;
	}

	// Find and update the Person entity for this author.
	foreach ( $data as $key => $entity ) {
		if ( ! isset( $entity['@type'] ) || $entity['@type'] !== 'Person' ) {
			continue;
		}

		// Match the author's Person entity (has /author/ in @id).
		if ( isset( $entity['@id'] ) && strpos( $entity['@id'], '/author/' ) !== false ) {
			$data[ $key ]['image'] = array(
				'@type'      => 'ImageObject',
				'@id'        => esc_url( $profile_image['url'] ),
				'url'        => esc_url( $profile_image['url'] ),
				'caption'    => $author->display_name,
				'inLanguage' => get_locale(),
			);
		}
	}

	return $data;
}, 20 );

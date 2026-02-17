<?php
/**
 * Social Links Helper
 *
 * Centralized retrieval of social media links from ACF options.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get social media links from ACF options.
 *
 * Returns an associative array of platform => URL, filtering out empty values.
 * Platform names match the icon names used in erh_the_icon().
 *
 * @return array<string, string> Platform name => URL pairs.
 */
function erh_get_social_links(): array {
	$platforms = array( 'youtube', 'instagram', 'tiktok', 'facebook', 'twitter', 'linkedin' );
	$links     = array();

	foreach ( $platforms as $platform ) {
		$url = get_field( "social_{$platform}", 'option' );
		if ( $url ) {
			$links[ $platform ] = $url;
		}
	}

	return $links;
}

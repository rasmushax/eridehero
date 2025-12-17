<?php
declare(strict_types=1);

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HFT_Affiliate_Link_Helper' ) ) {

    /**
     * Class HFT_Affiliate_Link_Helper.
     *
     * Provides methods for generating affiliate links based on scraper settings.
     */
    class HFT_Affiliate_Link_Helper {

        /**
         * Generates an affiliate link based on scraper settings, source type, and user GEO.
         *
         * @param string $original_url The original product URL.
         * @param string $source_type_slug The slug of the parser (e.g., 'levoit.com', 'amazon').
         * @param string|null $user_geo The 2-letter uppercase country code of the user, or null.
         * @return string The affiliate URL or the original URL if no applicable rule is found.
         */
        public static function get_affiliate_link(string $original_url, string $source_type_slug, ?string $user_geo = null): string {
            $scrapers_settings = get_option( 'hft_scrapers_settings', [] );

            if ( empty( $scrapers_settings ) || ! is_array( $scrapers_settings ) ) {
                return $original_url;
            }

            $source_type_slug = strtolower(trim($source_type_slug));
            $resolved_user_geo = ($user_geo !== null) ? strtoupper(trim($user_geo)) : null;

            $target_geos_to_try = [];
            if ($resolved_user_geo !== null) {
                $target_geos_to_try[] = $resolved_user_geo;
            }
            $target_geos_to_try[] = 'US'; // Always try US as a fallback
            $target_geos_to_try = array_unique($target_geos_to_try); // Ensure US isn't tried twice if user_geo is US

            foreach ($target_geos_to_try as $current_geo_to_match) {
                foreach ( $scrapers_settings as $parser_slug_from_settings => $settings_for_parser ) {
                    if ( strtolower(trim($parser_slug_from_settings)) !== $source_type_slug ) {
                        continue;
                    }

                    // $settings_for_parser is an array of rule sets for this parser_slug
                    // (because settings are saved as hft_scrapers_settings[parser_slug][0], hft_scrapers_settings[parser_slug][1] etc.)
                    // We need to iterate through these if they are indexed numerically (repeater style saving)
                    // However, our current settings save one set of rules per parser slug directly.
                    // The structure from HFT_Admin_Settings save_scrapers_settings is:
                    // $options[$parser_slug]['affiliate_format'] = ...
                    // $options[$parser_slug]['applicable_geos'] = ...

                    $applicable_geos_str = $settings_for_parser['applicable_geos'] ?? '';
                    $affiliate_format    = $settings_for_parser['affiliate_format'] ?? '';

                    $applicable_geos_array = [];
                    if ( ! empty( $applicable_geos_str ) ) {
                        $applicable_geos_array = array_map( 'strtoupper', array_map( 'trim', explode( ',', $applicable_geos_str ) ) );
                    }

                    // If applicable_geos is empty, it implies a global/default for that parser slug,
                    // which we treat as effectively matching any GEO if we haven't found a specific match yet,
                    // OR it matches our $current_geo_to_match if that is 'US' (our fallback target)
                    $geo_match_found = false;
                    if (empty($applicable_geos_array)) {
                        // This rule is a general rule for the parser, or should be treated as US if current_geo_to_match is US
                         if ($current_geo_to_match === 'US') { // Treat empty GEO list as matching US for fallback purposes
                            $geo_match_found = true;
                         }
                    } elseif (in_array($current_geo_to_match, $applicable_geos_array, true)) {
                        $geo_match_found = true;
                    }

                    if ( $geo_match_found ) {
                        if ( ! empty( $affiliate_format ) ) {
                            $formatted_url = str_replace( '{URL}', $original_url, $affiliate_format );
                            $formatted_url = str_replace( '{URLE}', urlencode( $original_url ), $formatted_url );
                            return $formatted_url;
                        } else {
                            // Matched a GEO rule, but the format is empty. Consider this a valid stop for this specific GEO attempt.
                            // If $current_geo_to_match was the user's actual GEO, we don't want to fall back to US if a specific rule for their GEO existed but was empty.
                            // However, if $current_geo_to_match IS 'US' (our fallback), and its format is empty, then we fall through to original_url.
                            if ($resolved_user_geo !== null && $current_geo_to_match === $resolved_user_geo) {
                                return $original_url; // Specific GEO rule found but empty, so return original.
                            }
                            // Otherwise, continue to see if a US fallback (if this wasn't it) has a format, or ultimately return original_url.
                        }
                    }
                } // end foreach settings_for_parser
            } // end foreach target_geos_to_try

            return $original_url; // Ultimate fallback
        }
    }
}

// Example Usage (for testing - not part of the class itself):
/*
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('hft_test_link', function($args) {
        list($url, $slug, $geo) = $args;
        // Mock settings for testing
        // update_option('hft_scrapers_settings', [
        //    'levoit.com' => [
        //        'affiliate_format' => 'https://levoit.com/product/{URL}?tag=levoit-us&geo={GEO}', // Fictional {GEO} tag for demo
        //        'applicable_geos' => 'US,CA'
        //    ],
        //    'levoit.com' => [ // This structure is wrong, settings save one config per slug
        //        'affiliate_format' => 'https://levoit.co.uk/product/{URL}?tag=levoit-gb',
        //        'applicable_geos' => 'GB'
        //    ],
        //    'amazon.com' => [
        //        'affiliate_format' => 'https://amazon.com/dp/B0EXAMPLE/?tag=amazon-us-20&linkCode=ogi&th=1&psc=1&language=en_US&ref={URL}', // just an example
        //        'applicable_geos' => 'US'
        //    ]
        // ]);
        $affiliate_link = HFT_Affiliate_Link_Helper::get_affiliate_link($url, $slug, $geo);
        WP_CLI::line("Original: $url");
        WP_CLI::line("Slug: $slug, GEO: $geo");
        WP_CLI::line("Affiliate: $affiliate_link");
    });
}
*/ 
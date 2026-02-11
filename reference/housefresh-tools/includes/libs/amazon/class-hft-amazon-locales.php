<?php
declare(strict_types=1);

namespace Housefresh\Tools\Libs\Amazon;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class HFT_Amazon_Locales
 *
 * Provides mapping between GEO codes and Amazon Creators API details like
 * marketplace domain, region group, credential version, and currency.
 */
class HFT_Amazon_Locales {

    /**
     * Holds the locale mapping data.
     * Structure: [GEO => [Name, Retail Host / Marketplace Domain, Region Group, Credential Version, Currency]]
     */
    private static array $locales = [
        'AE' => ['United Arab Emirates', 'www.amazon.ae', 'EU', '2.2', 'AED'],
        'AU' => ['Australia', 'www.amazon.com.au', 'FE', '2.3', 'AUD'],
        'BE' => ['Belgium', 'www.amazon.com.be', 'EU', '2.2', 'EUR'],
        'BR' => ['Brazil', 'www.amazon.com.br', 'NA', '2.1', 'BRL'],
        'CA' => ['Canada', 'www.amazon.ca', 'NA', '2.1', 'CAD'],
        'DE' => ['Germany', 'www.amazon.de', 'EU', '2.2', 'EUR'],
        'EG' => ['Egypt', 'www.amazon.eg', 'EU', '2.2', 'EGP'],
        'ES' => ['Spain', 'www.amazon.es', 'EU', '2.2', 'EUR'],
        'FR' => ['France', 'www.amazon.fr', 'EU', '2.2', 'EUR'],
        'GB' => ['United Kingdom', 'www.amazon.co.uk', 'EU', '2.2', 'GBP'],
        'IN' => ['India', 'www.amazon.in', 'EU', '2.2', 'INR'],
        'IT' => ['Italy', 'www.amazon.it', 'EU', '2.2', 'EUR'],
        'JP' => ['Japan', 'www.amazon.co.jp', 'FE', '2.3', 'JPY'],
        'MX' => ['Mexico', 'www.amazon.com.mx', 'NA', '2.1', 'MXN'],
        'NL' => ['Netherlands', 'www.amazon.nl', 'EU', '2.2', 'EUR'],
        'PL' => ['Poland', 'www.amazon.pl', 'EU', '2.2', 'PLN'],
        'SA' => ['Saudi Arabia', 'www.amazon.sa', 'EU', '2.2', 'SAR'],
        'SE' => ['Sweden', 'www.amazon.se', 'EU', '2.2', 'SEK'],
        'SG' => ['Singapore', 'www.amazon.sg', 'FE', '2.3', 'SGD'],
        'TR' => ['Turkey', 'www.amazon.com.tr', 'EU', '2.2', 'TRY'],
        'US' => ['United States', 'www.amazon.com', 'NA', '2.1', 'USD'],
        // Aliases
        'UK' => ['United Kingdom', 'www.amazon.co.uk', 'EU', '2.2', 'GBP'],
    ];

    /**
     * Token endpoints per region group (Amazon Cognito OAuth 2.0).
     */
    private static array $token_endpoints = [
        'NA' => 'https://creatorsapi.auth.us-east-1.amazoncognito.com/oauth2/token',
        'EU' => 'https://creatorsapi.auth.eu-south-2.amazoncognito.com/oauth2/token',
        'FE' => 'https://creatorsapi.auth.us-west-2.amazoncognito.com/oauth2/token',
    ];

    /**
     * Get all locale data.
     *
     * @return array
     */
    public static function get_all_locales(): array {
        return self::$locales;
    }

    /**
     * Get data for a specific locale.
     *
     * @param string $geo_code The 2-letter country code (e.g., 'US', 'GB'). Case-insensitive.
     * @return array|null Locale data array or null if not found.
     */
    private static function get_locale_data(string $geo_code): ?array {
        $geo_code_upper = strtoupper(trim($geo_code));
        return self::$locales[$geo_code_upper] ?? null;
    }

    /**
     * Get the credential version for a specific locale.
     *
     * @param string $geo_code The 2-letter country code.
     * @return string|null The credential version (e.g., '2.1') or null if locale not found.
     */
    public static function get_credential_version(string $geo_code): ?string {
        $data = self::get_locale_data($geo_code);
        return $data[3] ?? null;
    }

    /**
     * Get the region group for a specific locale.
     *
     * @param string $geo_code The 2-letter country code.
     * @return string|null The region group ('NA', 'EU', or 'FE') or null if locale not found.
     */
    public static function get_region_group(string $geo_code): ?string {
        $data = self::get_locale_data($geo_code);
        return $data[2] ?? null;
    }

    /**
     * Get the OAuth 2.0 token endpoint for a specific locale's region.
     *
     * @param string $geo_code The 2-letter country code.
     * @return string|null The Cognito token URL or null if locale not found.
     */
    public static function get_token_endpoint(string $geo_code): ?string {
        $region_group = self::get_region_group($geo_code);
        if ( ! $region_group ) {
            return null;
        }
        return self::$token_endpoints[$region_group] ?? null;
    }

    /**
     * Get the currency code for a specific locale.
     *
     * @param string $geo_code The 2-letter country code.
     * @return string|null The 3-letter currency code (e.g., 'USD') or null if locale not found.
     */
    public static function get_currency_code(string $geo_code): ?string {
        $data = self::get_locale_data($geo_code);
        return $data[4] ?? null;
    }

    /**
     * Get the Marketplace domain for a specific locale.
     * Used for the 'x-marketplace' header and 'marketplace' parameter in the Creators API.
     *
     * @param string $geo_code The 2-letter country code.
     * @return string|null The marketplace domain (e.g., 'www.amazon.com') or null if locale not found.
     */
    public static function get_marketplace_name(string $geo_code): ?string {
        $data = self::get_locale_data($geo_code);
        return $data[1] ?? null;
    }

    /**
     * Get the retail domain for a specific locale.
     *
     * @param string $geo_code The 2-letter country code.
     * @return string|null The retail host (e.g., 'www.amazon.com') or null if locale not found.
     */
    public static function get_retail_host(string $geo_code): ?string {
        $data = self::get_locale_data($geo_code);
        return $data[1] ?? null;
    }

     /**
     * Check if a GEO code is valid (exists in our mapping).
     *
     * @param string $geo_code The 2-letter country code.
     * @return bool True if valid, false otherwise.
     */
    public static function is_valid_geo(string $geo_code): bool {
        return self::get_locale_data($geo_code) !== null;
    }
}

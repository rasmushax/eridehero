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
 * Provides mapping between GEO codes and Amazon PA-API 5.0 details like
 * hostname, region, marketplace name, and currency.
 */
class HFT_Amazon_Locales {

    /**
     * Holds the locale mapping data.
     * Structure: [GEO => [Name, Retail Host, API Host, Region, Currency, Marketplace Name]]
     */
    private static array $locales = [
        'AE' => ['United Arab Emirates', 'www.amazon.ae', 'webservices.amazon.ae', 'eu-west-1', 'AED', 'www.amazon.ae'],
        'AU' => ['Australia', 'www.amazon.com.au', 'webservices.amazon.com.au', 'us-west-2', 'AUD', 'www.amazon.com.au'],
        'BE' => ['Belgium', 'www.amazon.com.be', 'webservices.amazon.com.be', 'eu-west-1', 'EUR', 'www.amazon.com.be'],
        'BR' => ['Brazil', 'www.amazon.com.br', 'webservices.amazon.com.br', 'us-east-1', 'BRL', 'www.amazon.com.br'],
        'CA' => ['Canada', 'www.amazon.ca', 'webservices.amazon.ca', 'us-east-1', 'CAD', 'www.amazon.ca'],
        'DE' => ['Germany', 'www.amazon.de', 'webservices.amazon.de', 'eu-west-1', 'EUR', 'www.amazon.de'],
        'EG' => ['Egypt', 'www.amazon.eg', 'webservices.amazon.eg', 'eu-west-1', 'EGP', 'www.amazon.eg'],
        'ES' => ['Spain', 'www.amazon.es', 'webservices.amazon.es', 'eu-west-1', 'EUR', 'www.amazon.es'],
        'FR' => ['France', 'www.amazon.fr', 'webservices.amazon.fr', 'eu-west-1', 'EUR', 'www.amazon.fr'],
        'GB' => ['United Kingdom', 'www.amazon.co.uk', 'webservices.amazon.co.uk', 'eu-west-1', 'GBP', 'www.amazon.co.uk'],
        'IN' => ['India', 'www.amazon.in', 'webservices.amazon.in', 'eu-west-1', 'INR', 'www.amazon.in'],
        'IT' => ['Italy', 'www.amazon.it', 'webservices.amazon.it', 'eu-west-1', 'EUR', 'www.amazon.it'],
        'JP' => ['Japan', 'www.amazon.co.jp', 'webservices.amazon.co.jp', 'us-west-2', 'JPY', 'www.amazon.co.jp'],
        'MX' => ['Mexico', 'www.amazon.com.mx', 'webservices.amazon.com.mx', 'us-east-1', 'MXN', 'www.amazon.com.mx'],
        'NL' => ['Netherlands', 'www.amazon.nl', 'webservices.amazon.nl', 'eu-west-1', 'EUR', 'www.amazon.nl'],
        'PL' => ['Poland', 'www.amazon.pl', 'webservices.amazon.pl', 'eu-west-1', 'PLN', 'www.amazon.pl'],
        'SA' => ['Saudi Arabia', 'www.amazon.sa', 'webservices.amazon.sa', 'eu-west-1', 'SAR', 'www.amazon.sa'],
        'SE' => ['Sweden', 'www.amazon.se', 'webservices.amazon.se', 'eu-west-1', 'SEK', 'www.amazon.se'],
        'SG' => ['Singapore', 'www.amazon.sg', 'webservices.amazon.sg', 'us-west-2', 'SGD', 'www.amazon.sg'],
        'TR' => ['Turkey', 'www.amazon.com.tr', 'webservices.amazon.com.tr', 'eu-west-1', 'TRY', 'www.amazon.com.tr'],
        'US' => ['United States', 'www.amazon.com', 'webservices.amazon.com', 'us-east-1', 'USD', 'www.amazon.com'],
        // Aliases or deprecated codes
        'UK' => ['United Kingdom', 'www.amazon.co.uk', 'webservices.amazon.co.uk', 'eu-west-1', 'GBP', 'www.amazon.co.uk'], // Alias for GB
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
     * Get the PA-API Host for a specific locale.
     *
     * @param string $geo_code The 2-letter country code.
     * @return string|null The API host (e.g., 'webservices.amazon.com') or null if locale not found.
     */
    public static function get_api_host(string $geo_code): ?string {
        $data = self::get_locale_data($geo_code);
        return $data[2] ?? null;
    }

    /**
     * Get the AWS Region for a specific locale's PA-API endpoint.
     *
     * @param string $geo_code The 2-letter country code.
     * @return string|null The AWS region (e.g., 'us-east-1') or null if locale not found.
     */
    public static function get_region(string $geo_code): ?string {
        $data = self::get_locale_data($geo_code);
        return $data[3] ?? null;
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
     * Get the Marketplace Name (typically the retail domain) for a specific locale.
     * Needed for the 'Marketplace' parameter in the PA-API payload.
     *
     * @param string $geo_code The 2-letter country code.
     * @return string|null The marketplace name (e.g., 'www.amazon.com') or null if locale not found.
     */
    public static function get_marketplace_name(string $geo_code): ?string {
        $data = self::get_locale_data($geo_code);
        return $data[5] ?? null;
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
<?php
/**
 * Amazon Locales - GEO to PA-API mapping.
 *
 * Based on HFT_Amazon_Locales for Amazon PA-API 5.0.
 *
 * @package ERH\Amazon
 */

declare(strict_types=1);

namespace ERH\Amazon;

/**
 * Provides mapping between GEO codes and Amazon PA-API 5.0 details.
 */
class AmazonLocales {

    /**
     * Locale mapping data.
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
        'UK' => ['United Kingdom', 'www.amazon.co.uk', 'webservices.amazon.co.uk', 'eu-west-1', 'GBP', 'www.amazon.co.uk'],
    ];

    /**
     * Get all locale data.
     *
     * @return array All locales.
     */
    public static function get_all_locales(): array {
        return self::$locales;
    }

    /**
     * Get data for a specific locale.
     *
     * @param string $geo_code The 2-letter country code.
     * @return array|null Locale data or null.
     */
    private static function get_locale_data(string $geo_code): ?array {
        $geo_code_upper = strtoupper(trim($geo_code));
        return self::$locales[$geo_code_upper] ?? null;
    }

    /**
     * Get the PA-API Host for a locale.
     *
     * @param string $geo_code The 2-letter country code.
     * @return string|null The API host or null.
     */
    public static function get_api_host(string $geo_code): ?string {
        $data = self::get_locale_data($geo_code);
        return $data[2] ?? null;
    }

    /**
     * Get the AWS Region for a locale.
     *
     * @param string $geo_code The 2-letter country code.
     * @return string|null The AWS region or null.
     */
    public static function get_region(string $geo_code): ?string {
        $data = self::get_locale_data($geo_code);
        return $data[3] ?? null;
    }

    /**
     * Get the currency code for a locale.
     *
     * @param string $geo_code The 2-letter country code.
     * @return string|null The currency code or null.
     */
    public static function get_currency_code(string $geo_code): ?string {
        $data = self::get_locale_data($geo_code);
        return $data[4] ?? null;
    }

    /**
     * Get the Marketplace Name for a locale.
     *
     * @param string $geo_code The 2-letter country code.
     * @return string|null The marketplace name or null.
     */
    public static function get_marketplace_name(string $geo_code): ?string {
        $data = self::get_locale_data($geo_code);
        return $data[5] ?? null;
    }

    /**
     * Get the retail domain for a locale.
     *
     * @param string $geo_code The 2-letter country code.
     * @return string|null The retail host or null.
     */
    public static function get_retail_host(string $geo_code): ?string {
        $data = self::get_locale_data($geo_code);
        return $data[1] ?? null;
    }

    /**
     * Check if a GEO code is valid.
     *
     * @param string $geo_code The 2-letter country code.
     * @return bool True if valid.
     */
    public static function is_valid_geo(string $geo_code): bool {
        return self::get_locale_data($geo_code) !== null;
    }
}

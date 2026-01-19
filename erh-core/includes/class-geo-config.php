<?php
/**
 * Geo Configuration - Single source of truth for geo/region constants.
 *
 * @package ERH
 */

declare(strict_types=1);

namespace ERH;

/**
 * Centralized geo configuration for all components.
 */
class GeoConfig {

    /**
     * Supported regions.
     *
     * @var array<string>
     */
    public const REGIONS = ['US', 'GB', 'EU', 'CA', 'AU'];

    /**
     * EU member country codes.
     *
     * @var array<string>
     */
    public const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ];

    /**
     * Region to currency mapping.
     *
     * @var array<string, string>
     */
    public const CURRENCIES = [
        'US' => 'USD',
        'GB' => 'GBP',
        'EU' => 'EUR',
        'CA' => 'CAD',
        'AU' => 'AUD',
    ];

    /**
     * Currency symbols.
     *
     * @var array<string, string>
     */
    public const CURRENCY_SYMBOLS = [
        'USD' => '$',
        'GBP' => '£',
        'EUR' => '€',
        'CAD' => 'CA$',
        'AUD' => 'A$',
    ];

    /**
     * Country to region mapping for non-EU countries.
     *
     * @var array<string, string>
     */
    private const COUNTRY_MAP = [
        'US' => 'US',
        'GB' => 'GB',
        'CA' => 'CA',
        'AU' => 'AU',
        'NZ' => 'AU', // New Zealand uses AU region
    ];

    /**
     * Get region for a country code.
     *
     * @param string $country Two-letter country code.
     * @return string Region code (US, GB, EU, CA, AU).
     */
    public static function get_region(string $country): string {
        $country = strtoupper($country);

        // Check EU countries first.
        if (in_array($country, self::EU_COUNTRIES, true)) {
            return 'EU';
        }

        // Check direct mappings.
        if (isset(self::COUNTRY_MAP[$country])) {
            return self::COUNTRY_MAP[$country];
        }

        // Default to US for unknown countries.
        return 'US';
    }

    /**
     * Get currency for a region.
     *
     * @param string $region Region code.
     * @return string Currency code.
     */
    public static function get_currency(string $region): string {
        return self::CURRENCIES[strtoupper($region)] ?? 'USD';
    }

    /**
     * Get currency symbol.
     *
     * @param string $currency Currency code.
     * @return string Currency symbol.
     */
    public static function get_symbol(string $currency): string {
        return self::CURRENCY_SYMBOLS[strtoupper($currency)] ?? $currency;
    }

    /**
     * Check if a region is valid.
     *
     * @param string $region Region code.
     * @return bool True if valid.
     */
    public static function is_valid_region(string $region): bool {
        return in_array(strtoupper($region), self::REGIONS, true);
    }

    /**
     * Check if a country is in the EU.
     *
     * @param string $country Country code.
     * @return bool True if EU country.
     */
    public static function is_eu_country(string $country): bool {
        return in_array(strtoupper($country), self::EU_COUNTRIES, true);
    }

    /**
     * Parse comma-separated geo string into array of country codes.
     *
     * Handles: 'DE', 'AT,BE,DE', null, ''
     *
     * @param string|null $geo_string Geo string (single or comma-separated).
     * @return array<string> Array of uppercase country codes.
     */
    public static function parse_geo_codes(?string $geo_string): array {
        if ($geo_string === null || $geo_string === '') {
            return [];
        }

        if (strpos($geo_string, ',') !== false) {
            return array_map('trim', array_map('strtoupper', explode(',', $geo_string)));
        }

        return [strtoupper(trim($geo_string))];
    }

    /**
     * Map geo string (single or comma-separated) to region.
     *
     * Returns region for first recognized country code in the string.
     * Falls back to currency-based inference if geo is empty.
     *
     * @param string|null $geo      Geo string (single like 'DE' or comma-separated like 'AT,BE,DE').
     * @param string      $currency Currency code for fallback inference.
     * @return string|null Region code (US, GB, EU, CA, AU) or null if unrecognized.
     */
    public static function map_geo_to_region(?string $geo, string $currency): ?string {
        $codes = self::parse_geo_codes($geo);

        // Try each code until we find a recognized one.
        foreach ($codes as $code) {
            // Direct region match (US, GB, EU, CA, AU).
            if (in_array($code, self::REGIONS, true)) {
                return $code;
            }

            // EU country codes map to EU.
            if (self::is_eu_country($code)) {
                return 'EU';
            }

            // Check country map (NZ → AU, etc.).
            if (isset(self::COUNTRY_MAP[$code])) {
                return self::COUNTRY_MAP[$code];
            }
        }

        // Empty geo: infer region from currency.
        if (empty($codes)) {
            return self::get_region_by_currency($currency);
        }

        // No recognized codes.
        return null;
    }

    /**
     * Get region by currency code.
     *
     * @param string $currency Currency code (USD, EUR, GBP, etc.).
     * @return string|null Region code or null if unrecognized currency.
     */
    public static function get_region_by_currency(string $currency): ?string {
        $currency = strtoupper($currency);

        foreach (self::CURRENCIES as $region => $curr) {
            if ($curr === $currency) {
                return $region;
            }
        }

        return null;
    }
}

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
}

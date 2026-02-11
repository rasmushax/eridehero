<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Maps ISO country codes to their primary currency codes.
 *
 * Used by Shopify Markets integration to:
 * - Inject correct cart_currency cookies for geo-specific scraping
 * - Group countries by currency for market detection
 * - Validate scraped prices match expected currency
 */
class HFT_Shopify_Currencies {

    /**
     * Country code → currency code mapping.
     */
    private const COUNTRY_CURRENCY = [
        // North America
        'US' => 'USD',
        'CA' => 'CAD',
        'MX' => 'MXN',

        // United Kingdom
        'GB' => 'GBP',

        // Eurozone
        'DE' => 'EUR',
        'FR' => 'EUR',
        'IT' => 'EUR',
        'ES' => 'EUR',
        'NL' => 'EUR',
        'BE' => 'EUR',
        'AT' => 'EUR',
        'IE' => 'EUR',
        'PT' => 'EUR',
        'FI' => 'EUR',
        'GR' => 'EUR',
        'LU' => 'EUR',
        'SK' => 'EUR',
        'SI' => 'EUR',
        'EE' => 'EUR',
        'LV' => 'EUR',
        'LT' => 'EUR',
        'CY' => 'EUR',
        'MT' => 'EUR',
        'HR' => 'EUR',

        // EU non-Euro
        'SE' => 'SEK',
        'DK' => 'DKK',
        'PL' => 'PLN',
        'CZ' => 'CZK',
        'HU' => 'HUF',
        'RO' => 'RON',
        'BG' => 'BGN',

        // Nordic non-EU
        'NO' => 'NOK',
        'IS' => 'ISK',

        // Other Europe
        'CH' => 'CHF',

        // Asia-Pacific
        'AU' => 'AUD',
        'NZ' => 'NZD',
        'JP' => 'JPY',
        'KR' => 'KRW',
        'SG' => 'SGD',
        'HK' => 'HKD',
        'TW' => 'TWD',
        'TH' => 'THB',
        'MY' => 'MYR',
        'PH' => 'PHP',
        'ID' => 'IDR',
        'VN' => 'VND',
        'IN' => 'INR',

        // Middle East
        'AE' => 'AED',
        'SA' => 'SAR',
        'IL' => 'ILS',

        // South America
        'BR' => 'BRL',
        'AR' => 'ARS',
        'CL' => 'CLP',
        'CO' => 'COP',

        // Africa
        'ZA' => 'ZAR',
        'NG' => 'NGN',
    ];

    /**
     * Get the currency code for a given country code.
     *
     * @param string $country_code ISO 3166-1 alpha-2 country code.
     * @return string ISO 4217 currency code. Falls back to USD.
     */
    public static function get_currency(string $country_code): string {
        $country_code = strtoupper(trim($country_code));
        return self::COUNTRY_CURRENCY[$country_code] ?? 'USD';
    }

    /**
     * Group an array of country codes by their currency.
     *
     * @param array $country_codes Array of ISO country codes.
     * @return array Keyed by currency code, values are arrays of country codes.
     *               e.g. ['USD' => ['US'], 'EUR' => ['DE','FR','IT'], 'GBP' => ['GB']]
     */
    public static function group_by_currency(array $country_codes): array {
        $groups = [];
        foreach ($country_codes as $code) {
            $code = strtoupper(trim($code));
            if (empty($code) || $code === 'GLOBAL') {
                continue;
            }
            $currency = self::get_currency($code);
            $groups[$currency][] = $code;
        }
        return $groups;
    }

    /**
     * Get the full country→currency mapping.
     *
     * @return array
     */
    public static function get_all_mappings(): array {
        return self::COUNTRY_CURRENCY;
    }
}

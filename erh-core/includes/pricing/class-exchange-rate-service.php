<?php
/**
 * Exchange Rate Service - Currency conversion for price display.
 *
 * @package ERH\Pricing
 */

declare(strict_types=1);

namespace ERH\Pricing;

/**
 * Handles currency exchange rates for frontend price conversion.
 * Uses frankfurter.app (free, no API key required) for live rates.
 */
class ExchangeRateService {

    /**
     * Cache key for stored exchange rates.
     */
    private const CACHE_KEY = 'erh_exchange_rates';

    /**
     * Cache expiry in seconds (24 hours).
     */
    private const CACHE_EXPIRY = DAY_IN_SECONDS;

    /**
     * Base currency for all conversions.
     */
    private const BASE_CURRENCY = 'USD';

    /**
     * API URL for frankfurter.app.
     */
    private const API_URL = 'https://api.frankfurter.app/latest';

    /**
     * Fallback rates if API is unavailable (approximate, updated periodically).
     */
    private const FALLBACK_RATES = [
        'USD' => 1.0,
        'EUR' => 0.92,
        'GBP' => 0.79,
        'CAD' => 1.36,
        'AUD' => 1.53,
        'DKK' => 6.88,
        'SEK' => 10.42,
        'NOK' => 10.65,
        'CHF' => 0.88,
        'PLN' => 3.98,
        'CZK' => 23.15,
        'HUF' => 356.0,
        'JPY' => 149.5,
        'CNY' => 7.24,
        'MXN' => 17.15,
    ];

    /**
     * Currency symbols for display.
     */
    private const CURRENCY_SYMBOLS = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'CAD' => 'CA$',
        'AUD' => 'A$',
        'DKK' => 'kr',
        'SEK' => 'kr',
        'NOK' => 'kr',
        'CHF' => 'CHF',
        'PLN' => 'zł',
        'CZK' => 'Kč',
        'HUF' => 'Ft',
        'JPY' => '¥',
        'CNY' => '¥',
        'MXN' => 'MX$',
    ];

    /**
     * Cached rates loaded from options.
     *
     * @var array<string, float>|null
     */
    private ?array $rates = null;

    /**
     * Get exchange rate from one currency to another.
     *
     * @param string $from Source currency code (e.g., 'EUR').
     * @param string $to   Target currency code (e.g., 'USD').
     * @return float|null Exchange rate or null if not available.
     */
    public function get_rate(string $from, string $to): ?float {
        $from = strtoupper($from);
        $to = strtoupper($to);

        // Same currency = rate of 1.
        if ($from === $to) {
            return 1.0;
        }

        $rates = $this->get_rates();

        // Get both rates relative to base currency.
        $from_rate = $rates[$from] ?? null;
        $to_rate = $rates[$to] ?? null;

        if ($from_rate === null || $to_rate === null) {
            return null;
        }

        // Calculate cross-rate.
        // rate(FROM->TO) = rate(TO/BASE) / rate(FROM/BASE)
        return $to_rate / $from_rate;
    }

    /**
     * Convert an amount from one currency to another.
     *
     * @param float  $amount Amount to convert.
     * @param string $from   Source currency code.
     * @param string $to     Target currency code.
     * @return float|null Converted amount or null if conversion not possible.
     */
    public function convert(float $amount, string $from, string $to): ?float {
        $rate = $this->get_rate($from, $to);

        if ($rate === null) {
            return null;
        }

        return round($amount * $rate, 2);
    }

    /**
     * Get all cached exchange rates.
     *
     * @return array<string, float> Array of currency => rate (relative to USD).
     */
    public function get_rates(): array {
        if ($this->rates !== null) {
            return $this->rates;
        }

        // Try to get cached rates.
        $cached = get_option(self::CACHE_KEY);

        if ($cached && isset($cached['rates']) && isset($cached['updated_at'])) {
            $age = time() - $cached['updated_at'];

            // Use cached rates if less than 24 hours old.
            if ($age < self::CACHE_EXPIRY) {
                $this->rates = $cached['rates'];
                return $this->rates;
            }
        }

        // Try to refresh rates.
        if ($this->refresh_rates()) {
            return $this->rates;
        }

        // Fall back to hardcoded rates.
        $this->rates = self::FALLBACK_RATES;
        return $this->rates;
    }

    /**
     * Refresh exchange rates from external API.
     *
     * @return bool True on success.
     */
    public function refresh_rates(): bool {
        $url = self::API_URL . '?from=' . self::BASE_CURRENCY;

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            error_log('[ERH] Exchange rate API error: ' . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log('[ERH] Exchange rate API returned status: ' . $status_code);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['rates']) || !is_array($data['rates'])) {
            error_log('[ERH] Exchange rate API returned invalid data');
            return false;
        }

        // Build rates array with base currency = 1.
        $rates = [self::BASE_CURRENCY => 1.0];
        foreach ($data['rates'] as $currency => $rate) {
            if (is_numeric($rate)) {
                $rates[$currency] = (float)$rate;
            }
        }

        // Cache the rates.
        $cache_data = [
            'rates'      => $rates,
            'updated_at' => time(),
            'source'     => 'frankfurter.app',
        ];

        update_option(self::CACHE_KEY, $cache_data, false);

        $this->rates = $rates;

        error_log(sprintf('[ERH] Exchange rates refreshed: %d currencies', count($rates)));

        return true;
    }

    /**
     * Get last update time for cached rates.
     *
     * @return int|null Unix timestamp or null if no cached rates.
     */
    public function get_last_updated(): ?int {
        $cached = get_option(self::CACHE_KEY);

        if ($cached && isset($cached['updated_at'])) {
            return (int)$cached['updated_at'];
        }

        return null;
    }

    /**
     * Check if rates are stale (older than cache expiry).
     *
     * @return bool True if rates should be refreshed.
     */
    public function are_rates_stale(): bool {
        $last_updated = $this->get_last_updated();

        if ($last_updated === null) {
            return true;
        }

        return (time() - $last_updated) >= self::CACHE_EXPIRY;
    }

    /**
     * Get currency symbol for a currency code.
     *
     * @param string $currency_code ISO currency code.
     * @return string Currency symbol.
     */
    public function get_symbol(string $currency_code): string {
        $currency_code = strtoupper($currency_code);
        return self::CURRENCY_SYMBOLS[$currency_code] ?? $currency_code;
    }

    /**
     * Format a price with currency symbol.
     *
     * @param float  $amount   The amount.
     * @param string $currency The currency code.
     * @param bool   $symbol_before Whether symbol goes before amount.
     * @return string Formatted price string.
     */
    public function format_price(float $amount, string $currency, bool $symbol_before = true): string {
        $symbol = $this->get_symbol($currency);
        $formatted = number_format($amount, 2);

        // Some currencies put symbol after.
        $after_currencies = ['DKK', 'SEK', 'NOK', 'CZK', 'PLN', 'HUF'];

        if (in_array(strtoupper($currency), $after_currencies, true)) {
            return $formatted . ' ' . $symbol;
        }

        return $symbol . $formatted;
    }

    /**
     * Get supported currencies.
     *
     * @return array<string> Array of currency codes.
     */
    public function get_supported_currencies(): array {
        return array_keys(self::CURRENCY_SYMBOLS);
    }

    /**
     * Check if a currency is supported.
     *
     * @param string $currency Currency code.
     * @return bool True if supported.
     */
    public function is_supported(string $currency): bool {
        return isset(self::CURRENCY_SYMBOLS[strtoupper($currency)]);
    }

    /**
     * Get default currency for a geo/country code.
     *
     * @param string $geo Country/geo code.
     * @return string Currency code.
     */
    public function get_currency_for_geo(string $geo): string {
        $geo = strtoupper($geo);

        $geo_currencies = [
            // North America
            'US' => 'USD',
            'CA' => 'CAD',
            'MX' => 'MXN',
            // UK
            'GB' => 'GBP',
            'UK' => 'GBP',
            // Eurozone
            'DE' => 'EUR',
            'FR' => 'EUR',
            'IT' => 'EUR',
            'ES' => 'EUR',
            'NL' => 'EUR',
            'BE' => 'EUR',
            'AT' => 'EUR',
            'PT' => 'EUR',
            'IE' => 'EUR',
            'FI' => 'EUR',
            'GR' => 'EUR',
            'LU' => 'EUR',
            'SI' => 'EUR',
            'SK' => 'EUR',
            'EE' => 'EUR',
            'LV' => 'EUR',
            'LT' => 'EUR',
            'MT' => 'EUR',
            'CY' => 'EUR',
            // Non-Euro Europe
            'DK' => 'DKK',
            'SE' => 'SEK',
            'NO' => 'NOK',
            'CH' => 'CHF',
            'PL' => 'PLN',
            'CZ' => 'CZK',
            'HU' => 'HUF',
            // Asia Pacific
            'JP' => 'JPY',
            'CN' => 'CNY',
            'AU' => 'AUD',
        ];

        return $geo_currencies[$geo] ?? 'USD';
    }
}
